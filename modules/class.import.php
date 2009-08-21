<?php
define('IMPORT_DEFAULT_DIR', './modules/import/');
define('IMPORT_MODULES_PATH', './modules/import/modules/');

class Import 
{
	/** var with design strings */
	private $design = "";
	
	/** array for saving strings from file */
	private $list = array();
	
	/** array for saving filters */
	//private $filters = array(); /** @todo Filters are going to apply after submit theirs form and it changed {@link Import::list}. Therefor there isn't need for saving it. */
	
	/** is first row description? */
	private $frisdesc = true;
	
	/** list of supported filetypes */
	private $supported_types=array("text/csv"=>"csv", "text/xml"=>"xml");
	/** default filetype */
	private $type = "text/csv";
	
	/** default path to file */
	private $path = "";
	
	/** name of import file */
	private $file = "";
	
	/** format of input in {@link Import::htmlList} */
	private $input_format = "r%sa%s";
	
	/**
	 *	name of actions
	 *	there are loading methods html*. By {@link $_GET["akce"]}.
	 */
	
	/** htmlList */
	private $action_htmlList = "";
	
	/** htmlCheck */
	private $action_htmlCheck = "check";
	
	/** 
	 * vars from cooperation with DB
	 */
	private $tables = array();
	
	/**
	 * how many rows will be showed.
	 */
	private $show_limit = 100;
	
	
	/**
	 * types of clauses
	 * for method {@link Import::filter}
	 */
	
	/** when is equals. */
	const F_EQUALS = "=";
	/** when doesn't eqauls. */
	const F_DONT_EQUALS = "!=";
	
	/** when something is bigger than value */
	const F_BIGGER = ">";
	/** when something is bigger or equals than value */
	const F_BIGGER_E = ">=";
	
	/** when something is smaller than value */
	const F_SMALLER = "<";
	/** when something is smaller or equals than value */
	const F_SMALLER_E = "<=";
	
	/** whatever regular expression (for function {@link ereg()}*/	
	const F_EREG = "ereg";
	
	/** all rules must be right */
	const F_ALL = "all";
	
	/** one from rules must be right */
	const F_ONE_FROM = "one from";
	
	
	/**
	 * for method {@link Import::change}
	 */
	
	/** how key is for change as numer */
	const CHANGE_TYPE_INT = "int";
	
	/** how key is for change as string */
	const CHANGE_TYPE_STRING = "string";
	
	/** expression who will be replace value. */
	const CHANGE_TYPE_X = "_x_";
	
	/** for differentiation filters and autochange. */
	const CHANGE_TYPE_PREFIX = "a_";
	
	/** name of file for saving import */
	const TMP_FILENAME = ".tmpimport";
	
	/** name of file for saving from remote server */
	const TMP_FILENAME_REMOTE = ".tmpdata";
	
	/** dir for saving patterns for import */
	const DIR_SNAPSHOTS = "snapshots/";
	
	/** file for saving temporary pattern for import */
	const TMP_SNAPSHOTS = ".tmpsnapshots";
	
	/** showing first row as description */
	private $first_row = true;
	
	/** allow update tables */
	private $allow_update = true;
	
	/** allow write new rows to tables */
	private $allow_insert = true;
	
	/** var for saving structure saving */
	private $structure_db = array();
	
	/** count of chars in replacing */
	const COUNT_CHARS = 3;

	function __autoload($name) {	
		$path = IMPORT_MODULES_PATH."/class.".strtolower($name).".php";

		if(is_file($path)) {
			require_once $path;
		}
	} 
		
	/**
	 * It's private for unicated this class
	 */
	public function __construct() 
	{
		global $vzhled;
		$this->design = $vzhled->design("import");	
	}
	
