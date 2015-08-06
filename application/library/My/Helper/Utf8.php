<?php
class My_Helper_Utf8 extends Zend_Controller_Action_Helper_Abstract
{

	/**
	 * Implementation substr() function for UTF-8 encoding string.
	 *
	 * @param    string  $str
	 * @param    int     $offset
	 * @param    int     $length
	 * @return   string
	 * @link     http://www.w3.org/International/questions/qa-forms-utf-8.html
	 *
	 * @license  http://creativecommons.org/licenses/by-sa/3.0/
	 * @author   Nasibullin Rinat, http://orangetie.ru/
	 * @charset  ANSI
	 * @version  1.0.5
	 */
	public function utf8_substr($str, $offset, $length = null)
	{
		#в начале пробуем найти стандартные функции
		if (function_exists('mb_substr')) return mb_substr($str, $offset, $length, 'utf-8'); #(PHP 4 >= 4.0.6, PHP 5)
		if (function_exists('iconv_substr')) return iconv_substr($str, $offset, $length, 'utf-8'); #(PHP 5)
		if (! function_exists('utf8_str_split')) include_once 'utf8_str_split.php';
		if (! is_array($a = utf8_str_split($str))) return false;
		if ($length !== null) $a = array_slice($a, $offset, $length);
		else                  $a = array_slice($a, $offset);
		return implode('', $a);
	}

	public function utf8_strlen($str)
	{
		if (function_exists('mb_strlen')) return mb_strlen($str, 'utf-8');

		/*
		 utf8_decode() converts characters that are not in ISO-8859-1 to '?', which, for the purpose of counting, is quite alright.
		 It's much faster than iconv_strlen()
		 Note: this function does not count bad UTF-8 bytes in the string - these are simply ignored
		 */
		return strlen(utf8_decode($str));

		/*
		 DEPRECATED below
		 if (function_exists('iconv_strlen')) return iconv_strlen($str, 'utf-8');

		 #Do not count UTF-8 continuation bytes.
		 #return strlen(preg_replace('/[\x80-\xBF]/sSX', '', $str));
		 */
	}
}