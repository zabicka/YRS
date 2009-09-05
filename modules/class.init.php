<?php
/**
 * zajistuje spravne nacitani trid a funkci.
 */
class Init {
	static $init=NULL;

	/** prefix pro tridy, ktere je mozne spoustet */
	const PREFIX_METHODS = "view";

	/** nazev tridy, ktera se nacita */
	var $trida="";

	/** promena pro ulozeni tridy */
	var $cre=NULL;

	/** pole s bloky, ktere se budou vykreslovat **/
	static $blocks = array();

	var $html="";

	var $showtmp=true;
	var $savetmp=true;

	/**
	 * nacteni nazvu tridy ze slovniku
	 *
	 * {@source}
	 * @return boolean
	 */
	private function __construct() {
		$this->trida = Dictionary::modul($_GET["class"]);
		return true;
	}

	public function construct() {
		if(self::$init==NULL) {
			$init = new Init;
		}
		return $init;
	}

	/**
	 * metoda, ktera pridava bloky do pole
	 *
	 * {@source}
	 * @param string $block nazev bloku
	 * @param string $html obsah bloku
	 * @param boolean $rewrite=false urcuje, zda se maji bloky slucovat, nebo prepisovat
	 *
	 * @return boolean
	 */
	public function addToBlocks($block, $html, $rewrite=true) {
		// pokud se maji bloky slucovat a blok uz existuje
		if($rewrite==false and self::$blocks[$block]!="") {
			// slouceni bloku
			self::$blocks[$block]["html"] .= $html;

		// pokud se nemaji bloky slucovat nebo blok jeste neexistuje
		} else {
			// pridani bloku
			self::$blocks[$block] = array("name"=>$block, "html"=>$html);
		}
		return true;
	}

	/**
	 * funkce, ktera overuje, zda ma uzivatel opravneni k nacteni urcite metody. Zatim nejsou povoleny
	 * vsechny metody, ktere maji v nazvu admin. Pokud uzivatel nema opravneni, je rovnou presmerovan
	 * na prihlasovaci stranku.
	 *
	 * {@source}
	 * @param string $action volana metoda
	 *
	 * @return boolean
	 */
	private function auth($action="") {
		if($this->trida=="admin") {
			if(Admin::isLogged()==false)
				return true;

			if(Admin::getAccess(URL::getAddress('', $action)))
				return true;

		} else {
			if(Admin::getAccess(URL::getAddress('', $action)))
				return true;
		}

		return false;
	}

	/**
	 * funkce, ktera vraci pole s bloky. Je potreba proto, ze vzhledova trida class.template.php musi mit
	 * bloky ocislovane a ne popsane.
	 *
	 * {@source}
	 *
	 * @return array
	 */
	public function getBlocks() {
		// vytvoreni pole, ktere se bude vracet
		$out = array();

		// prochazeni polem s bloky
		foreach(self::$blocks as $blok) {
			// zapsani do noveho pole
			$out[] = array($blok["name"], $blok["html"]);
		}

		return $out;
	}

	/**
	 * funkce, ktera nacita Index. Pokud neni Index nastaven, spusti se instalace.
	 *
	 * {@source}
	 * @return boolean
	 */
	private function getIndex() {
		// overeni, ze indexova metoda je stanovena
		if(ACTION_INDEX=='ACTION_INDEX' or ACTION_INDEX=="") {
			// pokud neni, presmerovani na instalaci.
			header("Location: ".CESTA_ABSOLUTNI."install/install.php");
			header("Connection: close");
		} else {
			// rozdeleni do pole a odstraneni prazdnych policek
			$index = removeEmptyFields(explode("/", ACTION_INDEX));

			// zavolani metody pro ziskani obsahu
			return $this->getClass($index[0], $index[1], $index[2], $index[3], $index[4], $index[5]);
		}
	}

	/**
	 * podobna funkce, jako $this->getIndex(). Nacita stranku s Error404 (nenalezeno).
	 *
	 * {@source}
	 * @return boolean
	 */
	public function getError404() {
		// overeni, zda errorova metoda neni prazdna
		if(ACTION_ERROR404=='ACTION_ERROR404' or ACTION_ERROR404=="") {
			header("Location: ".CESTA_ABSOLUTNI."install/install.php");
			header("Connection: close");
		} else{
			// ziskani a odmazani prazdnych policek
			$index = removeEmptyFields(explode("/", ACTION_ERROR404));
			// pokud by tu tahle podminka nebyla, mohlo by se v pripade, ze error neexistuje, skript zacyklit.
			if(class_exists(Dictionary::modul($index[0]))) {
				// ziskani stranky
				return $this->getClass($index[0], $index[1], $index[2], $index[3], $index[4], $index[5]);
			} else {
				return false;
			}
		}
	}

	/**
	 * podobna funkce, jako $this->getIndex(). Nacita stranku s Error403 (pristup odepren).
	 *
	 * {@source}
	 * @return boolean
	 */
	public function getError403() {
		// overeni, zda errorova metoda neni prazdna
		if(ACTION_ERROR403=='ACTION_ERROR403' or ACTION_ERROR403=="") {
			header("Location: ".CESTA_ABSOLUTNI."install/install.php");
			header("Connection: close");
		} else{
			// ziskani a odmazani prazdnych policek
			$index = removeEmptyFields(explode("/", ACTION_ERROR403));

			// pokud by tu tahle podminka nebyla, mohlo by se v pripade, ze error neexistuje, skript zacyklit.
			if(class_exists(slovnik($index[0]))) {
				// ziskani stranky
				$this->cre = new $index[0];
				$html = $this->cre->view($index[1]);

				$this->addToBlocks(BLOCK_CONTENT, $html);
			} else {
				return false;
			}
		}
	}

