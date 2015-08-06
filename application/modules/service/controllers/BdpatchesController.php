<?php
class Service_BdpatchesController extends Zend_Controller_Action
{
	protected 	$session;
	protected 	$baseLink;
	protected	$redirectLink; // ссылка в этот модуль/контроллер
	protected	$studyYear_now;
	private 	$_model;
	private 	$specDefault;
	private 	$osnovs;
	private 	$hlp; // помощник действий Typic
	private 	$logPath; // путь к логам
	private 	$patchDescription="descript.ion"; // имя фала-описания патчей, находится в папка с логами
	private 	$patchCurrent=''; // текущего патча
	//	private 	$patchLog="descript.ion"; // имя фала-описания с логами

	private $_num_len=3; // кол-во знаков в № зачетки
	private $_list1="|";
	private $_listFil="--";
	private $_author; // пользователь шо щаз залогинен


	function init()
	{
		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->currentController = $this->_request->getControllerName();
		$this->baseLink=$this->_request->getBaseUrl()."/".$currentModule."/".$this->_request->getControllerName();
		$this->view->baseLink=$this->baseLink;
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
		$this->hlp=$this->_helper->getHelper('Typic');
		$this->logPath=APPLICATION_PATH."/logs/patches";
		$this->patchCurrent=$this->_request->getActionName();
		Zend_Loader::loadClass('Service');

		// @TODO из реестра взять с каким факультетом работает данный пользователь
		// @TODO а еси может со всем факультетами работать? админ например

		$groupEnv=Zend_Registry::get("groupEnv");
		//		$this->currentFacultId=$roleEnv['currentFacult'];
		$this->_model=new Service();
		$this->studyYear_now=$this->_model->getStudyYear_Now();
		//		$this->currentDivision=$this->data->getDivisionId();
		//		$this->SpecsOnFacult=$this->data->getSpecsByFacultForSelectList();
		//		$this->specDefault=$this->getFirstElem($this->SpecsOnFacult);
		//		$this->studyYear_now=$this->_models->getStudyYear_Now();

		//		$this->osnovs=$this->data->getInfoForSelectList("osnov");
		//		$this->view->facultInfo=$this->data->getFacultInfo($this->currentFacultId);
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->title=$moduleTitle
		.". ".$modContrTitle.'. ';
		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');

		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		//		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');
		//		$ajaxContext ->addActionContext('freelist', 'json')->initContext('json');
		//		$ajaxContext ->addActionContext('zachchange', 'json')->initContext('json');
		$ajaxContext ->addActionContext('logshow', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/service.js');
		//		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/styles/dekanat.css');
		//		Zend_Controller_Action_HelperBroker::addPrefix('My_Helper');
		$this->_author=Zend_Auth::getInstance()->getIdentity();
	}

	// имена патчей начинаются с "patch"
	function indexAction()
	{
		// применены ли патчи
		// список файлов в директории с логами. Включая файл описаний
		$logList=scandir($this->logPath);
		// ключ = файл
		$logList=array_flip($logList);

		// описания патчей
		$_description=file($this->logPath."/".$this->patchDescription);
		$description=array();
		foreach ($_description as $line)
		{
			$_line=explode("\t",$line);
			$description[$_line[0]]=$_line[1];
		}
		//		echo "<pre>".print_r($logList,true)."</pre>";

		// построим меню-список Actions
		// методы класса-родителя
		$actionsParent=get_class_methods(get_parent_class($this));
		// методы класса
		$actionsList=get_class_methods($this);
		// методы исключительнло ЭТОГО контроллера (без "init" )
		//		$diff=array();
		$diff=array_diff($actionsList,$actionsParent);
		$actions=array();
		foreach ($diff as $variable)
		{
			$_action=str_replace("Action",'',$variable);
			// со словом "patch"
			if (strpos($_action,"patch")!==false)
			{
				//				// лог
				$actions[$_action]["log"]=isset($logList[$_action.".log"])
				?	true
				:	'';
				// описание
				$actions[$_action]["description"]=isset($description[$_action])
				?	$description[$_action]
				:	"";
			}
			;
		}
		$this->view->list=$actions;
		//		echo "<pre>".print_r($actions,true)."</pre>";

	}

	function logshowAction() {
		// если AJAX
		if ($this->_request->isXmlHttpRequest())
		{
			$patchName=$this->_request->getParam("patch",'');
			if (empty($patchName)) return;
			$logFilePath=$this->logPath.DIRECTORY_SEPARATOR.$patchName.".log";
			// очистим вывод
			$this->view->clearVars();
			$this->view->baseLink=$this->baseLink;
			$this->view->baseUrl = $this->_request->getBaseUrl();
			// лог
			$out["log_".$patchName]=isset($logFilePath)
			?	nl2br(file_get_contents($logFilePath))
			:	'';
			$this->view->out=$out;
		}
		;
	}

	/**
	 * подготовка таблиц для прием данных
	 */
	function patch0Action ()
	{
		set_time_limit(240);
		$this->logstart();
		$q[]="TRUNCATE TABLE `studyplans_discip`";
		$q[]="TRUNCATE TABLE `studyplans`";
		$q[]="TRUNCATE TABLE `attendance`";
		$q[]="TRUNCATE TABLE `attendance_list`";
		$q[]="TRUNCATE TABLE `ocontrol` ";
		$q[]="TRUNCATE TABLE `ocontrol_list`";
		$q[]="TRUNCATE TABLE `disciplinestud` ";
		$q[]="TRUNCATE TABLE `kafedry`";
		$q[]="ALTER TABLE `kafedry` ADD `title_small` VARCHAR( 15 ) NULL DEFAULT NULL COMMENT 'сокращенное название кафедры',
		ADD UNIQUE (
		`title_small`
		)";
		$this->logwrite("подготовка таблиц для прием данных");
		foreach ($q as $sql)
		{
			$this->logwrite("SQL-запрос: ".$sql);
			$result=$this->_model->execSql($sql);
			if (method_exists($result, "errorInfo"))
			{
				$this->logwrite("Успешно");
			}
			else $this->logwrite("Текст ошибки: ".$result);
		}


		$this->logend();
		;
		$this->_redirect($this->redirectLink);

	}

	/**
	 * Внос данных КАФЕДРЫ
	 */
	function patch1Action()
	{
		set_time_limit(240);
		$this->logstart();

		$this->logwrite(setlocale());
		// 1. файл со списком кафедр
		$handle=fopen($this->logPath."/"."kaf.txt","r");
		// переберем
		$this->logwrite("НАчало обработки файла ".$this->logPath."/"."kaf.txt");
		while (!feof($handle)) {
			$buffer = fgets($handle, 4096);
			$data=explode("\t",$buffer);
			//		while (($data = fgetcsv($handle, 0, "\t")) !== FALSE) {
			$titleSmall=$data[1];
			$title=$data[2];
			$this->logwrite("Найдена запись ".$titleSmall." ".$title);
			$res=$this->_model->insert("kafedry", array("title_small"=>$titleSmall,"title"=>$title));
			if 	($res["status"]===true) $this->logwrite("Внесение в БД: Успешно");
			else $this->logwrite("Внесение в БД: Неудача. ".$res["errorMsg"]);
		}
		$this->logend();
		$this->_redirect($this->redirectLink);

	}

	/**
	 * Внос данных Дисцпилин студентов
	 */
	function patch2Action()
	{
		set_time_limit(240);
		$this->logstart();

		// 1. файл со списком Дисциплин
		$f="dis.txt";
		$handle=fopen($this->logPath."/".$f,"r");
		// переберем
		$this->logwrite("НАчало обработки файла ".$this->logPath."/".$f);
		//		while (($data = fgetcsv($handle, 0, "\t")) !== FALSE) {
		while (!feof($handle)) {
			$buffer = fgets($handle, 4096);
			$data=explode("\t",$buffer);

			$title=$data[1];
			$kaf=$data[2];
			$this->logwrite("Найдена запись ".$title);
			$q = " INSERT INTO `disciplinestud`";
			$q.=" (`title`,`kafedra`)";
			$q.="\n SELECT '".$title."', `id` FROM `kafedry` WHERE title_small LIKE '".$kaf."'";
			$this->logwrite("Запрос: ".$q);
			$result=$this->_model->execSql($q);
			if (method_exists($result, "errorInfo"))
			{
				$this->logwrite("Успешно");
			}
			else $this->logwrite("Текст ошибки: ".$result);
			//			if ($row>5) exit;
		}
		$this->logend();
		$this->_redirect($this->redirectLink);

	}

	/**
	 * типы выходного контроля
	 */
	function patch3Action ()
	{
		set_time_limit(240);
		$this->logstart();

		$q[]="TRUNCATE TABLE `studoutcontrols`";
		$q[]="INSERT INTO `studoutcontrols` (`id`, `title`) VALUES(1, 'курсовая работа')";
		$q[]="INSERT INTO `studoutcontrols` (`id`, `title`) VALUES(2, 'контрольная работа')";
		$q[]="INSERT INTO `studoutcontrols` (`id`, `title`) VALUES(7, 'экзамен')";
		$q[]="INSERT INTO `studoutcontrols` (`id`, `title`) VALUES(9, 'курсовой проект')";
		$q[]="INSERT INTO `studoutcontrols` (`id`, `title`) VALUES(10, 'зачет')";
		$q[]="INSERT INTO `studoutcontrols` (`id`, `title`) VALUES(11, 'расчетно-графическая работа')";
		$q[]="INSERT INTO `studoutcontrols` (`id`, `title`) VALUES(12, 'реферат')";
		$this->logwrite("типы выходного контроля");
		foreach ($q as $sql)
		{
			$this->logwrite("SQL-запрос: ".$sql);
			$result=$this->_model->execSql($sql);
			if (method_exists($result, "errorInfo"))
			{
				$this->logwrite("Успешно");
			}
			else $this->logwrite("Текст ошибки: ".$result);
		}


		$this->logend();
		;
		$this->_redirect($this->redirectLink);

	}

	/**
	 * листы выходного контроля - група может быть NULL
	 */
	function patch4Action ()
	{
		set_time_limit(240);
		$this->logstart();

		$q[]="ALTER TABLE `ocontrol_list` CHANGE `groupid` `groupid` BIGINT( 20 ) UNSIGNED NULL ";
		$this->logwrite("листы выходного контроля - група может быть NULL");
		foreach ($q as $sql)
		{
			$this->logwrite("SQL-запрос: ".$sql);
			$result=$this->_model->execSql($sql);
			if (method_exists($result, "errorInfo"))
			{
				$this->logwrite("Успешно");
			}
			else $this->logwrite("Текст ошибки: ".$result);
		}


		$this->logend();
		;
		$this->_redirect($this->redirectLink);

	}
	/**
	 * таблица привязки преподов к дисциплинам
	 */
	function patch5Action ()
	{
		set_time_limit(240);
		$this->logstart();

		$q[]="CREATE  TABLE IF NOT EXISTS `academyutf8`.`teachers` (
		`id` BIGINT(20) NOT NULL AUTO_INCREMENT ,
		`userid` BIGINT(20) UNSIGNED NOT NULL ,
		`discipline` BIGINT(20) UNSIGNED NOT NULL ,
		PRIMARY KEY (`id`) ,
		INDEX `fk_teachers_disciplinestud1` (`discipline` ASC) ,
		INDEX `fk_teachers_acl_users1` (`userid` ASC) ,
		CONSTRAINT `fk_teachers_disciplinestud1`
		FOREIGN KEY (`discipline` )
		REFERENCES `academyutf8`.`disciplinestud` (`id` )
		ON DELETE CASCADE
		ON UPDATE CASCADE,
		CONSTRAINT `fk_teachers_acl_users1`
		FOREIGN KEY (`userid` )
		REFERENCES `academyutf8`.`acl_users` (`id` )
		ON DELETE CASCADE
		ON UPDATE CASCADE)
		ENGINE = InnoDB";
		$this->logwrite("таблица привязки преподов к дисциплинам");
		foreach ($q as $sql)
		{
			$this->logwrite("SQL-запрос: ".$sql);
			$result=$this->_model->execSql($sql);
			if (method_exists($result, "errorInfo"))
			{
				$this->logwrite("Успешно");
			}
			else $this->logwrite("Текст ошибки: ".$result);
		}


		$this->logend();
		;
		$this->_redirect($this->redirectLink);

	}

