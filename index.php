<?php
/**
 * <b>YRS this is: YRS require somebody. Somebody require YRS.</b>
 *
 * Toto je univerzalni system pro spravu jakkehokoliv webu. Pracuje diky ruznorodym modulum.
 * Pouziva mod_rewrite k vytvareni Cool URI a za databazi bylo zvoleno MySQL, diky modulum vsak neni problem
 * napsat i podporu pro jinou databazi. Vse pracuje na PHP.
 *
 * Tato dokumentace slouzi hlavne jako pomoc pri vytvareni novych modulu a vzajemne spolupraci mezi moduly.
 *
 * @package core
 *
 * @author Juda Yetty Kaleta
 * @copyright Copyright 2009, Juda Yetty Kaleta
 *
 * @version 0.1 alpha
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

/**
 * @todo Presmerovani plus jazyky je nekde kolem radku 237, vola se funkce {@link jazyky()}
 * @todo Zasadni zmena systemu. V puvodnim planu bylo, ze druhy parametr vola metodu ve tride (pokud existuje). Ted mi ale prijde, ze by bylo mnohem snazsi volat nejakou rozrazovaci metodu, ktera dany parametr libovolne zpracuje. Ubyly by pak problemy s overovanim pristupu...
 * @todo Tak jsem to prepracoval uplne - vsechno je v tride Init;
 */

/**
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!
 * !!						!!
 * !! Slozky konci lomitkem !!
 * !!						!!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!
 */

/**
 * ob_start by mel zamezit chybovym hlaskam pri posilani header. Trochu zpomaluje vykonnost - pri zatezi
 *
 * siege -c 50 -i -b URL
 *
 * asi o 0,1s.
 *
 */

ob_start();


// zjisteni, zda je vytvoren .htaccess. Pokud ne, spusti se instalace.
if(!is_file(".htaccess")) {
	if(is_file("install/install.php")) {
		// presmerovani, u uzivatele se diky tomu projevi zmena
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: install/install.php");
		header("Connection: close");
	} else {
		echo "<h1>YRS</h1>";
		echo "<p>Yout install of YRS doesn't work. It may be because you remove file .htaccess and directory
		install not exists any more.<br> Please download correct version of YRS and reinstall or create file .htaccess
		by hand.</p>";
	}
	exit;
}



/**
 * Jsou tam takove veci jako vychozi jazyk, cesta k modulum, sablonam apod.
 */
include('settings.php');

/**
 * Parametry pro pripojeni k DB!
 */
include('db.php');

/**
 * funkce, ktera nastavuje, odkud se nacitaji tridy.
 *
 * {@source}
 * @param string $jmeno_tridy nazev tridy
 * @todo Pribude overeni, zda se ma modul nacist, asi z nejakeho konfiguracniho souboru nebo z DB. Nebo by to
 * slo provest tak, ze si to modul bude hlidat sam, ale to by bylo zbytecne slozite. Nejjednodusi by bylo to vyresit
 * tabulkou se zakazanymi (nebo povolenymi?) moduly.
 */
function __autoload($jmeno_tridy) {
	if(ereg('.(.*)Admin', $jmeno_tridy)) {
		$jmeno_tridy = str_replace('Admin', '', $jmeno_tridy);
	}

	if(ereg('.(.*)View', $jmeno_tridy)) {
		$jmeno_tridy = str_replace('View', '', $jmeno_tridy);
	}

	$cestaClass = CESTA_MODULY."/class.".strtolower($jmeno_tridy).".php";

	if(is_file($cestaClass)) {

		require_once $cestaClass; // cesta k souborum s tridami

		$jazyk = souborLang($jmeno_tridy);

		if(is_file($jazyk))	{
			require_once $jazyk;
		}
	} else {
		//echo "Nebyla nalezena trida s adresou: ./".$cestaClass;
	}
}


/**
 * Funkce odchytavajici chybove hlasky PHP a ukladajici je pomoci modulu Hlaseni do databaze. Banalni chyby se
 * ignoruji (definice <var>IGNORE_ERRORS</var>), dulezitejsi se vypisuji (definice <var>PRINT_ERRORS</var>).
 *
 * {@source}
 * @param int $cislo udava dulezitost chyby
 * @param string $zprava obsahuje text chyby
 * @param string $soubor soubor ve kterem k chybe doslo
 * @param int $radek radek s chybou
 * @todo Chtelo by prepracovat odstraneni cesty k souboru. Uz ani nevim proc, zatim mi to nevadilo.
 */