	/**
	 * Methode for created class.
	 * 
	 * {@source}
	 * @param string $file name of import file
	 * @param string $path path to file
	 * @param string $type type of file (ex. text/csv) - {@link Import::$supported_types}
	 * 
	 * @return object class. Import
	 */
	public function construct($file="", $path="", $type="") 
	{
		// create class
		$ret = new Import;
		
		if(ereg("://", $file))
		{
			copy($file, IMPORT_DEFAULT_DIR.self::TMP_FILENAME_REMOTE);
			
			$file = self::TMP_FILENAME_REMOTE;
			$path = "./";
		}
		
		// set path
		if($ret->setPath($path)) 
		{
			// set file
			if($ret->setFile($file))
			{
				// set type of file
				$ret->setType($type);
			}
		}
		
		$import->setTables(
			array(
				'yrs_page_pages',
				'yrs_menu_menus',
			)
		);
		
		
		
		// return new class
		return $ret;
	}
	
	/**
	 * load data from session
	 * 
	 * {@source } 
	 * @return boolean
	 */
	public function sessionRestore() 
	{
		// register session
		session_register("show_limit", "show_from", "first_row");
				
		$this->show_limit = ($_SESSION["show_limit"]!=0 and is_numeric($_SESSION["show_limit"])) ? $_SESSION["show_limit"] : $this->show_limit;		
		$this->show_from = ($_SESSION["show_from"]>=0) ? $_SESSION["show_from"] : $this->show_from;
		$this->first_row = ($_SESSION["first_row"]!="") ? $_SESSION["first_row"] : $this->first_row;
		$this->structure_db = ($_SESSION["structure_db"]!="") ? $_SESSION["structure_db"] : $this->structure_db;
		
		if(count($this->list)<1)
		{
			$eval = file_get_contents(IMPORT_DEFAULT_DIR.self::TMP_FILENAME);
			
			eval('$list = '.$eval);
			
			$this->setList($list);
		}
		
		
		
				
		return true;
	}
	
	public function sessionSave() {
		session_register("show_limit", "show_from", "first_row", "structure_db");

		$_SESSION["show_limit"] = $this->show_limit;		
		$_SESSION["show_from"] = $this->show_from;
		$_SESSION["first_row"] = $this->first_row;
		$_SESSION["structure_db"] = $this->structure_db;

		$sl = fopen(IMPORT_DEFAULT_DIR.self::TMP_FILENAME, 'w');
		fwrite($sl, return_var($this->checkap($this->getList())));
		fclose($sl);

		
		return false;
	}
	
	/**
	 * return name of file.
	 *
	 * {@source} 
	 * @return string
	 */
	public function getFile() 
	{
		return $this->file;
	}
	
	/**
	 * return type of file
	 * 
	 * {@source}
	 * @return string
	 */
	public function getType() 
	{
		return $this->type;
	}
	
	/**
	 * return path to file
	 * 
	 * {@source}
	 * @return string
	 */
	public function getPath() 
	{
		return $this->path;
	}
	
	/**
	 * return list
	 * 
	 * {@source}
	 * @return array
	 */
	public function getList() 
	{
		return $this->list;
	}
	
	/**
	 * return array with tables for import. If it isn't set, try on setting.
	 * 
	 * {@source}
	 * @return array
	 */
	public function getTables() 
	{
		if(count($this->tables)==0) {
			// try on setting
			$this->setTables();
		}
		
		return $this->tables;	
	}
	
	/**
	 * return array with rows in table (from db).
	 * 
	 * {@source}
	 * @param string $table name of table
	 * @return array
	 * @todo check table exists
	 */
	private function getTableDescribe($table)
	{
		global $db;
		
		// var for return
		$ret = array();
		
		// query to get list of rows
		$des = $db->query("describe ".$table);
		
		// walking
		while($rows = $db->fetch_array($des)) {
			// add row to return
			$ret[] = $rows["Field"];
		}
		
		return $ret;
	}
	
	/**
	 * return how many cols have imports file.
	 * 
	 * {@source}
	 * @return int
	 */
	public function getCountCols() 
	{
		// walking in array
		for($i=0; $i<$this->getCountRows(); $i++) 
		{
			// get count
			$pocet = count($this->list[$i]);
			
			// if count is bigger than zero.
			if($pocet>0)
				return $pocet;
		}	
		
		return false;
	}
	
	/**
	 * return how many rows have imports file.
	 * 
	 * {@source }
	 * @return int
	 */
	public function getCountRows() 
	{
		// get count
		return count($this->list);
	}
	
	
	public function setList($array)
	{
		if(is_array($array))
		{
			$this->list = $array;
			return true;	
		}
		else if($array=="")
		{
			$this->list = array();
		}
	
		return false;
	}
	
