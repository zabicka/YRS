<?php

define('PAGE_INDEX', 'index');
define('PAGE_ERROR404', 'error404');
define('PAGE_ERROR403', 'error403');

abstract class Page {
	static $design;

	public function __construct() {
		global $vzhled;

		if ($vzhled) {
			Template::$title[] = Lang::view('page;title;Pages');

			self::$design = $vzhled->design('page');
			return true;
		}

		return false;
	}

	public function view() {
		Page::__construct();
		list($a,$b,$c,$d) = func_get_args();
		return PageView::view($a,$b,$c,$d);
	}
}

class PageCategories extends Page {

	public function categoryExists($url) {
		$page = PageView::load($url);

		$sql = DB::query('select count(ID) as ex from __page_pages where category="'.$page['name'].'"')->fetchSingle();

		return ($sql[ex]) ? true : false;
	}

	protected function loadPages($category) {
		$ret = array();

		$query = DB::query("select name, url, description, DATE_FORMAT(date, '%d.%m.%Y') as datetime
 from __page_pages where category='".$category."' and lang='".$_COOKIE['lang']."' order by date desc, name");

		foreach($query->getIterator() as $page) {
			$ret[] = $page;
		}

		return $ret;
	}

	public function viewList($category) {
		$out = "";
		$list = PageCategories::loadPages($category);

		foreach($list as $page) {
			$to = 'page';
			if(PageCategories::categoryExists($page['url'])) {
				$to = 'categories';
			} else if(ereg(Gallery::PAGE_PREFIX, $page['url'])) {
				$to = 'gallery';
				$page['url'] = str_replace(Gallery::PAGE_PREFIX, '', $page['url']);
			}
			$page['url'] = URL::create($to, $page['url']);

			$out .= design(parent::$design['category_list_pages'], $page);
		}
		return $out;
	}

	public function view() {
		$out = '';
		list($url, $from, $howmany) = func_get_args();

		$page = PageView::load($url);

		Template::$title[] = $page['name'];

		if($page) {
			$out .= PageView::render($page);
			$out .= PageCategories::viewList($page['name']);
		} else {
			$out = PageView::view(PAGE_ERROR404);
		}

		return $out;
	}
}

class PageAdmin extends Page  {
	static $design;

