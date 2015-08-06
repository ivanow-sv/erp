<?php

/**
 * @author zlydden
 *
 */
class Abiturs extends Zend_Db_Table
{

	private $db;
	private $person;								// работа с журналом операций
	private $users;									// работа с учетками
	private $sprav;									// работа со справочником
	private $tbl; 									// рег.номера абитуриентов
	private $tblPrefix		=	"abitur_";			// префикс таблиц рег. номеров
	private $currentYear;
	//	private $tblUsers		=	"acl_users";	 	// Пользователи
	private $tblPrivateInfo	=	"personal";		 	// информация
	private $tblLog			=	"personprocess";	// журнал операций
	private $tblDocs		=	"docus";			// перечень документов
	private $tblFiledInfo	=	"abitur_filed2";	// таблица с информацией о том, кудап поданы документы абитура
	private $tblResultsInfo	=	"results2"; 		// результаты рассмотрений комиссиями
	private $tblProt		=	"prot"; 			// комиссии
	private $tblSpecs		=	"specs"; 			// специальности
	private $tblSubspecs	=	"subspecs"; 		// профили
	private $tblStud		=	"students"; 		// студенты
	private $tblFilters		=	"filters";			// фильтры пользователей для списков
	private $tblFiltCond	=	"filters_cond";		// условия для фильтра
	private $tblFiltCols	=	"filters_cols";		// выбранные отображаемые колонки для фильтра
	private $prot_type		=	2; 					// тип комиссии 2= зачисление
	private $state			=	6; 					// результат комиссии 6 = зачисленные
	private $operation		=	1; 					// кода операции 1 = "зачисление"
	private $operSubGrp		=	5; 					// кода операции 5 = "перевод в группу/подгруппу"
	private $docus_type		=	2; 					// тип документа 2 ="приказ"
	private $studRole		=	8; 					// роль пользователя 8 = "студент"

	public function __construct()
	{
		Zend_Loader::loadClass('Person');
		Zend_Loader::loadClass('Users');
		Zend_Loader::loadClass('Sprav');
		$this->sprav=new Sprav("specs");
		$this->person=new Person;
		$this->users=new Users;
		$this->db = $this->getDefaultAdapter();
		$this->setYear();
	}


	/**
	 * установка года кампании и текущей таблицы абитуриентов
	 * если год =0, то брать  из настройки "текущая кампания"
	 * @param integer $year
	 */
	public function setYear($year=0)
	{
		$year=(int)$year;
		// берется из таблицы 'about' столбец 'campaignYear'
		if (empty($year))
		{
			$year_params=$this->params_getCurrentCampaign();
			$this->currentYear=$year_params;
		}
		else
		{
			$this->currentYear=$year;
		}
		$this->tbl=$this->tblPrefix.$this->currentYear;

		return;
	}

	public function getYear()
	{
		return $this->currentYear;
	}

	/**
	 * получение списка кампаний (их таблиц)
	 *
	 *  @return array (year => tableName ) последний год кампании - верхний в массиве
	 */
	public function getCampaignList()
	{
		// найдем все таблицы PREFIX_
		$sql="SHOW TABLES LIKE '".$this->tblPrefix."%'";
		$result=$this->db->fetchCol($sql);
		// переберем и оставим тока PREFIX_ЦИФРЫ
		unset($_rez);
		foreach ($result as $value)
		{
			unset($_val);
			$chk=preg_match("|".$this->tblPrefix."([0-9]+)|i",$value,$_val);
			if ($chk>0) $_rez[$_val[1]]=$_val[0];
			;
		}
		krsort($_rez);
		return ($_rez);
	}

	/**
	 * узнаем год кампании в параметрах
	 * берется из таблицы 'about' столбец 'campaignYear'
	 * @return integer
	 *
	 */
	public function params_getCurrentCampaign()
	{
		$select=$this->db->select();
		$select->from("about","campaignYear");
		$year= $this->db->fetchCol($select);
		return $year[0];
	}

	/**
	 * смена года текущей кампании
	 * @param integer $year
	 * @return array
	 */
	public function params_setCurrentCampaign($year)
	{
		try
		{
			$result["affected"]=$this->db->update("about", array("campaignYear"=>$year));
			$result["status"]=true;
		}
		catch (Zend_Exception $e)
		{
			$result["status"]=false;
			$result["errorMsg"]=$e->getMessage();
		}
		return $result;
	}