	public function setType($type) 
	{
		$types = $this->supported_types;
		
		// check
		if($types[$type]!='') 
		{
			$this->type = $type;
			
			return true;
		}
		return false;
	}
	
	/**
	 * reset var {@link Import::$file}. Checking file existing.
	 * 
	 * {@source }
	 * @param string $file name of new file
	 * 
	 * @return boolean
	 */
	public function setFile($file) 
	{
		// get path
		$path = $this->getPath();
		
		// check
		if(is_file($path.$file)) 
		{
			// reset file
			$this->file = $file;
			
			return true;
		} 
		
		return false;
	}
	
	
	/**
	 * reset var of path to import file. Control existing dir.
	 * 
	 * {@source }
	 * @param string $path new path to dir
	 * 
	 * @return boolean
	 */
	public function setPath($path) 
	{
		// check
		if(is_dir($path)) 
		{
			// trim end /
			$path = rtrim($path, "/");
			$this->path = $path."/";
			
			return true;
		}
		
		return false;
	}
	
	/**
	 * it is setting list of Tables to import.
	 * 
	 * {@source}
	 * @param array $array list with tables
	 * @return boolean
	 *  
	 */
	public function setTables($array="") {
		// check
		if(is_array($array)) {
			// save to objects var
			$this->tables = $array;
			
			return true;
		} else {
			// load db
			global $db;
			
			// array to ret
			$out = array();
			
			// query
			if($q = $db->query("show tables from yrs")) {

				while(list($r, ) = $db->fetch_array($q)) {
					$out[] = $r;
				}
				
				// save to objects var
				$this->tables = $out;
				return true;
			}
		}
		
		return false;
	}
	
	
	/**
	 * function for open file, parsing it and return results (to {@link Import::$list})  with singles arrays
	 * 
	 * {@source}
	 * 
	 * @return boolean its saving to {@link Import::$list}
	 */
	public function load() {
		$typ = $this->supported_types[$this->getType()];
		
		if($this->getFile()!="")
		{
			@unlink(IMPORT_DEFAULT_DIR.rtrim(self::DIR_SNAPSHOTS, "/")."/".self::TMP_SNAPSHOTS);
			fopen(IMPORT_DEFAULT_DIR.rtrim(self::DIR_SNAPSHOTS, "/")."/".self::TMP_SNAPSHOTS, "w");
		}
		
		if(class_exists($typ)) {
			$cload = new $typ;
			
			$cload->file = $this->getFile();
			
			$cload->load();
			
			$this->setList($cload->list);
		}
		
		return true;
	}	
	
	/**
	 * 
	 *	<code>
	 *	array (
	 *		0=> {@link Import::F_ALL Import::F_ONE_FROM}.
	 * 
	 * 		1=>
	 * 		array(
	 * 			1=>
	 * 			array(
	 * 				what=> int(),
	 * 				cause=> '= | != | < | > ...',
	 * 				exp=> defined expression,
	 * 			) 
	 * 		)			
	 *	)
	 *	</code>
	 * 
	 */
	public function filter($array) 
	{
		/** @todo is it going to do shit? */
		sort($array);
		
		list($type, $rules) = $array;

		$new_lists = array();
		
		foreach($rules as $rule) 
		{
			$new_lists[] = $this->filterRules($rule);
		}
				
		if($type==self::F_ALL or count($new_lists)==1)
		{
			$list = array();
			
			if(count($new_lists)>1) {
				
				$result = "";
				
				for($x=1; $x<count($new_lists); $x++) 
				{
					if($result=="")
						$result = $new_lists[$x-1];

					$new_lists[$x-1] = array_intersect($result, $new_lists[$x]);
					
					$result = $new_lists[$x-1];
				}
				
			} else {
				$result = $new_lists[0];
			}
			 
		} 
		else if($type==self::F_ONE_FROM)
		{
			$list = array();
			
			for($x=1; $x<count($new_lists); $x++) 
			{
				$new_lists[$x-1] = $new_lists[$x-1] + $new_lists[$x];
				$result = $new_lists[$x-1];
			}
			
		}

		return $result;
	}
	