	public function edit($url) {
		list($page) = DB::query('select * from __page_pages where url="'.$url.'" and lang="'.$_COOKIE['lang'].'" limit 1')->fetchAll();

		return design(
			parent::$design['edit'],
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
		$ret = '';

		$load = DB::query('select category from __page_pages group by category');

		foreach($load->getIterator() as $row) {
			$category = $row['category'];
			if($category) {
				$ret .= sprintf(parent::$design['licategory'], $category, $category);
			}
		}

		return $ret;
	}

	public function delete($url) {
		$page = PageView::load($url);

		if($page) {
			if($_POST['delete']) {
				DB::query('delete from __page_pages where url=%s', $url);
			} else {
				return design(parent::$design['potvrzenismazani'], $page);
			}
		}

		header("HTTP/1.1 301 Moved Permanently");
		header("Location: ".URL::create('admin','page'));
		header("Connection: close");
	}

	public function save($url) {
		$ret = '';

		PageAdmin::newpage();

		if($_POST["name"]=='') return design(parent::$design['neulozeno'], array('page'=>$url));


		if(ereg('([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})', $_POST["date"])) {
			$date = $_POST["date"];
		} else {
			$date = date('Y-m-d G:i:s');
		}

		$query = DB::query('update __page_pages set name=%s, description=%s, content=%s, category=%s, date=%t where url=%s and lang=%s', $_POST["name"], $_POST["description"], $_POST["content"], $_POST["category"], $date, $url, $_COOKIE['lang']);

		$ret .= design(parent::$design['ulozeno'], array('page'=>$url));

		$ret .= PageAdmin::edit($url);

		return $ret;
	}

	public function newpage() {
		$name = $_POST['url'];
		$url = goodurl($_POST['url']);

		list($load) = DB::query('select count(ID) from __page_pages where url=%s and lang=%s', $url, $_COOKIE['lang'])->fetchSingle();

		if(!$load) {
			DB::query('insert into __page_pages (name, url, lang, date) values ("'.$name.'", "'.$url.'", "'.$_COOKIE['lang'].'", NOW())');

		}

		return PageAdmin::edit($url);
	}

	public function view() {
		// pridani formulare pro novou stranku
		$ret .= design(parent::$design["cnewpage"], array("hlaska"=>$zobraz));

		// nacteni seznamu stranek
		$ret .= $this->listPages();
		return $ret;
	}

	private function listPages() {
		$ret = '';
		$category = '';

		// nacteni vsech stranek a serazeni podle ID
		$load = DB::query('select * from __page_pages where lang="'.$_COOKIE['lang'].'" order by category');

		// prochazeni strankami
		$x = 0;
		foreach($load->getIterator() as $page) {
			if($category != $page['category']) {
				$ret .= design(parent::$design['list_category'], array('category'=>$page['category']));
				$category = $page['category'];
			}

			// pridani do tabulky
			$ret .= design(
				parent::$design['listpagesrow'],
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
		$ret = sprintf(parent::$design['listpagestable'], $ret);

		// vraceni tabulky se seznamem
		return $ret;
	}
}

/**
 * User handler for images
 *
 * @param TexyHandlerInvocation  handler invocation
 * @param TexyImage
 * @param TexyLink
 * @return TexyHtml|string|FALSE
 */
function imageHandler($invocation, $image, $link)
{
	$parts = explode(':', $image->URL);
	if (count($parts) !== 2) return $invocation->proceed();

	switch ($parts[0]) {
	case 'youtube':
		$video = htmlSpecialChars($parts[1]);
		$dimensions = 'width="'.($image->width ? $image->width : 425).'" height="'.($image->height ? $image->height : 350).'"';
		$code = '<div><object '.$dimensions.'>'
			. '<param name="movie" value="http://www.youtube.com/v/'.$video.'" /><param name="wmode" value="transparent" />'
			. '<embed src="http://www.youtube.com/v/'.$video.'" type="application/x-shockwave-flash" wmode="transparent" '.$dimensions.' /></object></div>';

		$texy = $invocation->getTexy();
		return $texy->protect($code, Texy::CONTENT_BLOCK);
	}

	return $invocation->proceed();
}

class PageView extends Page  {
	static $design;

	public function formateText($text) {
		if(class_exists('Texy')) {
			$texy = new Texy;
			$texy->addHandler('image', 'imageHandler');
			return $texy->process($text);
		} else {
			return $text;
		}
	}

	public function load($url) {
		list($load) = DB::query("select * from __page_pages where url=%s and lang=%s limit 1", $url, $_COOKIE['lang'])->fetchAll();

		return $load;
	}

	public function render($data) {
		return design(
			parent::$design['stranka'],
			array(
				'h1' => $data['name'],
				'description' => PageView::formateText($data['description']),
				'url' => $data['url'],
				'content' =>  PageView::formateText($data['content'])
			)
		);
	}

	public function view() {
		$args = func_get_args();
		$url = Dictionary::modul($args[0], 'page');

		if($url) {
			if(PageCategories::categoryExists($url)) {
				header("HTTP/1.1 301 Moved Permanently");
				header("Location: ".URL::create('categories', $url));
				header("Connection: close");
			}

			if(ereg(Gallery::PAGE_PREFIX, $url)) {
				header("HTTP/1.1 301 Moved Permanently");
				header("Location: ".URL::create('gallery', str_replace(Gallery::PAGE_PREFIX, '', $url)));
				header("Connection: close");
			}

			$page = PageView::load($url);

			if(!$page) {
				$page = PageView::load(PAGE_ERROR404);
			}

		} else {
			$page = PageView::load(PAGE_INDEX);
		}

		Template::$title[] = $page['name'];
		return PageView::render($page);
	}

}
