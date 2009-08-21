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
	/** ulozeni sablon */
	var $design = "";

	/**
	 * inicializacni procedury funkce
	 *
	 * {@source}
	 * @return boolean
	 */
	function __construct() {
		global $vzhled;

		if($vzhled!="") {
			$this->design = $vzhled->design("dictionary");
		}

		return true;
	}

	/**
	 * hlavni spousteci metoda tridy
	 *
	 * {@source}
	 *
	 * @return HTML
	 */
	public function admin() {
		// promenna pro vraceni
		$out = "";

		// pokud se ma ukladat
		if($_GET["parametr1"]=="save") {
			// spusteni nejdrive ulozeni a pak ziskani hlasky
			$out .= $this->htmlSave($this->save());
		}

		// vypsani formulare pro upravy
		$out .= $this->htmlRules($this->loadRules($_COOKIE["lang"]));

		// vraceni html
		return $out;
	}

	/**
	 * Metoda pro ulozeni zmen
	 *
	 * {@source}
	 * @return boolean
	 */
	private function save() {
		// spojeni s databazi
		global $db;

		// promenna pro vraceni
		$ret = true;

		// ziskani existujicich pravidel
		$rules = $this->loadRules($_COOKIE["lang"]);

		// prochazeni existujicimi pravidly
		foreach($rules as $rule) {
			// ziskani odeslanych dat, osetreni, prevedeni na original
			$co = mysql_real_escape_string($_POST[$rule["ID"]."-co"]);
			$cim = ($_POST[$rule["ID"]."-class"]!="") ? slovnik($_POST[$rule["ID"]."-cim"], false, NULL, $_POST[$rule["ID"]."-class"]) : $_POST[$rule["ID"]."-cim"];
			$class = ($_POST[$rule["ID"]."-class"]!="") ? slovnik($_POST[$rule["ID"]."-class"]) : "";

			// pokud data nejsou prazdna
			if($co!="" and $cim!="") {
				// pokud data nezustala stejna
				if($co!=$rule["co"] or $cim!=$rule["cim"] or $class!=$rule["class"]) {
					// uprava zaznamu
					if(!$db->query("update __dictionary set cim='".$cim."', co='".$co."', class='".$class."' where ID='".$rule["ID"]."'")) {
						$ret = false;
					}
				}
			} else {
				// pokud data byla vymazana
				if(!$db->query("delete from __dictionary where ID='".$rule["ID"]."'")) {
					$ret = false;
				}
			}
		}

		// pokud neni prazdne policko pro novy zaznam
		if($_POST["co"]!="" and $_POST["cim"]!="") {
			// osetreni, upraveni podle jiz existujiciho slovniku
			$co = mysql_real_escape_string($_POST["co"]);
			$cim = slovnik($_POST["cim"]);
			$class = slovnik($_POST["class"]);

			// ulozeni
			if(!$db->query("insert into __dictionary (co, cim, class, jazyk) values ('".$co."','".$cim."','".$class."','".$_COOKIE["lang"]."')")) {
				$ret = false;
			}
		}

		return $ret;
	}

	/**
	 * Metoda pro nacteni vsech pravidel z databaze do pole
	 *
	 * {@source}
	 * @param string(2) $lang Jazyk se kterym se operuje
	 * @return array
	 */
	private function loadRules($lang="") {
		// spojeni s databazi
		global $db;

		// pole pro vraceni
		$ret = array();

		// pokud byl dodan jazyk vytvoreni podminky
		$where = ($lang!="") ? " where jazyk='".substr($lang, 0, 2)."'" : "";
		// vytvoreni dotazu
		$query = $db->query("select * from __dictionary".$where." order by cim");

		// prochazeni vysledky
		while($rule = $db->fetch_array($query)) {
			// ukladani do pole
			$ret[] = $rule;
		}

		return $ret;
	}

	/**
	 * Metoda, ktera vraci HTML kod s hlaskou o ulozeni
	 *
	 * {@source}
	 * @param boolean $status Zda se ma vracet kladna nebo zaporna hlaska
	 * @return HTML
	 */
	private function htmlSave($status) {
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
	 * Metoda pro vypsani HTML kodu s formularem pro upravy.
	 *
	 * {@source}
	 * @param array $rules pole z metody {@link Dictionary::loadRules()}
	 * @return HTML
	 */
	private function htmlRules($rules) {
		// promenna pro ukladani HTML kodu
		$out = "";

		// prochazeni pravidly
		foreach($rules as $rule) {
			// ziskani a pridani adresy pro prvek
			$rule["url"] = adresa(
				(($rule["class"]=="")?$rule["cim"]:$rule["class"]),
				(($rule["class"]!="")?$rule["cim"]:""));

			// ziskani html kodu pro radek
			$out .= design($this->design["rule"], $rule);
		}

		// vraceni vysledneho kodu
		return design($this->design["rules"], array("body"=>$out, "save"=>adresa("admin", "dictionary", "save")));
	}
}
