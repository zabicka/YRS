<?php
/**
 * @package modules
 */
/**
 * Modul, ktery spravuje tabulku dictionary. Slouzi k urcovani pravidel pro
 * presmerovavani stranek podle jazykovych verzi.
 *
 * <code>
 * page (default)	=>	clanek (cs)
 * 					=>	artikel (de)
 * 					=>	article (fr, en)
 *
 * admin (default)	=>	administrace (cs)
 * 					=>	administration (de, fr)
 * </code>
 *
 * Nekde umoznuje menit i parametr $_GET["action"].
 *
 * @package modules
 * @subpackage core
 */
class Dictionary {
	static $design = '';

	/**
	 * inicializacni procedury funkce
	 *
	 * {@source}
	 * @return boolean
	 */
	public function __construct() {
		global $vzhled;

		if($vzhled!="") {
			self::$design = $vzhled->design("dictionary");
		}

		return true;
	}

	/**
	 * Metoda pro nacteni vsech pravidel z databaze do pole
	 *
	 * {@source}
	 * @param string(2) $lang Jazyk se kterym se operuje
	 * @return array
	 */
	protected function load() {
		return DB::query("select * from __dictionary where lang=%s order by translation", $_COOKIE['lang'])->fetchAll();
	}

	public function translate($term, $class=NULL, $gettranslate=false, $lang=NULL) {
		$lang = ($lang==NULL) ? $_COOKIE['lang'] : $lang;

		$translation = DB::select(($gettranslate==false) ? 'modul' : 'translation')
						->from('__dictionary')
						->where('lang = %s', $lang)
						->and((($gettranslate==true) ? 'modul' : 'translation').' = %s', $term)
						->execute()->fetchSingle();

		return ($translation=='') ? $term : $translation;
	}

	public function translation($modul, $class=NULL) {
		return self::translate($modul, $class, true);
	}

	public function modul($translation, $class=NULL) {
		return self::translate($translation, $class);
	}

	protected function addRule($translation, $modul, $class=NULL) {
		if(self::translation($modul, $class)!=$translation) {
			DB::query('insert into __dictionary',
				array(
					'translation' => $translation,
					'modul' => Dictionary::modul($modul),
					'class' => Dictionary::modul($class),
					'lang' => $_COOKIE['lang']
				)
			);
		}
	}

}

class DictionaryAdmin extends Dictionary {

	/**
	 * Metoda, ktera vraci HTML kod s hlaskou o ulozeni
	 *
	 * {@source}
	 * @param boolean $status Zda se ma vracet kladna nebo zaporna hlaska
	 * @return HTML
	 */
	private function saveStatus($status) {
		// pokud je hlaska kladna
		if($status==true) {
			// vraceni kladne hlasky
			return $this->design["save_ok"];
		} else {
			// nebo vraceni zaporne hlasky
			return $this->design["save_ko"];
		}
	}

	/**
	 * Metoda pro ulozeni zmen
	 *
	 * {@source}
	 * @return boolean
	 */
	public function save() {
		foreach(parent::load() as $rule) {
			$modul = $_POST[$rule->ID.'_modul'];
			$translation = $_POST[$rule->ID.'_translation'];
			$class = $_POST[$rule->ID.'_class'];

			if(!$modul or !$translation) {
				DB::query('delete from __dictionary where ID=%i', $rule->ID);
			} else {
				$update = array();

				if($modul!=$rule->modul) {
					$update['modul'] = Dictionary::modul($modul);
				}

				if($translation!=$rule->translation) {
					$update['translation'] = $translation;
				}

				if($class!=$rule->class) {
					$update['class'] = Dictionary::modul($class);
				}

				if(count($update)>0) {
					DB::query('update __dictionary set ', $update, 'where ID=%i', $rule->ID);
				}
			}
		}

		if($_POST['modul'] and $_POST['translation']) {
			parent::addRule($_POST['translation'], $_POST['modul'], $_POST['class']);
		}

		return self::view();

	}

	/**
	 * hlavni spousteci metoda tridy
	 *
	 * {@source}
	 *
	 * @return HTML
	 */
	public function view() {
		// promenna pro vraceni
		$out = '';

		// vypsani formulare pro upravy
		return self::rulesForm(parent::load());

		// vraceni html
		return $out;
	}

	/**
	 * Metoda pro vypsani HTML kodu s formularem pro upravy.
	 *
	 * {@source}
	 * @param array $rules pole z metody {@link Dictionary::loadRules()}
	 * @return HTML
	 */
	protected function rulesForm($rules) {
		// promenna pro ukladani HTML kodu
		$out = "";

		// prochazeni pravidly
		foreach($rules as $rule) {
			// ziskani a pridani adresy pro prvek
			$rule["url"] = URL::create(
				(($rule["class"]=="")?$rule["modul"]:$rule["class"]),
				(($rule["class"]!="")?$rule["modul"]:""));

			// ziskani html kodu pro radek
			$out .= design(parent::$design["rule"], $rule);
		}

		// vraceni vysledneho kodu
		return design(parent::$design["rules"], array("body"=>$out, "save"=>URL::create("admin", "dictionary", "save")));
	}
}