	public function filterRules($array) 
	{
	
		list($what, $cause, $exp) = array_values($array);
		$list = $this->list;
		
		
		foreach($list as $key=>$row)
		{
			if($key>0 or $this->first_row==false)
			{
				switch($cause) 
				{
					case self::F_EQUALS:
						if($row[$what]==$exp) 
						{
							break;
						} else {
							unset($list[$key]);
						}
					break;
					
					case self::F_DONT_EQUALS:
						if($row[$what]!=$exp) 
						{
							break;
						} else {
							unset($list[$key]);
						}
					break;	
					
					case self::F_BIGGER:
						if($row[$what]>$exp) 
						{
							break;
						} else {
							unset($list[$key]);
						}
					break;
					
					case self::F_SMALLER:
						if($row[$what]<$exp) 
						{
							break;
						} else {
							unset($list[$key]);
						}
					break;
					
					case self::F_EREG:
						if(ereg($exp, $row[$what])) 
						{
							break;
						} else {
							unset($list[$key]);
						}
					break;
				}
			}
		}
		
		return $list;
	}
	
	function checkap($array) {
		$ret = array();
		foreach($array as $key=>$field) {
			if(is_array($field)) {
				$ret[$key] = $this->checkap($field);
			} else {
				$field = str_replace("\'", "&apos;", $field);
				$ret[$key] = str_replace("'", "&apos;", $field);
			}
				
		}
		
		return $ret;
	}
	
	
	/**
	 * 
	 * <code>
	 * $how = array (
	 * 		key => array (
	 * 			"type"=> {@link Import::CHANGE_TYPE_STRING} | {@link Import::CHANGE_TYPE_INT},
	 * 			"exp" => ex. - "neco_{@link Import::CHANGE_TYPE_X}" or "({@link Import::CHANGE_TYPE_X}*5)/4"
	 * 		),
	 * );
	 * </code>
	 */
	public function change($array, $how) 
	{
		$list = $this->list;
		
		// walk in list with changed arrays
		foreach($array as $key=>$row) 
		{
			// check if array exists in original array and value is identical
			if($list[$key] == $row)
			{
							
				// walk in row and changing field
				foreach($row as $index=>$field) 
				{
					// check if model for field exists
					if($how[$index]!="" and ($this->first_row==false or $key>0))
					{
						if($how[$index]["type"]==self::CHANGE_TYPE_STRING)
						{
							$row[$index] = str_replace(self::CHANGE_TYPE_X, $field, $how[$index]["exp"]);
						} 
						
						else if($how[$index]["type"]==self::CHANGE_TYPE_INT and ereg("[0-9\.,]",$field)) 
						{
							$field = ($field=="") ? 0 : $field;				
							$field = str_replace(self::CHANGE_TYPE_X, $field, $how[$index]["exp"]);
							
							// , to .
							$field = str_replace(",", ".", $field);
							
							// remove next commands
							$field = explode(";", $field);
							$field = $field[0];
							
							eval("\$vysledek = ".$field.";");

							$row[$index] = $vysledek;
						}
						
						$list[$key][$index] = $row[$index];													
					}
				}
			}
		}

		return $list;			
	}
	
