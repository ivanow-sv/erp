<?php

class Service extends Zend_Db_Table
{

	private $db;
	//	private $dekanat;
	private $person;
	private $user;
	private $role;
	private $abitur;
	private $docus;
	private $abiturRole=30;


	public function __construct()
	{
		$db = $this->getDefaultAdapter();
		$this->db=$db;
		Zend_Loader::loadClass('Person');
		Zend_Loader::loadClass('Users');
		Zend_Loader::loadClass('Abiturs');
		Zend_Loader::loadClass('Docus');
		Zend_Loader::loadClass('Roles');
		$this->person=new Person();
		$this->user=new Users();
		$this->role=new Roles();
		// @FIXME брать из ABOUT.CAMPAIGNYEAR
		$this->abitur=new Abiturs();
		$this->docus=new Docus();
	}

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


	/** выполнить запрос
	 * @param string $sql
	 * @return Zend_Db_Statement_Interface
	 */
	public function execSql($sql,$bind=array())
	{
		try
		{
			return $this->db->query($sql,$bind);

		}
		catch (Zend_Exception $e)
		{
			return $e->getMessage();
		}
	}

	/**
	 * выполнгить запрос и вернутть результаты
	 * @param string $sql
	 * @param unknown_type $bind
	 * @return Ambigous <multitype:, string, boolean, mixed>
	 */
	public function fetchSql($sql,$bind=array())
	{
		try
		{

			return $this->db->fetchAll($sql,$bind);

		}
		catch (Zend_Exception $e)
		{
			return $e->getMessage();
		}
	}

	public function getAiturPrivateInfo($abiturID)
	{
		$info=$this->abitur->getPrivateInfo($abiturID);
		return $info;
	}

	/**
	 * Информация из abiturients
	 * @return multitype:string Ambigous <multitype:, string, boolean, mixed>
	 */
	public function getAbitursAll() {
		$sql="SELECT * FROM abiturients";
		$rows = $this->db->fetchAll($sql);
		return $rows ;

	}

	public function updateAbitur2010($data,$id)
	{
		try
		{
			$result=$this->db->update("abitur_2010",$data,"id=".$id);
			return array("state"=>"OK","text"=>$result);
		}
		catch (Zend_Exception $e)
		{
			return array("state"=>"FAIL","text"=>$e->getMessage());
		}



	}

	public function addAbiturReg($userid,$regNo)
	{
		$data["userid"]=$userid;
		$data["id"]=$regNo;

		$data["created"]=date("Y-m-d H:i:s");
		try
		{
			$result=$this->db->insert("abitur_2010",$data);
			return array("state"=>"OK","text"=>$result);
		}
		catch (Zend_Exception $e)
		{
			return array("state"=>"FAIL","text"=>$e->getMessage());
		}
	}

	/**
	 * информация об экзаменах абитура. таблица ABITURIENTS
	 * @param integer $id
	 * @return array
	 */
	public function getAbiturExams($abiturRegNo)
	{
		$select=$this->db->select()->from(array("a"=> "exams"));
		$select->where("abitur = ".$abiturRegNo);
		$stmt = $this->db->query($select);
		$result = $stmt->fetchAll();
		return $result;
	}
	/**
	 * информация о том, куда подал. таблица ABITUR_FILED
	 * @param integer $abiturRegNo
	 * @return array
	 */
	public function getAbiturFiled($abiturRegNo)
	{
		$select=$this->db->select()->from(array("af"=> "abitur_filed"));
		$select->where("abitur = ".$abiturRegNo);
		$stmt = $this->db->query($select);
		$result = $stmt->fetchAll();
		return $result;
	}
	/**
	 * информация о результатах комиссий о данной заявке на специальность. таблица RESULTS
	 * @param integer $af_id - из старой таблицы ABITUR_FILED
	 * @return array
	 */
	public function getAbiturResults($af_id)
	{
		$select=$this->db->select()->from(array("rez"=> "results"));
		$select->where("abitur_id = ".$af_id);
		$stmt = $this->db->query($select);
		$result = $stmt->fetchAll();
		return $result;
	}
	/**
	 * информация о результатах комиссий о данной заявке на специальность. таблица RESULTS
	 * @param integer $af_id - из старой таблицы ABITUR_FILED
	 * @return array
	 */
	public function getAbiturResultsAll($abiturRegNo)
	{
		$select=$this->db->select()->from(array("rez"=> "results"),"rez.*");
		$select->where("af.abitur= ".$abiturRegNo);
		$select->joinLeft
		(
				array("af"=>"abitur_filed"),
				"af.id=rez.abitur_id"
		);
		$stmt = $this->db->query($select);
		$result = $stmt->fetchAll();
		return $result;
	}

