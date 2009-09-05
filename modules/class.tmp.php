<?php
/**
 * nacita docasne ulozene soubory
 */
class TMP {
	/** vychozi interval, pokud neni nastaveno jinak */
	const DEFAULT_INTERVAL = 60;

	/** minimalni interval, pod ktery je neefektivni znovuukladat docasny soubor */
	const MIN_INTERVAL = 10;

	/** maximalni interval, nula je neomezeno */
	const MAX_INTERVAL = 0;

	/** slozka, kam se ukladaji docasne soubory (pokud je nastaveno ukladani do souboru) */
	const TMP_DIR = "./modules/tmp/";

	/** typ, ktery se uklada do opravneni, pokud se ma tmp ukladat do databaze */
	const TYPE_DB = "db";

	/** typ, ktery se uklada do opravneni, pokud se ma tmp ukladat do souboru */
	const TYPE_FILE = "file";

	public function __construct() {
		if(!is_dir(self::TMP_DIR)) {
			if(mkdir(self::TMP_DIR)) {
				return true;
			}

			return false;
		}
	}

	/**
	 * ziskava docasny soubor. Pokud neni platny, zavola funkci pro smazani.
	 */
	public function getTMP($url) {
		$typ = self::checkAccess($url);

		if($typ==self::TYPE_DB) {
			if(self::checkTime($url, $typ)===true) {
				return self::getContent($url);
			} else {
				self::removeContent($url);
			}
		} else if($typ==self::TYPE_FILE) {
			if(self::checkTime($url, $typ)===true) {
				return self::getFile($url);
			} else {
				self::removeFile($url);
			}
		}

		return false;
	}

	/**
	 * Uklada docasny soubor. Pri tom kontroluje, zda je povoleno vytvaret pro URL docasne soubory.
	 */
	public function setTMP($url, $content) {
		$typ = self::checkAccess($url);

		if($typ==self::TYPE_DB) {
			if(self::setContent($url, $content))
				return true;
		} else if($typ==self::TYPE_FILE) {
			if(self::setFile($url, $content))
				return true;
		}

		return false;
	}

	/**
	 * zkontroluje, zda docasny soubor je jeste platny.
	 * Nejdrive se vola funkce {@link TMP::AccessbyURL}, ktera zjistuje ID opravneni, pripadne jestli opravneni vubec existuje. Pokud ano, nacte se z databaze interval a cas kdy byl ulozen docasny soubor. Pokud neni interval opravneni nulovy (rovna se zakazani, muze se hodit pro zakazani nejake podstranky, ktera bude povolena) se rozdeli cas ve formatu 00:00:00 na hodiny, minuty a sekundy a vytvori se z nej casove razitko funkci mktime. Casove razitko se vytvori i z aktualniho casu - interval v sekundach. Tyto casy se pak porovnaji a pokud je soucasny nizsi nez ukladaci, vraci se true.
	 */
	public function checkTime($url, $typ) {

		if(($id = self::AccessbyURL($url)) !== false) {
			list($access) = DB::query("select second from __tmp_access where ID=".$id)->fetchSingle();

			if($typ==self::TYPE_DB) {
				$content = DB::query("select time from __tmp_content where url=%s and lang=%s", $url, $_COOKIE['lang'])->fetchSingle();
			} else if($typ==self::TYPE_FILE) {
				$file = self::getFileName($url);

				if(is_readable($file)) {
					$content = filemtime($file);
				}
			}

			$access = ($access<self::MIN_INTERVAL) ? self::MIN_INTERVAL : $access;
			$access = ($access<self::MAX_INTERVAL or self::MAX_INTERVAL==0) ? $access : self::MAX_INTERVAL;


			if($access!=NULL) {
				list($hours, $minutes, $seconds) = explode(":", $content);

				$time = mktime( (int) $hours, (int) $minutes, (int) $seconds);
				$now = mktime(date("G"), date("i"), date("s")- ( (int) $access));

				//debug_var(array($time=>date("G:i:s", $time), $now=>date("G:i:s", $now), "now"=>date("G:i:s")));

				if($time>$now)
					return true;
			}
		}
		return false;
	}

