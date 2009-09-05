<?php

/**
 * Funkce zjisti, zda ma uzivatel definovany jazyk. Pokud ne, jazyk mu priradi a ulozi ho do <var>$_COOKIE["lang]</var>.
 *
 * {@source }
 *
 * @return array
 */
function jazyky() {
	$seznam = array(); // vytvoreni pole pro ukladani

	if($_GET["lang"]!="" and strlen($_GET["lang"])<=2 and is_dir(CESTA_JAZYKY.$_GET['lang'].'/')) {
		$seznam[] = $_GET["lang"]; // pokud uzivatel meni svuj jazyk
	} else {
		presmerovaniPlusJazyk();
		exit;
	}

	if($_COOKIE["lang"]!="")  $seznam[] = $_COOKIE["lang"]; // pokud uz ma uzivatel definovany jazyk
	if($_GET["lang"]!="" and $_COOKIE["lang"]!=$_GET["lang"]) {
		setcookie("lang", $_GET["lang"], time()+3600, "/"); // ulozeni noveho jazyka do cookies


		header("HTTP/1.1 301 Moved Permanently");
		header("Location: ".URL::create($_GET["lang"], $_GET["class"], $_GET["akce"], $_GET["parametr1"], $_GET["parametr2"], $_GET["parametr3"], $_GET["parametr4"], $_GET["parametr5"]));
		header("Connection: close");
	}

	$seznam = array_merge($seznam, seznamJazyku());

	return $seznam;
}

/**
 * Funkce, ktera ziska z promenne <var>$_SERVER["HTTP_ACCEPT_LANGUAGE"]</var> jazyky uzivatele a vrati je v poli.
 * Pouziva se napr. pokud uzivatel nema definovan jazyk a system mu chce priradit co nejvhodnejsi.
 *
 * {@source}
 * @return array
 */
function seznamJazyku() {
	$jazyky = explode(",", $_SERVER["HTTP_ACCEPT_LANGUAGE"]); // zjisteni a rozdeleni
	$seznam = array();
	foreach($jazyky as $jazyk) {
		list($detail) = explode(";", $jazyk);

		if(ereg("-", $detail)) {
			list($detail) = explode("-", $detail);
		}

		if(is_dir(CESTA_JAZYKY.$detail)) {
			$seznam[] = $detail;
		}
	}

	if(count($seznam)==0) {
		$seznam[] = DEFAULT_LANG;
	}

	return $seznam;
}

/**
 * Funkce, ktera vraci aktualni stranku uzivatele. Vraci bez absolutni cesty a bez jazyka
 *
 * {@source}
 * @return string
 */
function aktualniCesta() {
	$cesta = "/";

	$cesta .= ($_GET["class"]!="") ? $_GET["class"]."/" : "";
	$cesta .= ($_GET["akce"]!="") ? $_GET["akce"]."/" : "";
	$cesta .= ($_GET["parametr1"]!="") ? $_GET["parametr1"]."/" : "";
	$cesta .= ($_GET["parametr2"]!="") ? $_GET["parametr2"]."/" : "";
	$cesta .= ($_GET["parametr3"]!="") ? $_GET["parametr3"]."/" : "";
	$cesta .= ($_GET["parametr4"]!="") ? $_GET["parametr4"]."/" : "";

	return $cesta;
}

/**
 * Funkce, ktera se aktivuje pokud uzivatel zada misto jazyku primo stranku (tridu)
 * - <b>spatne:</b> www.mujweb.cz/stranka
 * - <b>dobre:</b> www.mujweb.cz/jazyk/stranka
 *
 * To, ze uzivatel nezadal jazyk se pozna podle delky retezce (jazyk je vzdy o dvou pismenech
 *
 * {@source}
 * @return boolean
 */
function presmerovaniPlusJazyk() {
	if($_COOKIE["lang"]!="") {
		header("HTTP/1.1 301 Moved Permanently");

		$adresa = URL::create($_COOKIE["lang"], $_GET["class"], $_GET["akce"], $_GET["parametr1"], $_GET["parametr2"], $_GET["parametr3"], $_GET["parametr4"], $_GET["parametr5"]);
		header("Location: ".$adresa);
		header("Connection: close");

	} else {
		$jazyky = seznamJazyku();
		$jazyk = $jazyky[0];

		header("HTTP/1.1 301 Moved Permanently");
		header("Location: ".URL::create($jazyk, $_GET["class"], $_GET["akce"], $_GET["parametr1"], $_GET["parametr2"], $_GET["parametr3"], $_GET["parametr4"], $_GET["parametr5"]));
		header("Connection: close");
	}

	return false;
}

