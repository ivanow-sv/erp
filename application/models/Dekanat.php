<?php
// Zend_Loader::loadClass('Typic');

class Dekanat extends Zend_Db_Table
{
	protected $specId;
	protected $divisionId;
	protected $specsOnFacult;
	protected $person;
	protected $gosParams;		// параметры ГОСов для факультета
	protected $gosParamDef;		// параметры ГОСа по умолчанию - первый из списка
	protected $_acl_user;

	// храним ID факультета
	protected  $facultId;

	// @TODO все операции с учетками пользователей - перепоручить классу Users
	// @TODO все операции с персональными данными  - перепоручить классу Person
	// @TODO некоторые вещи и привязку брать от прав пользователя
	public function __construct($facultId)
	{

		Zend_Loader::loadClass('Person');
		Zend_Loader::loadClass('Users');
		$this->person=new Person;
		$this->_acl_user=new Users;
		$this->facultId=$facultId;
		$this->setGosParams();
		$this->gosParamDef=$this->getGosParamsDef();
		$this->divisionId=$this->gosParamDef["division"];		
		$this->specsOnFacult=$this->getSpecsByFacultForSelectList($this->divisionId);
		//		$this->setSpecsAndDivision();
		;
	}



	/**
	 * возврат параметров ГОСа
	 * @return array
	 */
	public function getGosParams()
	{
		return $this->gosParams;
	}
	
	/**
	 *  параметр ГОСа по умолчанию
	 * @return array
	 */
	public function getGosParamsDef() 
	{
		$params=$this->gosParams;
		$params=array_shift ($params);
		return $params;
		;
	}

	/**
	 * получение списка параметров ГОСов в виде для списков форм 
	 */
	public function getGosParamsForSelectList() 
	{
		$result=array();
		foreach ($this->gosParams as $id => $params) 
		{
			$result[$id]=$params["gosTitle"];
		}
		return $result;
	}

	public function setSpecId($Id)
	{
		$this->specId=$Id;
	}

	public function setDivisionId($Id)
	{
		$this->divisionId=$Id;
	}

	public function setFacultId($id)
	{
		$this->facultId=$id;
	}

	/**
	 * @param integer $Id facult
	 */
	/*
	 public function setSpecsOnFacult()
	 {
	$db = $this->getDefaultAdapter();
	$q="SELECT id FROM specs WHERE facult =".$this->facultId;
	//		echo $q;
	//		die();
	//
	//		$this->specsOnFacult=$result;
	$result=$db->fetchCol($q);
	$this->specsOnFacult=$result;
	//		echo "<pre>".print_r($result,true)."</pre>";
	//		die();
	//
	}
	*/
	public function setSpecsAndDivision()
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT spec,division FROM gos_params WHERE facult =".$this->facultId;
		$result=$db->fetchAll($q);
		$specs=array();
		foreach ($result as $row)
		{
			$specs[]=$row["spec"];
		}
		// предполагается факультет только на одно оотделение
		$this->setDivisionId($result[0]["division"]);
		$this->specsOnFacult=$specs;
	}