	/**
	 * Перенос абитуриента на новые рельсы - привязки по USERID, а не по ABITUR_ID
	 * @param array $data строка из ABITURIENTS
	 * @return string лог обработки
	 */
	public function abiturTransfer($data)
	{
		$log=array();
		// узнаем результаты экзаменов
		$exams=$this->getAbiturExams($data["id"]);
		// узнаем куда подал
		$filed=$this->getAbiturFiled($data["id"]);
		///// узнаем результаты комиссий
		$rez=$this->getAbiturResultsAll($data["id"]);


		// Старт транзакции явным образом
		$this->db->beginTransaction();
		try {
			// 2.1. имя учетки = «рег.номер-год»
			$login=$data["id"]."-"."2010";
			$result=$this->addUser($login);
			$userid=($result["state"]==="OK")?(int)$result["text"]:false;

			// внесем в ABITUR_2010 рег № абитуриента и его USERID
			//			$log.=" + создание записи в ABITUR_2010 с ID=".$data["id"].": ";
			$result=$this->addAbiturReg($userid,$data["id"]);
			$regNo=($result["state"]==="OK")?(int)$result["text"]:false;

			// внос персональных данных в PERSONAL
			$d=array();
			$r=array();
			$d["family"]		= $data["family"];
			$d["name"]			= $data["name"];
			$d["otch"]			= $data["otch"];
			$d["gender"]		= $data["gender"];
			$d["identity"]		= $data["identity"];
			$d["iden_serial"]	= $data["iden_serial"];
			$d["iden_num"]		= $data["iden_num"];
			$d["iden_give"]		= $data["iden_give"];
			$d["iden_reg"]		= $data["iden_reg"];
			$d["iden_city"]		= $data["iden_city"];
			$d["iden_live"]		= empty($data["iden_live"])?NULL:$data["iden_live"];
			$d["birth_date"]	= $data["birth_date"];
			$d["birth_place"]	= $data["birth_place"];

			// @TODO образование в отдельную таблицу
			$d["edu_doc"]		= $data["edu_doc"];
			$d["edu_serial"]	= $data["edu_serial"];
			$d["edu_num"]		= $data["edu_num"];
			$d["edu_give"]		= $data["edu_give"];
			$d["edu_info"]		= $data["edu_info"];
			$d["edu_date"]		= $data["edu_date"];
			$d["edu_res"]		= $data["edu_res"];

			$d["category"]= $data["category"];
			$d["category_detail"]= $data["category_detail"];
			$d["award"]= empty($data["award"])?NULL:$data["award"];
			$d["olympic_detail"]= $data["olympic_detail"];
			$d["room"]= $data["room"];
			$d["lang"]= $data["lang"];
			$d["photos"]= $data["photos"];
			$d["misc"]= $data["misc"];
			$d["phone"]= $data["phone"];
			$d["createdate"]= $data["createdate"];
			$d["army"]= $data["army"];
			$d["work"]= $data["work"];
			$d["work_age"]= $data["work_age"];

			$result=$this->createPrivateInfo($userid,$d);

			// перепишем результаты экзаменов в EXAMS2
			foreach ($exams as $k=>$ex)
			{
				$d=array();
				$r=array();
				$d["userid"]=$userid;
				$d["exam"]=$ex["exam"];
				$d["exam_type"]=(int)$ex["exam_type"];
				$d["exam_date"]=$ex["exam_date"];
				$d["exam_value"]=$ex["exam_value"];
				$d["ege_svid"]=$ex["ege_svid"];
				$r=$this->db->insert("exams2",$d);
			}
			// переберем все места куда подал:
			foreach ($filed as $k=>$af)
			{
				$d=array();
				$r=array();
				///// внесем в ABITUR_FILED2
				$d["userid"]	=	$userid;
				$d["spec"]		=	$af["spec"];
				$d["subspec"]	=	empty($af["subspec"])?NULL:$af["subspec"];
				$d["createdate"]=	$af["createdate"];
				$d["taketime"]	=	$af["taketime"];
				$d["movetime"]	=	$af["movetime"];
				$d["doc_copy"]	=	$af["doc_copy"];
				$d["division"]	=	$af["division"];
				$d["payment"]	=	$af["payment"];
				$d["osnov"]		=	$af["osnov"];
				$d["order"]		=	$af["order"];
				$r=$this->db->insert("abitur_filed2",$d);
				// ID заявки на специальность
				$af_id_new=	$r>0?$this->db->lastInsertId("abitur_filed2","id"):false;
				///// внесем результаты в RESULTS2
				foreach ($rez as $j=> $re)
				{
					$d=array();
					$r=array();
					$d["abitur_id"]	=	$af_id_new;
					$d["komis_id"]	=	$re["komis_id"];
					$d["state_id"]	=	empty($re["state_id"])?NULL:$re["state_id"];
					$r=$this->db->insert("results2",$d);
					$aa=$this->db->lastInsertId("results2","id");
				}
			}
			$log["state"]="OK";
			$log["text"]="Успешно";

			// Если все запросы были произведены успешно, то транзакция фиксируется,
			// и все изменения фиксируются одновременно
			$this->db->commit();
			return $log;
		} catch (Exception $e) {
			// Если какой-либо из этих запросов прошел неудачно, то вся транзакция
			// откатывается, при этом все изменения отменяются, даже те, которые были
			// произведены успешно.
			// Таким образом, все изменения либо фиксируются, либо не фиксируется вместе.
			$this->db->rollBack();
			$log["state"]=false;
			$log["text"]="\n !!! ОШИБКА !!! ";
			//			$log["text"].="\n Запросы: ".$qq;
			$log["text"].="\n ".$e->getMessage()."\n ".$e->getLine()."\n ".$e->getTraceAsString();
			$log["text"].="\n данные из ABITURIENTS";
			$log["text"].="\n ".print_r($data,true);
			$log["text"].="\n данные из EXAMS";
			$log["text"].="\n ".print_r($exams,true);
			$log["text"].="\n данные из ABITUR_FILED";
			$log["text"].="\n ".print_r($filed,true);
			$log["text"].="\n данные из RESULTS";
			$log["text"].="\n ".print_r($rez,true);

			return $log;
			//			echo $e->getMessage();
		}
	}


