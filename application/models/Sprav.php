<?php
class Sprav extends Zend_Db_Table
{
	private $table;
	private $db;

	public function __construct($tablename)
	{
		$db = $this->getDefaultAdapter();
		$this->db=$db;
		$this->table=$tablename;

	}

	public function getTableName()
	{
		return $this->table;
	}

	public function setTableName($tablename)
	{
		$this->table=$tablename;
	
	}
	
	public function about()
	{
		$q="SELECT * FROM ".$this->table;
		//    	echo $q;
		$result=$this->db->fetchRow($q);
		return $result;
	}

	public function aboutSave($data,$id=1)
	{
		$where="id = ".$id;
		$this->db->update($this->table,$data,$where);
		return;
	}

	/**
	 * список типовой, для таблиц ID - TITLE
	 */
	public function typicList()
	{
		$q="SELECT id,title FROM ".$this->table;
		$q.=" ORDER BY title ASC";
		$result=$this->db->fetchAll($q);
		return $result;
	}

	public function typicChange($id,$title)
	{
		$where="id = ".$id;
		$data=array("title"=>$title);
		$this->db->update($this->table,$data,$where);
		return;
			
	}

	public function typicAdd($title)
	{
		$data=array("title"=>$title);
		$affecred=$this->db->insert($this->table,$data);
		$insertId=$this->db->lastInsertId($this->table,"id");
		return 	$insertId;
	}

	public function typicDel($id)
	{
		$where="id=".$id;
		$affected=$this->db->delete($this->table,$where);
		return 	$affected;
	}

	/// --------------------------------
	// для остальных таблиц

	public function otherList($table='',$where='')
	{
		if ($table==='' || is_null($table)) $table =$this->table;
		if ($where==='' || is_null($where)) $where='';
		else $where =" WHERE ".$where;
		//		else $where='';

		$q="SELECT * FROM ".$table.$where;
		//		echo $q; die();
		$result=$this->db->fetchAll($q);
		return $result;
	}

	/**
	 * @param array BIND $data
	 * @return integer
	 */
	public function otherAdd($data)
	{
		$affecred=$this->db->insert($this->table,$data);
		$insertId=$this->db->lastInsertId($this->table,"id");
		return $insertId;
	}


	public function otherChange($id,$data)
	{
		$where="id = ".$id;
		$this->db->update($this->table,$data,$where);
		return;
	}

	public function gosparamsList($spec)
	{
		$s=$this->db->select();
		$s->from(array("gos"=>$this->table));
		$s->joinLeft(array("s"=>"specs"), "s.id=gos.spec",array("specTitle"=>"title"));
		$s->joinLeft(array("d"=>"division"), "d.id=gos.division",array("divTitle"=>"title"));
		$s->joinLeft(array("f"=>"facult"), "f.id=gos.facult",array("facTitle"=>"title"));
		$s->where("gos.spec=".$spec);
		$s->order("divTitle");
		$s->order("specTitle");
		return $this->db->fetchAll($s);
		
		return $result;
		;
	}

	public function facultGetInfo($id)
	{
		$q="SELECT fs.id,fs.division,fs.spec,fs.facult"
		."\n , s.title AS specTitle, d.title AS divTitle, f.title AS facTitle"
		."\n FROM facult_specs AS fs"
		."\n LEFT JOIN specs AS s"
		."\n ON s.id=fs.spec"
		."\n LEFT JOIN division AS d"
		."\n ON d.id=fs.division"
		."\n LEFT JOIN facult AS f"
		."\n ON f.id=fs.facult"
		."\n WHERE fs.facult=".$id;
		//		echo $q;die();
		$result=$this->db->fetchAll($q);
		return $result;
	}

	public function facultDelInfo($id)
	{
		$where="facult=".$id;
		$affected=$this->db->delete("facult_specs",$where);
		return $affected;
	}

	public function facultNewInfo($data)
	{
		foreach ($data as $d)
		{
			$this->db->insert("facult_specs",$d);
		}
	}

	public function specGetInfo($id)
	{
		$q="SELECT * FROM ".$this->table." WHERE id=".$id;
		$result=$this->db->fetchRow($q);
		return $result;
	}

	public function specGetSubspecList($specid)
	{
		$q="SELECT id,title FROM subspecs WHERE specid=".$specid;
		$result=$this->db->fetchAll($q);
		return $result;
	}

	public function specAddSubspec($specid,$title)
	{
		$data=array("specid"=>$specid,"title"=>$title);
		$affecred=$this->db->insert("subspecs",$data);
		$insertId=$this->db->lastInsertId("subspecs","id");
		return 	$insertId;
	}

	public function specRenameSubspec($id,$title)
	{
		$where="id = ".$id;
		$data=array("title"=>$title);
		$this->db->update("subspecs",$data,$where);
		return;
			
	}

	public function specDeleteSubspec($id)
	{
		$where="id=".$id;
		$affected=$this->db->delete("subspecs",$where);
		return 	$affected;
	}


	public function getDisciplinesList()
	{
		$q="SELECT id AS `key`, title AS `value` FROM discipline ";
		$result=$this->db->fetchPairs($q);
		return $result;
			
	}

	public function getListForSelectList($table)
	{
		$q="SELECT id AS `key`, title AS `value` FROM `".$table."`";
		$q.=" ORDER BY title ASC";
		$result=$this->db->fetchPairs($q);
		return $result;
			
	}

	public function getSpecsForSelectList()
	{
		$sql='SELECT id AS `key`, CONCAT(numeric_title, " ",title) AS `value`';
		$sql.=' FROM specs';
		$sql.=' ORDER BY numeric_title ASC';
		$rows = $this->db->fetchPairs($sql);
		return $rows;

	}


	/** расписание звонков - список на выбранный год
	 * @param integer $yearStart
	 */
	public function bellsList($yearStart)
	{
		$q="SELECT id, yearStart"
		."\n , DATE_FORMAT(starts,'%H:%i') AS starts"
		."\n , DATE_FORMAT(ends,'%H:%i') AS ends"
		."\n FROM `".$this->table."`"
		."\n WHERE yearStart=".$yearStart
		."\n ORDER BY starts ASC"
		;
		$result=$this->db->fetchAll($q);
		return $result;
	}
	/** расписание звонков - изменить
	 * @param integer $id
	 */
	public function bellsEdit($id,$data)
	{
			
	}
	/** расписание звонков - удалить
	 * @param integer $id
	 */
	public function bellsDel($id)
	{
			
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
		$result["start"]=$nowMonth<6?$nowYear-1:$nowYear;
		// если первое полугодие, то заканчивается учебный год в этом году, иначе в след.
		$result["end"]=$nowMonth<6?$nowYear:($nowYear+1);
		//		echo "<pre>".print_r($result,true)."</pre>";
		return $result;
	}

}