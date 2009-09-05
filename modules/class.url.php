<?php
class URL {
	public function view($params) {
		$args = explode(';', strtolower($params));
		return self::create($args);
	}

	public function create() {
		$args = func_get_args();

		// for link from adresa() and URL::view()
		if(is_array($args[0])) {
			$args = $args[0];
		}

		if($args[0]===false) {
			$ret = '/';
			unset($args[0]);
			$args = removeEmptyFields($args);
		} else {
			$ret = CESTA_ABSOLUTNI;
		}

		if(strlen($args[0])==2) {
			$lang = $args[0];
		} else {
			$lang = $_COOKIE['lang'];
			array_splice($args, 0, 0, $lang);
		}

		$args = removeEmptyFields($args);

		$class = Dictionary::translation($args[1]);
		$action = Dictionary::translation($args[2], $args[1]);

		unset($args[0], $args[1], $args[2]);

		if(MOD_REWRITE=='true') {
			$ret .= $lang.'/';
			$ret .= ($class!='') ? $class.'/' : '';
			$ret .= ($action!='') ? $action.'/' : '';

			foreach($args as $arg) {
				if($arg!='') {
					$ret .= urlencode($arg).'/';
				}
			}

		} else {
			$ret .= 'index.php?lang='.$lang;
			$ret .= ($class!='') ? '&amp;class='.$class : '';
			$ret .= ($action!='') ? '&amp;akce='.$action : '';

			$x = 1;
			foreach($args as $arg) {
				if($arg!='') {
					$ret .= '&amp;parametr'.$x.'='.urlencode($arg);
					$x++;
				}
			}
		}

		return $ret;
	}

	public function getClass() {
		return $_GET["class"];
	}

	public function astyle($target="_blank") {
		if($_GET["only"]=="") {
			if($_SESSION["only"]==SPECIAL_VIEW_ONLY_CLASS)	{
				$url = URL::view(SPECIAL_VIEW_ALL_CLASS.str_replace('/', ';', (URL::getAddress())));
				$type = "grafika";
			} else {
				$url = URL::view(SPECIAL_VIEW_ONLY_CLASS_TEMP.str_replace('/', ';', (URL::getAddress())));
				$type = "tisk";
			}

			return '<a href="'.$url.'" target="'.$target.'">{LANG:GENERAL;'.$type.';'.$type.'}</a>';
		}
	}

	public function getAddress($trida=NULL, $akce=NULL, $parametr1=NULL, $parametr2=NULL,
$parametr3=NULL, $parametr4=NULL, $parametr5=NULL, $jazyk=NULL) {
				return
			"/".
			trim(
				Dictionary::modul(
					(($trida=="")?$_GET["class"]:$trida)
				)."/".

				Dictionary::modul(
					(($akce=="")?$_GET["akce"]:$akce), $_GET["class"]
				)."/".

				(($parametr1=="")?$_GET["parametr1"]:$parametr1)."/".
				(($parametr2=="")?$_GET["parametr2"]:$parametr2)."/".
				(($parametr3=="")?$_GET["parametr3"]:$parametr3)."/".
				(($parametr4=="")?$_GET["parametr4"]:$parametr4)."/".
				(($parametr5=="")?$_GET["parametr5"]:$parametr5)."/",
				"/").
			"/";
	}

}
?>
