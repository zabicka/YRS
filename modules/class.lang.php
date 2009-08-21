<?php
/**
 * Zatim jednoducha trida, ktera jen dosazuje preklady
 *
 */
class Lang {
	/** Pokud preklad neexistuje, dava se to timto najevo */
	const WHEN_DOESNT_EXIST = "<sup>(%s - %s)</sup>";

	const INIT_TRANS = "lang.";
	const END_TRANS = ".php";
	const NICE_TRANS_SELECT = true;
	const TRANS_DEFAULT = "lang.general.php";

	/**
	 * inicializacni procedury funkce
	 *
	 * {@source}
	 * @return boolean
	 */
	function __construct() {
		global $vzhled;

		if($vzhled!="") {
			$this->design = $vzhled->design("lang");
		}
		return true;
	}

	/**
	 * Volana funkce, ktera nacita a nahrazuje.
	 */
	public function view($param) {
		// rozdeleni dodanych parametru ve tvaru SOUBOR;ID;DEFAULT_TEXT
		preg_match("|([^;]*);([^;]*);(.*)|", $param, $ex);

		// cesta k souboru
		$file = "./".CESTA_JAZYKY."/".$_COOKIE["lang"]."/lang.".strtolower($ex[1]).".php";
		$short_file = strtolower($ex[1]);

		// zjisteni id
		$id = $ex[2];
		// odmazani nepotrebnych poli
		unset($ex[0], $ex[1], $ex[2]);

		// spojeni zbytku (pr. spoji se Neco(1); a neco(2))
		$text = implode($ex);

		// pokud soubor existuje
		if(is_file($file)) {
			// nacteni souboru
			if(is_readable($file))
				include $file;

			// pokud preklad existuje
			if($lang[$id]!="") {
				// vraceni prekladu
				return $lang[$id];

			// pokud preklad neexistuje
			} else {
				// vraceni defaultniho retezce + oznameni o neexistenci
				return $text.sprintf(self::WHEN_DOESNT_EXIST, $id, $short_file);
			}

		// pokud soubor neexistuje
		} else {
			// vraceni defaultniho retezce + oznameni o neexistenci
			return $text.sprintf(self::WHEN_DOESNT_EXIST, $id, $short_file);
		}
	}

	/**
	 * Metoda, ktera slouzi k pristupu na stranky tykajici se prekladu retezcu v systemu YRS. Stara se
	 * o zobrazovani formulare pro upravu, pro pridani novych retezcu, pro vyber upravovaneho souboru,
	 * pro import z jineho jazyka apod.
	 *
	 * {@source}
	 * @todo Umoznit rucni vytvoreni prazdneho souboru
	 * @param string $param predavano z _GET
	 * @return html kod
	 */
	public function admin($param) {
		// retezec pro vraceni
		$out = "";

		// retezec pro ulozeni html kodu s lib. hlaskou (pr. po ulozeni)
		$hlaska = "";

		// kontrola, zda jazyk uz existuje
		if(!is_dir("./".CESTA_JAZYKY."/".$_COOKIE["lang"]."/")) {
			// vytvoreni slozky
			if(mkdir("./".CESTA_JAZYKY."/".$_COOKIE["lang"]."/")) {
				// ok hlaska
				$out .= $this->design["newlang_ok"];
			} else {
				// ko hlaska a ukonceni
				return $this->design["newlang_ko"];
			}
		}

		// pokud uzivatel vyzaduje import
		switch ($param) {
			case 'import':
				// spusteni importovaci funkce;
				return $this->transImport();
			break;

			case 'save':
				$hlaska = ($this->transSave($_POST["listfil"])) ? $this->design["translate_page_save_ok"] : $this->design["translate_page_save_ko"];
			break;

			case 'new':
				switch($this->newFile($_POST["new"])) {
					case true:
						$hlaska = sprintf($this->design["translate_save_ok"], Lang::view("Lang;27;Soubor vyvoren"));
					 	$_POST["listfil"] = $_POST["new"];
					break;

					case 10:
						$hlaska = sprintf($this->design["translate_save_ko"], Lang::view("Lang;28;Nezadan nazev"));
					break;

					case 11:
						$hlaska = sprintf($this->design["translate_save_ok"], Lang::view("Lang;30;Jiz existuje."));
						$_POST["listfil"] = $_POST["new"];
					break;
				}
			break;
		}

		// zavolani funkce, ktera nacita seznam souboru pro dany jazyk
		list($cesta, $list) = $this->listFiles($_COOKIE["lang"]);

		// pridani maleho formulare pro vyber souboru
		$out .= $this->htmlListToSelect($list);

		// pokud je zadany soubor nulovy, nacita se defaultni
		$file = ($_POST["listfil"]=="") ? self::TRANS_DEFAULT : $_POST["listfil"];
		// rozparsovani php souboru s retezci
		$list = $this->transParse($file);

		$hlaska = ($hlaska=="" and !is_writable($cesta.$file)) ? $this->design["translate_nowritable"] : $hlaska;

		if(is_array($list)) {
			// ziskani vysledneho html kodu.
			$out .= design(
				$this->design["translate_page"],
				array(
					// ziskani nazvu souboru
					"name"=>$this->onlyName($file),
					"hlaska"=>$hlaska,
					// ziskani seznamu vsech polozek
					"content"=>$this->htmlParse($list)
				)
			);
		} else {
			$out .= design(
				$this->design["translate_noreadable"],
				array(
					// ziskani nazvu souboru
					"name"=>$this->onlyName($file),
				)
			);
		}

		// vraceni HTML
		return $out;
	}