	/**
	 * создание новой кампании
	 * @param integer $year
	 * @return array
	 */
	public function params_createCampaign($year)
	{
		// перестроим типовой шаблон, заменив %TABLENAME% на нужное имя таблицы
		$_sql=$this->sql_create;
		$_tblName=$this->tblPrefix.$year;
		$_sql=preg_replace("|"."%TABLENAME%"."|iu",$_tblName,$_sql);
		// 		return $_sql;
		try
		{
			return $this->db->query($_sql);
			// 			$result["status"]=true;
		}
		catch (Zend_Exception $e)
		{
			return $e->getMessage();
		}

	}

	/**
	 * список зачисленных абитуриентов по протоколу
	 * @param integer $prot_id
	 * @return array
	 */
	public function getNewbies($prot_id,$spec)
	{
		$select=$this->db->select();
		$select->from(array("a"=>$this->tbl),array("id","userid"));
		$select->joinLeft(array("p"=>$this->tblPrivateInfo), "p.userid=a.userid",
				array("family","name","otch","birth_date"));
		$select->joinLeft(array("af"=>$this->tblFiledInfo), "af.userid=a.userid",array("spec"));
		$select->joinLeft(array("rez"=>$this->tblResultsInfo),"rez.abitur_id=af.id",array("komis_id"));
		$select->joinLeft(array("pr"=>$this->tblProt),
				"pr.prot_id=rez.komis_id",
				array("prot_num","prot_date"));
		$select->joinLeft(array("s"=>$this->tblSpecs),
				"s.id=af.spec",
				array("specTitle"=>'CONCAT(numeric_title," ",title)'));
		// зачисленные
		$select->where("rez.state_id= ".$this->state);
		// учесть не "забратых"
		$select->where("a.taketime LIKE '0000-00-00 00:00:00'");

		// отсеять тех, кто уже в приказах этого года
		$subquery=$this->db->select()
		->from($this->tblLog,"userid")
		->where("operation=".$this->operation) // зачислен
		->where("YEAR(createdate)=".$this->currentYear) // вы этом году
		;
		// нету среди зачисленных в приказах этого года
		$select->where ("a.userid NOT IN ( $subquery )");

		if ($prot_id>0)	$select->where("rez.komis_id = ".$prot_id);
		if ($spec>0)	$select->where("af.spec = ".$spec);

		$select->order("pr.prot_date");
		$select->order("specTitle");
		$select->order("p.family");
		$result= $this->db->fetchAssoc($select);
		//		$pp= $this->db->getProfiler();
		//		$pp->getQueryProfiles();
		//		echo "<pre>".print_r($pp,true)."</pre>";
		//		$result = $stmt->fetchAll();
		return $result;

	}

	/**
	 * список протоколов на зачисление текущего года
	 */
	public function getProtList()
	{
		// SELECT * FROM `prot` where prot_type=2 AND YEAR(prot_date)=2011 ORDER BY prot_date ASC
		$select=$this->db->select()
		->from($this->tblProt,array(
				"key"	=>"prot_id",
				'value'	=>'CONCAT(prot_num," от ",prot_date)',
		));
		$select->where("prot_type = ".$this->prot_type);
		$select->where("YEAR(prot_date) = ".$this->currentYear);
		$select->order("prot_date ASC");
		$result= $this->db->fetchPairs($select);
		//		$result = $stmt->fetchAll();
		return $result;
	}

	/**
	 * список специальностей
	 * @return array
	 */
	public function getSpecs()
	{
		$select=$this->db->select()
		->from($this->tblSpecs,array(
				"key"	=>"id",
				"value"	=>'CONCAT(numeric_title," ",title)',
		));
		//		$select->where("year = ".$this->currentYear);
		$select->order("numeric_title ASC");
		$result= $this->db->fetchPairs($select);
		return $result;

	}

