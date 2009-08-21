<?php

define('PAGE_INDEX', 'index');
define('PAGE_ERROR404', 'error404');
define('PAGE_ERROR403', 'error403');

class PageAdmin {
	static $design;

	public function __construct() {
		global $vzhled;

		if ($vzhled) {
			self::$design = $vzhled->design('page');
			return true;
		}

		return false;
	}

	public function edit($url) {
		global $db;

		$page = $db->fetch_array('select * from __page_pages where url="'.$url.'" and lang="'.$_COOKIE['lang'].'"');

		return design(
			self::$design['edit'],
			array(
				'url' => $url,
				'id' => $page['ID'],
				'date' => $page['date'],
				'name' => $page['name'],
				'description' => $page['description'],
				'content' => $page['content'],
				'category' => $page['category'],
				'categories' => self::listCategories(),
			)
		);
	}

	private function listCategories() {
		global $db;

		$ret = '';
		$load = $db->query('select category from __page_pages group by category');

		while(list($category) = $db->fetch_array($load)) {
			if($category) {
				$ret .= sprintf(self::$design['licategory'], $category, $category);
			}
		}

		return $ret;
	}

	public function save($url) {
		global $db;

		$ret = '';

		$name = mysql_real_escape_string($_POST["name"]);
		$description = mysql_real_escape_string($_POST["description"]);
		$content = mysql_real_escape_string($_POST["content"]);
		$category = mysql_real_escape_string($_POST["category"]);

		if(ereg('([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})', $_POST["date"])) {
			$date = $_POST["date"];
		}

		if($db->query('update __page_pages set name="'.$name.'", description="'.$description.'", content="'.$content.'", category="'.$category.'", date="'.$date.'" where url="'.$url.'" and lang="'.$_COOKIE['lang'].'"')) {
			$ret .= design(self::$design['ulozeno'], array('page'=>$url));
		} else {
			$ret .= design(self::$design['neulozeno'], array('page'=>$url));
		}

		$ret .= PageAdmin::edit($url);

		return $ret;
	}

	public function newpage() {
		global $db;

		$name = $_POST['url'];
		$url = goodurl($_POST['url']);

		list($load) = $db->fetch_array('select count(ID) from __page_pages where url="'.$url.'" and lang="'.$_COOKIE['lang'].'"');

		if(!$load) {
			$db->query('insert into __page_pages (name, url, lang, date) values ("'.$name.'", "'.$url.'", "'.$_COOKIE['lang'].'", NOW())');

		}

		return PageAdmin::edit($url);
	}

	public function view() {
		// pridani formulare pro novou stranku
		$ret .= design(self::$design["cnewpage"], array("hlaska"=>$zobraz));

		// nacteni seznamu stranek
		$ret .= $this->listPages();
		return $ret;
	}

	private function listPages() {
		global $db;

		$ret = '';
		$category = '';

		// nacteni vsech stranek a serazeni podle ID
		$load = $db->query('select * from __page_pages where lang="'.$_COOKIE['lang'].'" order by category');

		// prochazeni strankami
		$x = 0;
		while($page = $db->fetch_array($load)) {
			if($category != $page['category']) {
				$ret .= design(self::$design['list_category'], array('category'=>$page['category']));
				$category = $page['category'];
			}

			// pridani do tabulky
			$ret .= design(
				self::$design['listpagesrow'],
				array(
					'id' => $page['ID'],
					'styl' => ($x%2) ? '' : 'even',
					'name' => $page['name'],
					'url' => $page['url'],
					'lang' => $page['lang'],
					'uprava' => URL::create('admin', 'page', 'edit', $page['url']),
					'smaz' => URL::create('admin', 'page', 'delete', $page['url'])
				)
			);

			$x++;
		}

		// zkompletovani tabulky
		$ret = sprintf(self::$design['listpagestable'], $ret);

		// vraceni tabulky se seznamem
		return $ret;
	}
}

class Page {
	static $design;

	public function __construct() {
		global $vzhled;

		if ($vzhled) {
			self::$design = $vzhled->design('page');
			return true;
		}

		return false;
	}

	public function formateText($text) {
		if(class_exists('Texy')) {
			$texy = new Texy;
			return $texy->process($text);
		} else {
			return $text;
		}
	}

	public function load($url) {
		global $db;

		$load = $db->fetch_array('select * from __page_pages where url="'.$url.'" and lang="'.$_COOKIE['lang'].'" limit 1');
		return $load;
	}

	public function render($data) {
		return design(
			self::$design['stranka'],
			array(
				'h1' => $data['name'],
				'description' => Page::formateText($data['description']),
				'url' => $data['url'],
				'content' =>  Page::formateText($data['content'])
			)
		);
	}

	public function view() {
		$args = func_get_args();
		$url = sslovnik($args[0], 'page');

		if($url) {
			$page = Page::load($url);

			if(!$page) {
				$page = Page::load(PAGE_ERROR404);
			}

		} else {
			$page = Page::load(PAGE_INDEX);
		}

		return Page::render($page);
	}

}