	/**
	 * funkce pro vytvoreni noveho souboru pro ukladani jazykovych nastaveni.
	 *
	 * vraci
	 * true: pokud se vse povedlo
	 * 10: nezadan nazev souboru
	 * 11: soubor jiz existuje
	 *
	 */
	private function newFile($file) {
		// prevod na mala pismenka, malinkata :)
		$file = strtolower($file);

		if($file=="") {
			return 10;
		}

		// cesta k souboru
		$path = "./".CESTA_JAZYKY."/".$_COOKIE["lang"]."/".self::INIT_TRANS.$file.self::END_TRANS;

		// kontrola zda soubor jiz neexistuje
		if(!file_exists($path)) {
			// pokus o vytvoreni
			fopen($path, "w");
			return true;
		} else {
			return 11;
		}
	}

	/**
	 * funkce, ktera obsluhuje import prekladu z jineho jazyka. Je volana funkci $this->translate().
	 *
	 * {@source}
	 * @return string HTML kod
	 */
	private function transImport() {
		// retezec pro vraceni
		$out = "";

		// promenna slouzici jako docasne uloziste
		$ret="";

		// cesta k prekladum
		$cesta = "./".CESTA_JAZYKY."/";

		if($_GET["parametr2"]=="save") {
			$out .= $this->htmlImportSave($this->importSave());
		}


		// nacteni obsahu slozky - zjisteni vsech dostupnych jazyku
		$obsah = scandir($cesta);
		// prochazeni obsahem slozky
		foreach($obsah as $slozka) {
			// kontrola, zda je polozka slozka a zda to neni jazyk prave ted pouzivany
			if(is_dir($cesta.$slozka) and $slozka!="." and $slozka!=".." and $slozka!=$_COOKIE["lang"] and strlen($slozka)==2)
				// ziskani html pro jednu slozku (jazyk)
				$ret .= design($this->design["trans_import_option"], array("lang"=>$slozka));
		}
		// ziskani html pro vyber slozek (jazyku)
		$out .= design($this->design["trans_import_select"], array("s"=>$ret));

		// vyprazdneni docasne promenne
		$ret = "";

		// pokud je vybran jazyk, ze ktereho se ma importovat
		if($_POST["fromlang"]!="") {
			// nastaveni cesty jazyka ze ktereho se importuje
			$cesta_from = "./".CESTA_JAZYKY."/".$_POST["fromlang"]."/";

			// nastaveni cesty jazyka do ktereho se importuje
			$cesta_to = "./".CESTA_JAZYKY."/".$_COOKIE["lang"]."/";

			// nacteni seznamu souboru s preklady
			$seznam = $this->listFiles($_POST["fromlang"]);

			// prochazeni seznamem souboru
			foreach($seznam[1] as $soubor) {
				// kontrola, zda tento soubor uz v jazyce do ktereho se importuje neexistuje.
				if(is_file($cesta_to.$soubor)) {
					// ziskani html kodu pro jiz existujici soubor
					$ret .= design($this->design["trans_import_list_row_e"], array("file"=>$soubor));

				// pokud soubor jeste neexistuje
				} else {
					// ziskani html pro neexistujici soubor
					$ret .= design($this->design["trans_import_list_row_ne"], array("file"=>$soubor));
				}
			}
		}
		// pridani do celkoveho html
		$out .= design($this->design["trans_import_list"], array("body"=>$ret, "lang"=>$_POST["fromlang"]));

		// vyprazdneni docasne promenne
		$ret = "";

		// vraceni html
		return $out;
	}

	/**
	 * funkce, ktera vraci HTML kod s hlaskou o stavu importu souboru z jineho jazyka
	 *
	 * {@source}
	 * @param boolean $status
	 * @return HTML
	 */
	private function htmlImportSave($status) {
		// pokud se zadarilo
		if($status==true) {
			// ok hlaska
			return $this->design["transimport_save_ok"];
		} else {
			// nebo pokud se nezadarilo - ko hlaska
			return $this->design["transimport_save_ko"];
		}
	}