	public function view() 
	{
		$out = "";
		
		switch ($_GET["action"]) 
		{
			case "load":
				$hlaska = $this->ffLoad();
			break;
			
			case "change":
				$hlaska = $this->ffNavigation("change");
			break;
			
			case "next":
				$hlaska = $this->ffNavigation("next");
			break;
			
			case "previous":
				$hlaska = $this->ffNavigation("previous");
			break;
			
			case "filters":
				list($f, ) = $this->ffFilters();
				$this->setList($f);
			break;
			
			case "auto":
				$this->setList($this->ffAuto());
			break;
			
			case "savesnapshot":
				$hlaska = $this->saveSnapshot();
			break;
			
			case "loadsnapshot":
				$hlaska = $this->loadSnapshot();
			break;
			
			case "savedb":
				$hlaska = $this->ffSaveDB();
			break;
			
			case "save":
				$hlaska = $this->run();
			break;
			
		}
		$this->first_row = ($_GET["first_row"]=="true" or $_GET["first_row"]=="false") ? $_GET["first_row"] : $this->first_row;
		
		if($_GET["first_row"]=="change") 
		{
			$this->first_row = ($this->first_row==true) ? false : true;
		}
		
		$formload = design($this->design["formload"], array("url"=>"?action=load"));
		
		$formdb = $this->viewDB();
		
		$formsnapshot = $this->viewSnapshots();
		
		$formfilters = $this->viewFilters();
		
		$formauto = $this->viewAuto();
		
		$navigation = $this->viewNavigation();
		
		$formend = design($this->design["formend"], array("url"=>"?action=save"));
		
		$formimport = $this->viewImport($this->show_limit, $this->show_from);

		$out = design($this->design["main"], 
			array(
				"count"=>$this->getCountCols(), 
				"formend"=>$formend, 
				"formfilters"=>$formfilters, 
				"formload"=>$formload,
				"formdb"=>$formdb,
				"formsnapshot"=>$formsnapshot,
				"formauto"=>$formauto,
				"formimport"=>$formimport,
				"navigation"=>$navigation,
			)
		);
		
		return $out;
	}
	
	
	private function viewDB() 
	{
		$tables = $this->getTables();
		
		$options = "";
		
		foreach($tables as $table) 
		{						
			$describe = $this->getTableDescribe($table);		
			$rows = "";
			foreach($describe as $row) 
			{
				$rows .= design($this->design["formdb_option"], array("table"=>$table, "name"=>$row));
			}			
			
			$options .= design($this->design["formdb_optgroup"], array("table"=>$table, "rows"=>$rows));	
		}
			
		return design($this->design["formdb"], array("url"=>"?action=savedb", "options"=>$options));	
	}
	
	private function viewSnapshots()
	{
		$path = rtrim(IMPORT_DEFAULT_DIR.self::DIR_SNAPSHOTS, "/")."/".self::TMP_SNAPSHOTS;
		$pattern = "\n".file_get_contents($path);
		
		$dir = rtrim(IMPORT_DEFAULT_DIR.self::DIR_SNAPSHOTS, "/")."/";
		$files = scandir($dir);
		$formlist = "";
		
		foreach($files as $file) 
		{
			if(is_file($dir.$file)) 
			{
				$formlist .= design($this->design["formsnapshot_files"], array("file"=>$file));		
			}
		}
		
		return design(
			$this->design["formsnapshot"],
			array(
				"urlsave"=>"?action=savesnapshot",
				"urlload"=>"?action=loadsnapshot",
				"formsnapshot_files"=>$formlist,
				"pattern"=>$pattern,
			)
		);
	}
	
	private function saveSnapshot() 
	{
		$dir = rtrim(IMPORT_DEFAULT_DIR.self::DIR_SNAPSHOTS, "/")."/";
		
		if(copy($dir.self::TMP_SNAPSHOTS, $dir.$_POST["file"])) 
		{
			return true;
		}
		
		return false;
	}
	
	public function loadSnapshot($file="") {
		$file = ($file=="") ? $_POST["file"] : $file;
		
		$dir = rtrim(IMPORT_DEFAULT_DIR.self::DIR_SNAPSHOTS, "/")."/";

		$content =  explode(");", file_get_contents($dir.$file));		
		
		foreach($content as $entry) {
		//	$entry = rtrim($entry, ");");
			$entry .= ");";
			
			if(ereg("=", $entry)) {
				eval($entry);	
				
				if($auto!="") {
					if($auto['filters']=="") {
						$this->setList($this->change(
							$this->list, 
							$auto['auto']
						));
					} else {
						$this->setList($this->change(
							$this->filter($auto['filters']), 
							$auto['auto']
						));
					}
				} else if($filters != "") {
					$this->setList($this->filter($filters));				
				} else if($db != "") {
					$this->structure_db = $db;
				}				
			}
			$auto = "";
			$filters = "";
		}		

		copy($dir.$file, $dir.self::TMP_SNAPSHOTS);
		return true;
	}
	