	public function getAbitursFresh($year)
	{

		$sql = "SELECT a.id,a.exam_list"
		."\n , a.family, a.name, a.otch"
		."\n ,rez.komis_id, YEAR(a.createdate) AS abitur_year"
		."\n , lang.title AS langTitle, pay.title AS payTitle, IL.title AS idenTitle"
		."\n FROM abiturients AS a";

		$sql.=' LEFT JOIN abitur_filed AS af ON af.abitur=a.id';
		$sql.=' LEFT JOIN results AS rez ON rez.abitur_id=af.id';
		$sql.="\n LEFT JOIN lang"
		."\n ON lang.id=a.lang"
		."\n LEFT JOIN payment AS pay"
		."\n ON pay.id=af.payment"
		."\n LEFT JOIN iden_live AS IL"
		."\n ON IL.id=a.iden_live"
		;
		// текущая специальность
		//		$sql .= ' WHERE af.spec ='.$spec;
		// специальности факультета
		//		$sql.="\n AND af.spec IN (".$this->getSpecsOnFacult_string().")";
		// зачисленные
		$sql .= "\n WHERE rez.state_id =6";
		// отделение
		//		$sql .= ' AND af.division ='.$div;
		// форма обучения
		//		$sql .= ' AND af.osnov ='.$osnov;
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
		//		// @TODO проверить это - должно учитывать шо абитуром  может быть и дважды
		//		// т.е. среди абитуров этого года
		." AND YEAR(a.createdate)=".$year.")"
		;

		// сортировка
		$sql.="\n ORDER BY a.id ASC";
		$rows = $this->db->fetchAll($sql);
		return array("sql"=>$sql,"result"=>$rows);
	}