// 	public function getGosOnFacult()
// 	{
// 		return $this->gosParams;
// 	}

	public function getSpecsOnFacult()
	{
		return $this->specsOnFacult;
	}

	/** список специальностей через запятую
	 * @return string
	 */
	public function getSpecsOnFacult_string()
	{
		$specs=$this->getSpecsOnFacult();
		//		echo "<pre>".print_r($specs,true)."</pre>"; die();
		//		$_spec_keys=array_keys($specs);
		return implode(",",array_keys($specs));
	}

	public function getDivisionId()
	{
		return $this->divisionId;
	}

	public function getfacultId()
	{
		return $this->facultId;
	}

	/** поиск студентов без группы
	 * @param integer $spec специальность
	 * @param integer $kurs курс
	 * @param integer $division отделение
	 */
	public function getStudentsFreeOnSpecDiv($spec,$kurs=1,$division=1)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT p.userid, CONCAT(p.family,' ',p.name,' ',p.otch) AS fio, p.iden_live,p.lang"
		."\n , stud.subgroup, il.title AS idenTitle, lang.title AS langTitle"
		."\n , stud.abitur_year, pay.id AS payId, pay.title AS payTitle"
		."\n FROM personal AS p"
		."\n LEFT JOIN students AS stud"
		."\n ON stud.userid=p.userid"
		."\n LEFT JOIN iden_live AS il"
		."\n ON p.iden_live=il.id"
		."\n LEFT JOIN lang "
		."\n ON p.lang=lang.id"
		."\n LEFT JOIN payment AS pay "
		."\n ON stud.payment=pay.id"
		."\n WHERE stud.spec=".$spec // специалдьность
		."\n AND stud.division=".$division // отделение
		."\n AND stud.subgroup IS NULL" // не числится в подгруппе
		."\n ORDER BY fio ASC , langTitle ASC , p.iden_live ASC , stud.abitur_year DESC"
		;
		$result=$db->fetchAll($q);
		return $result;
	}

	/** список убитуриентов, про которых нет ничего в личных данных
	 * @param integer	$spec
	 * @param integer $div
	 * @param integer $osnov
	 * @param integer $year
	 * @return array
	 */
	public function import_getNewbieList($spec,$osnov,$year,$div=null)
	{
		$db = $this->getDefaultAdapter();
		if ( is_null($div) ) $div=$this->divisionId;

		$sql = "SELECT a.id,a.exam_list"
		."\n , CONCAT(a.family,' ',a.name,' ',a.otch) AS fio "
		."\n ,rez.komis_id, YEAR(a.createdate) AS abitur_year"
		."\n , lang.title AS langTitle, pay.title AS payTitle, IL.title AS idenTitle"
		."\n FROM abiturients AS a";

		$sql.=' LEFT JOIN abitur_filed2 AS af ON af.abitur=a.id';
		$sql.=' LEFT JOIN results2 AS rez ON rez.abitur_id=af.id';
		$sql.="\n LEFT JOIN lang"
		."\n ON lang.id=a.lang"
		."\n LEFT JOIN payment AS pay"
		."\n ON pay.id=af.payment"
		."\n LEFT JOIN iden_live AS IL"
		."\n ON IL.id=a.iden_live"
		;
		// текущая специальность
		$sql .= ' WHERE af.spec ='.$spec;
		// специальности факультета
		$sql.="\n AND af.spec IN (".$this->getSpecsOnFacult_string().")";
		// зачисленные
		$sql .= ' AND rez.state_id =6';
		// отделение
		$sql .= ' AND af.division ='.$div;
		// форма обучения
		$sql .= ' AND af.osnov ='.$osnov;
		// не забравшие
		$sql .= "\n AND a.taketime LIKE '0000-00-00 00:00:00' ";
		// созданные в указанном году
		$sql .= "\n AND YEAR(a.createdate)=".$year;

		// о ком нет личных данных
		// хавать по док.удостовер. личностть
		$sql.="\n AND a.id NOT IN
		(SELECT a.id FROM abiturients AS a, personal AS p
		WHERE p.iden_serial LIKE a.iden_serial
		AND p.iden_num LIKE a.iden_num
		AND a.identity = p.identity"
		// @TODO проверить это - должно учитывать шо абитуром  может быть и дважды
		// т.е. среди абитуров этого года
		." AND YEAR(a.createdate)=".$year.")"
		;

		// сортировка
		$sql.="\n ORDER BY fio ASC";
		$rows = $db->fetchAll($sql);
		return $rows;
	}
	/** студенты, бывшие абитуриенты
	 * , которые не привязаны к подгруппе и которые были в приемной кампании YEAR года
	 * @param integer	$spec
	 * @param integer	$osnov
	 * @param integer	$year
	 * @param integer	$div
	 * @return array
	 */
	public function import_getNewbieList_v2($spec,$osnov,$year,$div)
	{
		$db = $this->getDefaultAdapter();
// 		if ( is_null($div) ) $div=$this->divisionId;

		$q="SELECT s.userid, YEAR(af.createdate) AS abitur_year"
		."\n , CONCAT(p.family,' ',p.name,' ',p.otch) AS fio"
		."\n , lang.title AS langTitle, pay.title AS payTitle, IL.title AS idenTitle"
		."\n , pp.documentid"
		."\n , CONCAT(d.titleNum, d.titleLetter, ' от ', DATE_FORMAT(d.titleDate,'%d-%m-%Y')) AS prikaz"
		."\n FROM students AS s"
		."\n LEFT JOIN abitur_filed2 AS af"
		."\n ON af.userid=s.userid"
		."\n LEFT JOIN personal AS p"
		."\n ON p.userid=s.userid"
		."\n LEFT JOIN lang"
		."\n ON lang.id=p.lang"
		."\n LEFT JOIN payment AS pay"
		."\n ON pay.id=s.payment"
		."\n LEFT JOIN iden_live AS IL"
		."\n ON IL.id=p.iden_live"
		."\n LEFT JOIN personprocess AS pp"
		."\n ON pp.userid=s.userid"
		."\n LEFT JOIN docus AS d"
		."\n ON d.id=pp.documentid"
		."\n WHERE "
		// без подгруппы
		."\n s.subgroup IS NULL"
		// указанный год, спец-ть, отделение и форма
		."\n AND YEAR(af.createdate)=".$year
		."\n AND af.spec=".$spec
		."\n AND af.division=".$div
		."\n AND af.osnov=".$osnov
		// специальности факультета
		."\n AND af.spec IN (".implode(",",array_keys($this->specsOnFacult)).")"
		// зачисленные по нужной специальности
		."\n AND af.id "
		."\n 	IN (SELECT af.id FROM `abitur_filed2` AS af"
		."\n 	LEFT JOIN results2 AS rez"
		."\n 	ON rez.abitur_id=af.id"
		."\n 	WHERE rez.state_id=6)"
		// ID операции - зачислен
		."\n AND pp.operation=1"
		// тип операции = приказ
		."\n AND d.type=2"
		."\n ORDER BY fio ASC"
		;
		$rows = $db->fetchAll($q);
		return $rows;
	}

	/** по заданным специальности, отделению и списку userid выдает только тех,
	 * кто относится к данным спец. + отделение
	 * @param array $spec_n_div ["spec"], ["division"]
	 * @param integer array $idz USERID
	 * @return array of USERID
	 */
	public function getStudentsFreeOnSpecDivAndInList($spec_n_div,$idz)
	{
		$idz=implode(',',$idz);
		$db=$this->getDefaultAdapter();
		$q="SELECT userid"
		."\n FROM students"
		."\n WHERE userid IN (".$idz.")"
		."\n AND spec=".$spec_n_div["spec"]
		."\n AND division=".$spec_n_div["division"];
		//		echo $q;
		//		die();
		$result=$db->fetchCol($q);
		return $result;
	}

	/** свободные студенты на той же специальности, что и подгрупа
	 * @param integer $subgroup
	 * @return array
	 */
	public function getStudentsFreeOnSpecSameSubGroup($subgroup)
	{
		$db = $this->getDefaultAdapter();

		// 1. унать специальность и отделение и год группы
		/*
		select gr.spec, gr.division, YEAR(gr.createdate) AS groupYear
		FROM studsubgroups AS sgr
		LEFT JOIN studgroups AS gr
		ON sgr.groupid=gr.id
		WHERE sgr.id=2
		*/
		// 2. узнать всех на специальности, у кого не задана подгруппа
		/*  getStudentsFreeOnSpecDiv
		*
		*/
		$q="SELECT gr.spec, gr.division, YEAR(gr.createdate) AS groupYear"
		."\n FROM studsubgroups AS sgr"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON sgr.groupid=gr.id"
		."\n WHERE sgr.id=".$subgroup
		;
		$info=$db->fetchRow($q);
		$result=$this->getStudentsFreeOnSpecDiv($info["spec"]);
		return $result;
	}



	/** количество подгруппе в группе
	 * @param integer $sugroup ID
	 * @return integer
	 */
	public function getSubgroupCountInGroup($id)
	{
		$db = $this->getDefaultAdapter();
		$q="select count(*) AS numz from studsubgroups where groupid=".$id;
		$result=$db->fetchOne($q);
		return $result;
	}

	/** количество студентов в подгруппе
	 * @param integer $sugroup ID
	 * @return integer
	 */
	public function getStudentsCountInSubGroup($id)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT count(stud.id) AS numz FROM students AS stud"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON sgr.id=stud.subgroup"
		."\n WHERE sgr.id=".$id;
		$result=$db->fetchOne($q);
		return $result;
	}

	/** студенты по ID группы или подгруппы
	 * если указана подгруппа, то группа игнорируется
	 * @param integer $group
	 * @param integer $subgroup
	 * @return array
	 */
	public function getStudentsInGroup($group,$subgroup=false)
	{
		$db = $this->getDefaultAdapter();
		$where=$subgroup?"\n WHERE sgr.id=".$subgroup:"\n WHERE gr.id=".$group;
		$q="SELECT p.userid, CONCAT(p.family,' ',p.name,' ',p.otch) AS fio, p.iden_live,p.lang"
		."\n , stud.subgroup, il.title AS idenTitle, lang.title AS langTitle"
		."\n , stud.abitur_year, pay.id AS payId, pay.title AS payTitle"
		."\n , gr.title AS groupTitle, sgr.title AS subgroupTitle"
		//		."\n , ".$year." - stud.abitur_year AS deltaYear"
		."\n FROM personal AS p"
		."\n LEFT JOIN students AS stud"
		."\n ON stud.userid=p.userid"
		."\n LEFT JOIN iden_live AS il"
		."\n ON p.iden_live=il.id"
		."\n LEFT JOIN lang "
		."\n ON p.lang=lang.id"
		."\n LEFT JOIN payment AS pay "
		."\n ON stud.payment=pay.id"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON stud.subgroup=sgr.id"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON sgr.groupid=gr.id"
		.$where
		."\n ORDER BY fio ASC , langTitle ASC , p.iden_live ASC , stud.abitur_year DESC"
		;
		$result=$db->fetchAll($q);
		return $result;

	}

	/** студенты по ID группы или подгруппы
	 * если указана подгруппа, то группа игнорируется
	 * @param integer $group
	 * @param integer $subgroup
	 * @return array userID, ФИО, subgroupID, groupTitle, subgroupTitle
	 */
	public function getStudentsInGroup_lightVer($group,$subgroup=false)
	{
		$db = $this->getDefaultAdapter();
		$where=$subgroup?"\n WHERE sgr.id=".$subgroup:"\n WHERE gr.id=".$group;
		$q="SELECT p.userid, CONCAT(p.family,' ',p.name,' ',p.otch) AS fio"
		."\n , stud.subgroup"
		."\n , gr.title AS groupTitle, sgr.title AS subgroupTitle"
		."\n FROM personal AS p"
		."\n LEFT JOIN students AS stud"
		."\n ON stud.userid=p.userid"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON stud.subgroup=sgr.id"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON sgr.groupid=gr.id"
		.$where
		."\n ORDER BY fio ASC"
		;
		$result=$db->fetchAll($q);
		return $result;

	}

	/** студенты по ID группы или подгруппы
	 * если указана подгруппа, то группа игнорируется
	 * @param integer $group
	 * @param integer $subgroup
	 * @return array userID=>ФИО
	 */
	public function getStudentsInGroup_userids($group)
	{
		$db = $this->getDefaultAdapter();
		$where=$subgroup?"\n WHERE sgr.id=".$subgroup:"\n WHERE gr.id=".$group;
		$q="SELECT p.userid AS `key`, CONCAT(UCASE(p.family),' ',p.name,' ',p.otch) AS `value`"
		//		."\n , stud.subgroup"
		//		."\n , gr.title AS groupTitle, sgr.title AS subgroupTitle"
		."\n FROM personal AS p"
		."\n LEFT JOIN students AS stud"
		."\n ON stud.userid=p.userid"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON stud.subgroup=sgr.id"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON sgr.groupid=gr.id"
		."\n WHERE gr.id=".$group
		."\n ORDER BY value ASC"
		;
		$result=$db->fetchPairs($q);
		return $result;
	}

	/**
	 * фильтрация пачки USERID по группе
	 * @param integer $group
	 * @param array $users ID
	 * @return array userID=>ФИО
	 */
	public function getStudentsFiltered_userids($group,$users)
	{
		$db = $this->getDefaultAdapter();
		$s=$db->select();
		$s->from(array("stud"=>"students"),array("key"=>"userid"));
		$s->joinLeft(array("sgr"=>"studsubgroups"),"sgr.id=stud.subgroup",null);
		$s->joinLeft(array("p"=>"personal"),"p.userid=stud.userid",array("value"=>"CONCAT(UCASE(p.family),' ',p.name,' ',p.otch)"));
		//		$s->joinLeft(array("gr"=>"studgroups"),"gr.id=sgr.groupid",null);
		$s->where("sgr.groupid=".$group);
		$usr=implode(",", $users);
		$s->where("stud.userid IN (".$usr.")");
		$s->order("value ASC");
		return $db->fetchPairs($s);
	}

	/**
	 * фильтрация пачки USERID,т.е. отсев несуществующих ID
	 * @param array $users ID
	 * @return array userID=>ФИО
	 */
	public function getStudentsFiltered2_userids($users)
	{
		$db = $this->getDefaultAdapter();
		$s=$db->select();
		$s->from(array("stud"=>"students"),array("key"=>"userid"));
		$s->joinLeft(array("p"=>"personal"),
				"p.userid=stud.userid",
				array("value"=>"CONCAT(UCASE(p.family),' ',p.name,' ',p.otch)"));
		//		$s->joinLeft(array("gr"=>"studgroups"),"gr.id=sgr.groupid",null);
		//		$s->where("sgr.groupid=".$group);
		$usr=implode(",", $users);
		$s->where("stud.userid IN (".$usr.")");
		$s->order("value ASC");
		return $db->fetchPairs($s);
	}


	/**
	 * возврат последнего №зачетки исключая последние цифры года
	 * @param integer $year год полностью
	 * @return string
	 */
	public function getLastZachNum($year=false)
	{
		//				$logger=Zend_Registry::get("logger");


		$db = $this->getDefaultAdapter();
		//		if ($year) $yearDigits=date('y',mktime(0,0,0,0,0,$year));// указан год
		if ($year) $yearDigits=substr($year,2,2);// указан год
		else $yearDigits=date('y');// текущий год
		//		$logger->log($yearDigits, Zend_Log::INFO);
		// начинается с двух последнийх цифр текущего года
		//		$q="SELECT MAX(zach) AS zach FROM students WHERE zach LIKE '".$yearDigits."%'";

		// выбрать из всех где начиная с третьего символа максимальное число
		$q="SELECT MAX(CAST(SUBSTRING(zach,3) AS UNSIGNED)) AS zach"
		."\n FROM students WHERE zach LIKE '".$yearDigits."%'"
		."\n AND zach NOT LIKE '%-%'"
		;
		$result=$db->fetchOne($q);
		// отсечем 1-ые две цифры
		//		$result=substr($result,2);
		return $result;
	}

	/**
	 * возвращает массив абитуриентов по ID специальности
	 * возвращает дневников по умолчанию
	 * возвращает ID, экз. лист, ФИО, № комиссии
	 * @param integer $specId
	 * @return array
	 */
	public function getAbitursBySpec($specId,$division=1)
	{
		//        $db = Zend_Registry::get('dbAdapter');
		$db = $this->getDefaultAdapter();
		$sql = 'SELECT a.id,a.exam_list,a.family,a.name,a.otch,rez.komis_id
		FROM abiturients AS a';
		$sql.=' LEFT JOIN abitur_filed2 AS af ON af.abitur=a.id';
		$sql.=' LEFT JOIN results2 AS rez ON rez.abitur_id=af.id';
		// текущая специальность
		$sql .= ' WHERE af.spec ='.$specId;
		// зачисленные
		$sql .= ' AND rez.state_id =6';
		// отделение
		$sql .= ' AND af.division ='.$division;
		// не забравшие
		$sql .= "\n AND a.taketime LIKE '0000-00-00 00:00:00' ";

		// о ком нет личных данных
		// хавать по док.удостовер. личностть
		$sql.="AND a.id NOT IN
		(SELECT a.id FROM abiturients AS a, personal AS p
		WHERE p.iden_serial LIKE a.iden_serial
		AND p.iden_num LIKE a.iden_num
		AND a.identity = p.identity)";

		// сортировка
		$sql.="\n ORDER BY a.family ASC, a.name ASC, a.otch ASC  ";
		//		        echo $sql;
		//		        die();
		$rows = $db->fetchAll($sql);
		return $rows;
	}

	/**
	 * возвращает название и шифр одной специальности
	 * @param integer $id специальность
	 * @return array название и шифр специальности
	 */
	public function getSpecTitles($id)
	{
		$db = $this->getDefaultAdapter();

		$sql='SELECT title,numeric_title';
		$sql.=' FROM specs';
		$sql.=' WHERE id ='.$id;
		$rows = $db->fetchRow($sql);
		return $rows;
	}

	/**
	 * информация об одном факультете
	 * @return array строка из таблицы facult
	 */
	public function getFacultInfo()
	{
		$select = $this->getDefaultAdapter()->select();
		$select->from("facult");
		$select->where ("id=".$this->facultId);
		$rows = $this->getDefaultAdapter()->fetchRow($select);
		return $rows;

	}

	public function getGroupInfo($id)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT gr.id, gr.title, gos.facult, gos.spec, gos.division, gr.osnov"
		."\n , gr.gos_params AS gosparam"
		."\n , YEAR(gr.createdate) AS groupYear"
		."\n , f.title AS facultTitle, CONCAT(s.numeric_title,' ',s.title) AS specTitle"
		."\n , d.title AS divTitle, osn.title AS osnovTitle"
		."\n FROM studgroups AS gr"
		."\n JOIN gos_params AS gos ON gos.id=gr.gos_params"
		."\n LEFT JOIN facult AS f"
		."\n ON f.id=gos.facult"
		."\n LEFT JOIN specs AS s"
		."\n ON s.id=gos.spec"
		."\n LEFT JOIN division AS d"
		."\n ON d.id=gos.division"
		."\n LEFT JOIN osnov AS osn"
		."\n ON osn.id=gr.osnov"
		."\n WHERE gr.id=".$id
		;
		//		echo $q;die();
		return $db->fetchRow($q);

	}
	/**
	 * информация о подгруппе
	 * @param integer $id
	 * @return Ambigous <multitype:, mixed>
	 */
	public function getSubGroupInfo($id)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT sgr.id, sgr.title AS subgroupTitle, sgr.groupid"
		."\n , gr.title AS groupTitle, gos.facult, gos.spec, gos.division,gr.gos_params AS gosparam"
		."\n , gr.osnov, YEAR(gr.createdate) AS groupYear"
		."\n , f.title AS facultTitle, CONCAT(s.numeric_title,' ',s.title) AS specTitle"
		."\n , d.title AS divTitle, osn.title AS osnovTitle"
		."\n FROM studsubgroups AS sgr"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON gr.id=sgr.groupid"
		."\n LEFT JOIN gos_params AS gos ON gos.id=gr.gos_params"
		."\n LEFT JOIN facult AS f"
		."\n ON f.id=gos.facult"
		."\n LEFT JOIN specs AS s"
		."\n ON s.id=gos.spec"
		."\n LEFT JOIN division AS d"
		."\n ON d.id=gos.division"
		."\n LEFT JOIN osnov AS osn"
		."\n ON osn.id=gr.osnov"
		."\n WHERE sgr.id=".$id
		;
		// @FIXME добавить условия для учета времени действия ГОСов относительно текущего года

		return $db->fetchRow($q);

	}


	/** имя (№) новой подгруппы, котроую нада создать
	 * @param integer $groupid
	 * @return integer
	 */
	public function getSubGroupTitle($groupid)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT MAX(title) FROM studsubgroups WHERE groupid=".$groupid
		;
		//		echo $q;
		$title=intval($db->fetchOne($q)) + 1;
		return $title;

	}

	/** имя (№) новой группы, котроую нада создать
	 * @param integer $kurs
	 * @param integer $spec
	 * @return integer
	 */
	public function getGroupTitle($gosparam,$kurs,$osnov)
	{
		$db = $this->getDefaultAdapter();
		$kursYear=$this->groupYearByKurs($kurs);
		$q="SELECT MAX(gr.title) FROM studgroups AS gr"
		."\n LEFT JOIN gos_params AS gos ON gos.id=gr.gos_params"
		."\n WHERE gos.id=".$gosparam
		."\n AND gr.osnov=".$osnov
		."\n AND gos.facult=".$this->facultId
		."\n AND YEAR(gr.createdate)=".$kursYear
		;
		//		echo $q;
		$title=intval($db->fetchOne($q)) + 1;
		return $title;
	}

	/**
	 *  создает группу и возвращает ID или false
	 * @param integer $Spec специальность
	 * @param integer $Kurs курс
	 * @return integer or boolean
	 */
	public function createGroup($gosparam,$kurs,$title,$osnov)
	{
		$db = $this->getDefaultAdapter();
		$kursYear=$this->groupYearByKurs($kurs);
		//		echo $kursYear."|";
		/*
		insert `studgroups` set title=2,
		osnov=1,
		createdate='2011',
		gos_params=(select id from gos_params where spec=17 AND division=1 AND facult=3)
		*/
// 		$q="select id from gos_params where spec=".$spec." AND division=".$div." AND facult=".$this->facultId;
// 		$gosparam=$db->fetchOne($q);
		$data=array();
		$data["osnov"]=$osnov;
		$data["createdate"]=date('Y-m-d',mktime(0,0,0, date("m"), date("d"),$kursYear));// указан год
		$data["title"]=$title;
		$data["gos_params"]=$gosparam;
		$db->insert(array('name'=>'studgroups'),$data);
		return $db->lastInsertId();
	}

	/**
	 *  создает подгруппу и возвращает ID
	 * @param integer Группы
	 * @param integer название
	 * @return integer
	 */
	public function createSubGroup($groupid,$title)
	{
		$db = $this->getDefaultAdapter();
		$data=array();
		$data["groupid"]=$groupid;
		$data["title"]=$title;
		$db->insert(array('name'=>'studsubgroups'),$data);
		return $db->lastInsertId();
		;
	}

	public function deleteGroup($id)
	{
		$db = $this->getDefaultAdapter();
		$q="DELETE FROM studgroups WHERE id=".$id;
		$db->query($q);
	}

	public function deleteSubgroup($id)
	{
		$db = $this->getDefaultAdapter();
		$q="DELETE FROM studsubgroups WHERE id=".$id;
		$db->query($q);
	}

	/** переименовать подгруппу
	 * @FIXME определять можно ли данному пользователю переименовывать - принадлежность к ЭТОМУ деканату
	 * @param integer $id
	 * @param string $title
	 */
	public function renameSubgroup($id,$title)
	{
		$db = $this->getDefaultAdapter();
		$q="UPDATE studsubgroups SET title ='".$title."' WHERE id=".$id;
		$db->query($q);
	}

	/** переименовать группу
	 * @FIXME определять можно ли данному пользователю переименовывать - принадлежность к ЭТОМУ деканату
	 * @param integer $id
	 * @param string $title
	 */
	public function renameGroup($id,$title)
	{
		$db = $this->getDefaultAdapter();
		$q="UPDATE studgroups SET title ='".$title."' WHERE id=".$id;
		$db->query($q);
	}

	public function getDiscipliesStudOrderedByKaf()
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT ds.*, kaf.title AS kafTitle"
		."\n FROM `disciplinestud` AS ds"
		."\n LEFT JOIN kafedry AS kaf"
		."\n ON kaf.id=ds.kafedra"
		."\n ORDER BY kafTitle ASC, ds.title ASC"
		;
		$result=$db ->fetchAll($q);
		$curKaf=0;
		$out=array();
		foreach ($result as $key=>$item)
		{

			$out["кафедра «".$item["kafTitle"]."»"][$item["id"]]=$item["title"];
		}

		return $out;

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

	/**
	 * массив для построеия списка спец. Zend_Form_Element_Select
	 * @param integer $id
	 * @return array  [key]=>[value]
	 */
	public function getSpecsByFacultForSelectList($division)
	{
		//		$db = Zend_Registry::get('dbAdapter');
		//		$db = $this->getDefaultAdapter();
		$result=array();
		foreach ($this->gosParams as $p)
		{
			if ($p["division"]==$division) $result[$p["spec"]]=$p["specTitle"];
		}
		return $result;
		//		$sql='SELECT s.id AS `key`, CONCAT(s.numeric_title, " ",s.title) AS `value`';
		//		$sql.=' FROM gos_params AS gos';
		//		$sql.=" LEFT JOIN specs AS s ON gos.spec=s.id";
		//		$sql.=" LEFT JOIN division AS d ON d.id=gos.division";
		//
		//		$sql.=' WHERE gos.facult ='.$id;
		//		// текущий уч. год попадает в "действие" специальности
		//		//		$y=$this->getStudyYear_Now();
		//		//		$sql.=" AND ".$y["start"]." >= yearStart";
		//		//		$sql.=" AND ".$y["end"]." <= yearEnd ";
		//
		//		$rows = $db->fetchPairs($sql);
		return $rows;

	}

	public function getSpecsByFacult($id)
	{
		//		$db = Zend_Registry::get('dbAdapter');
		$db = $this->getDefaultAdapter();

		$sql='SELECT id,CONCAT(numeric_title, " ",title) AS title';
		$sql.=' FROM specs';
		$sql.=' WHERE facult ='.$id;
		$rows = $db->fetchAll($sql);
		return $rows;

	}

	/** список групп/подгрупп на данной специальности, курсе и форма обучения
	 * также можно учесть факультет и отделение
	 * @param integer $gosparam
	 * @param integer $kurs
	 * @param integer $osnov
	 * @param integer $facult
	 * @param integer $division
	 * @return NULL|array
	 */
	public function getGroupsSubgroups($gosparam,$kurs,$osnov,$facult=null)
	{
		if (is_null($facult)) $facult=$this->facultId;
// 		if (is_null($division)) $division=$this->divisionId;
		$db = $this->getDefaultAdapter();
		$groupYear=$this->groupYearByKurs($kurs);

		$q="SELECT gr.id,gr.title AS groupTitle, YEAR(gr.createdate) AS groupYear"
		//		."\n , gr.osnov, osn.title AS osnovTitle "
		."\n , sgr.id AS subgroupid, sgr.title AS subgroupTitle"
		."\n , COUNT(stu.id) AS numz"
		."\n FROM studgroups AS gr "
		."\n LEFT JOIN gos_params AS gos ON gos.id=gr.gos_params"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON sgr.groupid=gr.id"
		."\n LEFT JOIN students AS stu"
		."\n ON stu.subgroup=sgr.id"
		//		."\n LEFT JOIN osnov AS osn"
		//		."\n ON osn.id=gr.osnov"
		."\n WHERE gos.id=".$gosparam
		."\n AND gos.facult=".$facult
// 		."\n AND gos.division=".$division
		."\n AND gr.osnov=".$osnov
		// считается группы создаются сразу после зачисления
		."\n AND YEAR(gr.createdate)=".$groupYear
		."\n GROUP BY sgr.id,gr.id"
		."\n ORDER BY gr.id ASC, sgr.id" // важно именно так
		;
		//		echo $q;
		//		die();
		$result=$db->fetchAll($q);
		// сделаем в виде дерева
		if (count($result)<1) return null;
		else return $result;
	}

	/** список групп/подгрупп при заданных параметрах ГОСа
	 * @param integer $spec
	 * @param integer $kurs
	 * @param integer $osnov
	 * @return NULL|array
	 */
	public function getGroupsSubgroups_v2($gosparam,$kurs,$osnov)
	{
		$db = $this->getDefaultAdapter();
		$groupYear=$this->groupYearByKurs($kurs);

		$q="SELECT gr.id,gr.title AS groupTitle, YEAR(gr.createdate) AS groupYear"
		//		."\n , gr.osnov, osn.title AS osnovTitle "
		."\n , sgr.id AS subgroupid, sgr.title AS subgroupTitle"
		."\n , COUNT(stu.id) AS numz"
		."\n FROM studgroups AS gr "
		."\n LEFT JOIN gos_params AS gos ON gos.id=gr.gos_params"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON sgr.groupid=gr.id"
		."\n LEFT JOIN students AS stu"
		."\n ON stu.subgroup=sgr.id"
		//		."\n LEFT JOIN osnov AS osn"
		//		."\n ON osn.id=gr.osnov"
		."\n WHERE gos.id=".$gosparam
		."\n AND gr.osnov=".$osnov
		// считается группы создаются сразу после зачисления
		."\n AND YEAR(gr.createdate)=".$groupYear
		."\n GROUP BY sgr.id,gr.id"
		."\n ORDER BY gr.id ASC, sgr.id" // важно именно так
		;
		//		echo $q;
		//		die();
		$result=$db->fetchAll($q);
		// сделаем в виде дерева
		if (count($result)<1) return null;
		else return $result;
	}


	/**
	 * список групп
	 * @param array $criteria (spec, ,osnov)
	 * @return array
	 */
	public function getGroupList($criteria,$groupYear)
	{
		$db = $this->getDefaultAdapter();
		$select=$db->select();
		$select->from(array("gr"=> "studgroups"),array("key"=>"id","value"=>"title"));
		$select->joinLeft(array("gos"=>"gos_params"), "gos.id=gr.gos_params",null);
		$select->joinLeft(array("s"=>"specs"), "s.id=gos.spec");
		$select->where("gr.osnov=".$criteria["osnov"]);
		$select->where("gos.facult=".$this->facultId);
		$select->where("gos.id=".$criteria["gosparam"]);
// 		$select->where("gos.spec=".$criteria["spec"]);
// 		$select->where("gos.division=".$this->divisionId);
		$select->where("YEAR(gr.createdate)=".$groupYear);
		$result=$db->fetchPairs($select);
		/*
		 $where="spec=".$criteria['spec']
		." AND division=".$this->currentDivision;
		// если форма "не важно"
		$where.= " AND osnov=".$criteria["osnov"];
		$where.=" AND facult=".$this->currentFacultId;
		$where.=" AND YEAR(createdate)=".$groupYear;

		*/
		return  $result;

	}

	/** список групп на зададанной спец./курсе
	 * @param integer $gosparam
	 * @param integer $kurs
	 * @return array OR null двумерный массив
	 */
	public function getGroupsOnSpecKursOsnYearStart($gosparam,$kurs,$osnov,$studyYearStart)
	{
		$db = $this->getDefaultAdapter();
		$groupYear=$this->groupYearByKurs($kurs,$studyYearStart);

		$q="SELECT gr.id,gr.title AS groupTitle, YEAR(gr.createdate) AS groupYear"
		."\n FROM studgroups AS gr "
		."\n LEFT JOIN gos_params AS gos ON gos.id=gr.gos_params"
		."\n WHERE gos.id=".$gosparam
// 		."\n AND gos.division=".$this->divisionId
		."\n AND gr.osnov=".$osnov
		// считается группы создаются сразу после зачисления
		."\n AND YEAR(gr.createdate)=".$groupYear
		//		."\n GROUP BY gr.id"
		."\n ORDER BY gr.id ASC" // важно именно так
		;
		$result=$db->fetchAll($q);
		// сделаем в виде дерева
		if (count($result)<1) return null;
		else return $result;
	}

	/** специальности-группы-подгруппы на курсе в виде многомерного массива
	 * @param array $specs key=>value
	 * @param integer $division
	 * @param integer $osnov
	 * @param integer $kurs
	 */
	public function getGroupsSubgroupsTree($specs,$division,$osnov,$kurs)
	{
		//		$specs=implode(",",array_keys($specs));
		//		$osnovs=implode(",",array_keys($osnovs));
		$db = $this->getDefaultAdapter();
		$result=array();
		// переберем специальности
		foreach ($specs as $specid => $specTitle)
		{
			$result[$specTitle]=array();
			// найдем группы/подгруппы в рамках специальности
			$groups=$this->getGroupsSubgroups($specid,$kurs,$osnov);
			if (! is_null($groups))
			{
				//				$curGrId=0;
				foreach ($groups as $gr)
				{
					$result[$specTitle]["группа ".$gr["groupTitle"]][$gr["subgroupid"]]=$gr["subgroupTitle"];
					;
				}
			}
			;
		}

		return $result;
	}

	public function getDivisionList()
	{
		//		$db = Zend_Registry::get('dbAdapter');
		$db = $this->getDefaultAdapter();

		$sql='SELECT id, title';
		$sql.=' FROM division';
		//        $sql.=' WHERE facult ='.$id;
		$rows = $db->fetchAll($sql);
		return $rows;
			
	}

	public function _rowsAbiturs($array)
	{
		$result=array();
		foreach ($array as $k=>$a)
		{
			$result[$k]['key']=$a['id'];
			$result[$k]['value']=$a['family'].' '.$a['name'].' '.$a['otch'].' ('.$a['exam_list'].')';
		}
		return $result;
	}


	/**
	 * возвращает инфо о том, куда поступили абитуры - специальность, специализация, отделение и т.п.
	 * @param array $ids
	 * @return array инфо
	 */
	public function getAbitursApprovedInfo($ids)
	{
		$ids= implode(',',$ids);

		$db = $this->getDefaultAdapter();
		$q="SELECT af.spec,af.subspec,af.division,af.osnov,af.payment"
		."\n FROM abitur_filed2 AS af"
		."\n LEFT JOIN results2 AS rez"
		."\n ON rez.abitur_id=af.id"
		."\n WHERE af.abitur IN (".$ids.")"
		."\n AND rez.state_id=6"
		;
		// важно, должно быть также, как в getAbitursApprovedInfo, findNewbieIds и в getAbitursInfo
		$q.="\n ORDER BY af.abitur ";
		//		echo $q;
		//		die();
		$info=$db->fetchAll($q);
		if (count($info)>0) return $info;
		else return false;
	}

	/**
	 * отсеивает из кучи и возвращает только новеньких
	 *
	 * @param array $ids перечень ID из формы
	 * @return array ID только новичков
	 */
	public function findNewbieIds($ids)
	{
		$ids= implode(',',$ids);
		$db = $this->getDefaultAdapter();

		$sql='SELECT a.id';
		$sql.="\n FROM abiturients AS a ";
		$sql.=' WHERE a.id IN ('.$ids.')';
		//  причем брать тех, о чьих док. удостов. личность нет данных - т.е. в БД о них ничего нет
		// определять по док. удостовер личность
		$sql.="\n AND a.id NOT IN
		(SELECT a.id FROM abiturients AS a, personal AS p
		WHERE p.iden_serial LIKE a.iden_serial
		AND p.iden_num LIKE a.iden_num
		AND a.identity = p.identity)";

		// сортировка
		// важно, должно быть также, как в getAbitursApprovedInfo, findNewbieIds и в getAbitursInfo
		$q.="\n ORDER BY a.id ";
		$result=$db->fetchPairs($sql);
		if (count($result)>0) return array_keys($result);
		else return false;
	}

	/**
	 * Информация об абитуриентах по их ID
	 * подавать на вход тока новичков
	 * @param array $ids ID абитуров
	 * @return array
	 */
	public function getAbitursInfo($ids)
	{
		$ids= implode(',',$ids);
		$db = $this->getDefaultAdapter();
		$sql='SELECT a.*';//,af.spec AS specid, af.subspec AS subspecid, af.division AS divisionid, af.osnov AS osnovid';
		$sql.="\n FROM abiturients AS a ";
		$sql.=' WHERE a.id IN ('.$ids.')';
		// сортировка
		// важно, должно быть также, как в getAbitursApprovedInfo, findNewbieIds и в getAbitursInfo
		$q.="\n ORDER BY a.id ";
		$rows=$db->fetchAll($sql);

		// еси массив пуст - вернуть false
		if (count($rows)>0) return $rows;
		else return false;

	}

	public function getPersonProcessLog($userid)
	{
		$data=$this->person->getPersonProcessLog($userid);
		return $data;
	}

	public function student_attendanceLog($userid)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT att.*,atst.title AS stateTitle,d.title AS disTitle"
		."\n , al.numb, al.studyYearStart,al.createdate"
		."\n FROM `attendance` AS att"
		."\n LEFT JOIN students AS stud"
		."\n ON stud.userid=att.userid"
		."\n LEFT JOIN attendance_states AS atst"
		."\n ON atst.id=att.state"
		."\n LEFT JOIN disciplinestud AS d"
		."\n ON d.id=att.discipline"
		."\n LEFT JOIN attendance_list AS al"
		."\n ON al.id=att.listid"
		."\n WHERE stud.userid=".$userid
		."\n ORDER BY att.modifydate DESC"
		;
		$result=$db->fetchAll($q);
		return $result;

	}

	public function student_ocontrolLog($userid)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT oc.*,d.title AS disTitle"
		."\n , ocl.numb, ocl.studyYearStart"
		."\n , ocl.kurs,ocl.semestr"
		."\n , ocl.createdate AS docDate, ocld.title AS doctypeTitle"
		."\n , oct.title AS typeTitle"
		."\n FROM `ocontrol` AS oc"
		."\n LEFT JOIN students AS stud"
		."\n ON stud.userid=oc.userid"
		."\n LEFT JOIN ocontrol_list AS ocl"
		."\n ON ocl.id=oc.listid"
		."\n LEFT JOIN disciplinestud AS d"
		."\n ON d.id=ocl.discipline"
		."\n LEFT JOIN ocontrol_doctypes AS ocld"
		."\n ON ocld.id=ocl.doctype"
		."\n LEFT JOIN studoutcontrols AS oct"
		."\n ON oct.id=ocl.type"

		."\n WHERE stud.userid=".$userid
		."\n ORDER BY oc.modifydate DESC"
		;
		$result=$db->fetchAll($q);
		return $result;
	}

	/**
	 * перечень выходного контроля, который должен выполнить студент
	 * экз, зач. и т.п. а также их кол-во - "количество на студента"
	 * @param integer $userid
	 * @return array
	 */
	public function student_ocontrolToDo($userid)
	{
		$db=$this->getDefaultAdapter();
		$s = $db->select();
		$s->from(array("stud"=>"students"),null);
		$s->joinLeft(array("sgr"=>"studsubgroups"), "sgr.id=stud.subgroup",null);
		$s->joinLeft(array("gr"=>"studgroups"), "gr.id=sgr.groupid",null);
		$s->joinLeft(array("plan"=>"studyplans"),
				"(plan.osnov=gr.osnov AND plan.gosParams=gr.gos_params)",
				array("kurs","semestr")
		);
		$s->joinLeft(array("pld"=>"studyplans_discip"),
				"pld.planid=plan.id",
				array("discipline","outControl","contrCount")
		);
		$s->joinLeft(array("d"=>"disciplinestud"),
				"d.id=pld.discipline",
				array("disTitle"=>"title")
		);
		$s->joinLeft(array("otype"=>"studoutcontrols"),
				"otype.id=pld.outControl",
				array("otypeTitle"=>"title")
		);
		$s->where("stud.userid=".$userid);
		$s->where("pld.contrCount<>0");
		$s->order("plan.kurs");
		$s->order("plan.semestr");
		/*
		 SELECT stud.userid,stud.zach,
		plan.kurs,plan.semestr,
		pld.discipline,pld.outControl,pld.contrCount,
		d.title AS disTitle,
		otype.title AS  otypeTitle,
		FROM students AS stud
		LEFT JOIN studsubgroups AS sgr ON sgr.id=stud.subgroup
		LEFT JOIN studgroups AS gr ON gr.id=sgr.groupid
		LEFT JOIN studyplans AS plan ON (plan.osnov=gr.osnov AND plan.gosParams=gr.gos_params)
		LEFT JOIN studyplans_discip AS pld ON pld.planid=plan.id
		LEFT JOIN disciplinestud AS d ON d.id=pld.discipline
		LEFT JOIN studoutcontrols AS otype ON otype.id=pld.outControl
		WHERE stud.userid=3725 AND pld.contrCount<>0
		ORDER BY plan.kurs ASC,plan.semestr ASC
		*/

		$result=$db->fetchAll($s);
		return $result;
	}

	/**
	 *  строит список студентов
	 * @param array $criteria
	 * @return array
	 */
	public function getStudentList($criteria)
	{
		//		$db = Zend_Registry::get('dbAdapter');
		$db = $this->getDefaultAdapter();
		//					$logger = Zend_Registry::get('logger');
		//		//
		//					$logger->log($criteria, Zend_Log::INFO);

		$sql="SELECT p.id,p.userid, p.family, p.name, p.otch, stud.zach AS zach"
		."\n , CONCAT(p.family,' ', p.name,' ', p.otch) AS fio"
		."\n , stud.osnov, stud.payment"
		."\n , stud.subgroup AS subgroupid "
		."\n , CONCAT(s.numeric_title,' ',s.title) AS specTitle, d.title AS divTitle"
		."\n , pay.title AS payTitle"
		."\n , osn.title AS osnovTitle"
		."\n , sgr.title AS subgroupTitle, gr.id AS groupid, gr.title AS groupTitle"
		."\n , YEAR(gr.createdate) AS groupYear"
		."\n , CASE "
		."\n WHEN (month(NOW()) <= 7) " // если первое полугодие
		."\n THEN YEAR(NOW()) - YEAR(gr.createdate)" // курс равен  ТЕКУЩЩИЙ ГОЛ - ГОД СОЗДАНИЯ ГРУППЫ
		."\n ELSE (YEAR(NOW()) - YEAR(gr.createdate) + 1 ) "  // ИНАЧЕ ТЕКУЩЩИЙ ГОЛ - ГОД СОЗДАНИЯ ГРУППЫ + 1
		."\n END "
		."\n AS kurs" // это номер курса
		."\n FROM personal AS p"
		."\n LEFT JOIN students AS stud"
		."\n ON stud.userid=p.userid"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON sgr.id=stud.subgroup"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON gr.id=sgr.groupid"
		."\n LEFT JOIN gos_params AS gos ON gos.id=gr.gos_params"
		."\n LEFT JOIN specs AS s"
		."\n ON s.id=gos.spec"
		."\n LEFT JOIN division AS d"
		."\n ON d.id=gos.division"
		."\n LEFT JOIN payment AS pay"
		."\n ON pay.id=stud.payment"
		."\n LEFT JOIN osnov AS osn"
		."\n ON osn.id=gr.osnov"
		;

		//		$logger = Zend_Registry::get('logger');
		//		$logger->log($criteria, Zend_Log::INFO);
		$check=(intval($criteria["gosparam"]) <=0 || intval($criteria["group"]) <=0 || intval($criteria["kurs"]<=0) );
		// если нету курса, специальности или группы - то пусто
		if ($check && $criteria["allfacult"]!==999) return array();

		$i=1;
		$where='';
		$wh=array();

		// если не искать на всем факультете
		if ($criteria["allfacult"]!=999)
		{
			if ($criteria["gosparam"] >0)	$wh[]=" gos.id=".$criteria["gosparam"];
			if ($criteria["group"] >0)		$wh[]=" gr.id=".$criteria["group"];
			if ($criteria["osnov"] >0) 		$wh[]=" gr.osnov=".$criteria["osnov"];
			if ($criteria["subgroup"] >0)	$wh[]=" stud.subgroup=".$criteria["subgroup"];
		}
// 		$wh[]=" gos.division=".$this->divisionId;
		if (intval($criteria["zach"]) >0) $wh[]=" stud.zach =".$criteria["zach"]."";
		if ($criteria["family"] !=='') $wh[]=" p.family LIKE '".$criteria["family"]."'";
		if ($criteria["name"] !=='') $wh[]=" p.name LIKE '".$criteria["name"]."'";
		if ($criteria["otch"] !=='') $wh[]=" p.otch LIKE '".$criteria["otch"]."'";
		if (intval($criteria["kurs"]>0))
		{
			$year=$this->groupYearByKurs($criteria["kurs"]);
			$wh[]="\n YEAR(gr.createdate) = ".$year;
		}
		// еси нет критериев - вернуть пустой массив
		if (count($wh)==0) return array() ;

		// на специальностях текушего факультета

		$gosparams=implode(",", array_keys($this->gosParams));
		$wh[]="\n gos.id IN (".$gosparams.")";

		$where="\n WHERE ".implode(" AND ",$wh);
		$sql.=$where;
		$sql.="\n ORDER BY stud.subgroup ASC";
		$sql.="\n ,  fio ASC";
		//				echo $sql;
		//				die();
		$rows=$db->fetchAll($sql);
		return count($rows)>0?$rows:array();
	}

	public function getStudSpecDivOsnov($userid)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT p.userid, CONCAT(p.family, ' ',p.name, ' ',p.otch) AS fio"
		."\n , stud.zach AS zach, stud.subgroup "
		."\n , gos.spec, gos.division, gr.osnov,gr.gos_params AS gosparam"
		."\n , CONCAT(s.numeric_title,' ',s.title) AS specTitle"
		."\n , d.title AS divTitle, osn.title AS osnovTitle"
		."\n FROM personal AS p"
		."\n LEFT JOIN students AS stud"
		."\n ON stud.userid=p.userid"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON sgr.id=stud.subgroup"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON gr.id=sgr.groupid"
		."\n LEFT JOIN gos_params AS gos ON gos.id=gr.gos_params"
		."\n LEFT JOIN specs AS s"
		."\n ON s.id=gos.spec"
		."\n LEFT JOIN division AS d"
		."\n ON d.id=gos.division"
		."\n LEFT JOIN osnov AS osn"
		."\n ON osn.id=gr.osnov"
		."\n WHERE p.userid=".$userid
		;
		//		echo $q;die();
		$result=$db->fetchRow($q);

		return $result;
		;
	}

	public function getStudInfo($zach)
	{
		$db = $this->getDefaultAdapter();
		$sql="SELECT p.id,p.userid, p.family, p.name, p.otch, stud.zach AS zach"
		."\n , CONCAT(p.family,' ', p.name,' ', p.otch) AS fio"
		."\n , gos.spec, gos.division, gr.osnov, stud.payment"
		."\n , osn.title AS osnovTitle"
		."\n , stud.subgroup AS subgroupid"
		."\n , CONCAT(s.numeric_title,' ',s.title) AS specTitle"
		."\n , d.title AS divTitle, pay.title AS payTitle"
		."\n , sgr.title AS subgroupTitle, gr.id AS groupid, gr.title AS groupTitle"
		."\n , YEAR(gr.createdate) AS groupYear"
		."\n , CASE "
		."\n WHEN (month(NOW()) <= 7) " // если первое полугодие
		."\n THEN YEAR(NOW()) - YEAR(gr.createdate)" // курс равен  ТЕКУЩЩИЙ ГОЛ - ГОД СОЗДАНИЯ ГРУППЫ
		."\n ELSE (YEAR(NOW()) - YEAR(gr.createdate) + 1 ) "  // ИНАЧЕ ТЕКУЩЩИЙ ГОЛ - ГОД СОЗДАНИЯ ГРУППЫ + 1
		."\n END "
		."\n AS kurs" // это номер курса
		."\n FROM personal AS p"
		."\n LEFT JOIN students AS stud"
		."\n ON stud.userid=p.userid"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON sgr.id=stud.subgroup"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON gr.id=sgr.groupid"
		."\n LEFT JOIN gos_params AS gos ON gos.id=gr.gos_params"
		."\n LEFT JOIN specs AS s"
		."\n ON s.id=gos.spec"
		."\n LEFT JOIN division AS d"
		."\n ON d.id=gos.division"
		."\n LEFT JOIN payment AS pay"
		."\n ON pay.id=stud.payment"
		."\n LEFT JOIN osnov AS osn"
		."\n ON osn.id=gr.osnov"
		;
		$sql.="\n WHERE stud.zach LIKE '".$zach."'";
		$result=$db->fetchRow($sql);
		return $result;
	}

	public function personalInfoChange($userid,$data)
	{
		return $this->_acl_user->personalInfoChange($userid,$data);
		//		$this->person->;

	}

	public function personProcessAddRecord($userid,$operation,$documentid=0,$comment='',$param,$value='',$author=0)
	{
		return $this->person->addRecord($userid,$operation,$documentid,$comment,$param,$value,$author);
	}

	public function getStudInfo_PrivateByUserid($userid)
	{
		$db = $this->getDefaultAdapter();
		$sql="SELECT p.* , DATE_FORMAT(p.birth_date,'%d-%m-%Y') AS birth_date"
		."\n , DATE_FORMAT(p.edu_date,'%d-%m-%Y') AS edu_date"
		."\n , stud.zach AS zach"
		."\n , CONCAT(p.family,' ', p.name,' ', p.otch) AS fio"
		."\n , gos.spec, gos.division, gos.id AS gosparam, gr.osnov, stud.payment"
		."\n , osn.title AS osnovTitle"
		."\n , stud.subgroup AS subgroupid, stud.abitur_id, stud.abitur_year"
		."\n , CONCAT(s.numeric_title,' ',s.title) AS specTitle"
		."\n , d.title AS divTitle, pay.title AS payTitle"
		."\n , sgr.title AS subgroupTitle, gr.id AS groupid, gr.title AS groupTitle"
		."\n , YEAR(gr.createdate) AS groupYear"
		."\n , CASE "
		."\n WHEN (month(NOW()) <= 7) " // если первое полугодие
		."\n THEN YEAR(NOW()) - YEAR(gr.createdate)" // курс равен  ТЕКУЩЩИЙ ГОЛ - ГОД СОЗДАНИЯ ГРУППЫ
		."\n ELSE (YEAR(NOW()) - YEAR(gr.createdate) + 1 ) "  // ИНАЧЕ ТЕКУЩЩИЙ ГОЛ - ГОД СОЗДАНИЯ ГРУППЫ + 1
		."\n END "
		."\n AS kurs" // это номер курса
		."\n , gen.title AS genderTitle"
		."\n FROM personal AS p"
		."\n LEFT JOIN students AS stud"
		."\n ON stud.userid=p.userid"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON sgr.id=stud.subgroup"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON gr.id=sgr.groupid"
		."\n LEFT JOIN gos_params AS gos ON gos.id=gr.gos_params"
		."\n LEFT JOIN specs AS s"
		."\n ON s.id=gos.spec"
		."\n LEFT JOIN division AS d"
		."\n ON d.id=gos.division"
		."\n LEFT JOIN payment AS pay"
		."\n ON pay.id=stud.payment"
		."\n LEFT JOIN osnov AS osn"
		."\n ON osn.id=gr.osnov"
		."\n LEFT JOIN gender AS gen"
		."\n ON gen.id=p.gender"
		;
		$sql.="\n WHERE stud.userid=".$userid;
		//		echo $sql;die();
		$result=$db->fetchRow($sql);
		return $result;

		;
	}

	public function getStudInfo_byUserid($userid)
	{
		$db = $this->getDefaultAdapter();
		$sql="SELECT p.id,p.userid, p.family, p.name, p.otch, stud.zach AS zach"
		."\n , CONCAT(p.family,' ', p.name,' ', p.otch) AS fio"
		."\n , gos.spec, gos.division, gos.id AS gosparam, gr.osnov, stud.payment"
		."\n , osn.title AS osnovTitle"
		."\n , stud.subgroup AS subgroupid, stud.abitur_id, stud.abitur_year"
		."\n , CONCAT(s.numeric_title,' ',s.title) AS specTitle"
		."\n , d.title AS divTitle, pay.title AS payTitle"
		."\n , sgr.title AS subgroupTitle, gr.id AS groupid, gr.title AS groupTitle"
		."\n , YEAR(gr.createdate) AS groupYear"
		."\n , CASE "
		."\n WHEN (month(NOW()) <= 7) " // если первое полугодие
		."\n THEN YEAR(NOW()) - YEAR(gr.createdate)" // курс равен  ТЕКУЩЩИЙ ГОЛ - ГОД СОЗДАНИЯ ГРУППЫ
		."\n ELSE (YEAR(NOW()) - YEAR(gr.createdate) + 1 ) "  // ИНАЧЕ ТЕКУЩЩИЙ ГОЛ - ГОД СОЗДАНИЯ ГРУППЫ + 1
		."\n END "
		."\n AS kurs" // это номер курса
		."\n FROM personal AS p"
		."\n LEFT JOIN students AS stud"
		."\n ON stud.userid=p.userid"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON sgr.id=stud.subgroup"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON gr.id=sgr.groupid"
		."\n LEFT JOIN gos_params AS gos ON gos.id=gr.gos_params"
		."\n LEFT JOIN specs AS s"
		."\n ON s.id=gos.spec"
		."\n LEFT JOIN division AS d"
		."\n ON d.id=gos.division"
		."\n LEFT JOIN payment AS pay"
		."\n ON pay.id=stud.payment"
		."\n LEFT JOIN osnov AS osn"
		."\n ON osn.id=gr.osnov"
		;
		$sql.="\n WHERE stud.userid=".$userid;
		//		echo $sql;die();
		$result=$db->fetchRow($sql);
		return $result;
	}

	/** перемещает студента
	 * @param integer $userid
	 * @param integer $subgroup куда
	 * @param integer $author кто
	 * @return integer $affected
	 */
	public function move2subgroup($userid,$subgroup,$author)
	{
		$db = $this->getDefaultAdapter();

		$data=array();
		$data["subgroup"]=$subgroup;
		$db->beginTransaction();
		try {
			$result["affected"]=$db->update("students",$data,"userid=".$userid);
			//		$comment="previous".$subgrFrom;
			// 5 - перевод из подгруппы в подгруппу
			$this->person->addRecord($userid,5,0,'',"subgroup",$subgroup,$author);
			$result["status"]=true;
			$db->commit();

		} catch (Zend_Exception $e) {
			$result["status"]=false;
			$result["errorMsg"]=$e->getMessage();
			$db->rollback();
		};
		//		$data["userid"]=$userid;
		return $result;
	}






	public function import_getAbiturPrikaz($abiturid)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT pr.prikazNum, pr.prikazDate FROM prot AS pr"
		."\n LEFT JOIN results2 as rez"
		."\n ON rez.komis_id=pr.prot_id"
		."\n LEFT JOIN abitur_filed2 AS af"
		."\n ON af.id=rez.abitur_id"
		."\n WHERE af.abitur=".$abiturid
		."\n GROUP BY pr.prikazNum"
		."\n ORDER BY pr.prikazDate DESC";
		//		echo $q; die();
		$result=$db->fetchRow($q);
		return $result;
		;
	}

	public function import_getAbiturInfo($id,$subgroupInfo)
	{
		// @TODO инфо о том, куда именно, форма оплаты...
		$db = $this->getDefaultAdapter();

		$q=" SELECT a.* "
		."\n , af.payment AS afPayment,af.order"
		."\n , af.spec AS afSpec, af.division AS afDivision, af.osnov AS afOsnov"
		."\n  FROM abiturients AS a"
		."\n LEFT JOIN abitur_filed2 AS af"
		."\n ON af.abitur=a.id"
		."\n WHERE a.id=".$id
		."\n AND af.spec=".$subgroupInfo["spec"]
		."\n AND af.division=".$subgroupInfo["division"]
		."\n AND af.osnov=".$subgroupInfo["osnov"]
		;
		//		echo $q;
		//		die();
		$result= $db->fetchRow($q);
		return $result;
	}

	/**
	 * инфо о студенте-новичке, только что зачисленном
	 * для привязки к подгруппе
	 * @param integer $userid
	 */
	public function import_getStudentNewbieInfo($userid)
	{
		// @TODO инфо о том, куда именно, форма оплаты...
		$db = $this->getDefaultAdapter();

		$q=" SELECT s.userid"//,a.id AS abitur_id"
		."\n , p.family, p.name, p.otch"
		."\n , af.payment , af.order"
		."\n , af.spec , af.division, af.osnov"
		."\n  FROM students AS s"
		."\n LEFT JOIN abitur_filed2 AS af"
		."\n ON af.userid=s.userid"
		//		."\n LEFT JOIN abitur_"
		."\n LEFT JOIN personal AS p"
		."\n ON p.userid=s.userid"
		."\n WHERE s.userid=".$userid
		// не числиться в подгруппе
		."\n AND s.subgroup IS NULL"
		// именно та заявка, которую зачислили
		."\n AND af.id "
		."\n 	IN (SELECT af1.id FROM `abitur_filed2` AS af1"
		."\n 	LEFT JOIN results2 AS rez"
		."\n 	ON rez.abitur_id=af1.id"
		."\n 	WHERE rez.state_id=6)";
		$result= $db->fetchRow($q);
		return $result;

	}

	/**
	 * Абитуры, зачисленные в указанном году
	 *
	 * @param integer $year
	 * @param integer $spec
	 * @param integer $osnov
	 * @param integer $division
	 * @return array
	 */
	public function import_getAbitursProfiles($year, $spec,$osnov,$division)
	{
		// payment userid abitur_id abitur_year abitur_prikazDate abitur_prikazNum

		$db = $this->getDefaultAdapter();
		$select=$db->select()->from(array("a"=> "abiturients"));

		// условия для таблицы ABITURIENTS
		$where=array();
		$where[]="YEAR(createdate) = ".$year;

		// условия для таблицы ABITUR_FILED2
		$where2=array();
		$where2[]="af.spec=".$spec;
		$where2[]="af.division = ".$division;
		$where2[]="af.osnov =".$osnov;

		$afFields= array(
				"afPayment"=>"payment",
				"order"=>"order",
				"afSpec"=>"spec",
				"afDivision"=>"division",
				"afOsnov"=>"osnov"
		);

		$select->joinLeft(array("af"=>"abitur_filed2"),
				"af.abitur=a.id",
				$afFields);
		foreach ($where AS $wh)
		{
			$select->where($wh);
		}
		foreach ($af as $wh)
		{
			$select->where($wh);
		}

		$stmt = $db->query($select);

		$result = $stmt->fetch();

		return $result;
		;
	}

	/** ищет абитуров по критериям
	 * @param array $where SQL критерии в таблице abiturients
	 * @param array $af SQL критерии в таблице abitur_filled
	 * @param boolean $fields =false BIND полей (см. ZEND_DB_SELECT)
	 * @return array
	 */
	public function import_getAbitursByCriteria($where,$af,$fields=FALSE)
	{
		$db = $this->getDefaultAdapter();
		/*
		 $q=" SELECT a.* "
		."\n , af.payment AS afPayment,af.order"
		."\n , af.spec AS afSpec, af.division AS afDivision, af.osnov AS afOsnov"
		."\n  FROM abiturients AS a"
		."\n LEFT JOIN abitur_filed2 AS af"
		."\n ON af.abitur=a.id"
		."\n WHERE a.id=".$id
		."\n AND af.spec=".$subgroupInfo["spec"]
		."\n AND af.division=".$subgroupInfo["division"]
		."\n AND af.osnov=".$subgroupInfo["osnov"]
		*/
		$select=$db->select()->from(array("a"=> "abiturients"));
		$afFields=$fields===FALSE
		? array(
				"afPayment"=>"payment",
				"order"=>"order",
				"afSpec"=>"spec",
				"afDivision"=>"division",
				"afOsnov"=>"osnov"
		)
		: $fields
		;
		$select->joinLeft(array("af"=>"abitur_filed2"),
				"af.abitur=a.id",
				$afFields);
		foreach ($where AS $wh)
		{
			$select->where($wh);
		}
		foreach ($af as $wh)
		{
			$select->where($wh);
		}

		$stmt = $db->query($select);

		$result = $stmt->fetch();
		return $result;
		;
	}

	/**
	 * Поиск студента-новичка по условиям
	 * @param array $where
	 * @return Ambigous <multitype:, mixed>
	 */
	public function import_getOneNewbieByCriteria($where)
	{
		$q="SELECT p.userid, s.zach"
		."\n FROM `personal` AS p"
		."\n LEFT JOIN students AS s"
		."\n ON s.userid=p.userid"
		."\n LEFT JOIN abitur_filed2 AS af"
		."\n ON af.userid=p.userid"
		."\n WHERE"
		// наш файкультет
// 		."\n  af.spec IN (".$this->getSpecsOnFacult_string().")"
		// без подгруппы
		."\n  s.subgroup IS NULL"
		// если нету записи в таблице STUDENTS ?
		// и если есть userid, т.е. запись существует
		."\n AND s.userid IS NOT NULL"

		// все зачисленные на нужной специальности-отделении
		."\n AND af.id IN ("
		."\n SELECT aff.id FROM abitur_filed2 AS aff"
		."\n LEFT JOIN results2 AS rez"
		."\n ON rez.abitur_id=aff.id"
		."\n WHERE rez.state_id=6"
		."\n ) "
		;


		// фио
		//		 ."\n CONCAT_WS(' ',p.family,p.name,p.otch) LIKE 'ВАСИЛЬЕВ Сергей Владимирович'"
		// год рождения
		//		 ."\n AND YEAR(p.birth_date)="1992
		// район
		//		 ."\n AND p.iden_reg LIKE '%Вурнарский%'
		// горд зачисления
		//		 ."\n AND s.abitur_year=2010
		// спец,, отделение и форма
		//		 ."\n AND af.spec=17
		//		 ."\n AND af.division=1
		//		 ."\n AND af.osnov=1

		// остальные условия
		$_where=implode("\n AND ", $where);
		$q.="\n AND ". $_where;
		//		echo $q;die();
		$db = $this->getDefaultAdapter();


		$result=$db->fetchRow($q);

		return $result;
		;
	}

	public function import_getAbitursFIO($ids)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT id AS `key`, CONCAT_WS(' ',family,name,otch) AS `value`"
		."\n FROM abiturients"
		."\n WHERE id IN (".implode(",",$ids).")";
		$result= $db->fetchPairs($q);
		return $result;
	}

	/**
	 * привязка абитуриента к подгруппе с учетом всех доп. операций. Транзакционная модель
	 * т.к. с вложенными транзакциями хз как, то пришлось вытащить все
	 * @param integer $userid
	 * @param string $zach номер зачетки
	 * @param integer $subgroup
	 * @param integer $author ID автора действия
	 */
	public function import_assign2subgroup($userid,$zach,$subgroup,$author)
	{
		$db = $this->getDefaultAdapter();
		$db->beginTransaction();
		try
		{
			// за основу брались эти вызовы.
			// 2. назначается № зачетки (журнал)
			//		$afZach=$this->data->zachChange($userid, $zach, $author);
			$table="students";
			$where= $db->quoteInto('userid = ?', $userid);
			$res["afZach"]=$db->update($table,array("zach"=>$zach),$where);
			// занесем в журнал персоны
			// 22 - смена номера зачетной книжки
			$this->person->addRecord($userid,22,0,'',"zach",$zach,$author);

			// 3. назначается подгруппа (журнал)
			//		$afMove=$this->data->move2subgroup($userid, $subgroup, $author);
			$data=array();
			//		$data["userid"]=$userid;
			$data["subgroup"]=$subgroup;
			$res["afMoved"]=$db->update("students",$data,"userid=".$userid);
			// 5 - перевод из подгруппы в подгруппу
			$this->person->addRecord($userid,5,0,'',"subgroup",$subgroup,$author);

			//4. меняется имя учетной записи
			//		$_afLogin=$this->data->zachChange_login($userid, $zach,$author);
			$res["afLogin"]=$this->_acl_user->renameLogin($userid,$zach);
			//
			$res["status"]=true;
			$res["errorMsg"]="OK";
			$db->commit();

		}
		catch (Zend_Exception $e)
		{
			$res["status"]=false;
			$res["errorMsg"]=$e->getMessage();
			$db->rollback();
		}
		;
		return $res;
	}

	/** узнаем специальность и отделение по подгруппе
	 * @param integer $selectedSubGroup
	 * @return array
	 */
	public function getSpecAndDivBySubgroup($selectedSubGroup)
	{
		$db=$this->getDefaultAdapter();
		$q="SELECT gr.spec, gr.division"
		."\n FROM studgroups AS gr"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON sgr.groupid=gr.id"
		."\n WHERE sgr.id=".$selectedSubGroup;
		$result=$db->fetchRow($q);
		return $result;
	}

	/** узнаем специальность и отделение по группе
	 * @param integer $selectedGroup
	 * @return array
	 */
	public function getSpecAndDivByGroup($selectedGroup)
	{
		$db=$this->getDefaultAdapter();
		$q="SELECT gr.spec, gr.division, s.title AS specTitle"
		."\n FROM studgroups AS gr"
		."\n LEFT JOIN specs AS s"
		."\n ON s.id=gr.spec"
		."\n WHERE gr.id=".$selectedGroup;
		$result=$db->fetchRow($q);
		return $result;
	}

	public function getOsnovByUser($userid)
	{
		$db=$this->getDefaultAdapter();
		$q="SELECT osnov"
		."\n FROM students"
		."\n WHERE userid=".$userid;
		$result=$db->fetchOne($q);
		return $result;
	}

	/**
	 * @FIXME перенести в ACL_USER 
	 * создание нового 
	 * @param string $login
	 * @param integer $role роль, по умолчанию гость
	 * @return integer ID
	 */
	public function addUser($loginName,$pass,$comment='',$disabled=1,$role=0)
	{
		$db = $this->getDefaultAdapter();

		$data=array('login'=>$loginName,'pass'=>$pass,'comment'=>$comment,'disabled'=>$disabled,'role'=>$role);

		$db->insert(array('name'=>'acl_users'),$data);

		//		$this->dbAdapter->insert(array('name'=>$this->table),$data);
		return $db->lastInsertId();
	}

	//	public function createStudentNew($login,$pass,$comment,$zach,$subgroup,$payment,$author,$docid)
	/**
	* Enter description here ...
	* @param string $login
	* @param string $pass
	* @param array $data family, name, otch, zach, subgroup, payment, docid
	* @param unknown_type $author
	* @return Ambigous <number, string>|boolean
	*/
	public function createStudentNew($login,$pass,$data,$author)
	{
		$db = $this->getDefaultAdapter();

		// Старт транзакции явным образом
		$db->beginTransaction();
		try {
			$comment="student ".$data["family"]." ".$data["name"]." ".$data["otch"];
			$userid=$this->addUser($login, $pass,$comment,1,8);

			// таблица PERSONAL ( ФИО )
			$this->_acl_user->personalInfoCreate($userid, array(
					"family"=>$data["family"],
					"name"=>$data["name"],
					"otch"=>$data["otch"]
			));

			// сделать запись в журнале - зачислен по проиказу
			$this->person->addRecord($userid,1,$data["docid"],'','','',$author);
			// создать запись в STUDENTS и учесть форму оплаты
			// присвоить к подгруппе одним запросом!!
			$person=array(
					"zach"=>$data["zach"],
					"userid"=>$userid,
					"payment"=>$data["payment"],
					"createdate"=>date("Y-m-d H:i:s"),
					"subgroup"=>$data["subgroup"]
			);
			$db->insert("students",$person);

			// сделать запись в журнале - подгруппа
			// 5 - перевод из подгруппы в подгруппу
			$this->person->addRecord($userid,5,0,'',"subgroup",$data["subgroup"],$author);

			// сделать запись в журнале - номер зачотки
			$this->person->addRecord($userid,22,0,'',"zach",$data["zach"],$author);

			// Если все запросы были произведены успешно, то транзакция фиксируется,
			// и все изменения фиксируются одновременно
			$db->commit();
			return $userid;
		} catch (Zend_Exception $e) {
			// Если какой-либо из этих запросов прошел неудачно, то вся транзакция
			// откатывается, при этом все изменения отменяются, даже те, которые были
			// произведены успешно.
			// Таким образом, все изменения либо фиксируются, либо не фиксируется вместе.
			$db->rollBack();
			return false;
			//			echo $e->getMessage();
		}
			


		;
	}

	/** проверка есть ли учетная запись
	 * @param string $loginName
	 * @return true если есть и false если нету
	 */
	private function existLogin($loginName)
	{
		$db = $this->getDefaultAdapter();
		$sql="SELECT count(*) AS numz FROM acl_users WHERE login LIKE '".$loginName."'";
		$row=$db->fetchOne($sql);
		//		echo $row;
		//		die();
		if ($row!=0) return true;
		else return false;
		//		return
	}

	/** смена номера зачетки - меняется таблица студентов, а не аккаунтов
	 * @param integer $userid
	 * @param string $zach
	 */
	public function zachChange($userid,$zach,$author)
	{
		$db = $this->getDefaultAdapter();
		$table="students";
		$where= $db->quoteInto('userid = ?', $userid);
		$aff=$db->update($table,array("zach"=>$zach),$where);
		// занесем в журнал персоны
		// 22 - смена номера зачетной книжки
		$this->person->addRecord($userid,22,0,'',"zach",$zach,$author);

		return $aff;
	}

	/** смена имени учетки
	 * @param integer $userid
	 */
	public function zachChange_login($userid,$login,$author)
	{
		$aff=$this->_acl_user->renameLogin($userid,$login);
		// @FIXME после вызова - проискходит рестарт приложения (или редирект ?) к этому ACTION
		//$this->person->addRecord($userid,24,0,'',"login",$zach,$author);
		return $aff;
	}

	/**
	 * смена номера зачетки и логина и отражение этого в журнале одной транзакцией
	 * @param integer $userid
	 * @param string $zach
	 * @param integer $author
	 * @return array
	 */
	public function zachAndLoginChange($userid,$zach,$author)
	{
		$db = $this->getDefaultAdapter();
		$result=array();
		$table="students";
		$db->beginTransaction();
		try {
			$where= $db->quoteInto('userid = ?', $userid);
			$db->update($table,array("zach"=>$zach),$where);
			// занесем в журнал персоны
			// 22 - смена номера зачетной книжки
			$this->person->addRecord($userid,22,0,'',"zach",$zach,$author);

			$this->_acl_user->renameLogin($userid,$zach);
			$result["status"]=true;

			$db->commit();
		} catch (Zend_Exception $e) {
			$result["status"]=false;
			$result["errorMsg"]=$e->getMessage();
			$db->rollback();
		}
		return $result;

	}

	public function getInfoByLogin($login)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT p.family, p.name,p.otch, u.login, p.userid"
		."\n FROM personal AS p"
		."\n LEFT JOIN acl_users AS u"
		."\n ON u.id=p.userid"
		."\n WHERE u.login LIKE '".$login."'";
		$result=$db->fetchRow($q);
		return $result;
	}



	/** узнаем нужный год создания по № курса относительно текущего учебного года
	 * ГОД СОЗДАНИЯ = ГОД НАЧАЛА УЧ. ГОДА - КУРС + 1
	 * @param integer $kurs
	 * @return integer год
	 */
	public function groupYearByKurs($kurs,$studyYearStart=null)
	{
		if (is_null($studyYearStart))
		{
			$studyYearStart=$this->getStudyYear_Now();
			$studyYearStart=$studyYearStart["start"];
		}
		$yearGroup=(int)($studyYearStart-$kurs) +1;
		return $yearGroup;
		/*
		 $year=date("Y"); // текущий год
		// выясним нужный год поступления, в завимисомти от текуше год, требуемого курса и какая половина года
		$monthNum=date("n");
		//
		//			$deltaYearString=" (".$year." - year(createdate)) ";
		// если первая половина года:
		// дата СОЗДАНИЯ ГРУППЫ = ТЕКУЩИЙ ГОД - КУРС
		if ($monthNum<=7)
		{
		$dateGroup=$year-$kurs;
		//				$whereKurs=" AND ".$deltaYearString."=".$selectedKurs;
		}
		//	 вторая половина года
		else
		{
		//	у перваков это ТЕКУЩИЙ ГОД
		if ($kurs==1) $dateGroup=$year ;
		//	ИНАЧЕ тех СОЗДАНИЕ ГРУППЫ = ТЕКУЩИЙ ГОД - КУРС +1
		else $dateGroup=($year - $kurs) + 1;
		}

		return $dateGroup;
		*/
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


	public function getParaList($yearStart)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT id AS `key`"
		."\n , CONCAT(DATE_FORMAT(starts,'%H:%i'),'-', DATE_FORMAT(ends,'%H:%i')) AS `value`"
		."\n FROM bells"
		."\n WHERE yearStart=".$yearStart
		."\n ORDER BY starts ASC"
		;
		$result=$db->fetchPairs($q);
		$k=1;
		foreach ($result as &$item)
		{
			$item=$k." пара. ".$item;
			$k++;
		}
		return $result;

	}

	/**
	 * минимальный и максимальный год среди учебных планов
	 * @return array
	 */
	public function studyPlans_getYearInterval($studyYearStart)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT MIN(YEAR(begins)) AS minYear,MAX(YEAR(begins)) AS maxYear FROM `studyplans` ";
		$row=$db->fetchRow($q);
		// если нету планов ышо
		if (is_null($row["minYear"]) || is_null($row["maxYear"])) $row=array("minYear"=>$studyYearStart,"maxYear"=>($studyYearStart+1));
		return $row;
	}

	public function studyPlans_buildYearList($yearz)
	{
		$maxKursCount=6;
		$result=array();
		for ($i = ($yearz["minYear"]-$maxKursCount); $i <= ($yearz["maxYear"]+$maxKursCount); $i++)
		{
			$result[$i]=$i;
		}
		return $result;
	}

	public function studyPlans_getList($yearStart,$gosparam,$osnov)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT pl.id,pl.studyYearStart,pl.kurs,pl.semestr,pl.spec,pl.division,pl.osnov "
		//		."\n , CASE"
		//		// если месяц сессии от сентября и до февраля - то это зима
		//		."\n WHEN ( month( pl.begins ) >=9 OR month( pl.begins ) <=2 )"
		//		."\n THEN 'winter'"
		//		."\n ELSE 'summer'"
		//		."\n END AS ses"
		."\n ,DATE_FORMAT(begins,'%d-%m-%Y') AS begins"
		."\n ,DATE_FORMAT(ends,'%d-%m-%Y') AS ends"
		."\n FROM studyplans AS pl"
		."\n LEFT JOIN gos_params AS gos ON gos.id=pl.gosParams"
		."\n WHERE "
		."\n studyYearStart =".$yearStart
		//		."\n ( "
		//		."\n (YEAR(pl.begins)=".$yearStart // начало учебного года
		//		."\n AND MONTH(pl.begins)>=9)" // после сентября этого года
		//		."\n OR "
		//		."\n (YEAR(pl.begins) =".($yearStart+1) // все шо в первой половине след. года
		//		."\n AND MONTH(pl.begins)<=7 )"
		//		."\n ) "
		//		."\n AND YEAR(pl.begins) "
		."\n AND gos.id=".$gosparam
		."\n AND pl.osnov=".$osnov
