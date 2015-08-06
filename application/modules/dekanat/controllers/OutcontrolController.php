<?php
class Dekanat_OutcontrolController extends Zend_Controller_Action
{

	protected $gosParams;
	protected $gosParamsDef;
	
	//	protected  $currentSpecIdList;
	private $osnovs;
	private $currentFacultId;
	private $session;
	private $baseLink;
	private $redirectLink;
	private $_author;
	private $data;
	private $studyYear_now;
	private $hlp; // помощник действий Typic
	private $confirmWord="УДАЛИТЬ";

	public function init()
	{
		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";

		$this->view->currentController = $this->_request->getControllerName();
		$this->baseLink=$this->_request->getBaseUrl()."/".$currentModule."/".$this->_request->getControllerName();
		$this->view->baseLink=$this->baseLink;
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();


		//		Zend_Controller_Action_HelperBroker::addPrefix('My_Helper');
		$this->hlp=$this->_helper->getHelper('Typic');
		// установим учебный год, который сейчас происходит
		Zend_Loader::loadClass('Dekanat');


		$groupEnv=Zend_Registry::get("groupEnv");
		$this->currentFacultId=$groupEnv['currentFacult'];
		$this->data=new Dekanat($this->currentFacultId);
		$this->studyYear_now=$this->data->getStudyYear_Now();

		//		$this->currentDivision=$this->data->getDivisionId();
		$this->gosParams=$this->data->getGosParams();
		$this->gosParamsDef=$this->data->getGosParamsDef();
		$this->osnovs=$this->data->getInfoForSelectList("osnov");
		$this->view->facultInfo=$this->data->getFacultInfo($this->currentFacultId);
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->title=$moduleTitle
		.'. Факультет - '.$this->view->facultInfo['title']
		.". ".$modContrTitle.'. ';
		//		$this->view->title='Деканат. Факультет - '.$this->view->facultInfo['title'].'. Выходной контроль. ';
		//		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');

		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');
		$ajaxContext ->addActionContext('approvechange', 'json')->initContext('json');
		//		$ajaxContext ->addActionContext('semestrchanged', 'json')->initContext('json');
		$ajaxContext ->addActionContext('discipchanged', 'json')->initContext('json');
		$ajaxContext ->addActionContext('formadd', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/dekanat.js');
		$this->_author=Zend_Auth::getInstance()->getIdentity();
	}

	public function indexAction ()
	{
		if ($this->_request->isPost())
		{
			// получим данные из запроса
			$params = $this->_request->getParams();
			// обновим сессию
			$this->sessionUpdate($params);
		}
		$criteria=$this->buildCriteria();

		$this->view->formFilter=$this->createForm_filter($criteria);
		// для отображение + для Jquery UI
		$this->view->studyYearEnd=$criteria["studyYearStart"]+1;

		$this->view->formAdd=$this->createForm_add($criteria);
		$this->view->formDelete=$this->createForm_delete();

		//		$this->view->confirmDelete=$this->confirmWord;
		//		$this->view->formDelete=$this->createForm_delete();
		//		$this->view->formEditDate=$this->createForm_editDate();

		// список листов удовлетворяющих критериям
		$list= $this->data->outControl_list($criteria["kurs"],$criteria["semestr"],$criteria["group"],$criteria["studyYearStart"]);
		$this->view->list=$list;
	}


	public function odtAction()
	{
		// отключить вывод
		$this->_helper->layout->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);

		$filled= (int)$this->_request->getParam('filled',0);
		$id= (int)$this->_request->getParam('id',0);
		if ($id<1) $this->_redirect($this->redirectLink);
		// есть ли такое?
		$listInfo=$this->data->outControl_listInfo($id);
		if (count($listInfo)<1) $this->_redirect($this->redirectLink);
		// наша группа ?
		$chkGr=( array_key_exists($listInfo["gosparam"],$this->gosParams)
		);
		$listInfo["studyYearEnd"]=$listInfo["studyYearStart"]+1;
		$TBS=new Tbs_Tbs;

		switch ($listInfo["doctype"]) {
			case 2:
				$data=$this->data->outControl_listDetails($id);
				$template='outControl_examList.odt';
				//				echo "<pre>".print_r($data,true)."</pre>";
				//				echo "<pre>".print_r($listInfo,true)."</pre>";
				//				die();
				break;
					
			case 1:
			default:
				$data=$this->data->outControl_listDetails($id);
				$template='outControl_examVedom.odt';
				break;
		}

		/* если filled=0 запробелить
		 [result] => 0
		 [itog] => 2
		 [rating_bally] => 0
		 */
		if ($filled===0) {
			foreach ($data as $key=>&$value)
			{
				$value["result"]=" ";
				$value["itog"]=" ";
				$value["rating_bally"]=" ";
				;
			}
		}
		//echo "<pre>".print_r($data,true)."</pre>";
		$TBS->LoadTemplateUtf8($template);
		$TBS->MergeBlock('info', array($listInfo));
		$TBS->MergeBlock('stud', $data);
		$file_name = str_replace('.','_'.date('Y-m-d').'.',$template);
		////
		////		// вывод
		$TBS->Show(OPENTBS_DOWNLOAD, $file_name);
		////
		//		// Output as a download file (some automatic fields are merged here)
		//		if ($debug) {
		//			$TBS->Show(OPENTBS_DOWNLOAD+TBS_EXIT+OPENTBS_DEBUG_XML*1, $file_name);
		//		} elseif ($suffix==='') {
		//			// download
		//			$TBS->Show(OPENTBS_DOWNLOAD, $file_name);
		//		} else {
		//			// save as file
		//			$file_name = str_replace('.','_'.$suffix.'.',$file_name);
		//			$TBS->Show(OPENTBS_FILE+TBS_EXIT, $file_name);
		//		}

	}