	private function viewNavigation() 
	{
		return design($this->design["navigation"], 
			array(
				"change" => "?action=change",
				"count"=>$this->show_limit,
				
				"next" => "?action=next",
				"previous" => "?action=previous",
			)
		);	
	}
	
	private function ffNavigation($type)
	{
		switch ($type)
		{
			case "change":
				if($_POST["count"]!="" and ereg("[0-9]", $_POST["count"])) 
				{
					$this->show_limit = $_POST["count"];
					return true;
				}
			break;
			
			case "next":
				if(count($this->list)>=($this->show_from+$this->show_limit))
				{
					$this->show_from = $this->show_from+$this->show_limit;
					return true;
				}
			break;	
			
			case "previous":
				if(($this->show_from-$this->show_limit)>=0)
				{
					$this->show_from = $this->show_from-$this->show_limit;
					return true;
				} else {
					$this->show_from = 0;
				}
			break;	
		}
		
		return false;
	}
	
	private function viewAuto()
	{
		return design($this->design["formauto"],
			array(
				"url" => "?action=auto",
				"x" => self::CHANGE_TYPE_X,
				
				/** for changes */
				"a_selid" => $this->viewSelID(self::CHANGE_TYPE_PREFIX),
				
				/** for filters */
				"selid" => $this->viewSelID(),
				
				"prefix" => self::CHANGE_TYPE_PREFIX,
				
				"int" => self::CHANGE_TYPE_INT,
				"string" => self::CHANGE_TYPE_STRING,
				
				"f_all" => self::F_ALL,
				"f_one_from" => self::F_ONE_FROM,
				
				"f_equals" => self::F_EQUALS,
				"f_dont_equals" => self::F_DONT_EQUALS,
				"f_bigger" => self::F_BIGGER,
				"f_e_bigger" => self::F_BIGGER_E, /** f_e_bigger == F_BIGGER_E, because f_bigger_e was replaced as f_bigger. F_SMALLER_E too. */
				"f_smaller" => self::F_SMALLER,
				"f_e_smaller" => self::F_SMALLER_E,
				"f_ereg" => self::F_EREG	
			)
		);
	}
	
	private function viewSelID($prefix="") 
	{
		$out = "";
		$count = $this->getCountCols();
		
		for($i=0; $i<$count; $i++) 
		{
			$out .= design($this->design["selid_opt"], array("value"=>$i));
		}
		
		return design($this->design["selid"], array("selid_opt"=>$out, "prefix"=>$prefix));
	}
	
	private function viewFilters() 
	{
		return design($this->design["formfilters"], 
			array(
				"selid"=>$this->viewSelID(),
				"url"=>"?action=filters",
				
				"f_all" => self::F_ALL,
				"f_one_from" => self::F_ONE_FROM,
				
				"f_equals" => self::F_EQUALS,
				"f_dont_equals" => self::F_DONT_EQUALS,
				"f_bigger" => self::F_BIGGER,
				"f_e_bigger" => self::F_BIGGER_E, /** f_e_bigger == F_BIGGER_E, because f_bigger_e was replaced as f_bigger. F_SMALLER_E too. */
				"f_smaller" => self::F_SMALLER,
				"f_e_smaller" => self::F_SMALLER_E,
				"f_ereg" => self::F_EREG			
			)
		);
	}
	
	private function viewImport($show_limit=0, $show_from=0, $first="") 
	{
		$id_show_limit = ($show_limit==0) ? $this->show_limit+$show_from : $show_limit+$show_from;
		$first = ($first=="") ? $this->first_row : $first; 
		
		
		$list = $this->list;
		$out = "";
		
		$trs = "";
		$th = "";
		
		foreach($list as $key=>$array) 
		{
			if($key>=$show_from) 
			{
				$rows="";
				if($first==false) $th = "";
				
				foreach($array as $akey=>$row) 
				{
					if($first == false or $key>0) 
					{
						if(strlen($row) < 30) 
						{ 
							$rows .= design($this->design["formimport_td"], array("r"=>$key, "c"=>$akey, "value"=>$row));
						} 
						else 
						{
							$rows .= design(
								$this->design["formimport_td_textarea"], 
								array(
									"r"=>$key, 
									"c"=>$akey, 
									"value"=>$row,
									"nrows"=>round(strlen($row["value"])/30)
								)
							);
						}
						
						if($first==false)
							$th .= sprintf($this->design["formimport_th"], $akey);
						
					} 
					else 
					{
						$th .= sprintf($this->design["formimport_th"], $akey." (".$row.")");
					}
				}
				
				if($first == false or $key>0) {
					$trs .= design($this->design["formimport_tr"], array("r"=>$key, "formimport_td"=>$rows));
				}
				
				if($key>=$id_show_limit)
					break;	
			}	
		}
		$out = design($this->design["formimport"], array("formimport_th"=>$th, "formimport_tr"=>$trs));
		return $out;
	}
	