function Chyby($cislo, $zprava, $soubor="", $radek="") {
	global $hlaseni;

	preg_match("/.*\/(.*\.php)/", $soubor, $soubor); /* odstraneni cesty souboru, ponechani
	jenom jeho nazvu, casem by se to melo prepracovat	*/

	// vypsani kritickych hlasek
	if($cislo < PRINT_ERRORS or ($hlaseni=="" and $cislo < IGNORE_ERRORS)) {
		echo "<p><strong>".strtoupper($soubor[1]).(($radek!="")?" (".$radek.")":"").": </strong><pre>".$zprava."</pre></p>";
	}

	// filtrovani nejmene dulezitych chyb
	if($cislo < IGNORE_ERRORS) {
		if($hlaseni!="") $hlaseni->pridej(
									sprintf(HLASENI_CHYBA, $radek, $zprava), /* text s chybou */
									false, /* oznaceni hlaseni za chybove */
									$soubor[1],
									/* dodani nazvu souboru, pole je tam kvuli
									  vracene hodnote z funkce preg_match */
								  	$cislo
								  ); // ulozeni do hlaseni

	}
}

/**
 * @link http://php.vrana.cz/zpracovani-fatalnich-chyb.php
 * @todo domyslet k cemu by to bylo dobre...
 */
/*function shutdown_error() {
    $error = error_get_last();
    if ($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE)) {
        echo "<pre>";
		print_r($error);;
		echo "</pre>";
		exit;
    }
}


register_shutdown_function('shutdown_error');
*/

/** kontrola, zda neni jazyk podvrzen */
if(!ereg("[a-zA-Z][a-zA-Z]", $_GET["lang"])) {
	$_GET["lang"] = '';
}
if(!ereg("[a-zA-Z][a-zA-Z]", $_COOKIE["lang"])) {
	$_COOKIE["lang"] = '';
}



include('functions.php');

session_start(); // zapnuti session
session_register("only");


/**
 * Objekt pro ukladani vsech systemovych hlasek a jejich ulozeni do DB.
 * @name $hlaseni
 * @global object $GLOBALS["hlaseni"]
 */
$hlaseni_class = "Hlaseni";
if(class_exists($hlaseni_class)) $GLOBALS["hlaseni"] = new Hlaseni;
set_error_handler('Chyby'); // nastaveni chybove funkce


/**
 * Pouziti Nette
 */
if(NETTE===true) {
	// Ladenka
	if(class_exists(NETTE_DEBUG)) Debug::enable(Debug::DETECT);
}

##################### INIT END #######################
#########


	######				######
############## CORE #############


/// SPOJENI S DB ///
DB::connect(array(
    'driver'   => DB,
    'host'     => DB_SERVER,
    'username' => DB_USER,
    'password' => DB_PASSWORD,
    'database' => DB_DB,
    'charset'  => DB_KODOVANI,
));

/*$typ = DB;

if($typ=='DB') {
	// spusteni instalace
	header("Location: install/install.php");
	header("Connection: close");
	exit;
}*/

/**
 * Vytvoreni objektu pro spolupraci s databazi.
 *
 * @name $db
 * @global object $GLOBALS["db"]
 */
/*
$GLOBALS["db"] = new $typ;
if(method_exists($db, "connect")) {
	if($db->connect(, , , , )==false) {
	//	instalace();

		Chyby(1, "Nepodarilo se pripojit k DB. Typ databaze: ".$typ, "/index.php", 0);
		exit;
	}
}*/


# Nacteni nastaveni
if($settings = DB::query("select * from __settings where name!=%s", "index.php")) {
	foreach($settings->getIterator() as $row) {
		/**
		 * @ignore
		 */
		define(strtoupper($row[name]), $row[value]);
	}
} else {
//	instalace();
}

jazyky();


/**
 * Objekt pro zobrazeni. Do neho se ukladaji jednotlive bloky, ktere se maji zaobrazit a pak se
 * vykresluji.
 *
 * @name $vzhled
 * @global object $GLOBALS["vzhled"]
 */


$GLOBALS["vzhled"]= new Template();
$GLOBALS["init"]= Init::construct();


if($init->run()!==false) {
	$vzhled->bloky = $init->getBlocks();

	$init->html = $vzhled->render();
}

echo $init->html;

ob_end_flush();
?>
