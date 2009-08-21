<?php
/**
 * Texy! - web text markup-language
 * --------------------------------
 *
 * Copyright (c) 2004, 2009 David Grudl (http://davidgrudl.com)
 *
 * This source file is subject to the GNU GPL license that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://texy.info
 *
 * @copyright  Copyright (c) 2004, 2009 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @link       http://texy.info
 * @package    Texy
 * @version    $Id: TexyBlockModule.php 226 2008-12-31 00:16:35Z David Grudl $
 */



/**
 * Module for rendering lyrics and chords.
 *
 * @author     Juda Kaleta
 * @package    Texy
 */
final class TexyLyricsModule extends TexyModule
{
	public function render($text) {
		$lines = explode("\n", $text);

		$ret = '';

		$ret .= self::replaceChords($lines);

		return $ret;
	}

	private function replaceChords($lines) {
		$ret = '';

		foreach($lines as $line) {
			$chord_line = '';

			preg_match_all('|\[([^\]]+)\]|', $line, $array);

			foreach($array[1] as $chord) {
				$replace = array('<b>'=>'', '</b>'=>'', '&nbsp'=>'');
				$position = strpos($line, '['.$chord.']');
				$spaces = str_repeat('&nbsp;', $position-(strlen(strtr($chord_line, $replace))));

				$chord_line .= $spaces.self::strongChord($chord);
				$line = self::removeFChord(strlen($chord), $line);
			}

			$ret .= $chord_line.'<br />'.$line.'<br />';
		}

		return $ret;
	}

	/**
	 * I dont know why, but Texy replace some chords (Dmi, G7maj etc.) like marks.
	 */
	private function strongChord($s) {
		$ret = '';
		$l = strlen($s);

		for($x=0; $l>=$x; $x++) {
			$ret .= '<b>'.$s[$x].'</b>';
		}

		return $ret;
	}

	/**
	 * Why str_replace with count doesn't work? This function is fix...
	 * http://bugs.php.net/bug.php?id=11457&edit=1
	 */
	private function removeFChord($lenght, $str)	{
		$l = strlen($str);
		$a = strpos($str,'[');
		$b = $a+$lenght+2;

		$temp = substr($str,0,$a).substr($str,$b,($l-$b));
		return $temp;
	}

}
