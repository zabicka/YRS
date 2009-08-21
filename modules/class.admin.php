<?php
/**
 * @package modules
 */
/**
 * Administracni modul.
 *
 * @package modules
 * @subpackage core
 */

define('ADMIN_INFO_FIRST_NAME', 'first_name');
define('ADMIN_INFO_LAST_NAME', 'last_name');
define('ADMIN_INFO_EMAIL', 'email');
define('ADMIN_INFO_WEB', 'web');
define('ADMIN_INFO_JABBER', 'jabber');
define('ADMIN_INFO_SIGNATURE', 'signature');

class Admin {
	/** promenna pro styly */
	var $design;

	const ID_ROOT_GROUP = 1;

	public function install() {
		global $db, $design;
		if($db->query("
			create table if not exists __admin_users (
				ID int auto_increment primary key,
				login varchar(255) primary key,
				password varchar(255))
			")
		) {

			$ret .= design($design["radek"], array("nazev"=>SM_INSTALL_DB, "stav"=>"[OK]", "barva"=>"true", "style"=>"lichy"));
		}
		$ret .= design($design["radekin"], array("nazev"=>SM_INSTALL_LOGIN, "name"=>"login", "style"=>"sudy", "value"=>"root", "type"=>"text"));
		$ret .= design($design["radekin"], array("nazev"=>SM_INSTALL_PASSWORD, "name"=>"password", "style"=>"lichy", "value"=>"", "type"=>"password"));
		$ret .= design($design["radekin"], array("nazev"=>SM_INSTALL_TIMEOUT, "name"=>"timeout", "style"=>"sudy", "value"=>"600", "type"=>"text"));
		return $ret;
	}

	public function install_save() {
		global $db;
		$db->query("delete from __admin_users where login='".$_POST["login"]."'");
		$db->query("delete from __settings where name='ADMIN_TIMEOUT'");
		$db->query("insert into __settings value('ADMIN_TIMEOUT', '".$_POST["timeout"]."')");
		if($db->query("insert into __admin_users (login, password) values('".$_POST["login"]."', '".yrshash("admin", $_POST["password"])."')")) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * inicializacni procedury funkce
	 *
	 * {@source}
	 * @return boolean
	 */
	function __construct() {
		global $vzhled;
		session_register("login", "admin_time");
		if($vzhled!="") {
			$this->design = $vzhled->design("admin");
		}
		return true;
	}

	/**
	 * funkce, ktera vraci odkaz na administraci podle toho, kde se uzivatel nachazi. (Pr. pokud je na
	 * strance /cs/page/index/ ==> /cs/admin/page/index.
	 * Podminka obslouzi, ze v administraci se zobrazuje odkaz na uvodni stranku administrace.
	 *
	 * 18.3 - zruseno odkomentovani podminky, nepamatuju si proc byla zakomentovana.
	 *
	 * {@source}
	 * @return string HTML
	 */
	public function footer() {
		if(slovnik($_GET["class"])!="admin") {
			return "- <a href='".URL::create("admin", $_GET["class"], $_GET["akce"], $_GET["parametr1"], $_GET["parametr2"], $_GET["parametr3"], $_GET["parametr4"])."'>".Lang::View("ADMIN;37;admin")."</a>";
		} else if($_GET["parametr1"]!="") {
			return "- <a href='".URL::create("admin", $_GET["akce"])."'>".Lang::View("ADMIN;37;admin")."</a>";
		} else  {
			return "- <a href='".URL::create("admin")."/'>".Lang::View("ADMIN;37;admin")."</a>";
		}
	}

	/**
	 * funkce pro odhlasovani ze systemu. Rusi vsechny promenne, ktere uzivatele poji k pristupu. Pouziva se
	 * i pri automatickem odhlasovani. Zatim se rusi jen <var>$_SESSION["login"]</var> a <var>$_COOKIE["login"]</var>
	 *
	 * {@source}
	 * @param string $p1,.. argumenty ziskane z adresniho radku, predavaji se dal
	 * @return NULL
	 */
	public function logout($p1=NULL,$p2=NULL,$p3=NULL,$p4=NULL) {
		// smazani session
		unset($_SESSION["login"]);

		// smazani cookis
		//unset($_COOKIE["login"]);

		unset($_SESSION["admin_time"]);

		// presmerovani, u uzivatele se diky tomu projevi zmena
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: ".adresa("admin", $p1, $p2, $p3, $p4));
		header("Connection: close");
	}

	/**
	 * funkce pro overeni spravnosti udaju a vytvoreni prihlaseni
	 */
	private function access($login, $password) {
		global $db;

		// overeni spravnosti udaju
		$dotaz = $db->fetch_array("select * from __admin_users where login='".$login."' and password='".$password."'");
		// pokud dotaz nevrati nulovou odpoved
		if($dotaz!=false) {
			// vytvoreni prihlasovaci session
			$_SESSION["login"] = $dotaz;
			// vytvoreni prihlasovaciho cookie a nastaveni limitu doby platnosti
			//setcookie("login", yrshash("admin", $login), time()+ADMIN_TIMEOUT, "/");

			$_SESSION["admin_time"] = time();

			// vraceni informace o uspechu
			return true;
		} else {
			// vraceni informace o neuspechu, spatne prihlasovaci udaje
			return false;
		}
	}

	/**
	 * funkce, ktera overuje, zda nevyprsela platnost cookies. Pokud ano, uzivatel je automaticky
	 * odhlasen.
	 *
	 * {@link http://php.vrana.cz/odhlaseni-uzivatele-po-urcite-dobe.php}
	 * Pouzivani cookies je nespolehlive.
	 */
	private function cookiecheck($p1=NULL,$p2=NULL,$p3=NULL,$p4=NULL) {
		// overeni, zda se hodnota cookie rovna hodnote v session
		/*if($_COOKIE["login"]!=yrshash("admin", $_SESSION["login"]["login"])) {
			// pokud se nerovna, system uzivatele odhlasi
			$this->logout($p1, $p2, $p3, $p4);
		}*/

		if ($_SESSION["admin_time"] < time()-ADMIN_TIMEOUT) {
			$this->logout($p1, $p2, $p3, $p4);
			return false;
		}

		$_SESSION["admin_time"] = time();
		return true;
	}

	/**
	 * trida, ktera slouzi jako hlavni rozcestnik administrace
	 */
	private function main() {
		$ret = design($this->design["main_header"], array());
		$moduly = scandir(CESTA_MODULY);

		foreach($moduly as $modul) {
			$link = $name = '';

			if(ereg("class.(.*).php", $modul)) {
				$modul = strtolower(str_replace(array("class.", ".php"), "", $modul));

				$class = ucfirst($modul).'Admin';
				if(class_exists($class) or method_exists($modul, "admin")) {
					$link = '<a href="'.URL::create("admin", $modul).'">'.Lang::View("ADMIN;c1;go to ->").'</a>';
				}


				$name = Lang::View(strtoupper($modul).";name;".$modul);

				if($link!="") {
					$tab .= design(
						$this->design["list_modules_row"],
						array(
							"modul"=>$modul,
							"name"=>$name,
							"link"=>$link
						)
					);
				}

			}
		}
		$ret .= design($this->design["list_table"], array("content"=>$tab));
		return $ret;
	}

	/**
	 * funkce, ktera vraci prihlasovaci formular.
	 */
	public function loginform($p1=NULL,$p2=NULL,$p3=NULL,$p4=NULL) {
		return design(
			$this->design["loginbox"],
			array(
				"url"=>adresa("admin", $p1, $p2, $p3, $p4)
			)
		);
	}


	public function isLogged($returnid=false) {
		if($_SESSION["login"]!="") {
			if($returnid==true) {
				return $_SESSION["login"];
			}
			return true;
		}
		return false;
	}

	/**
	 * funkce, ktera zpracovava stav a rozrazuje akce.
	 * Situace:
	 * 1. <b>Uzivatel neni prihlasen</b> - zobrazi se prihlasovaci formular
	 * 2. <b>Uzivatel neni prihlasen</b>, ale jsou odeslany _POST udaje - zkontroluje se spravnost
	 * 3. <b>Uzivatel je prihlasen</b> - overi se, zda neprovadly cookies
	 * 4. <b>Uzivatel je prihlasen</b> - zavola se akce pozadovana uzivatelem
	 * Uzivatel vzdy vola v druhem argumentu nazev tridy, jejich administraci chce spustit
	 * <code>
	 * http://mujweb.cz/cs/admin/trida/parametry_tridy
	 * </code>
	 * Bude vytvorena trida TRIDA a budou ji predany parametry. Pokud trida nebude existovat,
	 * nacte se defaultni stranka administrace.
	 *
	 * {@source}
	 * @param string $p1,.. argumenty ziskane z adresniho radku, predavaji se dal
	 * @return string HTML kod k zobrazeni
	 */
	public function view($p1=NULL,$p2=NULL,$p3=NULL,$p4=NULL) {
		// kontrola, zda je uzivatel prihlasen
		if($_SESSION["login"]=="") {
			// kontrola zda se uzivatel prihlasuje
			if($_POST["login"]=="") {
				// vypsani prihlasovaciho formulare
				return $this->loginform($p1,$p2,$p3,$p4);
			} else {
				// zkontrolovani zadanych udaju

				if($this->access($_POST["login"], yrshash("admin", $_POST["password"]))==true) {
					// pokud jsou udaje spravne, zobrazi se hlaska o uspechu a probehne presmerovani
					return $this->design["ok"].
					"<meta http-equiv='refresh' content='1;url=".adresa("admin", $p1, $p2, $p3, $p4)."'>";
				} else {
					// pokud nejsou udaje spravne, zobrazi se chybova hlaska a prihlasovaci formular
					return design($this->design["error"], array("loginbox"=>$this->loginform($p1,$p2,$p3,$p4)));
				}
			}
		} else {
			// zkontrolovani platnosti prihlaseni
			$this->cookiecheck($p1,$p2,$p3,$p4);

			// obnoveni cookie
			setcookie("login", yrshash("admin", $_SESSION["login"]["login"]), time()+ADMIN_TIMEOUT, "/");

			// zjisteni skutecneho nazvu tridy
			$p1 = slovnik($p1);

			/** @todo Novy princip - uz ne metody admin*, ale trida (Modul)Admin. */
			if($p1!='admin' and $p1!=NULL) {
				$admin_class = $p1.'Admin';
				if(class_exists($admin_class)) {
					$trida = new $admin_class;

					if(method_exists($trida, $p2)) {
						return $trida->$p2($p3, $p4);
					} else {
						return $trida->view($p2, $p3, $p4);
					}
				}
			}

			// pokud volana trida existuje
			if(class_exists($p1) and $p1!="admin") {
				// vytvoreni tridy
				$trida = new $p1;

				// kontrola, zda existuje metoda pro administraci
				if(method_exists($trida, "admin")) {

					// zavolani metody pro spravu tridy
					return $trida->admin($p2, $p3, $p4);

				// pokud metoda pro administraci neexistuje, nacte se hlavni rozbocovac
				} else {
					return $this->main();
				}

			// pokud je volana trida administrace, kontroluje se, zda metoda existuje
			} else if($p1=="admin" and method_exists($this, $p2)) {
				// pokud metoda existuje, zavola se
				return $this->$p2($p3, $p4);

			// pokud trida neexistuje
			} else {
				// zobrazi se hlavni rozbocovac
				return $this->main();
			}
		}
	}


	/**
	 * funkce pro upravu nastaveni
	 */
	private function settings($akce) {
		global $db;

		// vytvoreni nove polozky
		if($akce=="save" and $_POST["new_name"]!="") {
			$db->query("replace __settings values('".$_POST["new_name"]."','".$_POST["new_value"]."')");
		}


		// nacteni vsech nastaveni z databaze
		$nacti = $db->query("select * from __settings");

		// promenna pro HTML vystup
		$out = "";

		while($row = $db->fetch_array($nacti)) {
			// jmeno nastaveni
			$name = strtolower("set_".$row["name"]);
			// hodnota nastaveni
			$value = $row["value"];

			// pokud je zaskrtnuto, ze se ma polozka smazat
			if($akce=="save" and $_POST[$name."_remove"]!="") {
				// smazani polozky
				$db->query("delete from __settings where name='".$row["name"]."'");

			// pokud se polozka nemaze
			} else {
				// kontrola, zda nebyla polozka upravena
				if($akce=="save" and $_POST[$name."_value"]!=$value and $_POST["hid_".$name."_value"]==$value) {
					// nova hodnota polozky
					$value = $_POST[$name."_value"];

					// ulozeni hodnoty do DB
					$db->query("update __settings set value='".$value."' where name='".$row["name"]."'");
				}

				// pridani radky do vystupu
				$out .= design(
					$this->design["settings_row"],
					array(
						"name"=>$name,
						"popis"=>strtoupper($row["name"]),
						"value"=>$value
					)
				);
			}
		}

		// vysledny vystup
		return sprintf($this->design["settings_table"], $out);
	}

	/**
	 * metoda pro tvorbu opravneni
	 */
	private function createAccess($url, $owner=0, $group=0, $ao="rw", $ag="r", $aa="r") {
		global $db;

		/** 17.3. - hash zrusen, kvuli zjednoduseni administrace povoleni */
		$hash = $url;

		return $db->query("insert into __admin_access (hash, owner, igroup, ao, ag, aa) values ('".$hash."', '".$owner."', '".$group."', '".$ao."', '".$ag."', '".$aa."')");
	}

	/**
	 * Metoda, ktera zjistuje, zda ma aktualne prihlaseny uzivatel pristup k danem objektu.
	 *
	 * {@source}
	 * @param string $url relativni cesta k objektu
	 * @param boolean $write Pokud je true, zjistuje se povoleni k zapisu, v opacnem pripade ke cteni
	 * @param boolean $cnzjp "Co neni zakazano je povoleno"
	 *
	 * @return boolean TRUE - povolen pristup; FALSE - nepovolen pristup
	 */
	public function getAccess($url, $write=false, $cnzjp=true) {
		global $db;

		// prirazeni ID uzivatele
		$id = $_SESSION["login"]["ID"];
		// prirazeni ID skupiny
		$group = $_SESSION["login"]["igroup"];

		// urceni typu povoleni pro DB
		$typ = ($write==false) ? "r" : "w";

		// zjisteni, zda uzivatel nahodou neni root
		if($group == self::ID_ROOT_GROUP) {
			return true;

		// pokud uzivatel neni root
		} else {
			// vytvoreni hashe pro pristup
			/** 17.3. - hash zrusen, kvuli zjednoduseni administrace povoleni */
			$hash = $url;

			// nacteni vsech opravneni z DB
			$query = $db->query("select * from __admin_access where hash='".$hash."'");

			// kontrola, zda existuje alespon jedno pravidlo
			$epravidlo = false;
			while($access = $db->fetch_array($query)) {
				// kontrola, zda je uzivatel vlastnikem objektu
				if($access["owner"]==$id and ereg($typ, $access["ao"]))
					return true;

				// kontrola, zda je skupina uzivatele skupinou objektu
				if($access["igroup"]==$group and ereg($typ, $access["ag"]))
					return true;

				// nacteni povoleni pro ostatni uzivatele (nejsou ani vlastniky, ani ve skupine)
				if(ereg($typ, $access["aa"]))
					return true;

				// urceni, ze alespon jedno pravidlo existuje
				$epravidlo = true;
			}

			// pokud neexistuje ani jedno pravidlo a CNZJP je nastaveno na true, pristup je	povolen jen ke cteni
		/*	if($epravidlo==false and $cnzjp==true and $write==false)
				return true; */
			if($epravidlo==false) {
				$nurl = "/".strrev(strstr(strrev(trim($url, "/")), "/"));
				if($nurl!="/") {
					return Admin::getAccess($nurl, $write, $cnzjp);
				} else if($cnzjp==true and $write==false) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Bude to funkce, ktera zobrazuje formular pro upravu nastaveni opravneni pro dany objekt.
	 * @todo to chce dodelat
	 */
	public function htmlSetAccess($modul, $file) {
		$hash = yrshash($modul, $file);
		if(self::getAccess("admin", $hash)) {

		} else {
			return Page::load("error403");
		}
	}

	public function users($param, $param2) {
		$out = "";

		switch ($param)  {
			case 'new':
				echo "new";
			break;

			case 'delete':
				echo "delete";
			break;

			case 'update':
				if($param2=='users') {
					$this->updateUsers();
				} else if($param2=='groups') {
					$this->updateGroups();
				} else if($param2=='access') {
					$this->updateAccess();
				}
			break;
		}

		$listUsers = $this->getAllUsers();
		$out .= $this->htmlShowUsers($listUsers);

		$listGroups = $this->getAllGroups();
		$out .= $this->htmlShowGroups($listGroups);

		$listAccess = $this->getAllAccess();
		$out .= $this->htmlShowAccess($listAccess);

		return $out;
	}

	/**
	 * funkce, ktera upravuje uzivatele. Data cerpa z _POST.
	 *
	 * {@source}
	 * @return boolean
	 */
	private function updateUsers() {
		global $db;

		// nacteni seznamu uzivatelu
		$list = $this->getAllUsers();

		// prochazeni seznamem uzivatelu
		foreach($list as $user) {
			// kontrola, zda se ma uzivatel smazat
			if($_POST[$user["ID"]."_del"]!="") {
				// smazani uzivatele
				$db->query("delete from __admin_users where ID='".$user["ID"]."'");

			// kontrola, zda se meni uzivatelova skupina
			} else if($_POST[$user["ID"]."_group"] != $user["igroup"] and $_POST[$user["ID"]."_group"]!="") {
				// upraveni skupiny uzivatele
				$db->query("update __admin_users set igroup='".$_POST[$user["ID"]."_group"]."' where ID='".$user["ID"]."'");
			}
		}

		// pokud je vyplneno pole pro vytvoreii noveho uzivatele
		if($_POST["newuser_login"]!="" and $_POST["newuser_password"]!="") {
			// login
			$login = $_POST["newuser_login"];

			// heslo
			$password = yrshash("admin", $_POST["newuser_password"]);

			// skupina
			$group = $_POST["newuser_group"];

			// vyytvoreni uzivatele
			$db->query("insert into __admin_users (login, password, igroup) values ('".$login."','".$password."','".$group."')");
		}

		return true;
	}

	/**
	 * funkce, ktera vraci HTML kod se seznamem uzivatelu, formularem pro upravu a pro pridani noveho uzivatele.
	 *
	 * {@source}
	 * @param array $list seznam uzivatelu
	 * @return string HTML kod
	 */
	private function htmlShowUsers($list) {
		// promenna pro vraceni
		$ret = "";

		// prochazeni seznamem uzivatelu
		foreach($list as $user) {
			// ziskani kodu pro jeden radek
			$ret .= design(
				$this->design["users_list_all_row"],
				array(
					"id"=>$user["ID"],
					"login"=>$user["login"],
					"group"=>$user["igroup"]
				)
			);
		}

		// vraceni html kodu
		return sprintf($this->design["users_list_all_table"], $ret);
	}

	/**
	 * funkce pro ziskani seznamu vsech uzivatelu.
	 *
	 * {@source}
	 * @return array
	 */
	private function getAllUsers() {
		global $db;

		// pole pro vraceni
		$ret = array();

		// nacteni z db
		$load = $db->query("select ID, login, igroup from __admin_users order by ID");
		while($user = $db->fetch_array($load)) {
			// pridani do pole pro vraceni
			$ret[] = $user;
		}

		// vraceni pole
		return $ret;
	}

	/**
	 * funkce, ktera upravuje skupiny v DB po odeslanem formulari.
	 *
	 * {@source}
	 * @return boolean
	 */
	private function updateGroups() {
		global $db;

		// nacteni seznamu vsech existujicich skupin
		$list = $this->getAllGroups();

		// prochazeni skupinami
		foreach($list as $group) {
			// kontrola, zda se skupina nema smazat
			if($_POST[$group[ID]."_del"]!="") {
				// smazani skupiny
				$db->query("delete from __admin_groups where ID='".$group[ID]."'");

				// pokud je definovana nahradni skupina (vsichni uzivatele ze smazane skupiny se do teto presunou
				/** @todo Asi by pak chtelo udelat i presun opravneni a vlastnictvi... */
				if($_POST[$group[ID]."_nahr"]!="")
					// uprava uzivatelu
					$db->query("update __admin_users set igroup='".$_POST[$group[ID]."_nahr"]."' where igroup='".$group[ID]."'");

			// kontrola, zda se nemeni nazev skupiny
			} else if($_POST[$group[ID]]!=$group["name"] and $_POST[$group[ID]]!="") {
				// uprava nazvu skupiny
				$db->query("update __admin_groups set name='".$_POST[$group[ID]]."' where ID='".$group[ID]."'");
			}
		}

		// pokud je vyplneno policko pro vytvoreni nove skupiny
		if($_POST["newgroup"]!="")
			// vytvoreni nove skupiny
			$db->query("insert into __admin_groups (name) values ('".$_POST["newgroup"]."')");

		return true;
	}

	/**
	 * funkce, ktera vraci html kod se seznamem vsech skupin, formularem pro upravu a pro pridani nove skupiny
	 *
	 * {@source}
	 * @param array $list seznam skupin
	 * @return string HTML kod
	 */
	private function htmlShowGroups($list) {
		// retezec pro vraceni
		$ret = "";

		// prochazeni dodanym polem
		foreach($list as $group) {
			// ziskani html pro jeden radek
			$ret .= design($this->design["groups_list_all_row"], array("id"=>$group["ID"], "name"=>$group["name"]));
		}

		// vraceni html
		return sprintf($this->design["groups_list_all_table"], $ret);
	}

	/**
	 * funkce, ktera vraci seznam vsech skupin ulozenych v DB.
	 *
	 * {@source}
	 * @return array seznam vsech skupin
	 */
	private function getAllGroups() {
		global $db;

		// pole pro vraceni
		$ret = array();

		// nacteni udaju z DB
		$load = $db->query("select * from __admin_groups order by ID");

		// prochazeni nactenymi polozkami
		while($group = $db->fetch_array($load)) {
			// ulozeni do pole pro vraceni
			$ret[] = $group;
		}

		// vraceni seznamu skupin
		return $ret;
	}

	/**
	 * funkce, ktera vraci html kod se seznamem vsech opravneni, formularem pro upravu a pro pridani noveho opravneni
	 *
	 * {@source}
	 * @param array $list seznam opravneni
	 * @return string HTML kod
	 */
	private function htmlShowAccess($list) {
		// retezec pro vraceni
		$ret = "";

		// prochazeni dodanym polem
		foreach($list as $access) {
			// ziskani html pro jeden radek
			$ret .= design(
				$this->design["access_list_all_row"],
				array(
					"id"=>$access["ID"],
					"name"=>$access["hash"],
					"owner"=>$access["owner"],
					"group"=>$access["igroup"],
					"ao_r"=>((ereg("r", $access["ao"])) ? "checked" : ""),
					"ao_w"=>((ereg("w", $access["ao"])) ? "checked" : ""),
					"ag_r"=>((ereg("r", $access["ag"])) ? "checked" : ""),
					"ag_w"=>((ereg("w", $access["ag"])) ? "checked" : ""),
					"aa_r"=>((ereg("r", $access["aa"])) ? "checked" : ""),
					"aa_w"=>((ereg("w", $access["aa"])) ? "checked" : ""),
				)
			);
		}

		// vraceni html
		return design($this->design["access_list_all_table"], array("s"=>$ret));
	}

	/**
	 * funkce, ktera vraci seznam vsech opravneni ulozenych v DB.
	 *
	 * {@source}
	 * @return array seznam vsech opravneni
	 */
	private function getAllAccess() {
		global $db;

		// pole pro vraceni
		$ret = array();

		// nacteni udaju z DB
		$load = $db->query("select * from __admin_access order by ID");

		// prochazeni nactenymi polozkami
		while($access = $db->fetch_array($load)) {
			// ulozeni do pole pro vraceni
			$ret[] = $access;
		}

		// vraceni seznamu skupin
		return $ret;
	}

	private function getAccessFromForm($read, $write) {
		$read = ($read=="true") ? "r" : "";
		$write = ($write=="true") ? "w" : "";
		return $read.$write;
	}

	private function updateAccess() {
		global $db;

		$list = $this->getAllAccess();
		foreach($list as $row) {
			if($_POST[$row["ID"]."_del"]=="true")
				$db->query("delete from __admin_access where ID='".$row["ID"]."'");
		}

		if($_POST["new_name"]!="" and $_POST["new_owner"]!="" and $_POST["new_group"]!="") {
			$url = $_POST["new_name"];
			$owner = $_POST["new_owner"];
			$group = $_POST["new_group"];

			$ao = self::getAccessFromForm($_POST["new_ao_r"], $_POST["new_ao_w"]);
			$ag = self::getAccessFromForm($_POST["new_ag_r"], $_POST["new_ag_w"]);
			$aa = self::getAccessFromForm($_POST["new_aa_r"], $_POST["new_aa_w"]);

			$this->createAccess($url, $owner, $group, $ao, $ag, $aa);
		}

		return true;
	}

	public function getUserInfo($id, $keys) {
		global $db;
		$ret = array();

		foreach($keys as $key) {
			list($value) = $db->fetch_array("select hodnota from __admin_users_info where user='".$id."' and klic='".$key."'");
			$ret[] = ($value!="") ? $value : NULL;
		}

		return $ret;
	}
}
?>
