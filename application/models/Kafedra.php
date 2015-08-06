<?php

/**
 * @author zlydden
 *
 */
class Kafedra extends Zend_Db_Table
{
	private $table	=	'kafedry';

	private $db;
	private $acl;		// работа с ролями
	private $kafedra;


	public function __construct($kafedra)
	{
		$this->db= $this->getDefaultAdapter();
		Zend_Loader::loadClass('Aclmodel');
		$this->acl=new Aclmodel();
		$this->kafedra=$kafedra;
	}

	public function getInfo()
	{
		$s=$this->db->select();
		$s->from(array("kaf"=>$this->table));
		$s->where("id=".$this->kafedra);
		return $this->db->fetchRow($s);
		;
	}

	/**
	 * Список дисциплин данной кафедры
	 * @return Array
	 */
	public function getDisciplines()
	{
		$s=$this->db->select();
		$s->from(array("dis"=>"disciplinestud"),array("key"=>"id","value"=>"title"));
		$s->where("kafedra=".$this->kafedra);
		$s->order("dis.title");
		return $this->db->fetchPairs($s);
		;
	}

	/**
	 * список дисциплин с преподавателями данной кафедры
	 * @return array
	 */
	public function getDisciplinesAssoc()
	{
		$s=$this->db->select();

		$s->from(array("t"=>"teachers"),array("discipline","userid"));
		$s->joinLeft(array("d"=>"disciplinestud"), "d.id=t.discipline", array("title"));
		$s->joinLeft(array("p"=>"personal"),
		"p.userid=t.userid", 
		array("family","name","otch")
		);
		$s->joinLeft(array("u"=>"acl_users"),
		"t.userid=u.id", 
		array("login","comment")
		);
		//		$s->from(array("dis"=>"disciplinestud"),array("key"=>"id","value"=>"title"));
		$s->where("d.kafedra=".$this->kafedra);
		$s->order("d.title");
		return $this->db->fetchAll($s);
		;
	}

	/**
	 * возвращает пользователей кафедры (всех у кого в роли параметром нужная кафедра
	 * @param integer $kafedra
	 * @return array
	 */
	public function getUsersList($kafedra)
	{
		// узнаем у каких ролей есть данныый параметр
		$roles=$this->acl->getRolesByParam("kafedra=".$kafedra);
		// узнаем их потомков
		$_roles=array();
		foreach ($roles as $id=>$value)
		{
			// данная роль и её потомки
			$_r=$this->acl->getRoleTree($id,"down");
			$_r=$this->acl->treeRolePrepare($_r);
			// перепишем только роли
			foreach ($_r as $_rr)
			{
				$_roles[]=$_rr["roleid"]	;
			}
		}
		$_roles=implode(",", $_roles);
		$s=$this->db->select();
		$s->from(array("u"=>"acl_users"),array("id","login","comment"));
		$s->joinLeft(array("p"=>"personal"), "p.userid=u.id",
		array("fio"=>"CONCAT_WS(' ',p.family,p.name,p.otch)",		)
		);
		$s->where("u.role IN (".$_roles.")");
		$s->order("fio");
		$result=$this->db->fetchAssoc($s);
		return $result;
		;
	}

	public function teacherAssign($userid,$discipline)
	{
		$data=array();
		$data["userid"]=$userid;
		$data["discipline"]=$discipline;
		$this->db->insert("teachers",$data);
		return $this->db->lastInsertId();
		;
	}

	public function teacherUnassign($userid,$discipline)
	{
		$table="teachers";
		$where[] = $this->db->quoteInto('userid = ?', $userid);
		$where[] = $this->db->quoteInto('discipline = ?', $discipline);
		$this->db->delete($table,  $where);


		;
	}

