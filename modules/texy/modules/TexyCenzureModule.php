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
 * Module for remove bad words.
 *
 * @author     Juda Kaleta
 * @package    Texy
 */

final class TexyCenzureModule extends TexyModule {
	public function render($text) {
		$bad_words = DB::query('select * from __texy_badwords')->fetchAll();

		foreach($bad_words as $word) {
			$word = $word->word;

			$text = preg_replace('|'.$word.'|', str_repeat('*', strlen(goodurl($word))), $text);
			$text = preg_replace('|'.goodurl($word).'|', str_repeat('*', strlen(goodurl($word))), $text);
		}

		return $text;
	}
}
