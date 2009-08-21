<?php
/**
 * @package modules
 */

/**
 * modul pro spravu ruznych menu. Menu se nacita z databaze a ma svuj styl.
 *
 * @author Juda Yetty Kaleta <yettycz@gmail.com>
 * @package modules
 * @subpackage view
 */
class Menu {
	/** promenna pro nacteni designu */
	var $design = "";

	public function __construct() {
		global $vzhled;
		if($vzhled!="") {
			$this->design = $vzhled->design("menu");
		}
	}

	/**
	 *
	 * {@source}
	 * @return string HTML
	 */
	public function admin() {
		global $db;
		$ret = "";

		if($_GET["parametr1"]=="new") {
			if($this->adminNew()) {
				$ret .= $this->design["newok"];
			} else {
				$ret .= $this->design["newko"];
			}
		}

		if($_GET["parametr1"]=="save") {
			$this->adminSave();
		}

		// 15.7 - umozneni editovat pouze v aktualnim jazyce
		$seznam = $db->query("select * from __menu_menus where lang='".$_COOKIE["lang"]."' order by weight desc");

		while($radek = $db->fetch_array($seznam)) {
			$polozky[$radek["menu"]][] = $radek;
		}
		ksort($polozky);

		$ret .= design($this->design["newmenu"], array());

		// x - pozice celkove (vsechny menu)
		$x=0;
		if($polozky != "") {
			foreach($polozky as $nazev=>$menu) {
				//$ret .= sprintf($this->design["adminmenuh1"], $nazev);

				$rows="";
				$od = $x;
				// y - pozice v menu
				$y = 1;
				foreach($menu as $radek) {
					$rows .= design(
						$this->design["rowmenu"],
						array(
							"name"=>$radek["name"],
							"lang"=>$radek["lang"],
							"weight"=>$radek["weight"],
							"url"=>
								$this->adresa(
									$radek['url'],
									array(
										false,
										$radek['change_lang'],
										$radek['class'],
										$radek['akce'],
										$radek['parametr1'],
										$radek['parametr2'],
										$radek['parametr3'],
										$radek['parametr4'],
										$radek['parametr5']
									)
								),
							"x"=>$x,
							"y"=>$y,
							"id"=>$radek["ID"],
							"menu_id"=>$nazev
						)
					);

					$x++;
					$y++;
				}
				$ret .= sprintf($this->design["formmenu"],
				design($this->design["tablemenu"],
					array(
						"table"=>$rows,
						"od"=>$od,
						"head"=>$nazev,
					)
				));
			}
		}
		return $ret;
	}

	private function adminSave() {
		$x=$_POST["menu"];

		while($_POST["id_".$x]!="") {
			$id = $_POST["id_".$x];

			if($_POST["smazat_".$x]=="") {
				$menu_id = $_POST["menu_id_".$x];

				$nazev = $_POST["name_".$x];
				$url = $_POST["url_".$x];
			//	$lang = (strlen($_POST["lang_".$x])<=2) ? strtolower(substr($_POST["lang_".$x], 0, 2)) : ""; # pokud je jazyk delsi nez dva znaky, bude odstranen

				// 15.7 - umozneni editovat pouze aktualni jazyk
				$lang = $_COOKIE["lang"];

				$weight = $_POST["weight_".$x];

				// kontrola validity dat
				if($id!="" and is_numeric($id) and $nazev!= "" and $url!="") {
					$this->adminInsert($nazev, $url, $lang, $weight, $menu_id, $id);
				}
			} else {
				global $db;
				$db->query("delete from __menu_menus where ID='".$id."' limit 1");
			}

			$x++;
		}
	}

	/**
	 * Funkce, ktera vytvari novou polozku v tabulce menu. Prebira data z _POST a take je kontroluje.
	 * Sama rozpoznava, jestli byla dodana URL nebo tvar pro YRS.
	 *
	 * {@source}
	 * @return string HTML
	 */
	private function adminNew() {
		// prirazeni hodnot z _POST
		$id_menu = $_POST["menu"];
		$nazev = $_POST["nazev"];
		$url = $_POST["url"];
		//$lang = (strlen($_POST["lang"])<=2) ? strtolower(substr($_POST["lang"], 0, 2)) : ""; # pokud je jazyk delsi nez dva znaky, bude odstranen

		// 15.7 - umozneni editovat pouze aktualni jazyk
		$lang = $_COOKIE["lang"];

		$weight = $_POST["weight"];

		// kontrola validity dat
		if($id_menu!="" and is_numeric($id_menu) and $nazev!= "" and $url!="") {
			if($this->adminInsert($nazev, $url, $lang, $weight, $id_menu)) {
				return true;
			}
		} else {
			return false;
		}
	}

