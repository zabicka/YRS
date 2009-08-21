<?php
/**
 * @package modules
 */
/**
 * Trida pro spolupraci s databazi MySQL.
 * 
 * @package modules
 * @subpackage db
 * @author Juda Yetty Kaleta <yettycz@gmail.com>
 */
class MySQL {
	/**
	 * promenna se spojenim s databazi
	 */
	var $db = NULL;
	
	static $count_queries = 0;
	//static
	
	//var $disallowed  = "*";
	
	/**
	 * 
	 */
	public function install() {
		return array(
			array("nutne", "", "header"),
			array("user", "", "text"),
			array("password", "", "password"),
			array("db", "yrs", "text"), 
			
			array("pokrocile", "", "header"), 
			array("server", "localhost", "text"),
			array("kodovani", "UTF8", "text"),
			array("prefix", "yrs_", "text"),
			array("persistent", "true", "checkbox")
		);
	}
	
	public function check_install() {
		
		if($_POST["server"]!="") {
			if($this->connect($_POST["server"], $_POST["user"], $_POST["password"], $_POST["db"], $_POST["kodovani"])) {
				if($uloz = @fopen("../db.php", "w")) {
					if(fwrite($uloz, 
	'<?php
	define(\'DB\', \'MySQL\');
	define(\'DB_SERVER\', \''.$_POST["server"].'\');
	define(\'DB_USER\', \''.$_POST["user"].'\');
	define(\'DB_PASSWORD\', \''.$_POST["password"].'\');
	define(\'DB_DB\', \''.$_POST["db"].'\');
	define(\'DB_KODOVANI\', \''.$_POST["kodovani"].'\');
	define(\'DB_PREFIX\', \''.$_POST["prefix"].'\');
	define(\'DB_PERSISTENT\', \''.$_POST["persistent"].'\');
	?>'
					)) {
						return true;
					}
				} else {
					return false;
				}
			
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	/**
	 * Funkce pro pripojeni k DB.
	 * 
	 * {@source}
	 * @param string $server adresa serveru s MySQL
	 * @param string $user uzivatelske jmeno pro pripojeni k MySQL
	 * @param string $password heslo pro pripojeni k MySQL
	 * @param string $db nazev databaze, do ktere je system nainstalovan
	 * @param string $kodovani kodovani, ktere se ma pouzit v v prikazu SET NAMES
	 * @return boolean
	 */
	public function connect($server=DB_SERVER, $user=DB_USER, $password=DB_PASSWORD, $db=DB_DB, $kodovani=DB_KODOVANI) {
		//global $hlaseni;	
		// vytvoreni spojeni s MySQL
		if(DB_PERSISTENT=="true") {
			$this->db = @mysql_pconnect($server, $user, $password);
		} else {
			$this->db = @mysql_connect($server, $user, $password);
		}

		if($this->db==true) {
			// nacteni DB
			if(mysql_select_db($db, $this->db)) {
				// nastaveni kodovani
				mysql_query("SET NAMES ".$kodovani);
				return true;
			} else {
				return false;
			}
		} else {
			return false;	
		}			
	}
	
	/**
	 * Funkce pro vytvoreni dotazu k DB. Nekdy staci pouzit jen funkci {@link MySQL::fetch_array()}.
	 * 
	 * {@source}
	 * @param string $dotaz SQL prikaz
	 */
	public function query($dotaz, $soubor="") {
		global $hlaseni;
		// kontrola, zda dotaz neni prazdny
		if($dotaz!="") {
			// pridani prefixu za dve podtrzitka (__)
			$dotaz = str_replace("__", DB_PREFIX, $dotaz);
			
			// spusteni prikazu
			if($ret = mysql_query($dotaz, $this->db)) {
				self::$count_queries++;
		
				// vraceni vysledku
				return $ret;	
			} else {	
				// ulozeni zpravy o chybnem dotazu
				Chyby(2, "Chyba v prikazu: <i>".$dotaz."</i>", (($soubor=="")?"/class.mysql.php":"/".$soubor));
			//	$hlaseni->pridej(sprintf(MYSQL_QUERY_ERROR, $dotaz), false, "class.mysql.php");
				// vraceni informace o neuspechu
				return false;
			}
		}
	}
	
	/**
	 * funkce pro ziskani polozek z DB. Lze pouzit i jako spojeni funkce {@link MySQL::query()}
	 * pro ziskani pouze jednoho radku
	 * 
	 * Pouziti s {@link MySQL::query()} a {@link MySQL::fetch_array()}
	 * <code>
	 * $dotaz = $db->query("select * from users where user='".$_POST["user"]."'
	 * and password='".md5($_POST["password"])."' limit 1");
	 * $vysledek = $db->fetch_array($dotaz);
	 * </code>
	 * 
	 * Pouziti pouze s funkci {@link MySQL::fetch_array()}
	 * <code>
	 * $vysledek = $db->fetch_array("select * from users where user='".$_POST["user"]."'
	 * and password='".md5($_POST["password"])."' limit 1");
	 * </code>
	 * 
	 * Toto zjednoduseni se da pouzit pouze kdyz je vysledkem jeden radek.
	 * 
	 * 
	 * {@source}
	 * @param mixed $query muze to byt hodnota vracena funkci {@link MySQL::query()} nebo dotaz SQL
	 */
	public function fetch_array($query) {	
		global $hlaseni;
		
		// pokud je vlozeny parametr retezec
		if(is_string($query)) {
			// pridani prefixu za dve podtrzitka (__)
			$query = str_replace("__", DB_PREFIX, $query);
			
			// spusteni dotazu
			if($dotaz = mysql_query($query, $this->db)) {
				self::$count_queries++;
				
				// ziskani polozky a jeji vraceni
				$ret = mysql_fetch_array($dotaz);
				mysql_free_result($dotaz);
				return $ret;
			// pokud neni dotaz korektni
			} else {	
				// ulozeni hlasky o neuspechu
				if($hlaseni!="") $hlaseni->pridej(sprintf(MYSQL_QUERY_ERROR, $dotaz), false, "class.mysql.php");

				// vraceni informace o chybe
				return false;
			}
		// pokud vlozeny parametr neni retezec (jedna se o vystup funkce query() )
		} else {			
			// vraceni vysledku
			$ret = mysql_fetch_array($query);
			//mysql_free_result($query);
			return $ret;
		}
	}
	
	public function getCountQueries() {
		return self::$count_queries;
	}
}
?>
