<?php
class Search {
	/** seznam s moduly, ve kterych se bude vyhledavat */
	var $allowed_modules = array("page", "comments");

	/** metoda, ktera se pouziva v ostatnich tridach pro vyhledavani */
	const SEARCH_METHOD = "search";

	/** minimalni delka hledaneho vyrazu */
	const MIN_LENGHT = 4;

	/**
	 * inicializacni procedury funkce
	 *
	 * {@source}
	 * @return boolean
	 */
	function __construct() {
		global $vzhled;

		if($vzhled!="") {
			$this->design = $vzhled->design("search");
		}
		return true;
	}

	private function htmlVypis($array) {
		$out = "";

		list($array, $max) = $array;

		// po zapnuti se prestane radit podle MySQL, ale podle vlastnich retezcu v SearchRanks
		//krsort($array);

		foreach($array as $item) {
			$item["rank"] = round(($item["rank"]/$max)*100);
			$out .= design($this->design["item"], $item);
		}

		return sprintf($this->design["items"], $out);
	}


	public function view() {
		$q = htmlspecialchars($_GET["q"]);

		$out = "";

		$out .= design($this->design["form"], array("value"=>$q));

		if(strlen($q)<self::MIN_LENGHT) {
			$out .= sprintf($this->design["tooshort"], $q);

		} else	if($q!="") {
			$out .= sprintf($this->design["ws"], $q);
			$q = mysql_real_escape_string($q);

			foreach($this->allowed_modules as $modul) {
				if(class_exists($modul)) {
					$m = new $modul;
					if(is_array($m->search)) {


					}
				}
			}

		/*	foreach($this->allowed_modules as $modul) {
				if(is_array($modul::$search)) {
					$s = new $modul;
					$vysledky = $s->search($q);

					if(count($vysledky[0])>0) {
						$list = $this->htmlVypis($vysledky);
					} else {
						$list = $this->design["nicnenalezeno"];
					}

					$out .= design($this->design["found"], array("modul"=>ucfirst(rslovnik($modul)), "list"=>$list));

				} else {
					$out .= sprintf($this->design["notfound"], sprintf(Lang::view("SEARCH;notfound;"), $modul));
				}
			} */
		}

		return $out;
	}

}


class SearchRanks {
	static function vyhodnot($string, $q) {
		return similar_text($string, $q)*(strlen($string)/200);
	}

	static function soucet($array) {
		$suma = 0;
		foreach($array as $item) {
			$suma += $item;
		}

		return round($suma/count($array));
	}
}
