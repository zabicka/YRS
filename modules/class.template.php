<?php
/**
 * @package modules
 */
/**
 * Zakladni modul pro veskere zobrazovani obsahu a pro nacitani vzhledu.
 *
 * @author Juda Yetty Kaleta <yettycz@gmail.com>
 * @package modules
 * @subpackage core
 * @todo Chtelo by prepracovat nacitani stylu. Misto "default" pouzit konstantu uz pri definovani promenne
 * {@link Template::$name} a potom uz jen pripadne menit.
 */

class Template {
    /**
     * urceni modulu, ktere jsou
     * 1. potrebne pro beh teto tridy
     * 2. doporucene pro beh tridy
     */
    var $always = array("page");
    var $recommended = array("", "");

	const REPEAT_GET_BLOCKS=5;

	static $title = array();
	static $meta = array();

    /**
     * promenna pro ukladani jednotlivych bloku, ktere se maji zobrazit
     */
    var $bloky = array();

	static $file = '';

    /**
     * nazev pouziteho stylu
     */
    var $name="";


    /**
     * funkce slouzici k instalaci
     */
    public function install() {
        global $db, $design;

        include "../settings.php";

        $ret = "";
        $ret .= design($design["hlavicka"], array("nazev"=>"Stav instalace modulu", "style"=>"hlavicka"));

        if(($res=$db->query("create table if not exists __settings ( name varchar(126) primary key, value varchar(255))")))
		{
            $ret .= design(
				$design["radek"],
				array(
					"nazev"=>SM_INSTALL_DB,
					"stav"=>"[OK]",
					"barva"=>"true",
					"style"=>"lichy"
				)
			);
        }

		mysql_free_result($res);

        $cesta = "../".$_SESSION["cesta_styly"]."/";
        $slozka = scandir($cesta);

        foreach($slozka as $soubor) {
            if(is_dir($cesta.$soubor) and $soubor != "." and $soubor != "..") {
                $opt .= design($design["option"], array("name"=>$soubor));
            }
        }
        $ret .= design($design["radeksel"], array("style"=>"sudy", "nazev"=>SM_INSTALL_CHOOSE, "name"=>"template", "opt"=>$opt));

        return $ret;
    }

    public function install_save() {
        global $db;
        $db->query("delete from __settings where name='GENERAL_TEMPLATE'");
        $db->query("insert into __settings values('GENERAL_TEMPLATE','".$_POST["template"]."')");
    }

    /**
     * Funkce pro nacteni stylu a podani zpravy o tom.
     *
     * {@source}
     * @return boolean
     */
    public function __construct() {
        global $hlaseni;

        // pokud neni definovan vzhled
        if(GENERAL_TEMPLATE!="" and is_file(CESTA_STYLY."/".GENERAL_TEMPLATE."/index.html")) {
            // pridani hlaseni
            //$hlaseni->pridej(sprintf(TEMPLATE_NACTENO, GENERAL_TEMPLATE), true, "settings");
            // ulozeni nazvu stylu
            $this->name=GENERAL_TEMPLATE;

            // pokud neni definovan vzhled, nacte se defaultni
        } else {
            // pridani hlaseni
            //$hlaseni->pridej(sprintf(TEMPLATE_NENALEZENO, GENERAL_TEMPLATE), false, "settings");
            // ulozeni nazvu stylu
            $this->name="default";

            // vypsani chybove hlasky, zatim zruseno
            //printf(TEMPLATE_NENALEZENO, GENERAL_TEMPLATE);
        }
        return true;
    }

    /**
     * Funkce, ktera nacita nastaveni z DB a pak je zamenuje.
     *
     * {@source}
     * @param string $ret retezec, ve kterem maji byt konstanty nahrazeny obsahem.
     * @return string
     */
    private function konstanty($ret) {
		if(DB::query("show tables from yrs like '__settings'")->fetchSingle()) {
			$settings = DB::query("select * from __settings");
			foreach($settings->getIterator() as $row) {
				$ret = str_replace("{".strtoupper($row[name])."}", $row[value], $ret);
			}
		}

		$ret = str_replace("{CESTA_STYL}", trim(CESTA_ABSOLUTNI, "/")."/".CESTA_STYLY."/".GENERAL_TEMPLATE."/", $ret);
		$ret = str_replace("{CESTA_ABSOLUTNI}", trim(CESTA_ABSOLUTNI, "/")."/".$_COOKIE[lang]."/", $ret);

		// seznam vsech bloku, ktere se odstrani, pokud budou prazdne.
		$ret = str_replace("{INIT}", "", $ret);
		$ret = str_replace("{META}", implode("\n", Template::$meta), $ret);

		$titles = implode(" | ", Template::$title);
		$ret = str_replace("{TITLE}", ($titles!='') ? " | ".$titles : '', $ret);

        return $ret;
    }