	/**
	 * приказ из протокола в документ DOCUS
	 * @param array $data строка из prot
	 * @return array ("state","text") результат выполнения и ID (или текст ошибки)
	 */
	public function docus_prot2docus($data)
	{
		$num=explode("-", $data["prikazNum"]);
		$titleNum=(int)$num[0];
		$titleLetter=$num[1];
		$titleDate=$data["prikazDate"];
		$author=1; // админ
		$type=2; // приказ
		$comment="Патч №2";
		try
		{
			$result=$this->docus->addRecord($titleNum, $titleLetter, $titleDate, $author, $type,$comment);
			return array("state"=>"OK","text"=>$result);
		}
		catch (Zend_Exception $e)
		{
			return array("state"=>"FAIL","text"=>$e->getMessage());
			//			return ;
		}
		return $result;
	}

	public function getAbitursFresh_withInfo($year)
	{
		$sql = "SELECT a.id,a.exam_list"
		."\n , a.family, a.name, a.otch"
		."\n , a.gender"
		."\n , a.identity, a.iden_serial, a.iden_num, a.iden_give, a.iden_reg, a.iden_city, a.iden_live"
		."\n , a.birth_date, a.birth_place"
		."\n , a.edu_doc, a.edu_serial, a.edu_num, a.edu_give, a.edu_info, a.edu_date, a.edu_res"
		."\n , a.category, a.category_detail"
		."\n , a.award, a.olympic_detail"
		."\n , a.room, a.lang, a.photos, a.misc"
		."\n , a.phone, a.createdate, a.army, a.work, a.work_age"
		."\n ,rez.komis_id, YEAR(a.createdate) AS abitur_year"
		."\n , lang.title AS langTitle, pay.title AS payTitle, IL.title AS idenTitle"
		."\n FROM abiturients AS a";
		$sql.=' LEFT JOIN abitur_filed AS af ON af.abitur=a.id';
		$sql.=' LEFT JOIN results AS rez ON rez.abitur_id=af.id';
		$sql.="\n LEFT JOIN lang"
		."\n ON lang.id=a.lang"
		."\n LEFT JOIN payment AS pay"
		."\n ON pay.id=af.payment"
		."\n LEFT JOIN iden_live AS IL"
		."\n ON IL.id=a.iden_live"
		;
		// зачисленные
		$sql .= "\n WHERE rez.state_id =6";
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
		//		// @TODO проверить это - должно учитывать шо абитуром  может быть и дважды
		//		// т.е. среди абитуров этого года
		." AND YEAR(a.createdate)=".$year.")"
		;

		// сортировка
		$sql.="\n ORDER BY a.id ASC";
		$rows = $this->db->fetchAll($sql);
		return array("sql"=>$sql,"result"=>$rows);
	}