	private function ffSaveDB() {
		$tmp = array();
		
		for($i=0; $_POST["table_".$i]!=""; $i++)
		{
			list($table, $row) = explode(".", $_POST["table_".$i]);
			$what = trim($_POST["how_".$i]);
			
			if(ereg("[\"'](.*)[\"']", $what)) {
				$how = "string";
				
				$what = trim($what, "\"");
				$what = trim($what, "\'");
			} else if(ereg("[\({](.*)[\)}]", $what)) {
				$how = "key";
				
				$what = trim($what, "(");
				$what = trim($what, ")");
				
				$what = trim($what, "{");
				$what = trim($what, "}");
				
				$tmp["@keys"][$i] = array($table, count($tmp[$table]));
			} else {
				$how = "integer";
			}
			
			$tmp[$table][] = array(
				"row"=>$row,
				"what"=>$what,
				"how"=>$how,	
				"where"=>(($_POST["where_".$i]=="true") ? true : false),		
			);
		}
		
		$this->structure_db = $tmp;
		if($this->saveToSnaphot("db", $tmp))
			return true;
			
		return false;
	}
	
	private function ffAuto() {
		$list = $this->getList();
		$auto = array();
		$prefix = self::CHANGE_TYPE_PREFIX;
		
		if($_POST["activefilters"]=="true") 
		{
			list($flist, $ex) = $this->ffFilters(true);
			
			if(count($flist)>0)
			{
				$list = $flist;
			}
		}

		for($i=0; $_POST[$prefix."id_".$i]!=""; $i++)
		{
			$auto[$_POST[$prefix."id_".$i]] = array(
				"type"=>$_POST[$prefix."type_".$i], 
				"exp"=>$_POST[$prefix."exp_".$i]
			);
		}

		$this->saveToSnaphot("auto", array("auto"=>$auto, "filters"=>$ex)); 		
		
		if(is_array($auto))
		{
			return $this->change(
				$list, 
				$auto
			);
		}
		
		return $this->getList();
	}
	
	private function ffFilters($auto=false) 
	{
		$filt = array();
	//	$from = ($this->first_row==false) ? 0 : 1;
		
		for($i=0; $_POST["id_".$i]!=""; $i++)
		{
			$filt[] = array("what"=>$_POST["id_".$i], "cause"=>$_POST["cause_".$i], "exp"=>$_POST["exp_".$i]);
		}
		
		if($auto==false) 
		{
			$this->saveToSnaphot("filters", array("type"=>$_POST["type"], "filters"=>$filt));	
		}
		
		if(count($filt)>0) 
		{
			$filter = array(0=>$_POST["type"], 1=>$filt);
						
			$list = $this->filter($filter);		
			
			if(count($list)>0) 
			{
				return array($list, array("type"=>$_POST["type"], "filters"=>$filt));	
			}
		}
			
		return array($this->list, array("type"=>$_POST["type"], "filters"=>$filt));
	}
	
	private function ffLoad()
	{
		$file = $_FILES["file"];

		if($_FILES["file"]["error"]==0 and $_FILES["file"]!="") 
		{
			$this->setPath("");
			$this->setFile($file[tmp_name]);
			$this->setType($file[type]);
			
			$this->load();
			return true;
		}
		
		if($_POST["url"]!="") {	
			copy($_POST["url"],IMPORT_DEFAULT_DIR.self::TMP_FILENAME_REMOTE);
		
			$this->setPath("./");
			$this->setFile(IMPORT_DEFAULT_DIR.self::TMP_FILENAME_REMOTE);
			$this->setType($_POST["typ"]);
			
		//	echo $this->load();
		//	$this->setType($file[type]);	
		}	
		
		return false;
	}
	