	/**
	 * Funkce, ktera dokaze nacist casti HTML kodu z jineho souboru. Soubor se musi nachazet ve slozce
	 * aktualniho stylu.
	 *
	 * {@source}
	 * @param string $soubor soubor, ktery se ma nacist
	 * @return string HTML kod nebo hlaseni o chybe
	 */
	public function insert($soubor) {
		// kontrola, zda neni dodana prazdna hodnota
		if($soubor!="") {
			// prevod na mala pismena
			$soubor = strtolower($soubor);

			// kontrola zda soubor existuje
			if(is_file(CESTA_STYLY."/".$this->name."/".$soubor)) {

				// nacteni souboru
				$file = fopen(CESTA_STYLY."/".$this->name."/".$soubor, "r");

				// vlozeni obsahu do promenne
				while($radek = fgets($file)) $obsah .= $radek;

			} else {
				// nacteni hlasky o nenalezeni souboru
				$obsah = TEMPLATE_INSERT_ERROR;
			}
			// vraceni HTML kodu
			return $obsah;
		}
	}

    /**
     * Funkce pro vykresleni cele stranky. Vraci HTML kod. V HTML kodu souboru mohou byt zapsany bloky:
     * <code><title>{WEBSITE_NAME}</title></code>
     * Ty jsou pak nahrazeny:
     * 1. blokem, ktery je vytvoren v prubehu skriptu (napr. blok "obsah")
     * 2. konstantou (polozky v db->settings nebo par natvrdo definovanych)
     * 3. libovolnym modulem s metodou view()
     * Pak jeste jednou probehne bod 2. a nahradi se kontanty, ktere pridaly zavolane moduly.
     *
     * {@source}
     * @param string $soubor soubor, ktery se ma nacist.
     * @return string
     */
    public function render($soubor="") {
		$soubor = ($soubor == '') ? self::$file : $soubor;

		$soubor = ($soubor == '') ? Dictionary::modul($_GET['class']).'.html' : $soubor;

		$only = ($_SESSION["only"]=="") ? $_GET["only"] : $_SESSION["only"];

        // nacteni indexu
		if($only!=true) {
			if($soubor != "" and is_file(CESTA_STYLY."/".$this->name."/".$soubor)) {
				$index = CESTA_STYLY."/".$this->name."/".$soubor;
			} else if(is_file(CESTA_STYLY."/".$this->name."/".strtolower($_GET["class"]).".html")){
				$index = CESTA_STYLY."/".$this->name."/".strtolower($_GET["class"]).".html";
			} else {
				$index = CESTA_STYLY."/".$this->name."/index.html";
			}


			$obsah = file_get_contents($index);

			// nahrazeni prebyvajicich \" - chtelo by to prijit na to, proc tam vlastne jsou
			$obsah =  str_replace("\\\"", "", $obsah);

		} else {
			$obsah = "{".strtoupper(BLOCK_CONTENT)."}";
		}

        // prochazeni polem ulozenych bloku
        foreach($this->bloky as $blok) {
            // pokud ma blok v sablone misto je nahrazen
            $obsah = str_replace("{".strtoupper($blok[0])."}", $blok[1], $obsah);
        }
		for($x=1; $x<=self::REPEAT_GET_BLOCKS ;$x++) {
			// nahrazeni konstant
			$obsah = $this->konstanty($obsah);

			$obsah = $this->getBlocks($obsah);

			/*
			 * opetovne prochazeni bloku je tu proto, ze kdyz se na strance nacita pomoci funkce insert
			 * dalsi html kod, bloky se tam neprojevi.
			 */
			// prochazeni polem ulozenych bloku
			foreach($this->bloky as $blok) {
				// pokud ma blok v sablone misto je nahrazen
				$obsah = str_replace("{".strtoupper($blok[0])."}", $blok[1], $obsah);
			}
		}

        // vraceni HTML kodu
        return $obsah;
    }