	/**
	 * получение списка напр. подготовки вместе с профилями текущей кампании
	 */
	function getSubSpecsTreeCampaign()
	{
		$s=$this->db->select()
		->from(array("ss"=>$this->tblSubspecs),array("key"=>"id","value"=>"CONCAT(s.numeric_title,' ',s.title,'--',ss.title)"))
		->joinLeft(array("s"=>$this->tblSpecs), "ss.specid=s.id")
		->where("ss.specid IN (SELECT spec FROM gos_params WHERE yearLast IS NULL)")
		->order("s.numeric_title ASC")
		->order("ss.title ASC")
		;
		;
		$rows=$this->db->fetchPairs($s);
		
		
		// пары subspec.id --> название
// 		$q="SELECT ss.id,ss.specid, CONCAT(s.numeric_title,' ',s.title, ' -- ',ss.title) AS titlle,"
// 			."\n s.title AS stitle, s.numeric_title, ss.title AS sstitle"
// 			."\n FROM #__subspecs AS ss"
// 			."\n LEFT JOIN #__specs AS s ON ss.specid=s.id"
// 			."\n WHERE ss.specid IN (SELECT spec FROM #__gos_params WHERE yearLast IS NULL)"
// 			."\n ORDER BY s.numeric_title ASC, s.title ASC";
	
// 		$this->database->setQuery($q);
// 		$rows=$this->database->loadAssocList("id");
		return $rows;
	}

	/**
	 * получение списка напр. подготовки текущей кампании
	 */
	function getSpecsTreeCampaign()
	{
		$s=$this->db->select()
		->from($this->tblSpecs,array("key"=>"id","value"=>"CONCAT(numeric_title,' ',title)"))
		->where("id IN (SELECT spec FROM gos_params WHERE yearLast IS NULL)")
		->order("numeric_title ASC");
		;
		$rows=$this->db->fetchPairs($s);
		return $rows;
	}
	
	/**
	 * список приказов этого года
	 * @return array
	 */
	public function getOrders()
	{
		$select=$this->db->select()
		->from($this->tblDocs,array(
				"key"	=>"id",
				"value"	=>'CONCAT(titleNum,"-",titleLetter," от ",titleDate)',
		));
		$select->where("YEAR(titleDate) = ".$this->currentYear);
		$select->where("type = ".$this->docus_type);
		$select->order("titleDate DESC");
		$result= $this->db->fetchPairs($select);
		return $result;
		;
	}

	/**
	 * информация о приказа
	 * @param integer $docid
	 * @return array
	 */
	public function getOrderInfo($docid)
	{
		$select=$this->db->select();
		$select->from($this->tblDocs);
		$select->where("id = ".$docid);
		// нужный тип
		$select->where("type = ".$this->docus_type);
		$result= $this->db->fetchRow($select);
		return $result;
	}

	/**
	 * Информация о пользователе (ID и платность), зачисленном, но не указанном в приказе
	 * @param integer $userid
	 * @return array (userid,payment)
	 */
	public function getUserInfo($userid)
	{
		$select=$this->db->select();
		$select->from(array("af"=>$this->tblFiledInfo),array("userid","payment"));
		$select->joinLeft(array("rez"=>$this->tblResultsInfo),"rez.abitur_id=af.id",null);
		$select->joinLeft(array("a"=>$this->tbl),"a.userid=af.userid",array("regNo"=>"id"));
		$select->where("rez.state_id= ".$this->state);
		$select->where("af.userid=".$userid);
		// учесть, что не забрал
		$select->where("a.taketime LIKE '0000-00-00 00:00:00'");

		// отсеять тех, кто уже в приказах этого года
		$subquery=$this->db->select()
		->from($this->tblLog,"userid")
		->where("operation=".$this->operation) // зачислен
		->where("YEAR(createdate)=".$this->currentYear) // вы этом году
		;
		// нету в приказах этого года
		$select->where ("af.userid NOT IN ( $subquery )");


		$result= $this->db->fetchRow($select);
		return $result;

	}
	/**
	 * Информация о пользователе (ID и платность), зачисленном, но не указанном в приказе
	 * @param integer $userid
	 * @return array (userid,payment)
	 */
	public function getUserInfo_pack($ids)
	{
		$select=$this->db->select();

		$select->from(array("a"=>$this->tbl),array("regNo"=>"id","userid"));
		$select->joinLeft(array("p"=>$this->tblPrivateInfo), "p.userid=a.userid",
				array("family","name","otch","birth_date"));
		$select->joinLeft(array("af"=>$this->tblFiledInfo), "af.userid=a.userid","payment");
		$select->joinLeft(array("rez"=>$this->tblResultsInfo),"rez.abitur_id=af.id",null);

		$select->where("af.userid IN (".implode(",", $ids).")");
		$select->where("rez.state_id= ".$this->state);
		// учесть не "забратых"
		$select->where("a.taketime LIKE '0000-00-00 00:00:00'");
		// отсеять тех, кто уже в приказах этого года
		$subquery=$this->db->select()
		->from($this->tblLog,"userid")
		->where("operation=".$this->operation) // зачислен
		->where("YEAR(createdate)=".$this->currentYear) // вы этом году
		;
		// нету среди зачисленных в приказах этого года
		$select->where ("a.userid NOT IN ( $subquery )");

		$select->order("p.family");
		$result= $this->db->fetchAssoc($select);
		return $result;

	}