	/**
	 * заполнение GOS_PARAMS
	 */
	function patch6Action ()
	{
		set_time_limit(240);
		$this->logstart();

		// 		-- все параметры приняты в 2000 году
		$q[]="UPDATE `gos_params` SET yearStart=2000";

		// 		-- для дневного обучения принять срок = 4 года у тех, у кого numericTitle содержит .62
		$q[]="UPDATE `gos_params` SET years2study=4
		WHERE division=1
		AND spec IN (SELECT id FROM specs where numeric_title LIKE '%.62')";

		// 		-- для заочного обучения принять срок = 5 года у тех, у кого numeric_title содержит .62
		$q[]="UPDATE `gos_params` SET years2study=5
		WHERE division=2
		AND spec IN (SELECT id FROM specs where numeric_title LIKE '%.62')";

		// 		-- для дневного обучения принять срок = 5 года у тех, у кого numericTitle содержит .65
		$q[]="UPDATE `gos_params` SET years2study=5
		WHERE division=1
		AND spec IN (SELECT id FROM specs where numeric_title LIKE '%.65')";

		// 		-- для заочного обучения принять срок = 6 лет у тех, у кого numeric_title содержит .65
		$q[]="UPDATE `gos_params` SET years2study=6
		WHERE division=2
		AND spec IN (SELECT id FROM specs where numeric_title LIKE '%.65')";

		// 		-- специальности не активные. Установим что последний прием был 2010
		$q[]="UPDATE `gos_params` SET yearLast=2010
		WHERE spec IN
		(
		SELECT id FROM `specs`
		where numeric_title
		IN ('080109.65' , '080502.65', '110201.65', '110301.65', '110303.65', '110304.65', '110401.65')
		)
		";
		// 		-- установить в NULL дату последнего приема где оно равно 0000
		$q[]="UPDATE `gos_params` SET yearLast = NULL WHERE yearLast=0";
// 		-- сменить комментарии столбцов
		$q[]="ALTER TABLE `gos_params` CHANGE `yearStart` `yearStart` YEAR( 4 ) NOT NULL COMMENT 'срок действия - год первой приемной кампании'";
		$q[]="ALTER TABLE `gos_params` CHANGE `yearLast` `yearLast` YEAR( 4 ) NULL DEFAULT NULL COMMENT 'срок действия - год последней приемной кампании (последний студент принят)'";
		
		$this->logwrite("Заполнение GOS_PARAMS");
		foreach ($q as $sql)
		{
			$this->logwrite("SQL-запрос: ".$sql);
			$result=$this->_model->execSql($sql);
			if (method_exists($result, "errorInfo"))
			{
				$this->logwrite("Успешно");
			}
			else $this->logwrite("Текст ошибки: ".$result);
		}


		$this->logend();
		;
		$this->_redirect($this->redirectLink);

	}
	/**
	 * новая таблица - departments - подразделения предприятия
	 */
	function patch7Action ()
	{
		set_time_limit(240);
		$this->logstart();

		$q[]="CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `title` varchar(200) default NULL,
  `title_small` varchar(15) default NULL COMMENT 'сокращенное название кафедры',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `title_small` (`title_small`)
) TYPE=InnoDB COMMENT='перечень подразделений';
		";

