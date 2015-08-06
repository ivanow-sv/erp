<?php
class My_Helper_Typic extends Zend_Controller_Action_Helper_Abstract
{
	private $_list1="|";
	private $_listFil="-";
	private $_secondsInDay=86400; // 24 часа * 60 минут * 60 секунд

	/** инкеременn символьной строки содержащей целое с ведущими нулями
	 * @param string $number целое с ведущими нулями типа 007 или 00415
	 * @return string
	 */
	public function strIntInc($number)
	{
		// запомним длину
		$len=strlen($number);
		// уберем слева ведущие нули
		$_number=ltrim($number,"0");
		// преобразуем в integer
		$_number=(int)$_number;
		// инкремент
		$_number++;
		// добавим нули в начало до нужной длины
		$result=str_pad($_number,$len,"0",STR_PAD_LEFT);
		// вернем
		return strval($result);
	}

	public function getSecondsInDay()
	{
		return $this->_secondsInDay;
	}

	public function getTodayDate()
	{
		return date("d-m-Y");
	}

	/**
	 * ищет в массиве ключи с именем "date"|"Date" и конвертирует содержимое в формат Y-m-d
	 * @param array $array
	 * @return array
	 */
	public function dates2YMD($array)
	{
		$result=array();
		foreach ($array as $key => $value)
		{
			if (preg_match("#date|Date#ui", $key)) $result[$key]=$this->date_DMY2YMD($value);
			else $result[$key]=$value;
			;
		}
		return $result;
	}

	public function strIntDec($number)
	{

	}


	//	public function __construct()
	//	{
	//
	//	}
	// Recursively traverses a multi-dimensional array.
	/** строит из многомерного массива заготовку для формирование вып. списка
	* @param array $array
	* @param array $out (КЛЮЧ=> "|--" . "_ТИТЛЕ_")
	* @param integer $k счетчик для LABEL - наверна уже не нужно
	* @param integer $l счетчик текущего уровня
	*/
	public function array2tree_for_selectList($array,&$out,&$k=1,&$l=1)
	{
		// Loops through each element. If element again is array, function is recalled. If not, result is echoed.
		foreach($array as $key=>$value)
		{
			if(is_array($value))
			{
				// если уровень не самый верхний в данный момент
				$out[$key]=$l>1
				? $this->_list1.str_repeat($this->_listFil,$l*2).$key  // спереди символы |--
				: $key
				;
				$l++;
				if (count($value)>0) $this->array2tree_for_selectList($value,$out,$k,$l);
				else $out[$key]=$key;
				$l--;
			}
			else
			{
				// если уровень не самый верхний в данный момент
				$out[$key]=$l>1
				? $this->_list1.str_repeat($this->_listFil,$l*2).' '.$value // спереди символы |--
				:$value
				;
				$k++;
			}
		}
	}

	public function hello()
	{
		return "hello";
	}

	//	/**
	//	 * вычисляет и устанавливает интервал годов текущего (который идет сейчас ) учебного года
	//	 * @return array
	//	 */
	//	public function getStudyYear_Now()
	//	{
	//		$nowYear=(int)date("Y"); // текущий год
	//		$nowMonth=(int)date("n"); // текущий месяц
	//		//		echo $nowMonth;
	//		$result=array();
	//		// если  первое полугодие, то начинается учебный год в прошлом году, иначе в этом
	//		$result["start"]=$nowMonth<6?$nowYear-1:$nowYear;
	//		// если первое полугодие, то заканчивается учебный год в этом году, иначе в след.
	//		$result["end"]=$nowMonth<6?$nowYear:($nowYear+1);
	//		//		echo "<pre>".print_r($result,true)."</pre>";
	//		return $result;
	//	}

	public function createRadioList($elemName,$src,$nullTitle="",$selected=false,$listsep = "<br />\n")
	{
		Zend_Loader::loadClass('Zend_Form_Element_Radio');

		$result = new Zend_Form_Element_Radio($elemName);
		if ($nullTitle!=='') $result->addMultiOption(0,$nullTitle);

		$result->addMultiOptions($src);
		//		$radio->helper="FormMultiRadioList";
		$result  ->removeDecorator('Label');
		$result  ->removeDecorator('HtmlTag');
		$result  ->setSeparator($listsep);
		// выбранное значение SELECTED
		if ($selected !==false) $result  ->setValue($selected);

		return $result;
	}

