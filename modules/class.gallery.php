<?php
class Thumb extends Data {
	static $koncovky = Array('png', 'jpg', 'jpeg');
	const FILE_NOTALLOWED = 'soubor.png';

	//$path = PathInfo($_GET[src]);
	public function view($src, $width=0, $height=0) {

		$this->setPath('thumbs');
		$path = PathInfo($src);

		if(!in_array(strtolower($path['extension']), self::$koncovky)) {
			self::notAllowed();
		} else {
			if(!$this->getFilePath(md5($src.$width.$height))) {
				$size = getimagesize($src);

				// size[0] = width
				// size[1] = height

				if($size[0]>$width or $size[1]>$height) {
					if($width!=0) {
						$prop[0] = $width;
						$prop[1] = $width * ($size[1]/$size[0]);
					}

					if($height!=0 and ($prop[1]>$height or $prop[1]==NULL)) {
						$prop[1] = $height;
						$prop[0] = $height * ($size[0]/$size[1]);
					}
				} else {
					$prop[0] = $size[0];
					$prop[1] = $size[1];
				}

				$posun = array();

				if($prop[0]!=$width) {
					$posun[0] = ($width - $prop[0])/2;
				//	echo $posun[0];exit;
				}

				if($prop[1]!=$height and $height!=0) {
					$posun[1] = ($height - $prop[1])/2;
					$posun[1] = ($posun[1]>0) ? $posun[1] : 0;
				}

				$out = ImageCreateTrueColor($prop[0], $prop[1]);
				$trans_colour = imagecolorallocate($out, 255, 255, 255);
				imagefill($out, 0, 0, $trans_colour);

				switch(strtolower($path['extension'])) {
					case 'jpg':
						$source = ImageCreateFromJpeg($src);
					break;
					case 'jpeg':
						$source = ImageCreateFromJpeg($src);
					break;
					case 'png':
						$source = ImageCreateFromPng($src);
					break;
					default:
						//$source = ImageCreateFromJpeg($src);
						return false;
					break;
				}

				header('Content-type: image/jpeg');
				ImageCopyResized($out, $source, 0, 0, 0, 0, $prop[0], $prop[1], $size[0], $size[1]);
				ImageJpeg($out);


				$md5 = md5($src.$width.$height);
				ImageJpeg($out, $this->getPath().$md5, 100);
			} else {
				header('Content-type: image/jpeg');
				readfile($this->getPath().md5($src.$width.$height));
			}
		}
		exit;
	}

	public function notAllowed() {
		header('Content-type: image/png');
		readfile($this->getPath().self::FILE_NOTALLOWED);
	}
}

abstract class Gallery extends Data {
	static $design;

	static $allowed_types = array('image/png'=>'.png', 'image/jpeg'=>'.jpg', 'image/pjpeg'=>'.jpg', 'image/jpg'=>'.jpg', 'image/pjpg'=>'.jpg');
	static $max_size = 524288; # in bytes // 512 kB

	const PATH = 'gallery/';
	const PAGE_PREFIX = 'gallery-';


	public function __construct() {
		global $vzhled;
		if($vzhled!="") {
			self::$design = $vzhled->design('gallery');
		}

		$this->setPath(self::PATH);
	}

	protected function getName($photo) {
		$ex = explode('.', strtr($photo, '-_', '  '));
		unset($ex[count($ex)-1]);
		return implode('.', $ex);
	}

	public function thumb($galerie, $file, $width, $height=NULL) {
		$this->setPath(self::PATH.$galerie.'/');
		$path = $this->getFilePath($file);

		$thumb = new Thumb;
		return $thumb->view($path, $width, $height);
	}
}

/**
 *
 */
final class GalleryAdmin extends Gallery {
	public function remove($gallery) {
		parent::setPath(parent::PATH);
		if($_POST['delete']) {

			if(!parent::removeDir($gallery)) {
				return design(parent::$design['neuspechsmazani'], $page);
			}

		} else {
			return design(parent::$design['potvrzenismazani'], $page);
		}
		return $gallery;
	}


	protected function categories() {
		$out = '';
		$list = parent::readDir(parent::VIEW_DIRS, '.','..','.svn','.htaccess');

		foreach($list as $item) {
			$description = PageView::load(self::PAGE_PREFIX.$item);

			$out .= design(
				self::$design['categories'],
				array(
					'thumb'=>URL::create('gallery', 'randomphoto', $item),
					'urlview'=>URL::create('gallery', $item),
					'urladmin'=>URL::create('admin', 'gallery', 'edit', $item),
					'urlpage'=>URL::create('admin', 'page', 'edit', self::PAGE_PREFIX.$item),
					'urlremove'=>URL::create('admin', 'gallery', 'remove', $item),
					'name'=> ($description['name']!='') ? $description['name'] : $item,
					'datetime'=> ($description['date']!='') ? $description['date'] : '0000-00-00 00:00:00',
					'description'=> ($description['description']!='') ? $description['description'] : '<i>{LANG:gallery;44;popis nevytvoren}</i>',
				)
			);
		}
		return $out;
	}

	public function view() {
		$out = '';

		return GalleryAdmin::categories();

		return design(parent::$design['admin'], array('content'=>$out));
	}

	public function edit($gallery) {
		$out = '';

		parent::setPath(parent::PATH.$gallery);
		$list = parent::readDir(parent::VIEW_FILES, '.','..','.svn','.htaccess');

		foreach($list as $key=>$image) {
			$name = parent::getName($image);

			$out .= design(
				parent::$design['admin_image'],
				array(
					'id'=>$key,
					'url'=>URL::create('gallery', 'thumb', $gallery, $image, 200),
					'img'=>$image,
					'name'=>$name,
					'class'=>(ereg("\.", $name)) ? 'imghidden' : '',
				)
			);
		}

		return design(parent::$design['admin_images'], array('gallery'=>$gallery,'content'=>$out));
	}