	public function teacherAssignCheck($userid,$discipline)
	{
		$s=$this->db->select();
		$s->from("teachers","id");
		$s->where("userid=".$userid);
		$s->where("discipline=".$discipline);
		return $this->db->fetchAll($s);

		;
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

	public function ocontrol_getLists($userid,$disciplines,$criteria)
	{
		if (!is_array($disciplines)) $disciplines=array($disciplines);
		$dis=implode(",",$disciplines);
		$s=$this->db->select();
		$s->from(array("ol"=>"ocontrol_list"));
		$s->joinLeft(array("ot"=>"studoutcontrols"), "ot.id=ol.type",array("typeTitle"=>"title"));
		$s->joinLeft(array("dis"=>"disciplinestud"), "dis.id=ol.discipline",array("disTitle"=>"title"));
		$s->joinLeft(array("odoc"=>"ocontrol_doctypes"), "odoc.id=ol.doctype",array("docTypeTitle"=>"title"));
		$s->joinLeft(array("gr"=>"studgroups"), "gr.id=ol.groupid",array("groupTitle"=>"title","osnov"));
		$s->joinLeft(array("gos"=>"gos_params"), "gos.id=gr.gos_params",array("spec","division","facult"));
		$s->joinLeft(array("s"=>"specs"), "gos.spec=s.id",array("specTitle"=>"title"));
		$s->joinLeft(array("div"=>"division"), "gos.division=div.id",array("divTitle"=>"title"));
		$s->where("ol.discipline IN (".$dis.")");
		// этого учебного года
		$year=$this->getStudyYear_Now();
		$s->where("ol.studyYearStart=".$year["start"]);
		// условия чтобы не показывать чужие ведомости
		$s->where("ol.teacher=".$userid);
		// фильтр
		$s->where("ol.semestr=".$criteria["semestr"]);
		$s->where("ol.kurs=".$criteria["kurs"]);
		$s->where("gr.osnov=".$criteria["osnov"]);
		$s->where("gos.spec=".$criteria["spec"]);
		$s->where("gos.division=".$criteria["division"]);
		$s->where("ol.discipline=".$criteria["discipline"]);

		$s->order("gos.division");
		$s->order("gos.spec");
		$s->order("ol.discipline");
		$s->order("ol.groupid");
		$s->order("ol.eventdate DESC");
//		echo $s->__toString();
//die();
		return $this->db->fetchAll($s);
	}

	/** информация о документе
	 * @param integer $id
	 * @return array
	 */
	public function ocontrol_getListInfo($id)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT li.*, gos.spec,gos.division,gr.osnov"
		."\n , d.title AS disTitle, dt.title AS docTypeTitle"
		."\n , oc.title AS contolTitle, gr.title AS groupTitle"
		."\n , f.title AS facultTitle"
		."\n , CONCAT_WS(' ',p.family, p.name,p.otch) AS teacherFIO"
		."\n FROM ocontrol_list AS li"
		."\n LEFT JOIN personal AS p ON p.userid=li.teacher"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON gr.id=li.groupid"
		."\n LEFT JOIN gos_params AS gos ON gr.gos_params=gos.id"
		."\n LEFT JOIN facult AS f ON f.id=gos.facult"
		."\n LEFT JOIN disciplinestud AS d"
		."\n ON d.id=li.discipline"
		."\n LEFT JOIN ocontrol_doctypes AS dt"
		."\n ON dt.id=li.doctype"
		."\n LEFT JOIN studoutcontrols AS oc"
		."\n ON oc.id=li.type"
		."\n WHERE li.id=".$id;
		$result=$db->fetchRow($q);
		return $result;
	}

	public function getInfoForSelectList($table,$where='')
	{
		$db = $this->getDefaultAdapter();

		$q="SELECT id AS `key`, title AS `value` FROM ".$table;
		$q.=$where !='' ? "\n WHERE ".$where : '';
		//				echo $q;
		$result=$db ->fetchPairs($q);
		return $result;
	}

	public function getSpecsForSelectList()
	{
		$db = $this->getDefaultAdapter();

		$q="SELECT id AS `key`, CONCAT(numeric_title,' ',title) AS `value` FROM specs";
		$q.=" ORDER BY value ASC";
		$result=$db ->fetchPairs($q);
		return $result;
	}


	/** перечень студней в документе
	 * @param integer $id
	 * @return array
	 */
	public function ocontrol_listDetails($id)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT stud.zach,oc.userid, oc.result,oc.itog,oc.rating_bally,oc.modifydate"
		."\n , CONCAT(UCASE(p.family),' ',LEFT(p.name,1),'.',LEFT(p.otch,1),'.') AS fio"
		."\n FROM ocontrol AS oc"
		."\n LEFT JOIN personal AS p"
		."\n ON p.userid=oc.userid"
		."\n LEFT JOIN students AS stud"
		."\n ON stud.userid=oc.userid"
		."\n WHERE oc.listid=".$id
		."\n ORDER BY fio ASC"
		;
		//		echo $q;die();
		$result=$db->fetchAssoc($q);
		return $result;
	}

	/**
	 * ПОдписать ведомость преподавателем
	 * @param integer $id
	 * @param integer $state
	 * @return integer
	 */
	public function ocontrol_listStateChange($id,$state)
	{

		$data=array(
		"state"=>$state
		);
		$where[]= $this->db->quoteInto('id = ?', $id);
		$aff=$this->db->update("ocontrol_list",$data,$where);
		return $aff;


		;
	}

	/** смена личных достижений
	 * @param integer $docid
	 * @param integer $userid
	 * @param integer $ratingBally
	 * @param integer $result
	 * @param integer $itog
	 * @return integer
	 */
	public function ocontrol_personChange($docid,$userid,$ratingBally,$result,$itog)
	{
		$db = $this->getDefaultAdapter();
		$now=date("Y-m-d H:i:s");

		$data=array(
		"result"=>$result,
		"rating_bally"=>$ratingBally,
		"itog"=>$itog,
		"modifydate"=>$now
		);
		$where[]= $db->quoteInto('listid = ?', $docid);
		//		$where[] = $db->quoteInto('discipline = ?', $discipline);
		$where[] = $db->quoteInto('userid = ?', $userid);
		$aff=$db->update("ocontrol",$data,$where);
		return $aff;
	}

	/**
	 * дисциплины, которые пользователь преподает
	 * @param unknown_type $userid
	 * @return unknown
	 */
	public function ocontrol_getDisciplines($userid)
	{
		$s=$this->db->select();
		$s->from(array("t"=>"teachers"),array("key"=>"discipline"));
		$s->joinLeft(array("d"=>"disciplinestud"), "d.id=t.discipline",array("value"=>"title"));
		$s->where("userid=".$userid);
		$result=$this->db->fetchPairs($s);
		return $result;
		;
	}
}