	public function createMultiselectList($elemName,$src,$selected=false,$listsep = "<br />\n")
	{
		Zend_Loader::loadClass('Zend_Form_Element_Multiselect');
	
		$result = new Zend_Form_Element_Multiselect($elemName);
// 		if ($nullTitle!=='') $result->addMultiOption(0,$nullTitle);
	
		$result->addMultiOptions($src);
		//		$radio->helper="FormMultiRadioList";
		$result  ->removeDecorator('Label');
		$result  ->removeDecorator('HtmlTag');
		$result  ->setSeparator($listsep);
		// выбранное значение SELECTED
		if ($selected !==false) $result  ->setValue($selected);
	
		return $result;
	}
	
	


	/**
	 * построить список выбора
	 * @param string $elemName
	 * @param array $src data ID=>value
	 * @param integer $defaultValue selected option
	 * @return Zend_Form_Element_Select
	 */
	public function createSelectList($elemName,$src,$nullTitle="",$selected=false)
	{
		Zend_Loader::loadClass('Zend_Form_Element_Select');
		$result = new Zend_Form_Element_Select($elemName);
		$result	->setOptions(array("multiple"=>""));
		if ($nullTitle!=='') $result->addMultiOption(0,$nullTitle);

		$logger = Zend_Registry::get('logger');
		//$disabled=array();
		foreach ($src as $key=>$value)
		{

			/*	if ($key===$selected)
			 {
			$result ->addMultiOption($key,$value);
			//				$result  ->setValue($selected);
			//				$result  ->setAttrib("disable",$key);
			}
			else*/
			$result ->addMultiOption($key,$value);
			//			$result ->addM($key,$value);
			//			if (!is_int($value)) $disabled[]=$value;
		}
		// выбранное значение SELECTED
		if ($selected !==false) $result  ->setValue($selected);

		$result  ->removeDecorator('Label');
		$result  ->removeDecorator('HtmlTag');
		//		$result->setAttrib("disabled",$value);
		return $result;
	}

	/**
	 * преобразование даты из ГОД-МЕСЯЦ-ДЕНЬ в ДЕНЬ-МЕСЯЦ-ГОД
	 *
	 * @param string $text
	 * @return string
	 */
	public function date_YMD2DMY($text)
	{

		preg_match ( "|(\d{4}).{1}(\d{2}).{1}(\d{2})|Ui", $text, $edu_d );
		$out = $edu_d [3] . "-" . $edu_d [2] . "-" . $edu_d [1];
		return $out;
	}
	/**
	 * преобразование даты из ДЕНЬ-МЕСЯЦ-ГОД в ГОД-МЕСЯЦ-ДЕНЬ
	 *
	 * @param string $text
	 * @return string
	 */
	public function date_DMY2YMD($text)
	{

		preg_match ( "|(\d{2}).{1}(\d{2}).{1}(\d{4})|Ui", $text, $edu_d );
		$out = $edu_d [3] . "-" . $edu_d [2] . "-" . $edu_d [1];
		return $out;
	}

	public function date_YMD2array($text)
	{
		preg_match ( "|(\d{4}).{1}(\d{2}).{1}(\d{2})|Ui", $text, $edu_d );
		$out["year"]=$edu_d [3];
		$out["month"]=$edu_d [2];
		$out["day"]=$edu_d [1];
		return $out;
	}

	public function date_DMY2array($text)
	{
		preg_match ( "|(\d{2}).{1}(\d{2}).{1}(\d{4})|Ui", $text, $edu_d );
		$out["year"]=$edu_d [3];
		$out["month"]=$edu_d [2];
		$out["day"]=$edu_d [1];
		return $out;
	}

	/** возвращает первый элемент массива
	 * @param array $array
	 * @return array:
	 */
	public function getFirstElem($array)
	{
		$_ar=$array;
		reset($_ar);
		$result=each($_ar);
		return $result;
	}

	/** возвращает ключ первого элемента массива
	 * @param array $array
	 * @return array:
	 */
	public function getFirstElemKey($array)
	{
		$_ar=$array;
		reset($_ar);
		$result=key($_ar);
		return $result;
	}

	/** возвращает последний элемент массива
	 * @param array $array
	 * @return array:
	 */
	public function getLastElem($array)
	{
		return array_pop($array);
		//		$_ar=$array;
		//		reset($_ar);
		//		$result=each($_ar);
		//		return $result;
	}


	/** список курсов от 1 до 6
	 * @return array
	 */
	public function kursy()
	{
		return $this->arrayFilled(1,6);
	}