	public function addUser($login)
	{
		try
		{
			$result=$this->user->addUserByLogin($login,$this->abiturRole);
			return array("state"=>"OK","text"=>$result);
		}
		catch (Zend_Exception $e)
		{
			return array("state"=>"FAIL","text"=>$e->getMessage());
			//			return ;
		}
	}

	/**
	 * запись в PERSONPROCESS что абитура зачислили по данному документу
	 * @param unknown_type $userid
	 * @return unknown
	 */
	public function person_addProcessRecord($userid,$documentid,$operation,$comment,$param='',$paramValue='')
	{
		try
		{
			$result=$this->person->addRecord($userid, $operation, $documentid,$comment,$param,$paramValue,1);
			return array("state"=>"OK","text"=>$result);

		}
		catch (Zend_Exception $e)
		{
			return array("state"=>"FAIL","text"=>$e->getMessage());
		}

	}

	/**
	 * модификация записей в PERSONPROCESS
	 * @param array $where условия (столбец => значение)
	 * @param array $data новые значение (столбец => значение)
	 * @return multitype:string unknown |multitype:string NULL
	 */
	public function person_updateProcessRecords($where,$data)
	{
		//if (!is_array($where)) $where= array($where);
		$_where=array();
		foreach ($where  as $column => $value)
		{
			$_where[]= $this->db->quoteInto($column.' = ?', $value);
		}

		try
		{
			$this->person->updateRecords($_where, $data);
			return array("state"=>"OK","text"=>$result);

		}
		catch (Zend_Exception $e)
		{
			return array("state"=>"FAIL","text"=>$e->getMessage());
		}
		;
	}

	public function roleChange($userid,$newrole)
	{
		try
		{
			$result=$this->user->setRole($userid,$newrole);
			return array("state"=>"OK","text"=>$result);

		}
		catch (Zend_Exception $e)
		{
			return array("state"=>"FAIL","text"=>$e->getMessage());
		}

	}

	public function getPrikazByAbitur($abitur_id)
	{
		$q="SELECT af.abitur,p.prikazNum, p.prikazDate, d.id AS documentid"
		."\n FROM abitur_filed AS af"
		."\n LEFT JOIN results AS rez"
		."\n ON rez.abitur_id=af.id "
		."\n LEFT JOIN prot AS p"
		."\n ON p.prot_id=rez.komis_id"
		."\n LEFT JOIN docus AS d"
		// если "НОМЕР-БУКВА-ДАТА" в DOCUS такие же как и "НОМЕР_С_БУКВОЙ-ДАТА" в PROT
		."\n ON CONCAT(d.titleNum,'-',d.titleLetter,'-',d.titleDate) LIKE CONCAT(p.prikazNum,'-',p.prikazDate)"
		."\n WHERE af.abitur=".$abitur_id
		."\n AND rez.state_id=6";

		$row = $this->db->fetchRow($q);
		return $row;

	}

	/**
	 * Добавление строки в STUDENTS
	 * @param integer $userid
	 * @param array $data BIND-данные
	 * @return integer затронуто записей
	 */
	public function studentCreate($userid,$data)
	{
		$data["userid"]=$userid;
		try
		{
			$result=$this->db->insert("students",$data);
			return array("state"=>"OK","text"=>$result);
		}
		catch (Zend_Exception $e)
		{
			return array("state"=>"FAIL","text"=>$e->getMessage());
		}
	}

	public function createPrivateInfo($userid,$info)
	{
		return $this->user->personalInfoCreate($userid,$info);
	}

	public function personalInfoChange($userid,$info)
	{
		try
		{
			return $this->user->personalInfoChange($userid,$info);
		}
		catch (Zend_Exception $e)
		{
			return array("state"=>"FAIL","text"=>$e->getMessage());
		}

	}