	/**
	 * funkce, ktera ziskava obsah ze tridy a zadanych parametru. Overuje existenci tridy, existenci
	 * volanych metod, vola funkci s overenim opravneneho pristupu, vola errorovou stranku, pokud trida
	 * nema defaultni zobrazovaci metodu ani dodanou, nebo pokud trida neexistuje.
	 *
	 * {@source}
	 * @param string $class nazev tridy
	 * @param string $action volana metoda
	 * @param string $parametr1, $parametr2, $parametr3, $parametr4, $parametr5 dodane parametry funkci
	 *
	 * @return boolean
	 */
	public function getClass(
		$class='',$action='',$parametr1='',$parametr2='',$parametr3='',$parametr4='', $parametr5=''
	) {
		/** @todo nevim, jak lepe to udelat :( */
		$class = ($class=="") ? $this->trida : $class;
		$action = ($action=="") ? $_GET["akce"] : $action;
		$parametr1 = ($parametr1=="") ? $_GET["parametr1"] : $parametr1;
		$parametr2 = ($parametr2=="") ? $_GET["parametr2"] : $parametr2;
		$parametr3 = ($parametr3=="") ? $_GET["parametr3"] : $parametr3;
		$parametr4 = ($parametr4=="") ? $_GET["parametr4"] : $parametr4;
		$parametr5 = ($parametr5=="") ? $_GET["parametr5"] : $parametr5;

		$url = "/".trim($class."/".$action."/".$parametr1."/".$parametr2."/".$parametr3."/".$parametr4."/".$parametr5."/", "/")."/";
		// overeni zda trida existuje

		if(($html=TMP::getTMP($url))===false or $this->showtmp==false) {

			$class_name = $class;
			$class = (class_exists($class.'View')) ? $class.'View' : $class;

			if(class_exists($class)) {
				// vytvoreni nove tridy
				$this->cre = new $class;
				$akce = Dictionary::modul($action, $class_name);

				if($this->auth($akce)) {

					// pokud je dodana nejaka metoda a tato metoda existuje
					if($action!="" and method_exists($this->cre, self::PREFIX_METHODS.$akce)){
						// ziskani html

						$akce = self::PREFIX_METHODS.$akce;
						$html = $this->cre->$akce($parametr1, $parametr2, $parametr3, $parametr4, $parametr5);

					// pokud existuje defaultni metoda (metoda neni dodana, nebo neexistuje)
					} else if($action!='' and method_exists($this->cre, $akce) and ereg('(.*)View', $class)) {
						$html = $this->cre->$akce($parametr1, $parametr2, $parametr3, $parametr4, $parametr5);
					} else if(method_exists($this->cre, ACTION_DEFAULT)) {
						$def = ACTION_DEFAULT;

						// ziskani html
						$html = $this->cre->$def($action, $parametr1, $parametr2, $parametr3, $parametr4, $parametr5);
					// pokud neexistuje defaultni metoda a zadna jina nebyla dodana
					} else {
						// ziskani chybove hlasky
						return $this->getError404();
					}

					// pridani ziskaneho html do hlavniho bloku
					$this->addToBlocks(BLOCK_CONTENT, $html);
					// ulozeni do tmp
					if($this->savetmp!==false)
						TMP::setTMP($url, $html);
				} else {
					return $this->getError403();
				}

			// pokud trida neexistuje
			} else {
				// ziskani chybove hlasky
				return $this->getError404();
			}

			return $html;
		} else {
			$this->addToBlocks(BLOCK_CONTENT, $html);
			return $html;
		}
	}

	/**
	 * spousteci funkce, vola metody pro ziskani stranky
	 *
	 * {@source}
	 * @return boolean
	 */
	public function run() {
		$this->showtmp = false;
		$this->savetmp = false;

		if($this->trida[0]=="_") {
			session_register("only");

			$special = $this->trida;

			$_GET["class"]		=	$_GET["akce"];
			$_GET["akce"]		=	$_GET["parametr1"];
			$_GET["parametr1"]	=	$_GET["parametr2"];
			$_GET["parametr2"]	=	$_GET["parametr3"];
			$_GET["parametr3"]	=	$_GET["parametr4"];
			$_GET["parametr4"]	=	$_GET["parametr5"];
			$this->trida = Dictionary::modul($_GET["class"]);

			switch ($special) {
				case SPECIAL_VIEW_ONLY_CLASS:
					$_SESSION["only"] = true;
					$this->savetmp = false;
				break;

				case SPECIAL_VIEW_ALL_CLASS:
					$_SESSION["only"] = false;
					$this->savetmp = false;
				break;

				case SPECIAL_VIEW_ONLY_CLASS_TEMP:
					$_GET["only"] = true;
					$this->savetmp = false;
				break;

				case SPECIAL_REFRESH:
					$this->showtmp = false;
				break;
			}

			$this->run();
		} else {
			// pokud neni dodana trida
		//	if(($html=TMP::getTMP(URL::getAddress()))===false or $tmp==false) {
				if($this->trida=="") {
					// zavolani indexu
					return $this->getIndex();

				// pokud je dodana trida
				} else {
					// pokud trida existuje ## Tohle by tu ani nemuselo byt
					if(class_exists($this->trida)) {

						// ziskani obsahu tridy
						return $this->getClass();

					// pokud trida neexistuje
					} else {
						// ziskani chybove stranky
						return $this->getError404();
					}
				}
		/*	} else {
				$this->html = $html;
				return false;
			} */
		}
	}


	public function saveTMP() {
		$html = $this->html;
		TMP::setTMP(URL::getAddress(), $html);
	}
}
?>