/**
 * funkce, ktera zjistuje existenci souboru s jazykovymi nastavenimi podle seznamu
 * moznych jazyku z funkce {@link jazyky()}. Funkce vraci cestu k souboru.
 *
 * ------------
 *
 * <b>Uz zastarale, obstarava trida Lang;</b>
 *
 * {@source}
 * @param string $soubor cesta k souboru s jazykovymi udaji
 * @return string
 */
function souborLang($soubor) {
	$soubor = strtolower($soubor); // vsechny souboru jsou malymi pismenky
	$cestaLang = CESTA_JAZYKY."/%s/".strtolower($soubor); // definice cesty k souboru
	/*$jazyky = jazyky(); // nacteni moznych jazyku

	foreach($jazyky as $jazyk) {
		$now = sprintf($cestaLang, $jazyk); // dodani ziskaneho jazyka do definice cesty
		if(is_file($now)) {
			return $now; // soubor existuje, vrati funkce cestu k nemu
		}
	}
	*/
	$now = sprintf($cestaLang, $_COOKIE["lang"]); // dodani ziskaneho jazyka do definice cesty
	if(is_file($now)) {
		return $now; // soubor existuje, vrati funkce cestu k nemu
	}

	//$theme = new Template();
	// pokud zadny jazyk z moznych neexistuje nacte se defaultni
	if(is_file(sprintf($cestaLang, DEFAULT_LANG))) return sprintf($cestaLang, DEFAULT_LANG);
}


/**
 * Funkce, ktera zajisti zamenu retezce napr. <b>%seznam</b> v souboru se stylem (design.*.html)
 * za obsah pole s timto klicem.
 *
 * 21.5.2009 - pridan hack na doplneni poctu znaku (pro tvar %00x pri importu) {@author yetty}
 *
 * {@source}
 * @param string $obsah puvodni retezec s nenahrazenymi vyrazi ziskany ze souboru se sablonou
 * @param array("klic"=>"zamena") $nahrada pole se seznamem vyrazu, ktere se maji nahradit a za co se maji nahradit
 * @return string
 */
function design($obsah, $nahrada, $doplneni_znak="0", $doplneni_pocet=0, $znak="%") {
	// prochazeni polem
	foreach($nahrada as $radek) {
		// ziskani klice aktualniho radku
		$klic = key($nahrada);
		// nahrazeni vyrazu v retezci
		$obsah = str_replace($znak.str_pad($klic, $doplneni_pocet, $doplneni_znak, STR_PAD_LEFT), $radek, $obsah);
		// posunuti pole - pro ziskani klice
		next($nahrada);
	}

	return $obsah;
}


