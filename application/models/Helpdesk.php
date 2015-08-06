<?php
//Zend_Loader::loadClass('Typic');

class Helpdesk extends Zend_Db_Table
{
	private 	$db;
	// 	private 	$person;
	private 	$user;
	// 	private 	$role;
	private 	$aclgroups;
	private 	$aclmodel;

	private 	$_prefix	=	"hlpdsk_"; 		// префикс таблиц
	private		$_units		=	"units";  		// таблица с ед. техники
	private		$_utype		=	"utypes";  		// таблица с типами техники
	private		$_ulog		=	"ulog";			// таблица истории заявок
	private		$_uTickets	=	"utickets";		// таблица журнала заявок (привязка техники к заявке)
	// 	private		$_deps		=	"departments";	// таблица подразделений
	private		$_deps		=	"acl_groups";	// таблица подразделений
	private		$_tickets	=	"tickets";		// таблица заявок
	private		$_tLogs		=	"ticketlog";	// таблица заключений к заявкам
	private		$_tTypes	=	"tickettypes";	// таблица типов заявок

	public function __construct()
	{
		$db = $this->getDefaultAdapter();
		$this->db=$db;
		// 		Zend_Loader::loadClass('Person');
		Zend_Loader::loadClass('Users');
		// 		Zend_Loader::loadClass('Roles');
		Zend_Loader::loadClass('Aclgroups');
		Zend_Loader::loadClass('Aclmodel');
		// 		$this->person=new Person();
		$this->user=new Users();
		$this->aclmodel=new Aclmodel();
		$this->aclgroups=new Aclgroups();
		// 		$this->role=new Roles();
	}


	public function getUTypeTbl($utype)
	{
		$s=$this->db->select();
		$s->from($this->_prefix.$this->_utype,array("tbl"));
		$s->where("id=".$utype);
		$res=$this->db->fetchOne($s);
		return $res;
	}

	public function getUTypes($pairs=false)
	{
		$s=$this->db->select();
		if ($pairs)
		{
			$s->from($this->_prefix.$this->_utype,array("key"=>"id","value"=>"title"));
			$res=$this->db->fetchPairs($s);
		}
		else
		{
			$s->from($this->_prefix.$this->_utype);
			$res=$this->db->fetchAll($s);
		}

		return $res;
	}

	public function getDepartments($group)
	{
		return $this->aclmodel->getGroupTree(array($group),"DOWN");
	}

	public function getGroup($userid)
	{
		return $this->aclmodel->getUserGroups($userid);
		;
	}

	/**
	 * информация об отделе
	 * @param integer $id
	 * @return Ambigous <multitype:, mixed>
	 */
	public function getDepInfo($id)
	{
		return $this->aclgroups->getInfo($id);

	}

	/**
	 * перечень единиц техники в отделе
	 * @param integer $id отдела
	 * @return Ambigous <multitype:, multitype:mixed >
	 */
	public function getDepUnitsList($ids)
	{
		$ids=is_array($ids)?$ids:array($ids);
		$s=$this->db->select();
		$s->from(array("u"=>$this->_prefix.$this->_units));
		$s->where("u.department IN (".implode(",",$ids).")");
		$s->joinLeft(array("ut"=>$this->_prefix.$this->_utype), "ut.id=u.utype",array("typeTitle"=>"title"));
		$s->joinLeft(array("dep"=>$this->_deps),"dep.id=u.department",array("depTitle"=>"title"));
		$s->order("dep.title ASC");
		$res=$this->db->fetchAssoc($s);
		return $res;
	}


	/**
	 * Информация о ед. техники
	 * @param integer $id
	 * @return Ambigous <multitype:, mixed>
	 */
	public function getUnitInfo($id)
	{
		$s=$this->db->select();
		$s->from(array("u"=>$this->_prefix.$this->_units));
		$s->where("u.id=".$id);
		$s->joinLeft(array("ut"=>$this->_prefix.$this->_utype), "ut.id=u.utype",array("typeTitle"=>"title","tbl"));
		$s->joinLeft(array("d"=>$this->_deps), "d.id=u.department",array("depTitle"=>"title"));
		$res=$this->db->fetchRow($s);
		return $res;
	}

