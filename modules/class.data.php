<?php
class Data {
	const DEFAULT_PATH = 'modules/data/';
	private $path = '';

	const VIEW_ALL = 'all';
	const VIEW_DIRS = 'dirs';
	const VIEW_FILES = 'files';

	public function __construct($path=NULL) {
		if($path==NULL) {
			$this->path = self::DEFAULT_PATH;
		} else {
			$this->setPath($path);
		}
	}

	/**
	 * Vytvoreni nove slozky
	 */
	protected function createPath($dir) {
		$path = $this->path.$dir;
		if(!is_dir($path)) {
			if(mkdir($path, 0777, true)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Nastaveni cesty
	 */
	protected function setPath($dir) {
		if(is_dir(self::DEFAULT_PATH.$dir)) {
			// zajisteni, ze bude na konci lomitko
			$this->path = self::DEFAULT_PATH.rtrim($dir, '/').'/';
			return true;
		}

		return false;
	}

	/**
	 * Ziskani cesty
	 */
	protected function getPath($absolute=false, $without_default=false) {
		$path = ($absolute==true) ? CESTA_ABSOLUTNI.$this->path : $this->path;
		return ($without_default==true) ? str_replace(self::DEFAULT_PATH, '', $path) : $path;
	}

	/**
	 * Zjisteni cesty k souboru
	 */
	protected function getFilePath($file, $absolute=true) {
		$path = $this->path.$file;
		if(file_exists($path)) {
			return ($absolute==true) ? CESTA_ABSOLUTNI.$path : $path;
		}

		return false;
	}

	protected function saveFileForm($name, $file) {

	}

	protected function readDir($type) {
		$args = func_get_args();
		unset($args[0]);

		$ret = array();

		$dir = openDir($this->path);

		while($file = readDir($dir)) {
			$path = $this->path.$file;

			if(!in_array($file, $args)) {
				if($type==self::VIEW_FILES and is_file($path)) {
					$ret[] = $file;
				} else if($type==self::VIEW_DIRS and is_dir($path)){
					$ret[] = $file;
				} else if($type==self::VIEW_ALL) {
					$ret[] = $file;
				}
			}
		}

		sort($ret);

		return $ret;
	}

	protected function removeFile($file) {
		return @unlink($this->path.$file);
	}

	protected function renameFile($old, $new) {
		if(!file_exists($this->path.$new)) {
			return @rename($this->path.$old, $this->path.$new);
		}

		return false;
	}

	protected function getType($file) {
		$ex = explode('.', $file);
		return $ex[count($ex)-1];
	}

	protected function saveFile($tmp, $name) {
		if(copy($tmp, $this->path.$name)) {
			return true;
		}

		return false;
	}
}

