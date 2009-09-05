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
		$args = func_get_args();
		$ret = "";

		if($args[0]=='new') {
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
		$query = DB::query("select * from __menu_menus where lang=%s order by weight desc", $_COOKIE['lang']);

		foreach($query->getIterator() as $row) {
			$polozky[$row["menu"]][] = $row;
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
					$this->adminInsert($nazev, $url, $weight, $menu_id, $id);
				}
			} else {
				DB::query("delete from __menu_menus where ID=%i limit 1", $id);
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

		$weight = $_POST["weight"];

		// kontrola validity dat
		if($id_menu!="" and is_numeric($id_menu) and $nazev!= "" and $url!="") {
			if($this->adminInsert($nazev, $url, $weight, $id_menu)) {
				return true;
			}
		} else {
			return false;
		}
	}

	private function adminInsert($nazev, $url, $weight, $id_menu, $id=NULL) {
		if(ereg("://", $url)) {
			$data = array(
				'menu' => $id_menu,
				'lang' => $lang,
				'name' => $nazev,
				'url' => $url,
				'weight' => $weight,
				'lang' => $_COOKIE['lang'],
			);

			// pridani polozky do DB
			if($id==NULL and $id_menu!="") {
				DB::query("insert into __menu_menus", $data);
			} else {
				$data['ID'] = $id;
				DB::query("replace __menu_menus ", $data);
			}

		// pokud ma adresa tva YRS
		} else {
			// rozdeleni na casti
			$aurl = explode("/", $url);

			// odstraneni prazdnych poli
			$aurl = removeEmptyFields($aurl);

			$parametry = array(
					'name' => $nazev,
					'menu' => $id_menu,
					'lang' => $_COOKIE['lang'],
					'weight' => $weight,
			);

			// pokud je prvni cast retezce jazyk
			if(strlen($aurl[0])<=2) {
				$parametry['class'] = Dictionary::modul($aurl[1]);
				$parametry['akce'] = $aurl[2];
				$parametry['parametr1'] = $aurl[3];
				$parametry['parametr2'] = $aurl[4];
				$parametry['parametr3'] = $aurl[5];
				$parametry['parametr4'] = $aurl[6];
				$parametry['parametr5'] = $aurl[7];
				$parametry['change_lang'] = $aurl[0];
			} else {
				$parametry['class'] = Dictionary::modul($aurl[0]);
				$parametry['akce'] = $aurl[1];
				$parametry['parametr1'] = $aurl[2];
				$parametry['parametr2'] = $aurl[3];
				$parametry['parametr3'] = $aurl[4];
				$parametry['parametr4'] = $aurl[5];
				$parametry['parametr5'] = $aurl[6];
			}

			if(!$id) {
				DB::query("insert into __menu_menus", $parametry);
			} else {
				$parametry['ID'] = $id;
				DB::query("replace __menu_menus", $parametry);
			}
		}

		return true;
	}

	private function adminMenu($menu) {
		return $menu;
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

		/** retezec, ktery se bude vracet */
		$ret = '';

		// rozdeleni parametru na jednotlive casti
		list($menu, $pocet) = explode(';', $parametry);

		// nacteni polozek menu
		/** @todo Zjistit zda plati ze weight je cim vyssi cislo, tim vyssi dulezitost */
		$sql = DB::query('select * from __menu_menus where menu=%i and
		lang="'.$_COOKIE['lang'].'" order by weight desc '.(($pocet!=NULL) ? 'limit '. (int) $pocet : ''), $menu);

		// prochazeni polozkami DB
		$x = 1;
		foreach($sql->getIterator() as $row) {
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