	/**
	 * приминение приказа к абитуру и причисление к студентам
	 * @param array $userinfo "userid"=>id, "payment" => платный/бесплатный, "zach"=>номер зачетки
	 * @param integer $docid
	 * @param integer $author
	 */
	public function applyorder($userinfo,$docid,$author)
	{
		// транзакция:
		// 1 создать записи в STUDENTS с подгруппой NULL
		// 2. указать в PERSONPROCESS документ, автора, дату, и автора данной привязки
		// 3. сменить роль пользователя на студенческую
		$this->db->beginTransaction();
		try
		{
			// создать запись в STUDENTS и учесть форму оплаты
			$person=array(
					"zach"=>$userinfo["zach"],
					"userid"=>$userinfo["userid"],
					"payment"=>$userinfo["payment"],
					"createdate"=>date("Y-m-d H:i:s"),
					"subgroup"=>null
			);
			//			print_r($person);
			$this->db->insert($this->tblStud,$person);

			// сделать запись в журнале - зачислен по проиказу
			$this->person->addRecord($userinfo["userid"],$this->operation,$docid,'','','',$author);

			//  сменить роль пользователя на студенческую
			$this->users->setRole($userinfo["userid"],$this->studRole);


			$this->db->commit();
			return true;
		}
		catch (Zend_Exception $e)
		{
			$this->db->rollBack();
			return $e->getMessage();

		}


	}

	public function lists_addFilter($userid,$title,$col,$cond,$sign,$cond_value)
	{
		$this->db->beginTransaction();
		try
		{
			// создать набор
			$this->db->insert($this->tblPrefix.$this->tblFilters, array("title"=>$title,"userid"=>$userid));
			$id=$this->db->lastInsertId($this->tblPrefix.$this->tblFilters);
			// наполнить колонки
			foreach ($col as $prior => $value)
			{
				$_d=array("listid"=>$id,
						"keyname"=>$value,
						"prior"=>$prior+1);
				$this->db->insert($this->tblPrefix.$this->tblFiltCols, $_d);
			}
				
			// наполнить условия
			foreach ($cond as $key => $value)
			{
				$_d=array(
						"listid"	=>	$id,
						"keyname"	=>	$value,
						"cond"		=>	$sign[$key],
						"cond_val"	=>	$cond_value[$key]
				);
				$this->db->insert($this->tblPrefix.$this->tblFiltCond, $_d);
			}
				
			$this->db->commit();
			return array("status"=>true,"id"=>$id);

		}
		catch (Zend_Exception $e)
		{
			$this->db->rollBack();
			return array("status"=>false,"msg"=>$e->getMessage());

		}
	}