	private function adminInsert($nazev, $url, $lang, $weight, $id_menu=NULL, $id=NULL) {
		global $db;

		if(ereg("://", $url)) {
			// pridani polozky do DB
			if($id==NULL and $id_menu!="") {
				$db->query("insert into __menu_menus (menu, lang, name, url, weight) values ('".$id_menu."', '".$lang."', '".$nazev."', '".$url."', '".$weight."')");
			} else {
				$db->query("replace __menu_menus (menu, ID, lang, name, url, weight) values ('".$id_menu."', '".$id."', '".$lang."', '".$nazev."', '".$url."', '".$weight."')");
			}


		// pokud ma adresa tva YRS
		} else {
			// rozdeleni na casti
			$aurl = explode("/", $url);

			// odstraneni prazdnych poli
			$aurl = removeEmptyFields($aurl);

			// pokud je prvni cast retezce jazyk
			if(strlen($aurl[0])<=2) {
				$parametry = "'".slovnik($aurl[1])."', '".$aurl[2]."', '".$aurl[3]."', '".$aurl[4]."', '".$aurl[5]."', '".$aurl[6]."', '".$aurl[7]."', '".$aurl[0]."'";
			} else {
				$parametry = "'".slovnik($aurl[0])."', '".$aurl[1]."', '".$aurl[2]."', '".$aurl[3]."', '".$aurl[4]."', '".$aurl[5]."', '".$aurl[6]."', ''";
			}

			if($id==NULL) {
				$sql = sprintf("insert into __menu_menus (menu, lang, name, weight, class, akce, parametr1, parametr2, parametr3, parametr4, parametr5, change_lang) values ('".$id_menu."', '".$lang."', '".$nazev."', '".$weight."', %s)", $parametry);
			} else {
				$sql = sprintf("replace __menu_menus (menu, ID, lang, name, weight, class, akce, parametr1, parametr2, parametr3, parametr4, parametr5, change_lang) values ('".$id_menu."', '".$id."', '".$lang."', '".$nazev."', '".$weight."', %s)", $parametry);
			}
			$db->query($sql);
		}

		return true;
	}

	private function adminMenu($menu) {
		return $menu;
	}

	/**
	 *
	 */
	public function install() {
		global $db, $design;
		if($db->query("CREATE TABLE if not exists `__menu_menus` (
`ID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`menu` INT NOT NULL ,
`lang` VARCHAR( 2 ) NOT NULL ,
`name` VARCHAR( 255 ) NOT NULL ,
`url` VARCHAR( 255 ) NOT NULL ,
`class` VARCHAR( 128 ) NOT NULL ,
`akce` VARCHAR( 128 ) NOT NULL ,
`parametr1` VARCHAR( 128 ) NOT NULL ,
`parametr2` VARCHAR( 128 ) NOT NULL ,
`parametr3` VARCHAR( 128 ) NOT NULL ,
`parametr4` VARCHAR( 128 ) NOT NULL ,
`parametr5` VARCHAR( 128 ) NOT NULL ,
`change_lang` VARCHAR( 2 ) NOT NULL ,
`weight` INT NOT NULL)")) {
			$ret .= design($design["radek"], array("nazev"=>SM_INSTALL_DB, "stav"=>"[OK]", "barva"=>"true", "style"=>"lichy"));
		}

		return $ret;
	}

	private function adresa($url, $args) {
		if(ereg('http://', $url)) {
			return $url;
		} else {
			return URL::create($args);
		}
	}

	/**
	 * funkce, ktera vrati menu. Nacita se z DB a radi se podle vahy, cim vyssi cislo, tim dulezitejsi.
	 *
	 * {@source }
	 * @param string $parametry udaje o menu ve stylu: IdMenu;PocetPolozek
	 *
	 */
	public function view($parametry) {
		global $db;

		/** retezec, ktery se bude vracet */
		$ret = '';

		// rozdeleni parametru na jednotlive casti
		list($menu, $pocet) = explode(';', $parametry);

		// nacteni polozek menu
		/** @todo Zjistit zda plati ze weight je cim vyssi cislo, tim vyssi dulezitost */
		$sql = $db->query('select * from __menu_menus where menu="'. (int) $menu.'" and
		lang="'.$_COOKIE['lang'].'" order by weight desc '.(($pocet!=NULL) ? 'limit '. (int) $pocet : ''));

		// prochazeni polozkami DB
		$x = 1;
		while($row=$db->fetch_array($sql)) {
			$styl = $this->design['menu_'.$row[menu].'_polozka_'.$x];

			$design = ($styl!='') ? $styl : $this->design['menu_'.$row[menu].'_default'];
			$design = ($design!='') ? $design : $this->design['menu_default'];

			$params = array($row['change_lang'], $row['class'], $row['akce'], $row['parametr1'], $row['parametr2'], $row['parametr3'], $row['parametr4'], $row['parametr5']);

			$ret .= design(
						$design,
						array(
							'url'=>$this->adresa($row['url'], $params),
							'name'=>$row[name],
							'id'=>$x,
						)
					);

			$x++;
		}
		// vraceni HTML kodu s menu
		return $ret;
	}
}
?>