	private function getBlocks($obsah) {
		global $init;

	    // pokud zustal ve stylu nenahrazeny blok (ex.: {BLOK3})
        if(ereg("{(.*)}", $obsah)) {
            // nalezeni vsech techto bloku
            //preg_match_all("|{(.*)}|", $obsah, $vysledek);
            preg_match_all("/{([^}]*)}/", $obsah, $vysledek);

            // prochazeni temito bloky
            foreach($vysledek[1] as $radek) {
                // oddeleni nazvu od pridavnych parametru
                $vysledek = explode(":", $radek);

                list($name, $func)  = explode(";", $vysledek[0]);
                // nastaveni prvniho pismenka velkeho, ostatni male
                $name = ucfirst(strtolower($name));
                $func = strtolower($func);

				$url = strtolower("/".$name."/".(($func=="" or !method_exists($name, $func)) ? "view" : $func)."/".$vysledek[1]);

				$name = (class_exists($name.'View')) ? $name.'View' : $name;

                // pokud takovato trida existuje
				if(($html=TMP::getTMP($url))!==false and $init->showtmp!=false) {
					$obsah = str_replace("{".$radek."}", $html, $obsah);
                } else if(class_exists($name)) {
                    // vytvoreni tridy
                    $objekt = new $name;

                    if(method_exists($name, $func)) {
                    	$html = $objekt->$func($vysledek[1]);
					//	$this->bloky[] = Init::getClass($o
                    } else if(method_exists($name, "view")) {
                    	$html = $objekt->view($vysledek[1]);
                    } else {
                        $html = "Modul '".$name."' se nepodařilo načíst!";
                    }
					$obsah = str_replace("{".$radek."}", $html, $obsah);

					if($init->savetmp!==false)
						TMP::setTMP($url, $html);
                } else {
                    // pokud trida neexistuje, vypise se chybova hlaska.
                    //$obsah = str_replace("{".$radek."}", "Modul '".$name."' se nepodařilo nalézt!", $obsah);
                    # odstraneno, v javascriptu je tato syntaxe obcas pouzivana, takze je to spis na skodu nez k uzitku
                }


            }
        }
		return $obsah;
	}

    /**
     * Funkce nacitajici soubor design.*.html, ten rozkouskovava na jednotlive dily, dily sklada do pole.
     *
     * {@source}
     * @param string $soubor od jake tridy ma byt soubor nacten.
     * @return array pole s HTML kody
     */
    private function parseDesign($soubor) {
        // otevreni souboru
		$obsah = file_get_contents($soubor);
   //     $velikost = filesize($soubor);
   //     $soubor = fopen($soubor, "r");

        // nacteni souboru
   //     while(($znak=fgetc($soubor)) or strlen($obsah)<$velikost) $obsah .= $znak;



        // odstraneni znaku pro novy radek
        $obsah = str_replace("\n", "????", $obsah);

        // rozdeleni ziskaneho obsahu do jednotlivych dilu
        preg_match_all("|<!--(.*)-->(.*)<!--(.!.*)-->|U", $obsah, $casti);

		//debug_var($casti);

        // vytvoreni pole, ktere se bude vracet
        $ret = array();
        // vynulovani poctu ziskanych celku
        $pocet = 0;

        // prochazeni ziskanymi celky
        foreach($casti[1] as $cast) {
			$cast = str_replace(" ", "", $cast);
			$casti[2][$pocet] = str_replace("????", "\n", $casti[2][$pocet]);

			if($cast=='INIT') {
				$i = Init::construct();
				$i->addToBlocks('init', $casti[2][$pocet]);
			}

            // pridani celku do pole. Jako index je pouzit jeho nazev.
            $ret[strtolower($cast)] = $casti[2][$pocet];
            $pocet++;
        }

        return $ret;
    }

    /**
     * funkce, ktera nacita ze souboru design.*.html potrebne HTML
     * kody pro tridy. Tridy si vetsinou tuto metodu budou volat pri
     * jejich zavolani. Pokud nebude soubor ve slozce se sablonou existovat,
     * bude se hledat defaultni.
     *
     * Napriklad pro tridu Hlaseni se nacte soubor design.hlaseni.html.
     * Z toho se vytahnou jednotlive casti oznacene:
     * <code>
     * <!-- NAZEV_CASTI -->
     * 	KOD
     * <!-- !NAZEV_CASTI -->
     * </code>
     * Vraceno bude pole s indexy podle nazvu casti.
     *
     * {@source}
     * @param string $trida nazev tridy, pro kterou se ma sablona nacist.
     * @return array|boolean pole s kody nebo informace o chybe
     */
    public function design($trida) {
        // upraveni nazvu tridy - prevod na mala pismena, pripadne odstraneni mezerer
        $trida = str_replace(" ", "", strtolower($trida));

        // nadefinovani cest k souborum
        $cesta = CESTA_STYLY."/".GENERAL_TEMPLATE."/design.".$trida.".html";
        $cesta_default = CESTA_STYLY."/default/design.".$trida.".html";

        // kontrola, zda soubor pro zvolenou sablonu existuje
        if(is_file($cesta)) {
            // zavolani funkce pro rozkouskovani a vraceni ziskane hodnoty
            return $this->parseDesign($cesta);
            // pokud soubor nebyl nalezen
        } else {
            // kontrola zda existuje defaultni soubor
            if(is_file($cesta_default)) {
                // zavolani funkce pro rozkouskovani a vraceni ziskane hodnoty
                return $this->parseDesign($cesta_default);
                // pokud defaultni soubor neexistuje
            } else {
                // vraceni informace o neuspechu
                return false;
            }
        }
    }
}
?>