// 		."\n AND gos.division=".$this->divisionId
		."\n ORDER BY pl.kurs ASC, pl.begins ASC" // сортиовка важна!!
		;
		$rows=$db->fetchAssoc($q);
		return $rows;
	}

	/** дисциплины уч. плана и их параметры выходного контроля
	 * @param integer $planid
	 * @param boolean $IDsOnly - группировка по дисциплинам
	 * @return array
	 */
	public function studyPlans_getDisciplines($planid,$IDsOnly=false)
	{
		$db = $this->getDefaultAdapter();
		if ($IDsOnly!==false) $only="\n GROUP BY spd.discipline";
		else $only='';

		//		$db->se
		$q="SELECT spd.id,spd.planid,spd.discipline,spd.outControl AS outControl, spd.contrCount"
		."\n ,co.title AS outTitle, dis.title AS disTitle"
		//		."\n , DATE_FORMAT(spd.eventdate1,'%d-%m-%Y') AS eventdate1"
		//		."\n , DATE_FORMAT(spd.eventdate2,'%d-%m-%Y') AS eventdate2"
		//		."\n , DATE_FORMAT(spd.eventdate3,'%d-%m-%Y') AS eventdate3"
		//		."\n, spd.eventdate1, spd.eventdate2, spd.eventdate3 "
		//		."\n , CONCAT(LEFT(dis.title ,5),'...',RIGHT(dis.title ,5)) AS disT"
		."\n FROM `studyplans_discip` AS spd"
		."\n LEFT JOIN studoutcontrols AS co"
		."\n ON co.id=spd.outControl"
		."\n LEFT JOIN disciplinestud AS dis"
		."\n ON dis.id=spd.discipline"
		."\n WHERE spd.planid=".$planid
		.$only
		."\n ORDER BY disTitle ASC, outTitle ASC" // ВАЖНА СОРТИРОВКА
		;
		$rows=$db->fetchAssoc($q);
		if (count($rows)<1) return false;
		return $rows;
	}

	/** дисциплины группы в учебном году на курсе и семестре
	 * @param integer $groupid
	 * @param integer $studyYearStart
	 * @param integer $kurs
	 * @param integer $semestr
	 * @return array BIND
	 */
	public function studyPlans_getDisciplinesForGroup($groupid,$studyYearStart,$kurs,$semestr)
	{
		$db = $this->getDefaultAdapter();
		//		$kurs=$this->
		$q="SELECT spd.discipline AS `key`, d.title AS `value` "
		// sp.id AS planid, gr.id AS groupid,
		."\n FROM `studyplans_discip` AS spd"
		."\n LEFT JOIN studyplans AS sp"
		."\n ON sp.id=spd.planid"
		//		."\n LEFT JOIN gos_params AS gos ON sp.gosParams=gos.id"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON (gr.gos_params=sp.gosParams AND gr.osnov=sp.osnov)"
		."\n LEFT JOIN disciplinestud AS d"
		."\n ON d.id=spd.discipline"
		."\n WHERE gr.id=".$groupid
		."\n AND sp.studyYearStart=".$studyYearStart
		."\n AND sp.kurs=".$kurs // <<=====
		."\n AND sp.semestr=".$semestr
		// также курс группы
		."\n AND YEAR(gr.createdate)=".$this->groupYearByKurs($kurs,$studyYearStart)
		."\n GROUP BY spd.discipline"
		;
		//		 echo $q;die();
		$rows=$db->fetchPairs($q);
		if (count($rows)<1) return false;
		return $rows;
	}

	public function studyPlans_getInfo($planid)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT pl.id
		,DATE_FORMAT(pl.begins,'%d-%m-%Y') AS begins
		,DATE_FORMAT(pl.ends,'%d-%m-%Y') AS ends
		,pl.kurs,pl.semestr,pl.osnov "
		."\n , gos.spec, gos.division, gos.id AS gosparam"
		."\n FROM studyplans AS pl"
		."\n LEFT JOIN gos_params AS gos ON gos.id=pl.gosParams"
		."\n WHERE pl.id=".$planid
		."\n LIMIT 0,1"
		;
		$rows=$db->fetchRow($q);
		return $rows;
	}

	/** найти уч. план по курсу, специ, отделению и форме обучения
	 * году начала учебного года, курсу и семестру
	 * @param array $info (spec,division,osnov)
	 * @param integer $studyYearStart год
	 * @param integer $kurs
	 * @param integer $semestr 1 или 2
	 * @return integer ID плана
	 */
	public function studyPlans_findPlan($info, $studyYearStart, $kurs,$semestr)
	{
// 		$spec=$info["spec"];
// 		$division=$info["division"];
		$gosparam=$info["gosparam"];
		$osnov=$info["osnov"];

		$db = $this->getDefaultAdapter();
		$q="SELECT pl.id"
		."\n FROM studyplans AS pl"
		."\n LEFT JOIN gos_params AS gos ON gos.id=pl.gosParams"
		."\n WHERE pl.kurs=".$kurs
// 		."\n AND gos.spec=".$spec
// 		."\n AND gos.division=".$division
		."\n AND gos.id=".$gosparam
		."\n AND pl.osnov=".$osnov
		."\n AND pl.semestr = ".$semestr
		."\n AND pl.studyYearStart= ".$studyYearStart
		."\n LIMIT 0,1"
		;
		//		echo $q;die();
		$rows=$db->fetchOne($q);
		return $rows;
	}

	/** найти кол-во уч. планов по курсу, специ, семестру, отделению, форме обучения и учеб. году
	 * @param integer $kurs
	 * @param integer $spec
	 * @param integer $semestr
	 * @param integer $division
	 * @param integer $osnov
	 * @param integer $studyYearStart YEAR
	 * @return integer
	 */
	public function studyPlans_findPlanCount($kurs,$gosparam,$semestr,$osnov,$studyYearStart)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT count(*) AS numz FROM `studyplans`"
		."\n WHERE `studyYearStart` =".$studyYearStart
		."\n AND kurs=".$kurs
