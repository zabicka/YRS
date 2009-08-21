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
				$size = getImageSize($src);

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


final class Gallery extends Data {
	const PATH = 'gallery/';
	const PAGE_PREFIX = 'gallery-';

	public function __construct() {
		global $vzhled;
		if($vzhled!="") {
			$this->design = $vzhled->design('gallery');
		}

		$this->setPath(self::PATH);
	}

	public function htmlMenuCategories() {
		$out = '';
		$list = $this->readDir(parent::VIEW_DIRS, '.','..','.svn','.htaccess');

		foreach($list as $item) {
			$description = Page::load(self::PAGE_PREFIX.$item);

			$out .= '<li><a href='.URL::create('gallery', $item).'>'.(($description!='') ? $description['name'] : $item).'</a></li>';
		}
		return $out;
	}

	private function viewNoCategory() {
		$page = new Page;
		return $page->view('gallery');
	}

	private function viewPhoto($photo, $galerie) {
		list($name, ) = explode('.', strtr($photo, '-_', '  '), 2);

		return design(
			$this->design['photo'],
			array(
				'url'=>URL::create('gallery', 'thumb', $galerie, $photo, 500),
				'name'=>$name,
				'id'=>md5($photo.$galerie),
			)
		);
	}

	private function viewCategory($galerie) {
		$this->setPath(self::PATH.$galerie.'/');
		$description = Page::load(self::PAGE_PREFIX.$galerie);
		$photos = $this->readDir(parent::VIEW_FILES);

		$html_photos = '';
		foreach($photos as $img) {
			//$path = $this->getFilePath($img, false);
			$html_photos .= design(
				$this->design['thumb'],
				array(
					'thumb'=>URL::create('gallery', 'thumb', $galerie, $img, 150),
					'alt'=>$img,
					'url'=>URL::create('gallery', $galerie, $img),
					'height'=>50
				)
			);
		}

		return design(
			$this->design['main'],
			array(
				'category' => ($description!='') ? $description['name'] : $galerie,
				'cdescription' => $description['description'],
				'photos' => $html_photos,
			)
		);
	}

	public function view($galerie, $photo='') {
		$args = func_get_args();

		if(ereg(';', $galerie)) {
			$args = explode(';', $galerie);

			if($args[0]=='RANDOMPHOTOS') {
				return $this->viewRandomPhotos($args[1], $args[2]);
			};
		}

		if($galerie=='thumb') {
			return $this->thumb($args[1], $args[2], $args[3], $args[4]);
		} else if($galerie==NULL) {
			return $this->viewNoCategory();
		} else if($photo==NULL) {
			return $this->viewCategory($galerie);
		} else if($photo!=NULL) {
			return $this->viewPhoto($photo, $galerie);
		}
	}

	public function thumb($galerie, $file, $width, $height=NULL) {
		$this->setPath(self::PATH.$galerie.'/');
		$path = $this->getFilePath($file);

		$thumb = new Thumb;
		return $thumb->view($path, $width, $height);
	}

	public function viewRandomPhotos($galerie, $count) {
		$out = '';

		$this->setPath(self::PATH.$galerie.'/');
		$photos = $this->readDir(parent::VIEW_FILES);

		for($x=0;$x<$count and count($photos)>0;$x++) {
			sort($photos);
			$rand = rand(0, count($photos)-1);

			$out .= design(
				$this->design['thumb'],
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
}