function goodurl($url) {
	$utf8table = array ("\xc3\xa1"=>"a",
						"\xc3\xa4"=>"a",
						"\xc4\x8d"=>"c",
						"\xc4\x8f"=>"d",
						"\xc3\xa9"=>"e",
						"\xc4\x9b"=>"e",
						"\xc3\xad"=>"i",
						"\xc4\xbe"=>"l",
						"\xc4\xba"=>"l",
						"\xc5\x88"=>"n",
						"\xc3\xb3"=>"o",
						"\xc3\xb6"=>"o",
						"\xc5\x91"=>"o",
						"\xc3\xb4"=>"o",
						"\xc5\x99"=>"r",
						"\xc5\x95"=>"r",
						"\xc5\xa1"=>"s",
						"\xc5\xa5"=>"t",
						"\xc3\xba"=>"u",
						"\xc5\xaf"=>"u",
						"\xc3\xbc"=>"u",
						"\xc5\xb1"=>"u",
						"\xc3\xbd"=>"y",
						"\xc5\xbe"=>"z",
						"\xc3\x81"=>"A",
						"\xc3\x84"=>"A",
						"\xc4\x8c"=>"C",
						"\xc4\x8e"=>"D",
						"\xc3\x89"=>"E",
						"\xc4\x9a"=>"E",
						"\xc3\x8d"=>"I",
						"\xc4\xbd"=>"L",
						"\xc4\xb9"=>"L",
						"\xc5\x87"=>"N",
						"\xc3\x93"=>"O",
						"\xc3\x96"=>"O",
						"\xc5\x90"=>"O",
						"\xc3\x94"=>"O",
						"\xc5\x98"=>"R",
						"\xc5\x94"=>"R",
						"\xc5\xa0"=>"S",
						"\xc5\xa4"=>"T",
						"\xc3\x9a"=>"U",
						"\xc5\xae"=>"U",
						"\xc3\x9c"=>"U",
						"\xc5\xb0"=>"U",
						"\xc3\x9d"=>"Y",
						"\xc5\xbd"=>"Z");

	$text = strtolower(strtr($url, $utf8table));

	// mezery
	$text = str_replace(array(" ", "?", "<", ">", "?", ":", "\"", "'", ";", "{", "}", "[", "]", "/", "\\", "|", ",", ".", "=", ")", "(", "*", "&", "^", "%", "$", "#", "@", "!", "~", "`", "+", "ˇ"), "-", $text);

	while($text[strlen($text)-1]=="-") $text = substr($text, 0, strlen($text)-1);
	while(ereg("--", $text)) $text = str_replace("--", "-", $text);

	return $text;
}

/**
 * Funkce, ktera dokaze odmazat prazdna policka z pole.
 *
 * {@source}
 * @param array $array vstupni pole
 * @return array boolean Pole, pokud bylo uspesne provedeno. Jinak FALSE
 */
function removeEmptyFields($array, $reindex=true) {
	// kontrola, zda bylo dodano pole
	if(is_array($array)) {
		// prochazeni polem
		foreach($array as $klic=>$field) {
			// kontrola, zda je polozka prazdna
			if($field!="") {
				if($reindex==true) {
					// indexace od nuly
					$ret[] = $field;

				} else {
					// zachovani starych indexu
					$ret[$klic] = $field;
				}
			}
		}
		// vraceni pole
		return $ret;
	} else {
		// vraceni chyby
		return false;
	}
}

/**
 * passing array and using function trim().
 *
 * {@source}
 * @param array $array entry array
 * @param string $sep
 * @return array
 */
function array_trim($array, $sep) {
	$ret = array();
	foreach($array as $key=>$row) {
		$ret[$key] = trim($row, $sep);
	}
	return $ret;
}

/**
 * jednoducha funkce, ktera nahrazuje var_export a podobne. Rozdil je v tom, ze je text
 * krasne upraveny a citelny :). Vysledek vypisuje primo na obrazovku.
 *
 * {@source}
 * @param $var objekt, promenna - cokoliv
 *
 * @return NULL
 */
function debug_var($var) {
	if(class_exists('geshi')) {
		$var = return_var($var);

		$geshi = new GeSHi($var, 'php');
		$geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS);

		echo '<div style="background: white; border: 2px solid silver;">'.$geshi->parse_code().'</div>';
	} else {
		echo '<pre>';
		var_export($var);
		echo '</pre>';
	}
}

function yrshash($modul, $string) {
	$hashModul = md5(KEY.strtolower($modul));
	$hashString = md5(KEY.strtolower($string));
	return md5($hashModul.$hashString.KEY);
}

/**
 * func.tion for returning content.
 * It's like var_debug, but func.tion doesn't print var, but it return var.
 *
 * {@source}
 * @param mischung $var
 * @param int $layer how many tabs will be added.
 *
 * @return string
 */
function return_var($var, $layer=1)
{
	// var for return
	$ret = "";

	$nl = "\n"; // var with chars for new line (def. "\n")
	$tab = "\t"; // var with chars for tab (def. "\t")

	if(is_object($var))
		$var = (array) $var;

	// check
	if(is_array($var))
	{
		// begin of array
		$ret .= "array (".$nl;

		// walk in array
		foreach($var as $key=>$content) {
			// recursive walking in array
			$content = return_var($content, $layer+1);

			// adding
			$ret .= str_repeat($tab, $layer).((is_numeric($key)) ? $key : "'".$key."'")." => ".$content. ",".$nl;
		}

		// end of array
		$ret .= str_repeat($tab, $layer-1).')';

	} else {
		// return string
		$ret = "'".$var."'";
	}

	// adding ; to end
	if($layer==1)
		$ret .= ";";

	// return
	return $ret;
}