// 		."\n AND spec=".$spec
// 		."\n AND division=".$division
		."\n AND gosParams=".$gosparam
		."\n AND osnov=".$osnov
		."\n AND semestr=".$semestr
		;
		//		echo $q;die();
		$rows=$db->fetchOne($q);
		return $rows;
	}

	/**
	 * удаляет иинформацию о плане
	 *
	 * @param integer $planid
	 */
	public function studyPlans_deletePlan($planid)
	{
		$db = $this->getDefaultAdapter();
		// инфо о дисциплинах долой
		$table="studyplans_discip";
		$where = $db->quoteInto('planid = ?', $planid);
		$db->delete($table,  $where);
		// сам план долой
		$table="studyplans";
		$where = $db->quoteInto('id = ?', $planid);
		$db->delete($table, $where);
		;
	}

	public function studyPlans_editPlanDates($planid,$dates)
	{
		$db = $this->getDefaultAdapter();
		$table="studyplans";
		$where= $db->quoteInto('id = ?', $planid);
		$db->update($table,$dates,$where);
		;
	}

	/**
	 * @param integer $planId
	 * @param integer $discipline
	 * @param array of arrays $data
	 * 				BIND table columns
	 * 				"planid"=>$planId,
	 *				"discipline"=>$discipline,
	 *				"outControl"=>$coID,
	 *				"contrCount"=>$_param
	 */
	public function studyPlans_refreshPlanDiscipline($planId,$data,$discipline_old)
	{
		$db = $this->getDefaultAdapter();
		$db->beginTransaction();
		try
		{
			// 1.удалить инфо об этой дисцпилине на этом уч. плане
			if ($discipline_old>0) $this->studyPlans_deletePlanDiscipline($planId,$discipline_old);
			//		$table="studyplans_discip";
			//		$where[] = $db->quoteInto('planid = ?', $planId);
			//		$where[] = $db->quoteInto('discipline = ?', $discipline);
			//		$db->delete($table,  $where);

			// 2. внести новое
			foreach ($data as $key => $d)
			{
				$db->insert(array('name'=>'studyplans_discip'),$d);
			}
			$db->commit();
			return true;

		}
		catch (Zend_Exception $e)
		{
			$db->rollBack();
			return $e->getMessage();
			//			return false;
		}

	}


	/**
	 * ID дисциплины по её названию
	 * @param string $title
	 * @return integer OR boolean
	 */
	public function getDiciplineId($title)
	{
		$db=$this->getDefaultAdapter();
		$select=$db->select();
		$select->from("disciplinestud","id");
		$select->where("title LIKE '".$title."'");
		$disId=$db->fetchOne($select);
		return $disId;

	}

	public function studyPlans_deletePlanDiscipline($planId,$discipline)
	{
		$db = $this->getDefaultAdapter();
		$table="studyplans_discip";
		$where[] = $db->quoteInto('planid = ?', $planId);
		$where[] = $db->quoteInto('discipline = ?', $discipline);
		$db->delete($table,  $where);

	}

	//	/**
	//	 * записывает иинформацию о плане: функция-координатор, вызывает вспомогательные
	//	 *
	//	 * @param integer $planid
	//	 * @param array $disciplines
	//	 * @param array $time["begins", "ends"]
	//	 */
	//	public function studyPlans_savePlan($planid,$disciplines,$dates)
	//	{
	//		$db = $this->getDefaultAdapter();
	//		$db->beginTransaction();
	//		try {
	//			// инфо о дисциплинах
	//			$this->studyPlans_changePlanDisciplines($planid,$disciplines);
	//			// о сроках действия
	//			$this->studyPlans_changePlanTimedate($planid,$dates);
	//			$db->commit();
	//		} catch (Zend_Exception $e) {
	//			$db->rollback();
	//		}
	//		;
	//	}


	/**
	 * Иморт из массивов
	 * @param array $plan (kurs,semestr,spec,division,osnov,studyYearStart)
	 * @param array $disciplines
	 * @return unknown
	 */
	//	public function studyPlans_importKostroma($plan,$disciplines)
	//	{
	//		$db = $this->getDefaultAdapter();
	//		// выясним ID ГОСа
	//		// @TODO учесть срок действия ГОСа
	//		$select=$db ->select();
	//		$select->from("gos_params","id");
	//		// @FIXME учесть срок действия ГОСа
	//		$select->where("spec=".$plan["spec"]);
	//		$select->where("division=".$plan["division"]);
	//		$gos=$db->fetchOne($select);
	//		unset($plan["spec"]);
	//		unset($plan["division"]);
	//		$plan["gosParams"]=$gos;
	//
	//		$db->beginTransaction();
	//		try
	//		{
	//			$inserted=$db->insert(array('name'=>'studyplans'),$plan);
	//			foreach ($disciplines as $d)
	//			{
	//
	//				;
	//			}
	//
	//			$db->commit();
	//		}
	//		catch (Zend_Exception $e)
	//		{
	//
	//			$db->rollback();
	//		}
	//		return $result;
	//		;
	//	}

	public function studyPlans_newPlan($data)
	{
		$db = $this->getDefaultAdapter();

		// @TODO учесть срок действия ГОСа
// 		$select=$db ->select();
// 		$select->from("gos_params","id");
		// @FIXME учесть срок действия ГОСа
// 		$select->where("gosParams=".$data["gosparam"]);
// 		$select->where("division=".$data["division"]);
// 		$gos=$db->fetchOne($select);
// 		if (empty($gos)) return false;
// 		$_data=$data;
// 		unset($_data["spec"]);
// 		unset($_data["division"]);
// 		$_data["gosParams"]=$gos;
		try {
			$db->insert(array('name'=>'studyplans'),$data);
			$inserted=$db->lastInsertId('studyplans');
		} catch (Zend_Exception $e) {
			$inserted= false;
		}


		return $inserted;
	}

	//	/** изменение инфы о дисциплинах по данному плану
	//	 * использовать ТОЛЬКО ВНУТРИ ТРАНЗАКЦИИ
	//	 * @param integer $planid
	//	 * @param array $disciplines
	//	 */
	//	private function studyPlans_changePlanDisciplines($planid,$disciplines)
	//	{
	//		$db = $this->getDefaultAdapter();
	//		// 1. убрать среди дисциплин упоминание об этом плане
	//
	//		$q="DELETE FROM studyplans_discip WHERE planid=".$planid;
	//		$db->query($q);
	//		// 2. внести новое
	//		foreach ($disciplines as $key => $d)
	//		{
	//			$db->insert(array('name'=>'studyplans_discip'),$d);
	//		}
	//	}

	//	/** изменение инфы о сроках действия по данному плану
	//	 * @param integer $planid
	//	 * @param array $dates
	//	 */
	//	private function studyPlans_changePlanTimedate($planid,$dates)
	//	{
	//		$db = $this->getDefaultAdapter();
	//		$table="studyplans";
	//		$where = $db->quoteInto('id = ?', $planid);
	//		$rows_affected = $db->update($table, $dates, $where);
	//		return $rows_affected;
	//
	//	}

	/** список аттестационных листов
	 * @param integer $kurs
	* @param integer $groupid
	* @param array $studyYearStart
	* @return array
	*/
	public function attendance_list($kurs,$groupid,$studyYearStart)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT id, numb, studyYearStart, kurs, semestr, groupid"
		."\n , DATE_FORMAT(starts, '%d-%m-%Y') AS starts"
		."\n , DATE_FORMAT(ends, '%d-%m-%Y') AS ends"
		."\n FROM attendance_list"
		."\n WHERE kurs=".$kurs
		."\n AND groupid=".$groupid
		."\n AND studyYearStart=".$studyYearStart
		."\n ORDER BY numb ASC, semestr ASC"
		;
		$result=$db->fetchAll($q);

		return $result;
	}

	/** инфо об листе
	 * @param integer $id
	 * @return array
	 */
	public function attendance_listInfo($id)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT at.id, at.numb, at.studyYearStart"
		."\n , gr.title AS groupTitle, gr.gos_params AS gosparam"
		."\n , gos.division, gos.spec, gr.osnov, gos.facult, YEAR(gr.createdate) AS groupYear"
		."\n , at.kurs, at.semestr, at.groupid"
		."\n , DATE_FORMAT(at.starts, '%d-%m-%Y') AS starts"
		."\n , DATE_FORMAT(at.ends, '%d-%m-%Y') AS ends"
		."\n FROM attendance_list AS at"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON gr.id=at.groupid"
		."\n LEFT JOIN gos_params AS gos ON gos.id=gr.gos_params"
		."\n WHERE at.id=".$id
		;
		//		echo $q;die();
		$result=$db->fetchRow($q);

		return $result;
	}

	/** привязка студентов к аттестационному листу
	 * вызывать ТОЛЬКО внутри транзакции
	 * @param array $userlist
	 * @param array $disciplines
	 * @param integer $listID
	 * @return количество затронутых строк ВСЕГО
	 */
	public function attendance_assign($userlist,$disciplines,$listID)
	{
		$now=date("Y-m-d H:i:s");

		$db = $this->getDefaultAdapter();
		$table="attendance";
		$aff["users"]=0;
		$aff["disciplines"]=0;
		foreach ($userlist AS $user)
		{
			foreach ($disciplines as $d)
			{
				$data=array(
						"userid"=>$user["userid"],
						"discipline"=>$d["discipline"],
						"listid"=>$listID,
						"modifydate"=>$now
				);
				$aff["disciplines"]+=$db->insert($table,$data);
			}
			$aff["users"]++;
		}
		;
		return $aff;
	}

	public function attendance_dateEdit($id,$begins,$ends)
	{
		$db = $this->getDefaultAdapter();
		$table="attendance_list";
		$where = $db->quoteInto('id = ?', $id);
		$data=array(
				"starts"=>$begins,
				"ends"=>$ends
		);
		$aff=$db->update($table,$data,$where);
		return $aff;
	}

	/** замена состояния на аттестационном листе у студня-дисциплины
	 * @TODO фиксация времени последнего изменения
	 * @param integer $userid
	 * @param integer $stateid
	 * @param integer $discipline
	 * @param integer $planid
	 */
	public function attendance_personChange($userid,$stateid,$discipline,$listid,$comment='')
	{
		$db = $this->getDefaultAdapter();
		$now=date("Y-m-d H:i:s");

		$data=array(
				"state"=>$stateid,
				"comment"=>$comment,
				"modifydate"=>$now
		);
		$where[]= $db->quoteInto('listid = ?', $listid);
		$where[] = $db->quoteInto('discipline = ?', $discipline);
		$where[] = $db->quoteInto('userid = ?', $userid);
		$aff=$db->update("attendance",$data,$where);
		return $aff;
	}

	/**
	 * создание аттестационного листа
	 * @param string 	$begins		шапка "с"
	 * @param string 	$ends		шапка "по"
	 * @param integer 	$number		шапка "номер"
	 * @param integer 	$groupid	группа
	 * @param integer	$studyYearStart	учебный год
	 * @param integer	$kurs 		курс
	 * @param integer 	$semestr	семестр
	 * @return integer ID нового листа
	 */
	public function attendance_add($begins,$ends,$number,$groupid,$studyYearStart,$kurs,$semestr)
	{
		$now=date("Y-m-d H:i:s");

		$db = $this->getDefaultAdapter();
		$data=array(
				"numb"=>$number,
				"starts"=>$begins,
				"ends"=>$ends,
				"groupid"=>$groupid,
				"kurs"=>$kurs,
				"semestr"=>$semestr,
				"studyYearStart"=>$studyYearStart,
				"createdate"=>$now
		);
		//		echo $q; die();
		$db->insert("attendance_list",$data);
		return $db->lastInsertId();
	}

	/**
	 * Создание аттестационного листа и закрепление к нему студентов одной транзакцией
	 * @param string 	$begins		шапка "с"
	 * @param string 	$ends		шапка "по"
	 * @param integer 	$number		шапка "номер"
	 * @param integer 	$groupid	группа
	 * @param integer	$studyYearStart	учебный год
	 * @param integer	$kurs 		курс
	 * @param integer 	$semestr	семестр
	 * @param array 	$users	пользователи (userid,....)
	 * @return array
	 */
	public function attendance_addComplex($begins,$ends,$number,$groupid,$studyYearStart,$kurs,$semestr,$disciplines,$users)
	{
		$db = $this->getDefaultAdapter();
		$db->beginTransaction();
		$result=array();
		try
		{
			$insId=$this->attendance_add($begins,$ends,$number,$groupid,$studyYearStart,$kurs,$semestr);
			$affected=$this->attendance_assign($users,$disciplines,$insId);
			if (count($users)==$affected["users"])
			{
				$result["flag"]=true;
				$result["insId"]=$insId;
				$result["affected"]=$affected;
			}
			$db->commit();

		}
		catch (Zend_Exception $e)
		{
			$resut["flag"]=false;
			$resut["errorMsg"]=$e->getMessage();
			$db->rollback();
		}
		return $result;
	}

	public function attendance_del($id)
	{
		$db = $this->getDefaultAdapter();
		// аттестационнный лист
		$table="attendance_list";
		$where = $db->quoteInto('id = ?', $id);
		$db->delete($table,  $where);
		// все прикрепленные студни на данный лист
		$table="attendance";
		$where = $db->quoteInto('listid = ?', $id);
		$db->delete($table, $where);
		;

	}

	/** датали аттестационного листа
	 * @param integer $id
	 * @return array
	 */
	public function attendance_details($id)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT att.*,attL.numb, attL.kurs, att_st.title,att_st.title_letter, att_st.id AS stateid"
		."\n, dis.title AS disTitle "
		."\n, CONCAT(UCASE(p.family),' ',p.name,' ',p.otch) AS fio"
		."\n, st.subgroup, sgr.title AS subgrTitle"
		."\n, gr.id AS groupID, gr.title AS groupTitle"
		."\n FROM attendance AS att"
		."\n LEFT JOIN attendance_states AS att_st"
		."\n ON att_st.id=att.state"
		."\n LEFT JOIN attendance_list AS attL"
		."\n ON attL.id=att.listid"
		."\n LEFT JOIN disciplinestud AS dis"
		."\n ON dis.id=att.discipline"
		."\n LEFT JOIN personal AS p"
		."\n ON p.userid=att.userid"
		."\n LEFT JOIN students AS st"
		."\n ON st.userid=att.userid"
		."\n LEFT JOIN studsubgroups AS sgr"
		."\n ON st.subgroup=sgr.id"
		."\n LEFT JOIN studgroups AS gr"
		."\n ON sgr.groupid=gr.id"
		."\n WHERE att.listid=".$id
		// сортировать по подгруппам, ФИО - !! ВАЖНО
		."\n ORDER BY sgr.title ASC, fio ASC, dis.title ASC "
		;
		$result=$db->fetchAll($q);
		return $result;
	}

	public function attendance_disciplines($id)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT att.discipline AS `key`, dis.title AS `value`"
		."\n FROM attendance AS att"
		."\n LEFT JOIN disciplinestud AS dis"
		."\n ON dis.id=att.discipline"
		."\n WHERE listid=".$id;
		$q.="\n GROUP BY discipline";
		$q.="\n ORDER BY dis.title ASC "; // ! ВАЖНО
		$result=$db->fetchPairs($q);
		//		$select=$db->select();
		//		$select->from('attendance',"discipline")
		//		->where("listid = ?",$id)
		//		->group("discipline")
		//		;
		//		$result=$select->query()->fetchAll(2);
		return $result;
	}

	/** пользователи, перечисленные в аттестационном листе
	 * @param integer $id
	 * @return array (USERID=>ФИО)
	 */
	public function attendance_users($id)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT att.userid AS `key`"
		."\n , CONCAT(UCASE(p.family),' ',p.name,' ',p.otch) AS `value`"
		."\n FROM `attendance` AS att"
		."\n LEFT JOIN personal AS p"
		."\n ON p.userid=att.userid"
		."\n WHERE att.listid=".$id
		."\n GROUP BY att.userid";
		$result=$db->fetchPairs($q);
		//		$select=$db->select();
		//		$select->from('attendance',"discipline")
		//		->where("listid = ?",$id)
		//		->group("discipline")
		//		;
		//		$result=$select->query()->fetchAll(2);
		return $result;
	}

	/** добавка дисциплины к листу
	 * @param integer $listid
	 * @param integer $discipline
	 * @param array $users
	 */
	public function attendance_disciplineAdd($listid,$discipline,$users)
	{
		$now=date("Y-m-d H:i:s");

		$db = $this->getDefaultAdapter();
		$q="INSERT INTO attendance (userid,discipline,listid,modifydate) VALUES ";
		$_q=array();
		foreach ($users as $userid=>$fio)
		{
			$_q[]="(".$userid.",".$discipline.",".$listid.", '".$now."')";
		}
		$_q=implode(",",$_q);
		$q.=$_q.";";
		$db->query($q);

	}

	/** добавка пользователя к листу
	 * @param integer $listid
	 * @param integer $userid
	 * @param array $disciplines
	 */
	public function attendance_userAdd($listid,$userid,$disciplines)
	{
		$now=date("Y-m-d H:i:s");

		$db = $this->getDefaultAdapter();
		$q="INSERT INTO attendance (userid,discipline,listid,modifydate) VALUES ";
		$_q=array();
		foreach ($disciplines as $discipline=>$title)
		{
			$_q[]="(".$userid.",".$discipline.",".$listid.", '".$now."')";
		}
		$_q=implode(",",$_q);
		$q.=$_q.";";
		$db->query($q);

	}

	/** убирает дисциплины из аттестационного листа
	 * @param integer $listid
	 * @param array $disciplines (id,id,id)
	 */
	public function attendance_disciplineRemove($listid,$disciplines)
	{
		$db = $this->getDefaultAdapter();
		$table="attendance";
		$where[] = $db->quoteInto('listid = ?', $listid);
		//		$disciplines=array_keys($disciplines);
		$disciplines=implode(",",$disciplines);
		$where[] = $db->quoteInto('discipline IN (?)', $disciplines);
		$db->delete($table,  $where);

	}

	/** убирает пользователей из аттестационного листа
	 * @param integer $listid
	 * @param array $users (id,id,id)
	 */
	public function attendance_usersRemove($listid,$users)
	{
		$db = $this->getDefaultAdapter();
		$table="attendance";
		$where[] = $db->quoteInto('listid = ?', $listid);
		//		$disciplines=array_keys($disciplines);
		$users=implode(",",$users);
		$where[] = $db->quoteInto('userid IN (?)', $users);
		$db->delete($table,  $where);

	}

	// выходной контроль, экз. листы/ведомости
	/** список
	* @param integer $kurs
	* @param integer $groupid
	* @param array $studyYearStart
	* @return array
	*/
	public function outControl_list($kurs,$semestr,$groupid,$studyYearStart)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT ol.id, ol.numb, ol.type, ol.discipline, ol.kurs, ol.semestr"
		."\n , ol.groupid, ol.studyYearStart"
		."\n , ol.groupid, ol.doctype"
		."\n , ol.state, ol.approved"
		."\n , DATE_FORMAT(ol.eventdate, '%d-%m-%Y') AS eventdate, ol.createdate"
		."\n , d.title AS disTitle, t.title AS typeTitle, dc.title AS docTypeTitle"
		."\n , gr.title AS groupTitle"
		."\n FROM ocontrol_list AS ol"
		."\n LEFT JOIN disciplinestud AS d"
		."\n ON d.id=ol.discipline"
		."\n LEFT JOIN studoutcontrols AS t"
		."\n ON t.id=ol.type"
		."\n LEFT JOIN ocontrol_doctypes AS dc"
		."\n ON dc.id=ol.doctype"
		."\n LEFT JOIN studgroups AS gr ON gr.id=ol.groupid"
		."\n WHERE ol.kurs=".$kurs
		."\n AND ol.semestr=".$semestr;
		if ($groupid>0)$q.="\n AND ol.groupid=".$groupid;
		$q.="\n AND ol.studyYearStart=".$studyYearStart
		."\n ORDER BY d.title ASC, t.title ASC, dc.title ASC, ol.numb ASC, ol.eventdate ASC"
		;
		//		echo $q;die();
		$result=$db->fetchAll($q);

		return $result;
	}

	/**
	 * @param array $data(
		"discipline"=> ID Дисциплиыны
		"numb"=> номер документа
		"type"=> тип вых. контроля
		"kurs"=>курс
		"semestr"=> семестр
		"studyYearStart"=> год начала уч. года
		"groupid"=> ID группы
		"doctype"=> тип документа
		"eventdate"=> дата мероприятия
		"сreatedate"=> дата генерации
		);
	 * @return integer
	 */
	public function outControl_listAdd($data)
	{
		$db = $this->getDefaultAdapter();
		$table="ocontrol_list";
		$db->insert($table,$data);
		$insertedID=$db->lastInsertId($table);
		return $insertedID;
	}


	/**
	 * создание документа выходного контроля и пропиывание туда студентов используя транзакции
	 * @param array array $data(
		"discipline"=> ID Дисциплиыны
		"numb"=> номер документа
		"type"=> тип вых. контроля
		"kurs"=>курс
		"semestr"=> семестр
		"studyYearStart"=> год начала уч. года
		"groupid"=> ID группы
		"doctype"=> тип документа
		"eventdate"=> дата мероприятия
		"сreatedate"=> дата генерации
		);
	 * @param array $users перечень ID пользователей
	 * @return string
	 */
	public function outControl_listAddComplex($data,$users)
	{
		$db = $this->getDefaultAdapter();
		$result=array();
		$db->beginTransaction();
		try
		{
			$listid=$this->outControl_listAdd($data);
			$aff=$this->outControl_assign($users, $listid);
			$result["status"]=true;
			$result["msg"]="OK";
			$result["listid"]=$listid;
			$result["aff"]=$aff;
			$db->commit();
		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["errorMsg"]=$e->getMessage();
			$db->rollback();
		}

		return $result;

	}

	/**
	 * Список преподавателей для данной дисциплины
	 * @param integer $discipline
	 * @return array ID=>FIO
	 */
	public function ocontrol_getTeachers($discipline)
	{
		$db=$this->getDefaultAdapter();
		$s=$db->select();
		$s->from(array("t"=>"teachers"),array("key"=>"userid"));
		$s->joinLeft(array("p"=>"personal"),"p.userid=t.userid",
				array("value"=>"CONCAT_WS(' ',p.family,p.name,p.otch)")
		);
		$s->where("t.discipline=".$discipline);
		$s->order("value ASC");
		$result=$db->fetchPairs($s);




		return $result;
	}

	public function outcontrol_listApproveChange($id,$approveNew)
	{
		$db = $this->getDefaultAdapter();
		//		$now=date("Y-m-d H:i:s");

		$data=array(
				"approved"=>$approveNew
		);
		$where[]= $db->quoteInto('id = ?', $id);
		$aff=$db->update("ocontrol_list",$data,$where);
		return $aff;

		;
	}

	/** информация о документе
	 * @param integer $id
	 * @return array
	 */
	public function outControl_listInfo($id)
	{
		$db = $this->getDefaultAdapter();
		$q="SELECT li.*, gos.spec,gos.division,gr.osnov,gr.gos_params AS gosparam"
		."\n , d.title AS disTitle, dt.title AS docTypeTitle"
		."\n , oc.title AS contolTitle, gr.title AS groupTitle"
		."\n , f.title AS facultTitle"
		."\n FROM ocontrol_list AS li"
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

	/** перечень студней в документе
	 * @param integer $id
	 * @return array
	 */
	public function outControl_listDetails($id)
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
	 * удаление документа вых. контроля и связанных с ним записей о результатах
	 * @param integer $id
	 * @return array
	 */
	public function outControl_delComplex($id)
	{
		$db = $this->getDefaultAdapter();

		$db->beginTransaction();
		try
		{
			// лист
			$table="ocontrol_list";
			$where = $db->quoteInto('id = ?', $id);
			$db->delete($table,  $where);
			// все прикрепленные студни на данный лист удаляться благодаря внешним ключам
			//			$table="ocontrol";
			//			$where = $db->quoteInto('listid = ?', $id);
			//			$db->delete($table, $where);
			;
			$db->commit();
		}
		catch (Zend_Exception $e)
		{
			$db->rollback();
		}

		return $res;
	}


	/** привязка пользоватейлей к документу
	 * вызывать только внутри транзакции
	 * @param array $userlist
	 * @param integer $listID
	 * @return number
	 */
	public function outControl_assign($userlist,$listID)
	{
		$now=date("Y-m-d H:i:s");

		$db = $this->getDefaultAdapter();
		$table="ocontrol";
		$aff=0;
		foreach ($userlist AS $userid)
		{
			$data=array(
					"userid"=>$userid,
					"listid"=>$listID,
					"modifydate"=>$now
			);
			$aff+=$db->insert($table,$data);
		}
		;
		return $aff;
	}

	/** смена личных достижений
	 * @param integer $docid
	 * @param integer $userid
	 * @param integer $ratingBally
	 * @param integer $result
	 * @param integer $itog
	 * @return integer
	 */
	public function outControl_personChange($docid,$userid,$ratingBally,$result,$itog)
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

	public function getSpravTypic($tablename)
	{
		$db = $this->getDefaultAdapter();
		$sql="SELECT `id` AS `key`, `title` AS `value` FROM ".$tablename;
		//		$select=$db->select()
		//		->from(array("T"=> $tablename),
		//			array("key"=>"id","value"=>"title"));
		//		;
		//		$stmt = $select->query(Zend_Db::FETCH_NUM);
		//		$result=$stmt->fetchAll();
		$result=$db->fetchPairs($sql);
		return $result;

	}

	private function setGosParams()
	{
		$select=$this->getDefaultAdapter()->select();
		$select->from(array("gos"=>"gos_params"),
				array("gos.id","spec","division","facult","years2study","yearStart","yearLast"
						,"CONCAT(LEFT(d.title,4),', ',s.numeric_title,' ',s.title) AS gosTitle"));
		$select->joinLeft(array("s"=>"specs"), "s.id=gos.spec","CONCAT(s.numeric_title,' ',s.title) AS specTitle");
		$select->joinLeft(array("d"=>"division"), "d.id=gos.division",array("divTitle"=>"title"));
		$select->where("gos.facult=".$this->facultId);
		// 		$select->where("gos.division=".$division);
		// @TODO условия во времени действия ГОСа

		$select->order("gos.division ASC");
		$select->order("specTitle ASC");
		//		$result= $this->db->fetchAssoc($select);
		$this->gosParams=$this->getDefaultAdapter()->fetchAssoc($select);
		;
	}

}