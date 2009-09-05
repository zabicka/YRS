<?php
class Comments {
	/** promenna pro styly */
	static $design;

	static $allowed_tags = array("b", "i", "a");

	static $id = 0;

	static $exists = 0;

	static $parametr = NULL;

	/** ID defaultni skupiny */
	const GROUP_DEFAULT = 'default';

	/**
	 * urcuje jakym zpusobem se maji zobrazovat komentare.
	 * Zatim pouze:
	 * 		structure:
	 * 			1 ---
	 * 				|--- 2
	 * 				|--- 3
	 *
	 * 			4 ---
	 * 				|--- 5
	 * 				|	|--- 6
	 * 				|--- 7
	 *
	 * @todo Jeste by se dal udelat styl a la {@link http://phpfashion.com/}, pod sebou a pouze odkazovat
	 *
	 */
	const TYPE_SHOW_COMMENTS = "structure";

	/**
	 * urcuje, zda se pri zobrazovani komentaru od registrovanych uzivatelu maji zobrazovat aktualni udaje,
	 * nebo udaje pri ukladani.
	 */
	const ALWAYS_UPDATE_USERS = true;

	/**
	 * do jake urovne lze na komentar reagovat (vytvaret podkomentar)
	 */
	static $max_level = 5;

	/**
	 * maximalni delka titulku komentare.
	 */
	const SUBJECT_MAX_LENGHT = 255;

	/** cim vyssi cislo, tim prisnejsi kontrola mailu
	 * 0 - nekontroluje, nedoporucuji
	 * 1 - pouha kontrola syntaxe {@link check_email() }
	 * 2 - kontrola zda mail reaguje {@link try_email() }, nemusi fungovat vzdy (zda se, ze nefunguje seznam.cz)
	 *
	 * http://php.vrana.cz/kontrola-e-mailove-adresy.php
	 */
	const MAIL_LEVEL = 1;

	/**
	 * z jake adresy se ma overovat.
	 *
	 * {@link Comments::MAIL_LEVEL}
	 * {@link try_email() }
	 */
	const MAIL_FROM = "authentication@yrs.org";

	/**
	 * funguje pro zvyrazneni barvy vybranym uzivatelum - napriklad administratorum.
	 * Tvar:
	 * <code>
	 * $bygroups = array(
	 * 		ID_of_COLOR => ID_of_GROUP,
	 * );
	 * </code>
	 *
	 * @todo Je to hotovy? Nikde to nevidim aplikovane...
	 */
	static $bygroups = array('' =>'');

	public function __construct() {
		global $vzhled;

		if($vzhled!="") {
			self::$design = $vzhled->design("comments");
		}

		return true;
	}


	protected function textToTexy($text) {
		// zkusime Texy?
		$texy = new Texy;

		TexyConfigurator::safeMode($texy);

		/** zakazani vseho, co by mohlo delat neplechu */
		$texy->allowed['heading/underlined'] = false;
		$texy->allowed['heading/surrounded'] = false;
		$texy->allowed['table'] = false;
		$texy->allowed['image'] = false;
		$texy->allowed['html/tag'] = false;
		$texy->allowed['script'] = false;

		$text = TexyCenzureModule::render($text);

		return $texy->process($text);
	}

