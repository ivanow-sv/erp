<?php
class Dekanat_View_Helper_BuildDisciplines extends Zend_View_Helper_Abstract
{

public function BuildDisciplines($list,$contList)
{
	$colspan=count($contList)+1;
	// заголовок таблицы с перечнем выходных контролей
	$out='';
	$out.='
	<table class="table disciplineList" >
	
	<tr class="th">
		
		<th class="Col_1">Дисциплина</th>';
	
	$k=2;
//		$logger=Zend_Registry::get("logger");
		
//		$aa=$this->view->getHelperPaths();
//$logger->log($list, Zend_Log::INFO);
	
		foreach ($contList as $id=>$title) 
		{
			$out.='
		<th class="Col_'.$k.'">'.$this->sokrasheniya($title).'</th>
			 '
			;
		} 
	
	$out.='
	</tr>';
	
	foreach ($list as $title => $disInfo) 
	{
		$tr=$tr==1?2:1;
		$out.='
	<tr class="tr'.$tr.'">
		<td colspan="'.$colspan.'" class="Col_1 disTitle" title="'.$title.'">
			'.$title.'
		</td>
	</tr>
<!--	перечень выходных контролей этой дисциплины	-->'
	;
		$tr=$tr==1?2:1;
		$_temp=$disInfo;
		$_temp=array_shift($_temp);
		$planid=$_temp["planid"];
		$discipline=$_temp["discipline"];
//		$planid= 	
	$out.='
	<tr class="tr'.$tr.'" id="p'.$planid.'d'.$discipline.'">
	<td class="Col_1" >
		<span 
		onclick="removeDiscipline('.$planid.','.$discipline.');" 
		title="Удалить запись" class="removeIcoSmall"> 
		</span>
		<span '
		.'onclick="editDiscipline('.$planid.','.$discipline.');" 
		title="Редактировать запись" class="editIcoSmall"> 
		</span>
	</td>'
		;
	
	
	$k=2;
		foreach ($contList as $id=>$outTitle) 
		{
			$out.='<td class="Col_'.$k
			.'" id="contrCount'.$id.'">'; 
			if (isset($disInfo[$id]) && $disInfo[$id]["contrCount"]!=0) $out.=$disInfo[$id]["contrCount"];
			else $out.=" "; 
			$out.='</td>';
			$hiddens='';
			// еси экзамены, то добаыим даты в скрытых полях
//			if ($id==7)
//			{
//				for ($i = 1; $i <=3; $i++) 
//				{
//					$hiddens.='<td style="display:none;" id="examdate'.$i.'">'.$disInfo[$id]["eventdate".$i].'</td>';	
//				}
//			}
			$out.=$hiddens;
			
		
		} 
	
	$out.="</tr>";
	}
	$out.="
	</table>";
	
	return $out; 
}
	

	public function sokrasheniya($string,$len=3,$delim=" ")
	{
		$_str=explode($delim,$string);
		$result='';
		foreach ($_str as $sub)
		{
			$result.=$this->utf8_substr($sub,0,$len).". ";
		}
		return $result;
	}

	public function trimMiddle($string)
	{
		// пять символом от начала
		$begin=	$this->utf8_substr($string,0,5);
		// пять символом с конца
		$end=utf8_substr($string,$this->utf8_strlen($string)-5,5);
		return $begin."..".$end;
	}

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