	/**
	 * создает форму создания документа для AJAX-запросов
	 *
	 */
	public function formaddAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";
		$this->view->currentController = $this->_request->getControllerName();
		// получим данные из запроса
		$params = $this->_request->getParams();
		// обновим сессию
		$this->sessionUpdate($params);

		$criteria=$this->buildCriteria();

		/*
		 $kurs= $criteria["kurs"];
		 $semestr= $criteria["semestr"];
		 $groupid= $criteria["group"];
		 $studyYearStart=$criteria["studyYearStart"];
		 $osnov=$criteriaSession["osnov"];

		 // @TODO выбранные студни
		 $number= (int)$this->_request->getPost('numb',0);
		 $doctype= (int)$this->_request->getPost('doctype',0);
		 $discipline= (int)$this->_request->getPost('discipline',0);
		 $outcontrol= (int)$this->_request->getPost('outcontrol',0);
		 $begins= $this->_request->getPost('begins');

		 */

		$form=$this->createForm_add($criteria);
		$this->view->formAdd=$form;
		$out["form"]=$this->view->render($this->_request->getControllerName()."/_addForm.phtml");
		$this->view->out=$out;
	}

	/**
	 * выбор дисциплины в форме создания листа выходного контроля
	 */
	public function discipchangedAction()
	{
		// очистим вывод
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";
		$this->view->currentController = $this->_request->getControllerName();

		// получим данные из запроса
		$params = $this->_request->getParams();
		// обновим сессию
		$this->sessionUpdate($params);
		$criteria=$this->buildCriteria();

		$teachers=$this->data->ocontrol_getTeachers(
		$criteria["discipline"]
		);
		// если нету - то
		$teachers=$teachers?$teachers:array();
		$def=empty($teachers)?"не заданы":"";
		$_teachers=$this->hlp->createSelectList("teacher",$teachers,$def);
		$this->view->teacher=$_teachers->render();

	}

	//	public function semestrchangedAction()
	//	{
	//		// очистим вывод
	//		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
	//		$this->view->clearVars();
	//		$this->view->baseLink=$this->baseLink;
	//		$this->view->baseUrl = $this->_request->getBaseUrl();
	//		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";
	//		$this->view->currentController = $this->_request->getControllerName();
	//
	//		// получим данные из запроса
	//		$params = $this->_request->getParams();
	//		// обновим сессию
	//		$this->sessionUpdate($params);
	//		$criteria=$this->buildCriteria();
	//		if ($criteria["group"]<=0) $this->_redirect($this->baseLink);
	//
	//		$groupInfo=$this->data->getGroupInfo($criteria["group"]);
	//		// ваще наша ли группа?
	//		$chkGr=( array_key_exists($groupInfo["spec"],$this->SpecsOnFacult)
	//		&& $groupInfo["division"]==$this->currentDivision
	//		);
	//		// послать к началу работы с программой
	//		if (!$chkGr) $this->_redirect($this->_request->getBaseUrl());
	//
	//		$dislist=$this->data->studyPlans_getDisciplinesForGroup	(
	//		$criteria["group"],$criteria["studyYearStart"],$criteria["kurs"],$criteria["semestr"] );
	//		// если нету - то
	//		$dislist=$dislist?$dislist:array();
	//
	//		$dis=$this->hlp->createSelectList("discipline",$dislist,"Выбрать");
	//		$this->view->disciplines=$dis->render();
	//
	//	}

	public function formchangedAction()
	{

		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";
		$this->view->currentController = $this->_request->getControllerName();

		// узнаем что к нам пришло
		$formData = $this->_request->getPost('formData');
		$oldData=$this->session->getIterator();
		// обновим сессию
		$this->sessionUpdate($formData );
		$criteria=$this->buildCriteria();

		// обновим форму - группы зависят от курса, учебного года и формы обучения
		$groupList=$this->createForm_elementGroupsTreeList($criteria,$this->gosParams);
		$out["group"]=$groupList->render();

		// обновить форму добавления - дисциплины зависят от учебного плана через группу
		// а вообще - есть готовая функция
		$dislist=$this->data
		->studyPlans_getDisciplinesForGroup($criteria["group"],$criteria["studyYearStart"],$criteria["kurs"],$criteria["semestr"]);
		// если нету - то
		$dislist=$dislist?$dislist:array();
		$dis=$this->hlp->createSelectList("discipline",$dislist,"Выбрать");
		$out["discipline"]=$dis->render();

		// обновить список листов удовлетворяющих критериям
		// список листов удовлетворяющих критериям
		$list= $this->data->outControl_list($criteria["kurs"],$criteria["semestr"],$criteria["group"],$criteria["studyYearStart"]);

		$this->view->list=$list;
		$out["list"]=$this->view->render($this->_request->getControllerName()."/_list.phtml");

		$this->view->out=$out;
	}