	/**
	 * funkce, ktera kontroluje spravnost emailu. Prisnost nastavena v {@link Comments::MAIL_LEVEL}
	 *
	 * {@source}
	 *
	 * @param string $mail kontrolovany e-mail
	 *
	 * @return boolean
	 */
	protected function checkMail($mail) {
		// 1 - kontrola pouze podle syntaxe
		if(self::MAIL_LEVEL==1 and (check_email($mail)==false)) {
			return false;
		}

		// 2 - kontrola, zda mail reaguje
		if(self::MAIL_LEVEL==2 and ($test=try_email($mail, self::MAIL_FROM))!=true) {
			// pokud nereaguje
			if($test==false) {
				return false;
			// pokud se nepodarilo zkontrolovat, kontroluje se pouze syntaxe
			} else if(!check_email($mail)) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Funkce pro pridani noveho komentare.
	 *
	 * {@source}
	 *
	 * @param string | array $user Bud ID uzivatele nebo pole ve tvaru array('name', 'mail', 'web')
	 * @param string $subject Titulek komentare
	 * @param string $text Obsah komentare
	 * @param mixed $group ID skupiny komentaru
	 * @param integer $parent ID rodicovskeho komentare (pokud je komentar pridavan jako odpoved)
	 *
	 * @return true or integer
	 *
	 * 2 - prazdne jmeno
	 * 3 - chybny email
	 * 4 - nepodarilo se ulozit
	 * 5 - duplicitni komentar
	 * 6 - prazdny text
	 *
	 */
	protected function addComment($user, $subject, $text, $group,$parent=0) {
		// prevod ip na cislo
		$ip = ip2long(getIP());

		// zkraceni titulku
		$subject = substr(htmlspecialchars($subject), 0, self::SUBJECT_MAX_LENGHT);

		// kontrola zda text neni prazdny
		if($text) {
			// pokud je dodano ID uzivatele
			if(is_numeric($user)) {
				// ziskani informaci uzivatele
				list($fname, $lname, $mail, $web) = Admin::getUserInfo(
					$user,
					array(
						ADMIN_INFO_FIRST_NAME,
						ADMIN_INFO_LAST_NAME,
						ADMIN_INFO_EMAIL,
						ADMIN_INFO_WEB
					)
				);

				// vytvoreni jmena
				$name = $fname." ".$lname;

				// definovani id uzivatele
				$id = $user;

			// pokud jsou dodany informace o autorovi
			} else if(is_array($user)) {
				// rozdeni do promennych
				list($name, $mail, $web) = $user;

				$name = htmlspecialchars($name);
				$mail = htmlspecialchars($mail);
				$web = htmlspecialchars($web);

				// nastaveni id
				$id = NULL;
			}

			// kontrola zda jmeno neni prazdne
			if($name=="") {
				return 2;
			}

			// kontrola emailu
			if($this->checkMail($mail)!==true) {
				return 3;
			}

			$attrs = array(
				'id_group' => $group,
				'id_user' => $id,
				'name' => $name,
				'mail' => $mail,
				'web' => $web,
				'ip' => $ip,
				'subject' => $subject,
				'text' => $text,
				'date' => date('Y-m-d G:i:s'),
				'parent' => $parent,
			);

			// zjisteni duplicity
			$pocet = (int) DB::select('count(*)')
							->from('__comments_list')
							->where('id_group = %s', $group)
							->and('ip = %i', $ip)
							->and('text = %s', $text)
							->execute()->fetchSingle();

			if($pocet==0) {
				// vlozeni komentare
				DB::query('insert into __comments_list', $attrs);
				return true;
			} else {
				return 5;
			}
		}

		return 6;
	}

	/**
	 * zjisteni, jake urovne je dany komentar
	 *
	 * {@source}
	 *
	 * @param integer $id id komentare
	 * @param integer $level vychozi uroven (pouzito pri rekurzivnim volani funkce)
	 *
	 * @return integer uroven komentare
	 */
	public function getLevel($id, $level=0) {
		// nacteni id rodice
		$parent = DB::query("select parent from __comments_list where ID=%i", $id)->fetchSingle();

		// pokud rodic neni nulovy
		if($parent!=0) {
			// zjisteni levelu rodice
			$level = self::getLevel($parent, $level+1);
		}

		return $level;
	}
}



class CommentsAdmin extends Comments {

	public function getall() {
		$ret = array();
		$comments = DB::query('select * from __comments_list order by date')->fetchAll();

		foreach($comments as $comment) {
			$ret[$comment->id_group][] = $comment;
		}

		return $ret;
	}

	public function htmlGroup($name, $data) {
		$out= '';
		foreach($data as $comment) {
			$comment['ip'] = long2ip($comment['ip']);
			$comment['reply'] = ($comment['parent']==0) ? '' : 'YES';

			$out .= design(parent::$design['admin_comment'], $comment);
		}

		return design(parent::$design['admin_group'], array('name'=>$name, 'comments'=>$out));
	}

	public function delete($id) {
		DB::query('delete from __comments_list where ID=%i', $id);
		DB::query('delete from __comments_list where parent=%i', $id);

		return self::view();
	}

	public function view() {
		$out = '';
		$groups = '';

		foreach(self::getall() as $name=>$group) {
			$groups .= design(parent::$design['admin_list_group'], array('name'=>$name));
			$out .= self::htmlGroup($name, $group);
		}

		$groups = design(parent::$design['admin_list_groups'], array('groups'=>$groups));

		return design(parent::$design['admin'], array('comments'=>$out, 'groups'=>$groups));
	}
}




class CommentsView extends Comments {
	/**
	 * ziskava seznam komentaru pro danou skupinu. Vraci strukturu s podkomentari
	 *
	 * <code>
	 * return array (
	 * 	1 =>
	 * 		array (
	 * 			'own' => array (),
	 *
	 * 			2 => array (
	 * 				'own' => array (),
	 *			),
	 *
	 * 			3 => array (
	 * 				'own' => array (),
	 *			),
	 *
	 * 			4 => array (
	 * 				'own' => array (),
	 * 				5 => array (
	 * 					'own' => array (),
	 * 					),
	 * 			),
	 *
	 * 			6 => array (
	 * 				'own' => array (),
	 * 			),
	 * 		),
	 * 	7 =>
	 * 		array (
	 * 			'own' => array(),
	 * 			8 => array (
	 * 				'own' => array (),
	 * 				9 => array (
	 * 					'own' => array (),
	 * 					10 => array (
	 * 						'own' => array (),
	 * 					),
	 * 				),
	 * 			),
	 * 		),
	 * 	);
	 * </code>
	 *
	 * @param integer $group id skupiny komentaru
	 *
	 * @return array
	 *
	 */
	private function getComments($group) {
		// pole pro vraceni, uklada se se strukturou
		$comments = array();
		// pole, ktere de facto pouze ukazuje na jednotlive komentare v $comments
		$wos = array();

		// nacteni polozek z db
		$list = DB::query("select * from __comments_list where id_group=%s", $group);

		// prochazeni ziskanymi polozkami
		foreach($list->getIterator() as $c) {
			$c['text'] = self::textToTexy($c['text']);
			if(self::ALWAYS_UPDATE_USERS==true and $c['id_user']!=0) {

				list($fname, $lname, $c['mail'], $c['web'], $c['signature']) = Admin::getUserInfo(
					$c['id_user'],
					array(
						ADMIN_INFO_FIRST_NAME,
						ADMIN_INFO_LAST_NAME,
						ADMIN_INFO_EMAIL,
						ADMIN_INFO_WEB,
						ADMIN_INFO_SIGNATURE
					)
				);

				// vytvoreni jmena
				$c['name'] = $fname." ".$lname;

				// pridani podpisu
				$c['text'] .= design(parent::$design["signature"], array("text"=>$c['signature']));
			}


			// pokud je uroven 0
			if($c['parent']==0) {
				// pridani do vysledneho pole
				$comments[$c['ID']] = array('own'=>$c);

				// vytvoreni ukazatele
				$wos[$c['ID']] = &$comments[$c['ID']];
			} else {
				// pridani radku do rodicovskeho pole (diky tomu, ze $wos je ukazatel na $comments se pridani promitne i ve vysledku)
				$wos[$c['parent']]['children'][$c['ID']] = array('own'=>$c);

				// vytvoreni noveho ukazatele na komentar
				$wos[$c['ID']] = &$wos[$c['parent']]['children'][$c['ID']];
			}
		}

		//debug_var($comments);
		//ksort($comments);
		krsort($comments);

		// vraceni vysledneho pole
		return $comments;
	}

	private function htmlStructureComments($comment, $count=0) {
		$out = "";
		$children = "";

		$comment["own"]["next"] = "";
		$comment["own"]["lext"] = "";
		$comment["own"]["logged"] = ($comment["own"]["id_user"]>0) ? "reg" : "noreg";
		$comment["own"]["count"] = $count;
		$comment["own"]["photo"] = getGravatarLink($comment["own"]["mail"], 50);

		if(Dictionary::modul($_GET['class'])=='comments') {
			$comment["own"]["urlreply"] = URL::create(
				"comments",
				"r".$comment["own"]["ID"],
				Dictionary::translation($_GET["class"])
			)."#newcomment";
		} else {
			$comment["own"]["urlreply"] = URL::create(
				"comments",
				"r".$comment["own"]["ID"] ,
				Dictionary::translation($_GET["class"]),
				$_GET["akce"],
				$_GET["parametr1"]
			)."#newcomment";
		}

		if(is_array($comment["children"])) {
			foreach($comment["children"] as $key=>$kind) {
				list($tmpch, $count) = $this->htmlStructureComments($kind, $count+1);

				$children .= $tmpch;
			}

			if(self::$max_level>=(self::getLevel($comment["own"]["ID"])+1)) {
				$comment["own"]["next"] = $children;
			} else {
				$comment["own"]["lext"] = $children;
			}

		}

		$out .= design(parent::$design["comment"], $comment["own"]);

		return array($out, $count);
	}

	/**
	 * funkce zobrazujici vypis komentaru. Da se omezit pocet.
	 */
	public function comments($group, $from=0, $to=NULL) {
		$out = '';
		$comments = self::getComments($group);

		$to = ($to==NULL) ? NULL : $from+$to;

		$i = 0;
		$ai = 0;

		foreach($comments as $key=>$comment) {
			if(self::TYPE_SHOW_COMMENTS=="structure") {
				list($tmp, $ai) = $this->htmlStructureComments($comment, $ai+1);
			}

			if($i>=$from and ($i<=$to or $to==NULL)) {
				$out .= $tmp;
			}
			$i = $i+$ai;
		}

		return $out;
	}


	public function newComment($group, $parent=0) {
		if(ereg("r(.*)", self::$parametr)) {
			$parent = (int) substr(self::$parametr, 1);
		}

		if(Admin::isLogged()==true) {
			$user = Admin::isLogged(true);

			list($fname, $lname, $mail, $web) = Admin::getUserInfo(
				$user["ID"],
				array(
					ADMIN_INFO_FIRST_NAME,
					ADMIN_INFO_LAST_NAME,
					ADMIN_INFO_EMAIL,
					ADMIN_INFO_WEB
				)
			);

			$values["name"] = $fname." ".$lname;
			$values["mail"] = $mail;
			$values["web"] = $web;
		}

		if($_POST["group"]==$group and Admin::isLogged()==false) {
			$values["name"] = $_POST["name"];
			$values["mail"] = $_POST["mail"];
			$values["web"] = $_POST["web"];

			setcookie("comment_name", $values["name"], time()+60*60*24, "/");
			setcookie("comment_mail", $values["mail"], time()+60*60*24, "/");
			setcookie("comment_web", $values["web"], time()+60*60*24, "/");
		}

		if($_POST["group"]==$group) {
			$values["subject"] = $_POST["subject"];
			$values["text"] = $_POST["text"];

			$parent = $_POST["parent"];

			if(count($error)>0) {
				$error = implode("</p><p>", $error);
			} else {

				if($user["ID"]=="") {
					$error = $this->addComment(
						array(
							$values["name"],
							$values["mail"],
							$values["web"]
						),
						$values["subject"],
						$values["text"],
						$group,
						$parent
					);
				} else {
					$error = $this->addComment(
						$user["ID"],
						$values["subject"],
						$values["text"],
						$group,
						$parent
					);
				}

				if($error===true) {
					$error = sprintf(parent::$design["insert_ok"], Lang::view("COMMENTS;newok;Komentar pridan"));
					$values["subject"] = NULL;
					$values["text"] = NULL;
					$parent = 0;
				} else {
					$error = sprintf(parent::$design["insert_ko"], Lang::view("COMMENTS;chins".$error.";Komentar se nepodarilo ulozit"));
				}
			}
		}

		$values["name"] = ($values["name"]=="") ? $_COOKIE["comment_name"] : $values["name"];
		$values["mail"] = ($values["mail"]=="") ? $_COOKIE["comment_mail"] : $values["mail"];
		$values["web"] = ($values["web"]=="") ? $_COOKIE["comment_web"] : $values["web"];

		$reakce = ($parent!=0) ? "(".sprintf(Lang::view("COMMENTS;reply;#%s"),$parent).")" : "";

		return design(
			parent::$design["new_comment"],
			array(
				"logged"=>((Admin::isLogged()!=false) ? "" : "style='display: none;'"),
				"id"=>((Admin::isLogged()!=false) ? $user["ID"] : ""),
				"disabled"=>((Admin::isLogged()!=false) ? "disabled" : ""),

				"reakce"=>$reakce,
				"error"=>$error,
				"group"=>$group,
				"parent"=>$parent,
				"name"=>$values["name"],
				"mail"=>$values["mail"],
				"web"=>$values["web"],
				"subject"=>$values["subject"],
				"text"=>$values["text"],
			)
		);
	}

	public function view() {
		$args = func_get_args();

		if($args[0] and $args[2]) {
			if(!self::$parametr) {
				self::$parametr		=	$args[0];

				$_GET["class"]		=	Dictionary::modul($args[1]);
				$_GET["akce"]		=	Dictionary::modul($args[2], false, $args[1]);
				$_GET["parametr1"]	=	$args[3];
				$_GET["parametr2"]	=	$args[4];
			}

			$_GET['class'] = (!$_GET['class']) ? 'comments' : $_GET['class'];

			$ni = Init::construct();

			return $ni->getClass();
		} else {
			if($args[0] and !parent::$parametr) {
				parent::$parametr = $args[0];
			}

			# zobrazeni defaultni stranky
			$out = '';

			$out .= self::newComment(parent::GROUP_DEFAULT);
			$out .= self::comments(parent::GROUP_DEFAULT);

			return $out;
		}
	}
}
?>