	/** подробная информация о ед. техники
	 * @param integer $id
	 * @param string $typetbl таблица с деталями
	 * @return Ambigous <multitype:, mixed>
	 */
	public function getUnitInfoDetails($id,$typetbl)
	{
		$s=$this->db->select();
		$s->from(array("u"=>$this->_prefix.$this->_units));
		$s->joinLeft(array("det"=>$this->_prefix.$typetbl),
				"u.id=det.hlpdsk_units_id");
		$s->where("u.id=".$id);

		$res=$this->db->fetchRow($s);
		return $res;
	}

	/**
	 * имена столбцов и их комментарии, без комента игнорятся
	 * @param string $typetbl имя таблицы
	 * @return Ambigous <multitype:, multitype:mixed Ambigous <string, boolean, mixed> >
	 */
	public function getColumnsComments($typetbl)
	{
		$q="SHOW FULL COLUMNS FROM ".$this->_prefix.$typetbl;
		$_res=$this->db->fetchAssoc($q,array("key"=>"Field"));
		$result=array();
		// переберем результат и оставим только коментарии которые есть
		foreach ($_res as $col => $info)
		{
			if (!empty($info["Comment"])) $result[$col]=$info["Comment"];
		}
		return $result;
		;
	}

	public function unitINumbChange($inumb,$id)
	{
		$data=array("inumb"=>$inumb);
		$this->db->update($this->_prefix.$this->_units,$data,"id=".$id);
		;
	}

	public function unitInfoChange($data,$id,$author,$commentLog="")
	{
		try
		{
			$aff=$this->db->update($this->_prefix.$this->_units,$data,"id=".$id);
			$result["status"]=true;
			$result["affected"]=$aff;
			$this->unitsJournalAdd($id, $commentLog,$author);

		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["msg"]=$e->getMessage();

		}

		return $result;

	}

	public function unitDetailsChange($data,$tbl,$author,$commentLog="")
	{
		$id=$data["id"];
		unset($data["id"]);
		try
		{
			$aff=$this->db->update($this->_prefix.$tbl,$data,"hlpdsk_units_id=".$id);
			$result["status"]=true;
			$result["affected"]=$aff;
			$this->unitsJournalAdd($id, $commentLog,$author);

		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["msg"]=$e->getMessage();

		}

		return $result;

	}

	public function addUType($data)
	{
		try
		{
			$aff=$this->db->insert($this->_prefix.$this->_utype,$data);
			$result["status"]=true;
			$result["affected"]=$aff;

		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["msg"]=$e->getMessage();

		}

		return $result;
	}

	public function getUnitsList($utype)
	{
		$s=$this->db->select();
		$s->from($this->_prefix.$this->_units);
		$s->where("utype=".$utype);
		$result=$this->db->fetchAll($s);
		return $result;
		;
	}

	public function delUType($id)
	{
		try
		{
			$aff=$this->db->delete($this->_prefix.$this->_utype,"id=".$id);
			$result["status"]=true;
			$result["affected"]=$aff;

		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["msg"]=$e->getMessage();

		}

		return $result;
	}

	public function editUType($data,$id)
	{
		try
		{
			$aff=$this->db->update($this->_prefix.$this->_utype, $data,"id=".$id);
			$result["status"]=true;
			$result["affected"]=$aff;
		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["msg"]=$e->getMessage();
		}

		return $result;
		;
	}