// 	public function personeditAction()
// 	{
// 		// данные, получаются из JS
// 		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);

// 		// очистим вывод
// 		$this->view->clearVars();
// 		$this->view->baseLink=$this->baseLink;
// 		$this->view->baseUrl = $this->_request->getBaseUrl();
// 		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";
// 		$this->view->currentController = $this->_request->getControllerName();

// 		$discipline=(int)$this->_request->getPost('discipline');
// 		$id=(int)$this->_request->getPost('id'); // ID листа
// 		$state=(int)$this->_request->getPost('state');
// 		$userid=(int)$this->_request->getPost('userid');
// 		$filter = new Zend_Filter_Alnum(array('allowwhitespace' => true));

// 		$comment=$filter->filter($this->_request->getPost('comment'));

// 		// наш ли пользователь?
// 		$studInfo=$this->data->getStudSpecDivOsnov($userid);
// 		$chkStud=( $studInfo["division"]==$this->currentDivision
// 		&& array_key_exists($studInfo["spec"],$this->SpecsOnFacult) );

// 		// допустимо ли состояние ?
// 		$stateList=$this->data->getInfoForSelectList("attendance_states"," 1 ORDER BY id ASC"); // ! важно
// 		$chkSt=array_key_exists($state,$stateList);

// 		// терпимо - идем дальше
// 		$out=array();
// 		if ($chkSt && $chkStud)
// 		{
// 			$utf8=$this->_helper->getHelper('Utf8');
// 			$aff=$this->data->attendance_personChange($userid,$state,$discipline,$id,$comment);
// 			$elemName="d".$discipline."u".$userid;

// 			$title=$utf8->utf8_substr($stateList[$state],0,1);
// 			//			$this->view->asdasd=
// 			$out[$elemName]=$utf8->utf8_strlen($comment) >0
// 			?	$title."\n<br><small>".$comment."</small>"
// 			:	$title;
// 			if ($aff>0) $this->view->out=$out;
// 		}