	/**
	 * funkce pro naimportovani souboru z jineho jazyka, vytvoreni novych ci prepsani starych.
	 */
	private function importSave() {
		$ret = true;

		$orig_lang = $_POST["il"];
		$now_lang = $_COOKIE["lang"];

		list(, $list) = $this->listFiles($orig_lang);

		foreach($list as $file) {
			$nfile = str_replace(".", "_", $file);

			if($_POST["rep".$nfile]=="replace" or $_POST["cre".$nfile]=="create") {
				if(!copy("./".CESTA_JAZYKY."/".$orig_lang."/".$file, "./".CESTA_JAZYKY."/".$now_lang."/".$file)) {
					$ret = false;
				}
			}
		}

		return $ret;
	}

	/**
	 * funkce pro ulozeni prekladu
	 *
	 * {@source}
	 * @param string $file nazev souboru
	 * @return boolean
	 */
	private function transSave($file) {
		// cesta k souboru
		$cesta = "./".CESTA_JAZYKY."/".$_COOKIE["lang"]."/".$file;

		// promena pro ukladani zmenenych prekladu
		$tw = "";

		// kontrola, zda soubor existuje
		if(is_file($cesta)) {
			// rozparsovani souboru
			$list = $this->transParse($file);

			// prochazeni seznamem prekladu
			foreach($list as $row) {
				// kontrola, zda tento radek chce uzivatel ulozit
				if(isset($_POST[$row["name"]])) {

					// nazev prekladu - pokud je klic cislo necha se jak je, jinak se hodi do uvozovek
					$name = (is_numeric($row["name"])) ? $row["name"] : '"'.$row["name"].'"';
					// hodnota prekladu, - uvozovky se zjednodusujou
					$value = str_replace("\"", "'", $_POST[$row["name"]]);

					// takovy hack, ktery umoznuje vytvaret linky na preklady
					$value = str_replace(array("(|", "|)"), array("{", "}"), $value);

					// pridani do retezce
					$tw .= '$lang['.$name.'] = "'.$value."\"; \n";
				}
			}

			// pokud uzivatel zadava novy preklad
			if($_POST["newname"]!="") {
				// nazev prekladu - pokud je klic cislo necha se jak je, jinak se hodi do uvozovek
				$name = (is_numeric($_POST["newname"])) ? $_POST["newname"] : '"'.$_POST["newname"].'"';

				// takovy hack, ktery umoznuje vytvaret linky na preklady
				$value = str_replace(array("(|", "|)"), array("{", "}"), $_POST["newvalue"]);

				// pridani do retezce
				$tw .= '$lang['.$name.'] = "'.$value."\"; \n";
			}

			// uprava retezce na tvar PHP
			$tw = "<?php\n".$tw."?>";

			// otevreni souboru
			if(is_writable($cesta)) {
				$open = @fopen($cesta, "w");

				// zapsani zmeneneho prekladu
				if(@fwrite($open, $tw))
					return true;
			}
		}
		return false;
	}

	/**
	 * funkce, ktera vraci HTML - seznam vsech prekladu.
	 *
	 * {@source}
	 * @param array $array seznam prekladu
	 * @return string HTML
	 */
	private function htmlParse($array) {
		// retezec pro vraceni
		$out = "";

		// prochazeni dodanym polem s preklady
		foreach($array as $row) {
			// pokud je preklad kratsi nez X znaku
			if(strlen($row["value"]) < 30) {
				// dodani klasickeho <input type="text">
				$out .= design(
					$this->design["translate_page_list_one"],
					array(
						"name"=>$row["name"],
						"value"=>$row["value"]
					)
				);

			// pokud je delsi nez X znaku
			} else {
				// dodani textarey s dopocitanim radku
				$out .= design(
					$this->design["translate_page_list_one_textarea"],
					array(
						"name"=>$row["name"],
						"value"=>$row["value"],
						"rows"=>strlen($row["value"])/30
					)
				);
			}
		}

		// ziskani a vraceni vysledneho HTML
		return design(
			$this->design["translate_page_list"],
			array(
				"content"=>$out,
				"file"=>$_POST["listfil"]
			)
		);
	}