	public function save($gallery) {
		$out = '';
		$this->setPath(parent::PATH.$gallery);

		for($x=0; $_POST[$x.'_img']!=''; $x++) {

			$name = $_POST[$x.'_name'];
			$oldname = $_POST[$x.'_oldname'];
			$img = $_POST[$x.'_img'];

			if($name=='') {
				if($this->removeFile($img)) {
					$out .= sprintf(parent::$design['removedok'], $oldname);
				} else {
					$out .= sprintf(parent::$design['removedko'], $oldname);
				}
			} else if($name!=$oldname) {
				if($this->renameFile($img, str_replace(' ', '-', $name).'.'.$this->getType($img))) {
					$out .= sprintf(parent::$design['renamedok'], $oldname, $name);
				} else {
					$out .= sprintf(parent::$design['renamedko'], $oldname);
				}
			}

		}

		return sprintf(parent::$design['changes'], $out).$this->edit($gallery);
	}
}

/**
 *
 */
final class GalleryView extends Gallery {
	private function noCategory() {
		Page::__construct();
		return PageView::view('gallery');
	}

	private function photo($photo, $galerie) {
		$out = '';
		$name = parent::getName($photo);

		$out .= design(
			parent::$design['photo'],
			array(
				'url'=>URL::create('gallery', 'thumb', $galerie, $photo, 500),
				'name'=>$name,
				'id'=>md5($photo.$galerie),
				'photos' => GalleryView::photos($galerie),
			)
		);

		return $out;
	}

	private function photos($galerie) {
		parent::setPath(self::PATH.$galerie.'/');
		$photos = parent::readDir(parent::VIEW_FILES);

		$html_photos = '';

		foreach($photos as $img) {
			if(!ereg("\.", parent::getName($img))) {
				//$path = $this->getFilePath($img, false);
				$html_photos .= design(
					parent::$design['thumb'],
					array(
						'thumb'=>URL::create('gallery', 'thumb', $galerie, $img, 150),
						'alt'=>$img,
						'url'=>URL::create('gallery', $galerie, $img),
						'height'=>50
					)
				);
			}
		}

		return $html_photos;
	}

	private function category($galerie) {
		Page::__construct();
		$description = PageView::load(self::PAGE_PREFIX.$galerie);

		if(PageCategories::categoryExists($description['url'])) {
			return PageCategories::view($description['url']);
		} else {
			return design(
				parent::$design['main'],
				array(
					'category' => ($description!='') ? $description['name'] : $galerie,
					'cdescription' => $description['description'],
					'photos' => GalleryView::photos($galerie),
				)
			);
		}
	}

	public function view($galerie, $photo='') {
		$args = func_get_args();

		if(ereg(';', $galerie)) {
			$args = explode(';', $galerie);

			if($args[0]=='RANDOMPHOTOS') {
				return GalleryView::randomPhotos($args[1], $args[2]);
			};
		}

		if($galerie=='thumb') {
			return parent::thumb($args[1], $args[2], $args[3], $args[4]);
		} else if($galerie==NULL) {
			return GalleryView::noCategory();
		} else if($photo==NULL) {
			return GalleryView::category($galerie);
		} else if($photo!=NULL) {
			return GalleryView::photo($photo, $galerie);
		}
	}

	public function randomPhoto($galerie) {
		parent::setPath(self::PATH.$galerie.'/');
		$photos = parent::readDir(parent::VIEW_FILES);
		$rand = rand(0, count($photos)-1);

		return Gallery::thumb($galerie, $photos[$rand], 400);
	}


	public function randomPhotos($galerie, $count) {
		$out = '';

		parent::setPath(self::PATH.$galerie.'/');
		$photos = parent::readDir(parent::VIEW_FILES);

		for($x=0;$x<$count and count($photos)>0;$x++) {
			sort($photos);
			$rand = rand(0, count($photos)-1);

			$out .= design(
				parent::$design['thumb'],
				array(
					'thumb'=>URL::create('gallery', 'thumb', $galerie, $photos[$rand], 400),
					'alt'=>$img,
					'url'=>URL::create('gallery', $galerie, $photos[$rand]),
					'height'=>180
				)
			);

			unset($photos[$rand]);
		}

		return $out;
	}

	public function addphoto() {
		if($_POST['name']!='') {
			parent::setPath(parent::PATH.$_POST['kategorie']);

			$name = str_replace(' ', '-', str_replace('.', ',', $_POST['name']));
			$save = '';
			if(parent::$allowed_types[$_FILES['file']['type']]!='') {
				if($_FILES['file']['size']<parent::$max_size) {
					if(parent::saveFile($_FILES['file']['tmp_name'], '.'.$name.parent::$allowed_types[$_FILES['file']['type']])) {
						$save = parent::$design['saveok'];
					}
				}
			}

			if($save=='') {
				$save = parent::$design['saveko'];
			}
		}

		parent::setPath(parent::PATH);
		$list = parent::readDir(parent::VIEW_DIRS, '.','..','.svn','.htaccess');

		$html = '';
		foreach($list as $category) {
			$info = PageView::load(parent::PAGE_PREFIX.$category);
			$html .= '<option value="'.$category.'/">'.(($info['name']!='') ? $info['name'] : $category).'</option>';
		}

		return $save.design(parent::$design['addphoto'], array('categories'=>$html, 'size'=>(parent::$max_size/1024)));
	}
}