// 	}

	public function deleteAction()
	{
		$id= (int)$this->_request->getParam('id',0);
		// @FIXME проверка наш ли лист и верно ли все?
		if ($id<1) $this->_redirect($this->redirectLink);
		//
		$listInfo=$this->data->outControl_listInfo($id);
		// наша группа ?
		$chkGr=( array_key_exists($listInfo["gosparam"],$this->gosParams)
		);
		// если наше - удалить
		if ($chkGr)
		{
			$res=$this->data->outControl_delComplex($id);
		}
		$this->_redirect($this->redirectLink);


	}


	public function editdateAction()
	{
	}

	public function editAction()
	{
		//		if ($this->_request->isPost())
		//		{
		//			$id= (int)$this->_request->getPost('id',0);
		//			// @FIXME проверка наш ли лист и верно ли все?
		//			if ($id<1) $this->_redirect($this->redirectLink);
		//
		//			// есть ли такое?
		//			$listInfo=$this->data->outControl_listInfo($id);
		//			if (count($listInfo)<1) $this->_redirect($this->redirectLink);
		//			// наша группа ?
		//			$chkGr=( array_key_exists($listInfo["spec"],$this->SpecsOnFacult)
		//			&& $listInfo["division"]==$this->currentDivision
		//			);
		//
		//			// шо еще нам поступило?
		//			$userids= $this->_request->getPost('userid');
		//			$userids=$this->hlp->arrayINTEGER($userids);
		//			$results= $this->_request->getPost('result');
		//			$results= $this->hlp->arrayINTEGER($results);
		//			$rating_ballys= $this->_request->getPost('rating_bally');
		//			$rating_ballys= $this->hlp->arrayINTEGER($rating_ballys);
		//			$itogs= $this->_request->getPost('itog');
		//			$itogs= $this->hlp->arrayINTEGER($itogs);
		//
		//			//			$chkParams=(count($userids)==count($results)==count($itogs))
		//			// сравним между собой количество, должно быть одинаково
		//			$vars = array(count($userids), count($results), count($itogs),count($rating_ballys));
		//			// если совпадает
		//			$chkParams=count(array_unique($vars))==1;
		//
		//			if ( !$chkParams) $this->_redirect($this->redirectLink);
		//
		//			// сообразим
		//			$aff=0;
		//			foreach ($userids as $key=>$userid)
		//			{
		//				$res=$results[$key];
		//				$rat=$rating_ballys[$key];
		//				$it=$itogs[$key];
		//				$this->data->outControl_personChange($id,$userid,$rat,$res,$it);
		//				$af++;
		//			}
		//
		//
		//			// перейдем сюда же шобы показать шо получилось
		//			$this->_redirect($this->redirectLink."/edit/id/".$id);
		//			//
		//			//			$logger=Zend_Registry::get("logger");
		//			//			$logger->log($userids,Zend_Log::INFO);
		//			//			$logger->log($results,Zend_Log::INFO);
		//			//			$logger->log($results,Zend_Log::INFO);
		//			//			$logger->log($af,Zend_Log::INFO);
		//
		//		}
		//		else
		//		{
		$id= (int)$this->_request->getParam('id',0);
		// @FIXME проверка наш ли лист и верно ли все?
		if ($id<1) $this->_redirect($this->redirectLink);

		$listInfo=$this->data->outControl_listInfo($id);
		// наша группа ?
		//			$chkGr=( array_key_exists($listInfo["spec"],$this->SpecsOnFacult)
		//			&& $listInfo["division"]==$this->currentDivision
		//			);
		//			if (!$chkGr) $this->_redirect($this->redirectLink);

		$details=$this->data->outControl_listDetails($id);
		//		echo "<pre>".print_r($details,true)."</pre>";die();
		if (count($details)<1) $this->view->details=array();
		else
		{
			$this->view->listInfo=$listInfo;
			$this->view->listState=$this->getListState($listInfo["approved"],$listInfo["id"]);

			//			$details=$this->detailsPrepare($details);
			//		$disciplines=$this->
			$this->view->details=$details;

			// форма редактирования состояния студент-дисциплина
			$this->view->formEdit=$this->createForm_editDoc($details,$listInfo);
		}
		//		}
	}

		public function approvechangeAction()
		{
				if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
				// очистим вывод
			$this->view->clearVars();
			$this->view->baseLink=$this->baseLink;
			$this->view->baseUrl = $this->_request->getBaseUrl();

			$id= (int)$this->_request->getPost('id',0);
			if ($id<1) $this->_redirect($this->baseLink);;
			// есть ли такое?
			$listInfo=$this->data->outControl_listInfo($id);
			// есть лист и он нужного экзаменатора?
			$chk=empty($listInfo) || $listInfo["teacher"]!==$this->_author->id;
			if ($chk) $this->_redirect($this->baseLink); ;

			//если преподом уже подписано
			if ($listInfo["state"]==1)
			{
				$approveNew=$listInfo["approved"]==1?0:1;
				$aff=$this->data->outcontrol_listApproveChange($id,$approveNew);
			}
			else
			{

				//
			}
			// новая инфа о листе
			$listInfo=$this->data->outControl_listInfo($id);
			$this->view->listState=$this->getListState($listInfo["state"],$listInfo["id"]);
			$this->view->listState["dekan"]=$listInfo["approved"];
			$this->view->elem=$this->view->render($this->_request->getControllerName()."/_approveList.phtml");
			;
			;
		}

		public function addAction()
		{
			if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
			$logger=Zend_Registry::get("logger");

			// узнаем шо у нас в session хранится
			$criteria=$this->buildCriteria($_POST);


			$kurs= $criteria["kurs"];
			$semestr= $criteria["semestr"];
			$groupid= $criteria["group"];
			$studyYearStart=$criteria["studyYearStart"];
			//		$osnov=$criteria["osnov"];
			$teacher=$criteria["teacher"];
			// @FIXME Проверка экзаменатора - шобы был и по той дисциплине


			//		print_r($criteria);
			// выбранные студни
			$userids= $this->_request->getPost('userid');
			$userids=$this->hlp->arrayINTEGER($userids);
			//		$logger->log($userids,Zend_Log::INFO);

			$number= (int)$this->_request->getPost('numb',0);
			$doctype= (int)$this->_request->getPost('doctype',0);
			$discipline= (int)$this->_request->getPost('discipline',0);
			$outcontrol= (int)$this->_request->getPost('outcontrol',0);
			$begins= $this->_request->getPost('begins');

			$filter = new Zend_Filter_StripTags();
			$begins=$filter->filter($this->_request->getPost('begins'));
			$begins=$this->hlp->date_DMY2array($begins);
			$chkB=checkdate($begins["month"],$begins["day"],$begins["year"]);
			$teachers=$this->data->ocontrol_getTeachers($discipline);
			$chkTeacher= $teacher>0 && array_key_exists($teacher, $teachers) ;

			if ($groupid>0){
				$grInfo=$this->data->getGroupInfo($groupid);
				//		$chkGr=($grInfo["division"]==$this->currentDivision
				//		&& array_key_exists($grInfo["spec"],$this->SpecsOnFacult)
				//		&& $this->currentFacultId==$grInfo["facult"]);
			}
			else $groupid=null;
			// если даты неверны - отлуп
			if (!$chkB || !$chkTeacher ) $this->_redirect($this->redirectLink);

			$begins=$begins["year"]."-".$begins["month"]."-".$begins["day"];
			$now=date("Y-m-d H:i:s");
			// создадим документ
			$info=array(
		"discipline"=>$discipline,
		"numb"=>$number,
		"type"=>$outcontrol,
		"kurs"=>$kurs,
		"semestr"=>$semestr,
		"studyYearStart"=>$studyYearStart,
		"groupid"=>$groupid,
		"doctype"=>$doctype,
		"eventdate"=>$begins,
		"createdate"=>$now,
		"teacher"=>$teacher,
		"author"=>$this->_author->id				
			);
			print_r($info);
			// привязывать тех, кто в данной группе
			// найдем ID выбранных пользователей ЭТОЙ группы
			// @FIXME шобы отфильтровать ненужные ID, например несуществующие
			$users=$this->data->getStudentsFiltered2_userids($userids);
			//echo "<pre>".print_r($info,true)."</pre>";
			//die();

			//		$users=$this->data->getStudentsFiltered_userids($groupid,$userids);
			// если какая то шняга, список не принадлежит к этой группе
			//		if (empty($users)) {
			//			$this->view->msg="Ошибка. Выбранные студенты не относятся к выбранной группе";
				//			return;
				//		}
				// тип формируемого документа
				switch ($doctype)
				{
					// 2 - экз. лист, выписать один на первого
					case 2:
						$id=key($users);
						$res=$this->data->outControl_listAddComplex($info, array($id));
						;
						break;
							
						// 1 - ведомость
					case 1:
					default:
						$res=$this->data->outControl_listAddComplex($info, array_keys($users));
						break;
				}

				//echo "<pre>".print_r($res,true)."</pre>";
				//die();

				if ($res["status"]===true)
				{
					$this->view->insertID=$res["listid"];
					$this->view->affected=$res["aff"];
					$this->_redirect($this->redirectLink."/edit/id/".$res["listid"]);
				}
				else
				{
					$this->view->msg="Ошибка БД";
					$this->view->msg.=": ".$res["errorMsg"];
				}
			}

			/** детали атт. листа. ВЫХОД: подгруппа->ФИО->дисциплины->детали
			 *
			 * @param array $arr
			 * @return array
			 */
			//	private function detailsPrepare($arr)
			//	{
			//		// исходный массив отсортирован подгруппа, ФИО, дисциплина
				//		$result=array();
				//
			//		foreach ($arr as $elem)
			//		{
			//			$result[$elem["subgrTitle"]][$elem["fio"]][$elem["discipline"]]=$elem;
			//		}
			//		return $result;
			//	}


			private function sessionUpdate($params)
			{
				// обновим сессию
				foreach ($params as $param=>$value)
				{
					$_value=$value;
					switch ($param)
					{

						// переменная целая
						default:
							$_value=(int)$value;
							break;
					}

					$this->session->$param=$_value;
				}
			}


			private function formElementToArray(&$form,$varname,$newName)
			{
				$form->getElement($varname)->setName($newName)->setIsArray(true);

			}

			private function createForm_editDoc($content,$listInfo)
			{
				// форма добавления нового плана, изначально скрыта
				$form=new Formochki();
				$form->setAttrib('name','formEdit');
				$form->setAttrib('id','formEdit');
				$form->setMethod('POST');
				$form->setAction($this->baseLink."/edit");
				$form->addElement("hidden","id",array("value"=>$listInfo["id"]));
				// если экзамен
				if ($listInfo["type"]==7)
				{
					$resultList=array(
			"2"=>"Неудовлетворительно",
			"3"=>"Удовлетворительно",
			"4"=>"Хорошо",
			"5"=>"Отлично"		
			);
				}
				else
				{
					$resultList=array(
			"1"=>"Незачет",
			"5"=>"Зачет"	
			);
				}
				foreach ($content as $zach => $student)
				{
					$elemID="userid".$student["userid"];
					$form->addElement("hidden",$elemID,array("value"=>$student["userid"]));
					$form->getElement($elemID)
					->setIsArray(true)
					->setName("userid")
					;
					$elemID="rating_bally".$student["userid"];
					$form->addElement("text",$elemID,array("class"=>"inputSmall2","value"=>$student["rating_bally"]));
					$form->getElement($elemID)
					->setIsArray(true)
					->setName("rating_bally")
					;
					$elemID="result".$student["userid"];
					$form->addElement("text",$elemID,array("class"=>"inputSmall2","value"=>$student["result"]));
					$form->getElement($elemID)
					->setIsArray(true)
					->setName("result")
					;
					$elemID="itog".$student["userid"];
					$res=$this->hlp->createSelectList($elemID,$resultList,'Не явился',$student["itog"]);
					$form->addElement($res);
					$form->getElement($elemID)
					->setIsArray(true)
					->setName("itog")
					;
					;
				}
				$form->addElement("submit","OK",array(
		"class"=>"apply_text"		
		));
		$form->getElement("OK")->setName("Сохранить");
		return $form;
			}

			/** форма удаления группы с пустым ID, оно будет заполняться через JS
			 * @return Formochki
			 */
			private function createForm_delete()
			{
				$form=new Formochki;
				$form->setAttrib('name','formDelete');
				$form->setAttrib('id','formDelete');
				//				$form->setAttrib('style','display:none');
				$form->setMethod('POST');
				$form->setAction($this->baseLink."/"."delete");
				$textOptions=array('class'=>'typic_input');
				$form->addElement('hidden','id');
				$form->addElement('text','confirm',$textOptions);
				$form->addElement('submit','ok',array("class"=>"apply_text"));
				$form->getElement('ok')->setName("Удалить");

				return $form;
			}


			private function createForm_add($criteria)
			{
				// форма добавления нового документа, изначально скрыта
				$form=new Formochki();
				$form->setAttrib('name','addForm');
				$form->setAttrib('id','addForm');
				$form->setMethod('POST');
				$form->setAction($this->baseLink."/add");

				// если задана группа
				//		if ($criteria["group"]!==0)
				//		{
				//			$groupInfo=$this->data->getGroupInfo($criteria["group"]);
				//			// ваще наша ли группа?
				//			$chkGr=( array_key_exists($groupInfo["spec"],$this->SpecsOnFacult)
				//			&& $groupInfo["division"]==$this->currentDivision
				//			);
				//			// послать к началу работы с программой
				//			if (!$chkGr) $this->_redirect($this->_request->getBaseUrl());
				//
				//			$dislist=$this->data
				//			->studyPlans_getDisciplinesForGroup($criteria["group"],$criteria["studyYearStart"],$criteria["kurs"],$criteria["semestr"]);
				//			// если нету - то
				//			$dislist=$dislist?$dislist:array();
				//		}
				//		else
				//		{
				$dislist=$this->data->getDiscipliesStudOrderedByKaf();
				//		}
				$dis=$this->hlp->createSelectList("discipline",$dislist,"Выбрать");
				$form->addElement($dis);
				$form->getElement("discipline")->setAttrib("onchange", 'ocontroldisChanged()');

				$teachers=array();
				$_teachers=$this->hlp->createSelectList("teacher",$teachers,"Выбрать преподавателя");
				$form->addElement($_teachers);

					
				// семестры
				$semestr=$this->hlp->arrayFilled(1,2);
				$semList=$this->hlp->createSelectList("semestr",$semestr,"",$criteria["semestr"]);
				$form->addElement($semList);
				//		$form->getElement("semestr")->setAttrib("onchange", 'ocontrolsemChanged()');

				// курс
				$form->addElement("hidden","kurs");
				// группа
				$form->addElement("hidden","group");

				// вид документа
				$doctype=$this->data->getInfoForSelectList("ocontrol_doctypes"," 1 ORDER BY title ASC");
				$dc=$this->hlp->createSelectList("doctype",$doctype);
				$form->addElement($dc);

				// выд выходного контроля
				$outcontrol=$this->data->getInfoForSelectList("studoutcontrols", " 1 ORDER BY title ASC");
				$oc=$this->hlp->createSelectList("outcontrol",$outcontrol);
				$form->addElement($oc);

				$form->addElement("text","numb",array(
		"class"=>"inputSmall"		
		));
		// @TODO дата брацо из учебного плана
		$form->addElement("text","begins",array(
		"class"=>"typic_input",
		"onMouseDown"=>'picker($(this));'		
		));
		$form->getElement("begins")->setAttrib("id","beginsNew");

		$form->addElement("submit","OK",array("class"=>"apply_text"));
		$form->getElement("OK")->setName("добавить");
		return $form;
			}

			private function createForm_editDate()
			{
				// форма добавления нового плана, изначально скрыта
				$form=new Formochki();
				$form->setAttrib('name','editDateForm');
				$form->setAttrib('id','editDateForm');
				$form->setMethod('POST');
				$form->setAction($this->baseLink."/editdate");
				$form->addElement("hidden","id",array("value"=>0));

				$form->addElement("text","begins",array(
		"class"=>"typic_input",
		"onMouseDown"=>'picker($(this));'		
		));
		$form->getElement("begins")->setAttrib("id","begins");
		$form->addElement("text","ends",array(
		"class"=>"typic_input",
		"onMouseDown"=>'picker($(this));'
		));
		$form->getElement("ends")->setAttrib("id","ends");
		$form->addElement("submit","OK",array("class"=>"apply_text"));
		$form->getElement("OK")->setName("СМЕНИТЬ");
		return $form;
			}

			private function createForm_filter($criteria)
			{
				// форма добавления нового плана, изначально скрыта
				$form=new Formochki();
				$form->setAttrib('name','filterForm');
				$form->setAttrib('id','filterForm');
				$form->setMethod('POST');
				$form->setAction($this->baseLink);

				$yearz=$this->data->studyPlans_getYearInterval($criteria["studyYearStart"]);
				$yearList=$this->data->studyPlans_buildYearList($yearz);
				$yearSelect=$this->hlp->createSelectList("studyYearStart",$yearList,"",$criteria["studyYearStart"]);
				$form->addElement($yearSelect);

				$kursy=$this->hlp->kursy();
				$kursList=$this->hlp->createSelectList("kurs",$kursy,"",$criteria["kurs"]);
				$form->addElement($kursList);

				$semestr=$this->hlp->arrayFilled(1,2);
				$semList=$this->hlp->createSelectList("semestr",$semestr,"",$criteria["semestr"]);
				$form->addElement($semList);

				$osnovList=$this->hlp->createSelectList("osnov",$this->osnovs,"",$criteria["osnov"]);
				$form->addElement($osnovList);

				//		// древовидный список групп и специальностей
				$groupList=$this->createForm_elementGroupsTreeList($criteria,$this->gosParams);
				$form->addElement($groupList);


				//		$form->addElement("submit","OK");
				//		$form->getElement("OK")->setName("добавить");
				return $form;
			}

			/** по заданному курсу строит древовидный список групп, узлы - названия специальностей
			 * @param integer $kursSelected
			 * @return zend_form_element
			 */
			private function createForm_elementGroupsTreeList($criteria,$gos)
			{
				$kursSelected=$criteria["kurs"];
				$groupSelected=$criteria["group"];
				$osnSelected=$criteria["osnov"];
				$studyYearStart=$criteria["studyYearStart"];

				// комбинированный список групп и специальностей
				$groups=array();
				// переберем специальности
				foreach ($gos as $gosparam=>$gosInfo)
				{
					// узнаем группы на данной спец./курсе
					$groups_current=$this->data->getGroupsOnSpecKursOsnYearStart($gosparam,$kursSelected,$osnSelected,$studyYearStart);
					// если есть такие
					if (! is_null($groups_current))
					{
						// то построим массив для списка с <OPTGROUP>
						foreach ($groups_current as $groupInfo)
						{
							$groups[$gosInfo["gosTitle"]][$groupInfo["id"]]=$groupInfo["groupTitle"];
						}
					}

				}
				//		echo "<pre>".print_r($groups,true)."</pre>";
				$groupList=$this->hlp->createSelectList("group",$groups,"выбрать группу",$groupSelected);
				return $groupList;
			}

			private function buildCriteria($in=null)
			{
				if (is_null($in)) $in=$this->session->getIterator();
				$criteria['studyYearStart']=( !isset($in['studyYearStart']) || $in['studyYearStart'] <= 0)
				?	$this->studyYear_now["start"]
				:	$in['studyYearStart']
				;

				$criteria['kurs']=( !isset($in['kurs']) || $in['kurs'] <= 0)
				?	1
				:	$in['kurs'];
				// @TODO Проверка еси курс больше 6

				$criteria['semestr']=( !isset($in['semestr']) || $in['semestr'] <1 || $in['semestr']>2)
				?	1
				:	$in['semestr'];

				$criteria['group']=( !isset($in['group']) || $in['group'] < 0)
				?	0
				:	$in['group'];

				$criteria['discipline']=( !isset($in['discipline']) || $in['discipline'] < 0)
				?	0
				:	$in['discipline'];

				$criteria['teacher']=( !isset($in['teacher']) || $in['teacher'] < 0)
				?	0
				:	$in['teacher'];

				//		$criteria['kurs']=$in['kurs']<1?1:$in['kurs'];

				$osnDef=$this->hlp->getFirstElem($this->osnovs);
				$criteria['osnov']=(!isset($in['osnov']) || $in['osnov'] <= 0 )
				?	$osnDef["key"]
				:	$in['osnov'];

				return $criteria;
			}

			/**
			 * по статусу листа генерит текст и иконку
			 * @param integer $state
			 * @return array
			 */
			private function getListState($approved,$id)
			{
				if ($approved==1)
				{
					$result["dekan"]=1;
					$result["icoClass"]="lockedIcoSmall";
					$result["msg"]="Подписан/Изменения не принимаются";
					$result["class"]="warning";

				}
				else
				{
					$result["dekan"]=0;
					$result["icoClass"]="unlockedIcoSmall";
					$result["msg"]="Не подписан/Изменения принимаются";
					$result["class"]="done";
				}
				$result["id"]=$id;
				return $result;
			}
}