	/** создает INTEGER массив со значениями от $start до $end
	 * @param integer $start
	 * @param integer $end
	 * @return array
	 */
	public function arrayFilled($start,$end)
	{
		$result=array();
		for ($i = $start; $i <= $end; $i++)
		{
			$result[$i]=$i;
		}
		return $result;
	}

	/** преобразование массива в пригодный для списка SELECT
	 * @param array $array
	 * @param string $toKey имя ключа для ключа
	 * @param string $toValue имя ключа для значения
	 * @return array ($toKey=>$toValue)
	 */
	public function array2select($array,$toKey,$toValue)
	{
		$result=array();
		foreach ($array as $item)
		{
			$result[$item[$toKey]]=$item[$toValue];
		}
		return $result;
	}

	/**
	 * перебор массива и привидение значений к INTEGER
	 * @param unknown_type $array
	 * @return Ambigous <multitype:, number>
	 */
	public function arrayINTEGER($array)
	{
		if (!is_array($array)) $array=array($array);
		$result=array();
		foreach ($array as $key=>$val)
		{
			$result[$key]=(int)$val;
		}
		return $result;
	}

	/**
	 * делает из входного массива древовидный
	 * @param array $rows данные из БД
	 * @return array
	 */
	public function treeArray($rows)
	{
		$items=array();
		// верхний цикл
		foreach ($rows as $kk=>$row)
		{
			$aaa=array();
			$aaa["id"]=$row['id'];
			$aaa["title"]=$row['title'];
			$aaa["comment"]=$row['comment'];
			$aaa["disabled"]=$row['disabled'];
			$aaa["parent"]=$row['parent'];
	
			// это чей-то наследник?
			if (!is_null($row['parent']))
			{
				$this->appendChilds(&$items,$aaa,$row['parent']);
			}
			else
			{
				$items[$row['id']]=$aaa;
			}
		}
	
		return $items;
	}
	
	/**
	 * добавляет детей к элементу массива
	 * @param array $inArrayв какой массив добавляется
	 * @param array $ArrayAdd массив, который пристыкуется
	 * @param unknown_type $key "точка монтирования" :)
	 */
	private function appendChilds(&$inArray,$ArrayAdd,$key)
	{
		//				if ($ArrayAdd["id"]==18) echo "<pre>".print_r($inArray,true)."</pre>";
		//				if ($key==0) echo "<pre>".print_r($inArray,true)."</pre>";
		// если это массив - переберем его
		if (is_array($inArray))
		{
			//			if ($inArray["id"]===$key ) $inArray[$key][$ArrayAdd["id"]]=$ArrayAdd;
			//			echo "<pre>".print_r($inArray,true)."</pre>";
			// перебор вот он, значок амперсант важен
			foreach ($inArray as $kk=>&$subArray)
			{
				// если искомое - добавим
				if ($subArray["id"]==$key && is_array($subArray))
				{
					$subArray["child"]=trim($subArray["child"]." ".$ArrayAdd["id"]);
					$subArray[$ArrayAdd["id"]]=$ArrayAdd;
				}
				// если и он массив - то опять ввызов
				if (is_array($subArray))$this->appendChilds($subArray,$ArrayAdd,$key);
				//				else $this->appendChild($subArray,$ArrayAdd,$key);
			}
		}
		//		continue;
	}

	/**
	 * функция для рекурсии
	 * для переделки из древовидного многоуровневого массива массив для выпадающего списка
	 * @param array $item
	 * @param string $deep показатель глубины
	 * @param array $result
	 */
	private function selecElem(&$item,&$deep,&$result,$selected)
	{
		if (is_array($item))
		{
			$result[$item["id"]]=$deep.$item["title"];
			if ($selected==$item["id"]) $result[$item["id"]].=" <==";
		}
		if (isset($item["child"]))
		{
			// если нет родителей - занчит начало ветки
			if (is_null($item["parent"])) $deep="";
	
			// переберем "детей"
			$childs=explode(" ",$item["child"]);
			foreach ($childs as $name)
			{
				// если нижеследующий прямой потомок
				if ($item[$name]["parent"]==$item['id']) $dp="|".ltrim($deep."--","|");
				else $dp=$deep;
				$this->selecElem($item[$name],$dp,$result,$selected);
			}
	
	
		}
	
	}
	
	/**
	 * делает из древовидного многоуровневого массива массив для выпадающего списка
	 * @param array $rows в виде дерева
	 * @return Array
	 */
	public function itemsForSelect($rows,$selected)
	{
		$deep='';
		$result='';
		foreach ($rows as $k=>$item)
		{
			$this->selecElem($item,$deep,$result,$selected);
		}
		return($result);
	}
	
}