	/**
	 * funkce, ktera podle dodaneho url nacita z databaze moznou shodu.
	 * Nejprve rozdeli url na casti, pak ho spoji (takze z array('page', 'index') vznikne array('/page/', '/page/index/')). Nasledne ziskane URL prochazi (od nejdelsiho po nejkratsi). Pokud je nejaka shoda, vraci funkce ID povoleni z tabulky.
	 *
	 * {@source}
	 * @param string $url URL adresa
	 * @return integer ID opravneni (accessu)
	 */
	public function AccessByURL($url) {
		$parts = removeEmptyFields(explode("/", $url));
		$options = array();

		if(count($parts)>0) {
			foreach($parts as $key=>$part) {
				$options[] = rtrim($options[$key-1], "/")."/".$part."/";
			}
			rsort($options);

			foreach($options as $url) {
				$access = DB::query("select ID from __tmp_access where url like %s", $url);
				foreach($access->getIterator() as $row) {
					return $row['ID'];
				}
			}
		}

		return false;
	}

	/**
	 * vraci nazev a cestu k docasnemu souboru.
	 */
	public function getFileName($url) {
		return self::TMP_DIR.$_COOKIE["lang"].md5($url);
	}

	/**
	 * vraci obsah docasne stranky ze souboru
	 */
	public function getFile($url) {
		if(self::AccessByURL($url) !== false) {
			$file = self::getFileName($url);

			if(is_readable($file))
				return file_get_contents($file);
		}
		return false;
	}

	/**
	 * uklada stranku do docasneho souboru
	 */
	public function setFile($url, $content) {
		self::__construct();

		if(self::AccessByURL($url) !== false) {
			$file = self::getFileName($url);

			if(is_writable($file) or !file_exists($file)) {
				$cf = fopen($file, "w");
				if(fwrite($cf, $content))
					return true;
			}
		}

		return false;
	}

	/**
	 * maze docasny soubor
	 */
	public function removeFile($url) {
		if(self::AccessByURL($url) !== false) {
			$file = self::TMP_DIR.md5($url);
			if(is_writable($file)) {
				if(unlink($file))
					return true;
			}
		}

		return false;
	}

	/**
	 * vraci obsah docasneho souboru z db
	 */
	public function getContent($url) {
		if(self::AccessbyURL($url) !== false) {
			$content = DB::query("select content from __tmp_content where url=%s and lang=%s", $url, $_COOKIE['lang'])->fetchSingle();
			return $content;
		}

		return false;
	}

	/**
	 * nastavuje obsah docasneho souboru v db
	 */
	public function setContent($url, $content) {
		if(($id = self::AccessbyURL($url)) !== false) {
			self::removeContent($url);

			$parametry = array(
				'id_access' => $id,
				'content' => $content,
				'time' => date('Y-m-d G:i:s'),
				'lang' => $_COOKIE['lang'],
				'url' => $url,
			);

			DB::query("insert into __tmp_content", $parametry);
			return true;
		}

		return false;
	}

	/**
	 * odebira docasny soubor z db.
	 */
	public function removeContent($url) {
		if(($id = self::AccessbyURL($url)) !== false) {
			DB::query("delete from __tmp_content where url=%s and lang=%s", $url, $_COOKIE['lang']);
			return true;
		}

		return false;
	}


	/**
	 * vytvari nove povoleni pro vytvareni docasneho souboru
	 */
	public function createAccess($url, $interval) {
		DB::query("insert into __tmp_access", array('url'=>$url, 'second'=>$second));
	}

	/**
	 * maze povoleni pro vytvareni docasneho souboru a s nim i vsechny docasne soubory.
	 */
	public function removeAccess($url) {
		DB::query("delete from __tmp_access where url=%s", $url);
	}

	/**
	 * upravuje povoleni
	 * @todo nemam na to naladu, zatim to povazuji za zbytecne, nekdy dodelam.
	 */
	public function editInterval($url, $interval) {

	}

	/**
	 * zkontroluje, zda je povoleno nacitat docasny soubor. Vraci bud typ (db, file), nebo false.
	 */
	public function checkAccess($url) {
		if(($id = self::AccessbyURL($url)) !== false) {
			return DB::query("select type from __tmp_access where ID=%i", $id)->fetchSingle();
		}

		return false;
	}

}

?>
