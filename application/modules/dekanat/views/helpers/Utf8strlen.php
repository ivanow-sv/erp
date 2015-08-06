<?php
class Dekanat_View_Helper_Utf8strlen extends Zend_View_Helper_Abstract
{

	public function Utf8strlen($str)
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