	public function unitAdd($data,$tbl,$depTitle="",$author)
	{
		$data["createtime"]=new Zend_Db_Expr ("NOW()");
		// транзакция
		$this->db->beginTransaction();
		try
		{
			$aff=$this->db->insert($this->_prefix.$this->_units, $data);
			$result["status"]=true;
			$result["affected"]=$aff;
			$result["inserted"]=$this->db->lastInsertId($this->_prefix.$this->_units);
			// добавим запись в детали
			$_data=array("hlpdsk_units_id"=>$result["inserted"]);
			$this->db->insert($this->_prefix.$tbl,$_data);
			// добавим запись в журнал
			$this->unitsJournalAdd($result["inserted"],"Поставлено на учет в '".$depTitle."'",$author);
			$this->db->commit();
		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["msg"]=$e->getMessage();
			$this->db->rollBack();
		}

		return $result;
		;

	}

	public function unitsMove($ids,$depTo,$author,$depTitle="")
	{
		$data["createtime"]=new Zend_Db_Expr ("NOW()");
		// транзакция
		$this->db->beginTransaction();
		try
		{
			$_ids=implode(",",$ids);
			$data=array("department"=>$depTo);
			$this->db->update($this->_prefix.$this->_units, $data,"id IN (".$_ids.")");
			$result["status"]=true;

			// @TODO добавим запись в журнал
			foreach ($ids as $key => $value) {
				$this->unitsJournalAdd($value, "Перемещение в '".$depTitle."'",$author);	;
			}


			$this->db->commit();
		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["msg"]=$e->getMessage();
			$this->db->rollBack();
		}

		return $result;
		;

	}

	/**  получение заявок указанной единицы техники
	 * @param integer $id
	 */
	public function unitTicketsGet($id)
	{
		$s=$this->db->select();
		$s->from(array("ut"=>$this->_prefix.$this->_uTickets),null);
		$s->where("ut.unit=".$id);
		$s->joinLeft(array("t"=>$this->_prefix.$this->_tickets),"t.id=ut.ticket",array("id","created","subject","problem","closed"));
		$s->order("t.created DESC");
		$res=$this->db->fetchAll($s);
		return $res;
	}

	/**  получение журнала указанной единицы техники
	 * @param integer $id
	 */
	public function unitsJournalGet($id)
	{
		$s=$this->db->select();
		$s->from(array("ulog"=>$this->_prefix.$this->_ulog),array("createtime","comment","author","unit"));
		$s->where("ulog.unit=".$id);
		$s->joinLeft(array("u"=>"acl_users"),"u.id=ulog.author",array("login"=>"login"));
		$s->order("createtime DESC");
		$res=$this->db->fetchAll($s);
		return $res;
	}

	/** добавление записи в журнал
	 * @param integer $id
	 * @param string $comment
	 */
	public function unitsJournalAdd($id,$comment,$author)
	{
		$data["createtime"]=new Zend_Db_Expr ("NOW()");
		$data["unit"]=$id;
		$data["comment"]=$comment;
		$data["author"]=$author;
		$aff=$this->db->insert($this->_prefix.$this->_ulog,$data);

	}

	public function unitDublicate($info,$details,$author)
	{
		$this->db->beginTransaction();
		try
		{
			// 1. новая запись - копия $info
			$data=$info;
			unset($data["id"]);
			unset($data["typeTitle"]);
			unset($data["depTitle"]);
			unset($data["tbl"]);
			$data["comment"]=$data["inumb"]."-КОПИЯ\n".$data["comment"];
			$data["createtime"]=new Zend_Db_Expr ("NOW()");
			$this->db->insert($this->_prefix.$this->_units, $data);

			$result["inserted"]=$this->db->lastInsertId($this->_prefix.$this->_units);

			// 2. новые детали, копия $details
			$data=$details;
			unset($data["id"]);
			unset($data["inumb"]);
			unset($data["utype"]);
			unset($data["comment"]);
			unset($data["createtime"]);
			unset($data["parent"]);
			unset($data["department"]);
			$data["hlpdsk_units_id"]=$result["inserted"];
			$this->db->insert($this->_prefix.$info["tbl"],$data);
			$result["status"]=true;

			// 3. журнал
			$this->unitsJournalAdd($result["inserted"], "Поставлено на учет",$author);
			$this->db->commit();
		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["msg"]=$e->getMessage();
			$this->db->rollBack();
		}

		return $result;
		;

	}