	/**
	 * funkce, ktera dokaze rozparsovat PHP soubor
	 *
	 * <code>
	 * <?php
	 * $lang['lib_klic'] = "lib ret";
	 * $lang['lib_klic2'] = "lib ret2";
	 * ?>
	 * </code>
	 *
	 * na pole
	 *
	 * <code>
	 * array (
	 * 	1 =>
	 * 		array (
	 * 			'name' => 'lib_klic',
	 * 			'value' => 'lib ret',
	 * 		),
	 *
	 * 	2 =>
	 * 		array (
	 * 			'name' => 'lib_klic2',
	 * 			'value' => 'lib ret2',
	 * 		)
	 * )
	 * </code>
	 *
	 * {@source}
	 * @param string $file nazev souboru
	 * @return array
	 */
	private function transParse($file) {
		// cesta k souboru
		$cesta = "./".CESTA_JAZYKY."/".$_COOKIE["lang"]."/".$file;

		// pole pro vraceni
		$out = array();

		// kontrola, zda soubor existuje a je souborem :)
		if(is_file($cesta)) {
			// nacteni souboru
			/** @todo zase ty opravneni */
			if(is_readable($cesta)) {
				$open = fopen($cesta, "r");
			} else {
				return false;
			}

			// prochazeni souborem po radcich
			while($radek = fgets($open)) {
				// odstraneni pocatecniho php znaku
				$radek = str_replace("<?php", "", $radek);
				// odstraneni koncoveho php znaku
				$radek = str_replace("?>", "", $radek);
				// odstraneni
				$radek = str_replace("\n", "", $radek);

				// odstraneni prebytecnych mezer
				trim($radek);

				// kontrola, zda je na konci radku strednik (bodkociarka, ;)
				if($radek[strlen($radek)-2]==";") {
					// pridani radku z minula

					/**
					 * slouzi k tomu, kdyz se soubor blbe ulozi a jeden vyraz se rozhodi na dva radky.
					 * Dokaze spojit ale jen dva radky za sebou.
					 */

					$radek = $pridej.$radek;

					// rozparsovani (to byla makacka, tohle mi jeste nejde.)
					preg_match('/\$lang\[(?<name>[\w\"]+)\]?.=?.\"(?<value>[^"]+)\";/', $radek, $pars);

					// prirazeni klice a odstraneni prebytecnych uvozovek
					$name = trim($pars["name"], '"');
					// prirazeni hodnoty a odstraneni prebytecnych uvozovek
					$value = trim($pars["value"], '"');

					// takovy hack, ktery umoznuje vytvaret linky na preklady
					$value = str_replace(array("{", "}"), array("(|", "|)"), $value);

					// smazani zbytecnych poli
					unset($pars, $pridej);

					// pokud neni nazev prazdny
					if($name!="")
						// pridani do pole
						$out[$name] = array("name"=>$name, "value"=>$value);
				} else {
					// pokud je vyraz na dvou radcich
					$pridej .= $radek;
				}
			}
		}

		// serazeni podle nazvu klicu
		ksort($out);

		// vraceni pole
		return $out;
	}

	/**
	 * funkce, ktera z nazvu souboru vybira pouze jeho skutecny nazev. Pracuje s jednou predponou a jednou
	 * priponou (neco.nazev.nic ==> Nazev).
	 *
	 * {@source}
	 * @param string $file nazev souboru
	 * @return string Skutecny nazev souboru
	 *
	 */
	private function onlyName($file) {
		$name = explode(".", $file);
		unset($name[0], $name[count($name)]);
		return ucfirst(implode(".", $name));
	}

	/**
	 * funkce, ktera vraci html kod s vyberovym polickem se soubory.
	 *
	 * {@source}
	 * @param array $array seznam souboru
	 * @return HTML
	 */
	private function htmlListToSelect($array) {
		// promenna pro vraceni
		$out = "";

		// prochazeni seznamem
		foreach($array as $file) {
			$name = $file;

			// odstraneni zbytecnosti okolo
			if(self::NICE_TRANS_SELECT==true) {
				$name = $this->onlyName($file);
			}

			$out .= design($this->design["list_translate_files"], array("file"=>$file, "name"=>$name));
		}
		return sprintf($this->design["list_translate_select"], $out);
	}

	/**
	 * funkce, ktera nacita seznam souboru s preklady ve slozce.
	 *
	 * {@source}
	 * @param string $lang pro jaky jazyk jsou soubory hledany
	 * @return array array(cesta, array(seznam souboru))
	 */
	private function listFiles($lang) {
		// cesta k souborum
		$cesta = "./".CESTA_JAZYKY."/".$lang."/";

		// pole pro ukladani vysledku
		$out = array();

		// kontrola, zda slozka pro jazyk existuje
		if(is_dir($cesta)) {
			// ziskani seznamu souboru
			$obsah = scandir($cesta);

			// prochazeni seznamem
			foreach($obsah as $soubor) {
				// kontrola, zda ma nazev spravny tvar
				if(ereg(self::INIT_TRANS."*".self::END_TRANS, $soubor)) {
					// pridani do pole
					$out[] = $soubor;
				}
			}
		}

		return array($cesta, $out);
	}
}
?>
