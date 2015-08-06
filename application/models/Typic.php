<?php

class Typic extends Zend_Db_Table
{
	/** @FIXME эта же функция прописана в модели для модуля Service
	 * получение аннотаций к модулям
	 * @param string имя модуля
	 * @return string
	 */
	public function annotations_getText($module)
	{
		$db = $this->getDefaultAdapter();
		$select=$db->select()->from(array("res"=> "acl_resources"),null);
		$select->joinLeft
		(
				array("annot"=>"acl_res_annot"),
				"res.id=annot.resid","annotation"
		);
		// указанный модуль
		$select->where("res.module LIKE '".$module."'");
		// контроллер на задан
		$select->where("res.controller =''");

		$stmt = $db->query($select);
		$result = $stmt->fetchColumn();
		return $result;
	}
	
	/**
	 * вычисляет и устанавливает интервал годов текущего (который идет сейчас ) учебного года
	 * @return array
	 */
	public function getStudyYear_Now()
	{
		$nowYear=(int)date("Y"); // текущий год
		$nowMonth=(int)date("n"); // текущий месяц
		//		echo $nowMonth;
		$result=array();
		// если  первое полугодие, то начинается учебный год в прошлом году, иначе в этом
		$result["start"]=$nowMonth<7?$nowYear-1:$nowYear;
		// если первое полугодие, то заканчивается учебный год в этом году, иначе в след.
		$result["end"]=$nowMonth<7?$nowYear:($nowYear+1);
		//		echo "<pre>".print_r($result,true)."</pre>";
		return $result;
	}
	
	/** массив для построеия Zend_Form_Element_Select
	 * @param string $table таблица откуда данные
	 * @param string $where доп. условия проверки
	 * @return array [key]=>[value]
	 */
	public function getInfoForSelectList($table,$where='')
	{
		$db = $this->getDefaultAdapter();
	
		$q="SELECT id AS `key`, title AS `value` FROM ".$table;
		$q.=$where !='' ? "\n WHERE ".$where : '';
		//				echo $q;
		$result=$db ->fetchPairs($q);
		return $result;
	}
	
	
	
}