	/**
	 * @param integer $days древность заявок
	 * @param integer $state состояние
	 * @param array $type типы заявки
	 * @return array
	 */
	public function tickets_GetList($days,$state,$type)
	{
		$s=$this->db->select();
		$s->from(array("t"=>$this->_prefix.$this->_tickets),
				array("*",
					"daysOld" => "CURDATE()  - DATE(t.created)"
						)
				);
		// временной интервал
		// created > (NOW() - INTERVAL 60 DAY) - созданное за последние 60 дней
		$s->where("t.created > (NOW() - INTERVAL ".$days." DAY )");
		switch ($state) {
			// открытая
			case "0":
			$s->where("t.closed =0");
			break;

			// закрытая
			case "1":
			$s->where("t.closed !=0");
			break;
			
			default:
				;
			break;
		}
		if (!empty($type)) $s->where("t.typeid IN (".implode(",",$type).")");
		$s->joinLeft(array("u"=>"acl_users"),"u.id=t.author",array("login"=>"u.login"));
		$s->joinLeft(array("gru"=>"acl_groups_has_users"),"gru.acl_users_id=u.id",array("gr_id"=>"acl_groups_id"));
		$s->joinLeft(array("gr"=>"acl_groups"),"gru.acl_groups_id=gr.id",array("gr_title"=>"title"));
		$s->order("t.created DESC");
// 		echo $s->assemble();
// 		die();
		$res=$this->db->fetchAll($s);
		return $res;
	}

	/**
	 * типы заявок
	 * @return array
	 */
	public function getTicketTypes()
	{
		$s=$this->db->select();
		$s->from($this->_prefix.$this->_tTypes,array("key"=>"id","value"=>"title"));

		return $this->db->fetchPairs($s);
		;
	}

	/**
	 * @param array $data данные формы
	 * @param integer $user userid
	 * @return unknown
	 */
	public function tickets_AddNew($data,$user)
	{
		try
		{
			$_data=array(
					"subject"=>$data["subject"],
					"problem"=>$data["problem"],
					"place"=>$data["place"],
					"tfio"=>$data["fio"],
					"tdep"=>$data["dep"],
					"author"=>$user,
					"created"=>date("Y-m-d H:i:s"),
					"typeid"=>$data["typeid"],
			);
			$this->db->insert($this->_prefix.$this->_tickets,$_data);
			$result["status"]=true;
			$result["inserted"]=$this->db->lastInsertId($this->_prefix.$this->_tickets);
		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["msg"]=$e->getMessage();
		}

		return $result;
	}
	/** добавим заключение инженера
	 * @param integer $id заявка
	 * @param string $text текст заключения
	 * @param integer $author автор заключения
	 * @return boolean
	 */

	public function tickets_verdictAdd($id,$text,$author)
	{
		$data=array("verdict"=>$text,
				"ticket"=>$id,
				"author"=>$author);
		try
		{
			$this->db->insert($this->_prefix.$this->_tLogs, $data);
			$result["status"]=true;
		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["msg"]=$e->getMessage();
		}

		return $result;
		// 		;

	}


	/**
	 * журнал заявки (заключения тех. персонала)
	 * @param integer $id
	 * @return array
	 */
	public function tickets_getLog($id,$sort="DESC")
	{
		$s=$this->db->select();
		$s->from(array("tLog"=>$this->_prefix.$this->_tLogs));
		$s->joinLeft(array("p"=>"personal"), "p.userid=tLog.author",array("fio"=>"CONCAT_WS(' ',p.family,p.name,p.otch)"));
		$s->joinLeft(array("u"=>"acl_users"), "u.id=tLog.author",array("login"=>"login"));
		$s->where("tLog.ticket=".$id);
		$s->order("tLog.created ".$sort);
		$res=$this->db->fetchAll($s);
		return $res;
	}