	public function run() 
	{
		global $db;
		
		$const_spec = "&";
		
		$sql = array();
		
		$list = $this->getList();
		if($this->first_row==true)
			unset($list[0]);
		
		$count = 0;
		$structure = $this->structure_db;

		$keys = $structure["@keys"];
		
		unset($structure["@keys"]);
		
		$queries = array();
		
		foreach($structure as $name=>$table) 
		{
			$prikazy = & $queries[$name];
			
			foreach($table as $query) {
				$row = $query["row"];
				$what = $query["what"];
				$how = $query["how"];
				$where = $query["where"];
				
				$spec = "";
								
				switch ($how) {
					case "integer":
						$hodnota = "%".str_pad($what, self::COUNT_CHARS, '0', STR_PAD_LEFT);
					break;
					
					case "key":
						$prikazy["key"] = true;
						$hodnota = (($what!="") ? "&".str_pad($what, self::COUNT_CHARS, '0', STR_PAD_LEFT) : "");
					break;
					
					case "string":
						$hodnota = $what;			
					break;				
				}
				
				if($where == true) {
					$prikazy["exists"] .= $row."='".$hodnota."' and";
				} else {
					$prikazy["update"] .= $row."='".$hodnota."', ";
				}
				
				$ir = (($what!="") ? $spec.$row.", " : "");				
				$prikazy["insert"]["rows"] .= (($what!="") ? $row.", " : "");
				$prikazy["insert"]["values"] .= (($ir=="") ? "" : "'".$hodnota."', ");
				
			}
			
			$prikazy["insert"]["rows"] = trim($prikazy["insert"]["rows"], ", ");
			$prikazy["insert"]["values"] = trim($prikazy["insert"]["values"], ", ");
			$prikazy["update"] = trim($prikazy["update"], ", ");
			$prikazy["exists"] = trim($prikazy["exists"], " and");
		}
		
		foreach($list as $row) {	
			$tmp_keys = array();
					
			foreach($queries as $name=>$table) {
				$pocet = 0;
				
				//echo $table["update"];
				
				$insert_rows = $table["insert"]["rows"];
				$insert_values = design($table["insert"]["values"], $row, "0", self::COUNT_CHARS);
				
				$update = design($table["update"], $row, "0", self::COUNT_CHARS);
				$exists = design($table["exists"], $row, "0", self::COUNT_CHARS);
				
				$insert_values = design($insert_values, $tmp_keys, "0", self::COUNT_CHARS, "&");
				
				$update = design($update, $tmp_keys, "0", self::COUNT_CHARS, "&");
				$exists = design($exists, $tmp_keys, "0", self::COUNT_CHARS, "&");
				
				if($exists!="") 
				{
					list($pocet) = $db->fetch_array("select count(*) as pocet from ".$name." where ".$exists);
				}
				
				if($pocet>0) {
					$db->query("update ".$name." set ".$update." where ".$exists);
			//		echo "update ".$name." set ".$update." where ".$exists;
				} else {
					$db->query("insert into ".$name." (".$insert_rows.") values (".$insert_values.")");
			//		echo "insert into ".$name." (".$insert_rows.") values (".$insert_values.")<br>";
				}
				
				if($table["key"]==true) {
					foreach($keys as $klic=>$key) {
						if($key[0]==$name) {
							list($id, ) = $db->fetch_array("SELECT LAST_INSERT_ID()");
							$tmp_keys[$klic] = $id;
							break;
						}
					}
				}
				
			}
		}
	
	}

	
	private function saveToSnaphot($var, $content)
	{
		$path = rtrim(IMPORT_DEFAULT_DIR.self::DIR_SNAPSHOTS, "/")."/".self::TMP_SNAPSHOTS;
		$file = fopen($path, "a");
		
		$tosave = "$".trim($var, "$")." = ".return_var($content)."\n"."\n";
		if(fwrite($file, $tosave)) 
		{
			fclose($file);
			return true;
		}
		
		fclose($file);
		return false;		
	}
}
?>