/**
 * maze zdvojene znaky. Chtel jsem to pouzit, kdyz jsem mel na localu blbe nastavene php a z \ mi to delalo \\ a z ' zase ''.
 *
 * {@source}
 *
 * @param string $string retezec
 * @param char(1) $char ktery zdvojeny znak se ma odebirat
 *
 * @return string upraveny string
 */
function removeDoubles($string, $char) {
	$char = substr($char, 0, 1);

	$string = str_replace($char.$char, $char, $string);

	while(strpos($string, $char.$char)!==false) {
		$string = str_replace($char.$char, $char, $string);
	}

	return $string;
}

/**
 * Odeslání příkazů SMTP serveru
 *
 * {@source}
 *
 * @param resource $fp otevřený socket k SMTP serveru
 * @param array $commands příkazy k odeslání
 *
 * @return bool false v případě, že některý příkaz nevrátí 250
 */
function smtp_commands($fp, $commands) {
	foreach ($commands as $command) {
		fwrite($fp, "$command\r\n");
		$s = fgets($fp);

		if (substr($s, 0, 3) != '250') {
			return false;
		}
		while ($s{3} == '-') {
			$s = fgets($fp);
		}
	}
	return true;
}

/**
 * Ověření funkčnosti e-mailu
 *
 * {@source}
 *
 * @param string $email adresa příjemce
 * @param string $from adresa odesílatele
 *
 * @return bool na adresu lze doručit zpráva, null pokud nejde ověřit
 *
 * @copyright Jakub Vrána, http://php.vrana.cz
 */
function try_email($email, $from, $timeout=5) {
	if (!function_exists('getmxrr')) {
		return null;
	}

	$domain = preg_replace('~.*@~', '', $email);
	getmxrr($domain, $mxs);

	if (!in_array($domain, $mxs)) {
		$mxs[] = $domain;
	}

	$commands = array(
		"HELO " . preg_replace('~.*@~', '', $from),
		"MAIL FROM: <$from>",
		"RCPT TO: <$email>",
	);

	$return = null;

	foreach ($mxs as $mx) {
		// nevim proc, ale na localu mi to vzdycky vyhazovalo tuhle IP, pokud domena neexistovala
		if(gethostbyname($mx)!="67.215.65.132") {
			$fp = @fsockopen($mx, 25, $errno, $errstr, $timeout);

			if ($fp) {
				$s = fgets($fp);

				while ($s{3} == '-')
					$s = fgets($fp);

				if (substr($s, 0, 3) == '220')
					$return = smtp_commands($fp, $commands);

				fwrite($fp, "QUIT\r\n");
				fgets($fp);
				fclose($fp);

				if (isset($return))
					return $return;
			}
		}
	}

	return false;
}


/**
 * Kontrola e-mailové adresy
 *
 * {@source}
 *
 * @param string $email e-mailová adresa
 * @return bool syntaktická správnost adresy
 * @copyright Jakub Vrána, http://php.vrana.cz
 */
function check_email($email) {
	$atom = '[-a-z0-9!#$%&\'*+/=?^_`{|}~]'; // znaky tvořící uživatelské jméno
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // jedna komponenta domény
	return eregi("^$atom+(\\.$atom+)*@($domain?\\.)+$domain\$", $email);
}


/**
 * funkce, ktera vraci adresu pro obrazek z Gravataru
 *
 * {@source}
 *
 * @param string $email
 * @param int $size velikost obrazku
 *
 * @return string URL
 */
function getGravatarLink($email, $size) {
	$md5_mail = md5(strtolower($email));

	return "http://www.gravatar.com/avatar.php?gravatar_id=".$md5_mail."&size=". (int) $size;
}

function getIP() {
	return $_SERVER["REMOTE_ADDR"];
}
?>