	public function lists_updateFilter($listid,$title,$col,$cond,$sign,$cond_value)
	{
		$this->db->beginTransaction();
		try
		{
			// удалить все что относится к заданному
			$this->db->delete($this->tblPrefix.$this->tblFiltCols,"listid=".$listid);
			$this->db->delete($this->tblPrefix.$this->tblFiltCond,"listid=".$listid);
			
			// новое название
			$this->db->update($this->tblPrefix.$this->tblFilters, array("title"=>$title),"id=".$listid);
			// наполнить колонки
			foreach ($col as $prior => $value)
			{
				$_d=array("listid"=>$listid,
						"keyname"=>$value,
						"prior"=>$prior+1);
				$this->db->insert($this->tblPrefix.$this->tblFiltCols, $_d);
			}
				
			// наполнить условия
			foreach ($cond as $key => $value)
			{
				$_d=array(
						"listid"	=>	$listid,
						"keyname"	=>	$value,
						"cond"		=>	$sign[$key],
						"cond_val"	=>	$cond_value[$key]
				);
				$this->db->insert($this->tblPrefix.$this->tblFiltCond, $_d);
			}
				
			$this->db->commit();
			return true;

		}
		catch (Zend_Exception $e)
		{
			$this->db->rollBack();
			return $e->getMessage();

		}
	}

	public function lists_getFilterParams($id)
	{
		$s=$this->db->select();
		$s->from($this->tblPrefix.$this->tblFilters);
		$s->where("id=".$id);
		$res["info"]=$this->db->fetchRow($s);
		
		$s=$this->db->select();
		$s->from($this->tblPrefix.$this->tblFiltCols);
		$s->where("listid=".$id);
		$s->order("prior ASC");
		$res["cols"]=$this->db->fetchAll($s);
		
		$s=$this->db->select();
		$s->from($this->tblPrefix.$this->tblFiltCond);
		$s->where("listid=".$id);
		$res["cond"]=$this->db->fetchAll($s);
		
		return $res;
	}

	public function lists_getAbiturs($cols,$joins,$wheres)
	{
				$logger=Zend_Registry::get("logger");
		
		$s=$this->db->select();
		// 	обязательные таблицы
		$s->from(array("a"=>$this->tbl),array());
		$s->joinLeft(array("p"=>$this->tblPrivateInfo), "p.userid=a.userid",
				array());
		$s->joinLeft(array("af"=>$this->tblFiledInfo), "af.userid=a.userid",
				array());
		$s->joinLeft(array("ss"=>$this->tblSubspecs), "ss.id=af.subspec",
				array());
		$s->joinLeft(array("s"=>$this->tblSpecs), "s.id=ss.specid",
				array());
		// JOINs
		foreach ($joins as $i=>$j)
		{
			$s->joinLeft(
					array($j["alias"]=>$j["tbl"]),
					$j["cond"],
					array());
		}
		// WHEREs
		foreach ($wheres as $i => $wh) 
		{
			$s->where($wh);
		}
		// COLUMNS
		$s->columns($cols);
// 		$s->limit(10,0);
		try {
			return $this->db->fetchAll($s);
		} catch (Zend_Exception $e) {
			$logger->log($e->getMessage(),Zend_Log::ALERT);
			return false;
		}

	}

	public function lists_getFilters($userid)
	{
		$s=$this->db->select();
		$s->from($this->tblPrefix.$this->tblFilters,array("key"=>"id","value"=>"title"));
		$s->where("userid =".$userid);
		$s->order("id DESC");
		$result=$this->db->fetchPairs($s);

		return $result;
	}

	// запрос на создание таблицы абитуров в новой кампаниии
	private $sql_create=
	"
	--
	-- Table structure for table `%TABLENAME%`
	--

	CREATE TABLE IF NOT EXISTS `%TABLENAME%` (
	`id` int(10) unsigned NOT NULL auto_increment COMMENT 'регистрационный номер',
	`userid` bigint(20) unsigned NOT NULL COMMENT 'пользователь',
	`target` tinyint(1) unsigned NOT NULL COMMENT 'целевик/не целевик',
	`taketime` datetime NOT NULL COMMENT 'забрал/не забрал документы',
	`createdate` datetime NOT NULL COMMENT 'дата регистрации абитуриента',
	PRIMARY KEY  (`id`),
	KEY `userid` (`userid`),
	KEY `target` (`target`)
	) ENGINE=InnoDB  COMMENT='рег.номера абитуриентов';

	--
	-- Constraints for dumped tables
	--

	--
	-- Constraints for table `%TABLENAME%`
	--
	ALTER TABLE `%TABLENAME%`
	ADD CONSTRAINT `%TABLENAME%_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `acl_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

	";


	public function sprav_getList($table)
	{
		$this->sprav->setTableName($table);
		return $this->sprav->getListForSelectList($table);
	}
}