		$this->logwrite("новая таблица - departments - подразделения предприятия");
		foreach ($q as $sql)
		{
			$this->logwrite("SQL-запрос: ".$sql);
			$result=$this->_model->execSql($sql);
			if (method_exists($result, "errorInfo"))
			{
				$this->logwrite("Успешно");
			}
			else $this->logwrite("Текст ошибки: ".$result);
		}


		$this->logend();
		;
		$this->_redirect($this->redirectLink);

	}

	/**
	 * новая таблица Конкурсные группы
	 */
	function patch8Action ()
	{
		set_time_limit(240);
		$this->logstart();

		$q[]="CREATE TABLE `advantage` (
`id` int( 11 ) unsigned NOT NULL AUTO_INCREMENT ,
`title` text CHARACTER SET utf8,
PRIMARY KEY ( `id` )
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci COMMENT = 'типы наград';		
		";

		$q[]="ALTER TABLE `advantage` COMMENT = 'конкурсные группы';";
		$q[]="ALTER TABLE `abitur_filed2` ADD `advantage` INT UNSIGNED NULL DEFAULT NULL ,
ADD INDEX ( `advantage` ) ;
		";
		
		$q[]="ALTER TABLE `abitur_filed2` ADD FOREIGN KEY ( `advantage` ) REFERENCES `advantage` (
`id`
) ON DELETE RESTRICT ON UPDATE CASCADE ;		
		";
		$q[]="ALTER TABLE `abitur_filed2` CHANGE `advantage` `advantage` INT( 10 ) UNSIGNED NOT NULL COMMENT 'Конкурсная группа'";
		
		$q[]="INSERT INTO `advantage` (`id`, `title`) VALUES(1, 'Лица, имеющих особое право')";
		$q[]="INSERT INTO `advantage` (`id`, `title`) VALUES(2, 'Целевой прием')";
		$q[]="INSERT INTO `advantage` (`id`, `title`) VALUES(3, 'Финансируемые из федерального бюджета')";
		$q[]="INSERT INTO `advantage` (`id`, `title`) VALUES(4, 'С полным возмещением затрат')";
		$q[]="ALTER TABLE `abitur_filed2` CHANGE `payment` `payment` INT( 11 ) UNSIGNED NULL COMMENT 'форма оплаты'";
		
		$this->logwrite("новая таблица - advantage - Конкурсные группы");
		
		foreach ($q as $sql)
		{
			$this->logwrite("SQL-запрос: ".$sql);
			$result=$this->_model->execSql($sql);
			if (method_exists($result, "errorInfo"))
			{
				$this->logwrite("Успешно");
			}
			else $this->logwrite("Текст ошибки: ".$result);
		}


		$this->logend();
		;
		$this->_redirect($this->redirectLink);

	}


	/**
	 * применение конкурсных групп
	 */
	function patch9Action ()
	{
		set_time_limit(240);
		$this->logstart();

		for ($i=2010;$i<2014;$i++)
		{
			// adv=1
			$q[]="DROP TABLE IF EXISTS my_temp_table;";
			$q[]="CREATE TEMPORARY TABLE my_temp_table(id int  NOT NULL);
			
INSERT INTO my_temp_table(SELECT ab.userid FROM `abitur_".$i."` AS ab
LEFT JOIN abitur_filed2 AS af ON af.userid=ab.userid
LEFT JOIN personal AS p ON p.userid=ab.userid
where
ab.target=0
AND p.category >1
AND af.payment=1);
			
UPDATE abitur_filed2
SET advantage =1
WHERE userid IN (SELECT id FROM my_temp_table);
		";
				// adv=2
			$q[]="DROP TABLE IF EXISTS my_temp_table;";
			$q[]="
CREATE TEMPORARY TABLE my_temp_table(id int  NOT NULL);

INSERT INTO my_temp_table(
SELECT ab.userid FROM `abitur_".$i."` AS ab
LEFT JOIN abitur_filed2 AS af ON af.userid=ab.userid
LEFT JOIN personal AS p ON p.userid=ab.userid
where ab.target=1 
AND p.category=1
AND af.payment=1
);

UPDATE abitur_filed2 
SET advantage =2
WHERE userid IN (SELECT id FROM my_temp_table);
					";
			
			// adv=3
			$q[]="DROP TABLE IF EXISTS my_temp_table;";
			$q[]="
CREATE TEMPORARY TABLE my_temp_table(id int  NOT NULL);

INSERT INTO my_temp_table(
SELECT ab.userid FROM `abitur_".$i."` AS ab
LEFT JOIN abitur_filed2 AS af ON af.userid=ab.userid
LEFT JOIN personal AS p ON p.userid=ab.userid
where 
ab.target=0 
AND p.category =1
AND af.payment=1
);

UPDATE abitur_filed2 
SET advantage =3
WHERE userid IN (SELECT id FROM my_temp_table);
					";

			// adv=4
			$q[]="DROP TABLE IF EXISTS my_temp_table;";
			$q[]="
CREATE TEMPORARY TABLE my_temp_table(id int  NOT NULL);
			
INSERT INTO my_temp_table(
SELECT ab.userid FROM `abitur_".$i."` AS ab
LEFT JOIN abitur_filed2 AS af ON af.userid=ab.userid
LEFT JOIN personal AS p ON p.userid=ab.userid
where 
ab.target=0 
AND p.category =1
AND af.payment=2
		);
			
UPDATE abitur_filed2
SET advantage =4
WHERE userid IN (SELECT id FROM my_temp_table);
					";
				
			// adv=4, слушатели
			$q[]="UPDATE abitur_filed2 SET advantage =4 WHERE payment =3;";
			// остатки
			$q[]="UPDATE abitur_filed2 SET advantage =4 WHERE advantage <1 OR advantage is null;";
				
		}
		
		
		$this->logwrite("применение конкурсных групп");
		
		foreach ($q as $sql)
		{
			$this->logwrite("SQL-запрос: ".$sql);
			$result=$this->_model->execSql($sql);
			if (method_exists($result, "errorInfo"))
			{
				$this->logwrite("Успешно");
			}
			else $this->logwrite("Текст ошибки: ".$result);
		}


		$this->logend();
		;
		$this->_redirect($this->redirectLink);

	}


	/**
	 * открытие лога
	 */
	private function logstart()
	{
		$f=$this->logPath."/".$this->patchCurrent.".log";
		// запишем в него шо начали работу
		file_put_contents($f,"\n========= ".date("Y-m-d H:i:s, D")." =========\n",FILE_APPEND);
		file_put_contents($f,date("Y-m-d H:i:s, D")."\t Начало\n",FILE_APPEND);

	}

	/** пишем лог,
	 * @param string $text текст
	 */
	private function logwrite($text)
	{
		$f=$this->logPath."/".$this->patchCurrent.".log";
		if (is_array($text))
		{
			foreach ($text as $t)
			{
				file_put_contents($f,date("Y-m-d H:i:s, D")."\t ".$t." \n",FILE_APPEND);
			}
		}
		else {
			file_put_contents($f,date("Y-m-d H:i:s, D")."\t ".$text." \n",FILE_APPEND);
		}
	}

	/**
	 *  закрытие лога
	 */
	private function logend()
	{
		$f=$this->logPath."/".$this->patchCurrent.".log";
		file_put_contents($f,date("Y-m-d H:i:s, D")."\t Завершение\n",FILE_APPEND);
		//		chmod($f,0644);
		//		chown($f,"zlydden");
		//		chgrp($f,"zlydden");
	}

}