	/**
	 * закрытие заявки
	 * @param integer $id заявка
	 * @param string $time заявка
	 * @return number
	 */
	public function tickets_close($id,$time=false)
	{
		$time=$time===false ? date("Y-m-d H:i:s") : $time;
		$data=array("closed"=>$time);
		try
		{
			$aff=$this->db->update($this->_prefix.$this->_tickets,$data,"id=".$id);
			$result["status"]=true;
			$result["time"]=$time;
			$result["affected"]=$aff;
		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["msg"]=$e->getMessage();

		}
		return $result;
	}

	/** 
	 * установка метки "заявка неправильная"
	 * @param integer $id
	 * @param integer $mark 
	 * @param string $time время закрытия помеченной заявки
	 * @return boolean
	 */
	public function tickets_wrongToggle($id,$mark=1,$time=false)
	{
		$mark=$mark===1?1:0;
		
		$data=array("isWrong"=>$mark);

		$this->db->beginTransaction();
		try
		{
			// закроем заявку
			$r=$this->tickets_close($id,$time);
			// выставим "неправильная"
			$aff=$this->db->update($this->_prefix.$this->_tickets,$data,"id=".$id);
			$this->db->commit();
			$result["status"]=true;
		}
		catch (Zend_Exception $e)
		{
			$this->db->rollBack();
			$result["status"]=false;
			$result["msg"]=$e->getMessage();

		}
		return $result;
	}

	/**
	 * привязка техники к заявке
	 * @param integer $unit
	 * @param integer $ticket
	 * @return unknown
	 */
	public function unitAssignTicket($unit,$ticket)
	{
		$data=array(
				"unit"	=>	$unit,
				"ticket"=>	$ticket
		);
		$this->db->beginTransaction();
		try
		{
			// уберем упомниание данной комбинации
			// одна завяка - одна техника
			// 			$this->db->delete($this->_prefix.$this->_uTickets,"unit=".$unit." AND ticket=".$ticket);
			$this->db->delete($this->_prefix.$this->_uTickets,"ticket=".$ticket);
			// добавим запись
			$this->db->insert($this->_prefix.$this->_uTickets,$data);
			$this->db->commit();
			$result["status"]=true;

		}
		catch (Zend_Exception $e)
		{
			$this->db->rollBack();
			$result["status"]=false;
			$result["msg"]=$e->getMessage();

		}
		return $result;
		;
	}


	/**
	 * @param integer $id
	 * @param integer $user =0 для исполнителей
	 * @return mixed
	 */
	public function tickets_getInfo($id,$user=0)
	{
		$s=$this->db->select();
		$s->from(array("tickets"=>$this->_prefix.$this->_tickets));
		$s->joinLeft(array("ttype"=>$this->_prefix.$this->_tTypes), "ttype.id=tickets.typeid",array("ticketTypeTitle"=>"title"));
		$s->joinLeft(array("p"=>"personal"), "p.userid=tickets.author",array("fio"=>"CONCAT_WS(' ',p.family,p.name,p.otch)"));
		$s->joinLeft(array("u"=>"acl_users"), "u.id=tickets.author",array("login"=>"login"));
		$s->joinLeft(array("gru"=>"acl_groups_has_users"), "u.id=gru.acl_users_id");
		$s->joinLeft(array("grp"=>"acl_groups"), "grp.id=gru.acl_groups_id",array("depTitle"=>"grp.title"));
		// есть ли привязка к Unit ID
		$s->joinLeft(array("ut"=>$this->_prefix.$this->_uTickets),"ut.ticket=tickets.id",array("unitid"=>"unit"));
		$s->joinLeft(array("unt"=>$this->_prefix.$this->_units),"unt.id=ut.unit","inumb");
		$s->joinLeft(array("utype"=>$this->_prefix.$this->_utype),"utype.id=unt.utype",array("typeTitle"=>"title"));
		$s->where("tickets.id=".$id);
		// владалец
		if ($user>0) $s->where("tickets.author=".$user);
		// закрыта или открыта
		$s->limit(1);
		$res=$this->db->fetchRow($s);
		return $res;

		;
	}
}