	/**
	 * INSERT SELECT по условию второй таблицы (сравнение LIKE)
	 * @param string $table куда
	 * @param string $table2 откуда для условия
	 * @param array $data (field=>value)
	 * @param array $whereBind для table2 (field=>value)
	 * @return boolean
	 */
	//	public function insertSelect($table,$table2,$data,$whereBind)
	//	{
	//		$res=array();
	//		try
	//		{
	//			$fields="(";
	//			$subquery="SELECT ";
	//			foreach ($data as $field=>$v)
	//			{
	//				$_fields[]="`".$field."`";
	//				$fields4Select[]="'".$v."'";
	//			}
	//			$fields.=implode(",",$_fields).")";
	//			$subquery.=implode(",",$fields4Select);
	//			$subquery.=" FROM ".$this->db->quoteIdentifier($table2);
	//			$wh='';
	//			foreach ($whereBind as $k=>$v)
	//			{
	//				$wh="`".$k."` LIKE '".$v."'";
	//				;
	//			}
	//			$subquery.=" WHERE ".$wh;
	//			$q = "INSERT INTO ".$this->db->quoteIdentifier($table)." ".$fields." " ;
	//			$q.=$subquery;
	//			echo $q."<br>";
	//			$this->db->query($q);
	////			$q.=
	//			//			$this->db->insert($table, $data);
	//			$res["status"]=true;
	//		}
	//		catch (Zend_Exception $e)
	//		{
	//			$res["status"]=false;
	//			$res["errorMsg"]=$e->getMessage();
	//		}
	//		return $res;
	//		;
	//	}

	public function insert($table,$data)
	{
		$res=array();
		try
		{
			$this->db->insert($table, $data);
			$res["status"]=true;
		}
		catch (Zend_Exception $e)
		{
			$res["status"]=false;
			$res["errorMsg"]=$e->getMessage();
		}
		return $res;
		;
	}

	public function update($table,$data, $where)
	{

		return $this->db->update($table,$data, $where);
		;
	}


	/**
	 * получение аннотаций к модулям
	 * @param string имя модуля
	 * @return string
	 * @MOVED TO ACLMODEL::getModuleAnnotation($module)
	 */
// 	public function annotations_getText($module)
// 	{
// 		$select=$this->db->select()->from(array("res"=> "acl_resources"),null);
// 		$select->joinLeft
// 		(
// 				array("annot"=>"acl_res_annot"),
// 				"res.id=annot.resid","annotation"
// 		);
// 		// указанный модуль
// 		$select->where("res.module LIKE '".$module."'");
// 		// контроллер на задан
// 		$select->where("res.controller =''");

// 		$stmt = $this->db->query($select);
// 		$result = $stmt->fetchColumn();
// 		return $result;
// 	}

	/**
	 * @param integer $id
	 * @return string
	 */
	public function annotations_getTextByResID($id)
	{
		$select=$this->db->select()->from(array("annot"=> "acl_res_annot"),"annotation");
		$select->where("annot.resid = ".$id);

		$stmt = $this->db->query($select);
		$result = $stmt->fetchColumn();
		return $result;
	}

	/**
	 * @param string $text
	 * @param integer $id
	 * @return unknown
	 */
	public function annotations_newText($text,$resid)
	{
		$data["annotation"]=$text;
		$data["resid"]=$resid;
		try
		{
			$result=$this->db->insert("acl_res_annot",$data);
			return array("state"=>"OK","text"=>$result);
		}
		catch (Zend_Exception $e)
		{
			return array("state"=>"FAIL","text"=>$e->getMessage());
		}

	}

	/**
	 * @param string $text
	 * @param integer $id
	 * @return unknown
	 */
	public function annotations_changeText($text,$resid)
	{
		$data["annotation"]=$text;
		try
		{
			$result=$this->db->update("acl_res_annot",$data,"resid=".$resid);
			return array("state"=>"OK","text"=>$result);
		}
		catch (Zend_Exception $e)
		{
			return array("state"=>"FAIL","text"=>$e->getMessage());
		}


	}

}