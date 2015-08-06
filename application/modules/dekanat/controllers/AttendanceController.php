<?php
/** Посещаемость
 * @author zlydden
 *
 */
class Dekanat_AttendanceController extends Zend_Controller_Action
{

	//	protected  $currentSpecIdList;
	protected $gosParams;
	protected $gosParamsDef;

	private $osnovs;
	private $currentFacultId;

	private $session;
	private $baseLink;
	private $redirectLink;
	private $confirmWord;
	private $data;
	private $studyYear_now;
	private $hlp; // помощник действий Typic

	private $assignError=999;
	private $assignErrorMsg=array(
			"0"=>"Успешно",
			"1"=>"Не найден учебный план",
			"2"=>"Не определены дисциплины учебного плана",
			"3"=>"Пустой состав группы",
			"999"=>"Неизвестная ошибка"
	);

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

		$this->confirmWord="УДАЛИТЬ";
		//		Zend_Controller_Action_HelperBroker::addPrefix('My_Helper');
		$this->hlp=$this->_helper->getHelper('Typic');
		// установим учебный год, который сейчас происходит
		Zend_Loader::loadClass('Dekanat');

		$groupEnv=Zend_Registry::get("groupEnv");
		$this->currentFacultId=$groupEnv['currentFacult'];
		$this->data=new Dekanat($this->currentFacultId);
		$this->studyYear_now=$this->data->getStudyYear_Now();

		//		$this->currentDivision=$this->data->getDivisionId();
		// 		$this->SpecsOnFacult=$this->data->getSpecsByFacultForSelectList($this->currentDivision);
		// 		$this->specDefault=$this->hlp->getFirstElem($this->SpecsOnFacult);
		$this->gosParams=$this->data->getGosParams();
		$this->gosParamsDef=$this->data->getGosParamsDef();

		$this->osnovs=$this->data->getInfoForSelectList("osnov");
		$this->view->facultInfo=$this->data->getFacultInfo($this->currentFacultId);
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->title=$moduleTitle
		.'. Факультет - '.$this->view->facultInfo['title']
		.". ".$modContrTitle.'. ';
		//		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');

		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('formnewlist', 'json')->initContext('json');
		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');
		$ajaxContext ->addActionContext('personedit', 'json')->initContext('json');
		$ajaxContext ->addActionContext('personmassedit', 'json')->initContext('json');
		//		$ajaxContext ->addActionContext('details', 'json')->initContext('json');
		//		$ajaxContext ->addActionContext('plansave', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/dekanat.js');

		//		// для ODT
		//		$this->oo=$this->_helper->getHelper('Oo');
		//		// Init the Context Switch Action helper
		//		$contextSwitch = $this->_helper->contextSwitch();
		//
		//		// Add the new context
		//		$contextSwitch->setContexts($this->oo->getHeaterJpg());
		//
		//		// Set the new context to the reports action
		//		$contextSwitch->setActionContext('get', 'jpg');
		//
		//		// Initializes the action helper
		//		$contextSwitch->initContext();



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

		$this->view->formAdd=$this->createForm_add();

		$this->view->confirmDelete=$this->confirmWord;
		$this->view->formDelete=$this->createForm_delete();
		$this->view->formEditDate=$this->createForm_editDate();

		// список листов удовлетворяющих критериям
		$list=$criteria["group"]==0
		? array()
		: $this->data->attendance_list($criteria["kurs"],$criteria["group"],$criteria["studyYearStart"]);
		$this->view->list=$list;
	}


	public function odtAction()
	{
		// отключить вывод
		$this->_helper->layout->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);

		$id= (int)$this->_request->getParam('id',0);
		if ($id<1) $this->_redirect($this->redirectLink);
		$listInfo=$this->data->attendance_listInfo($id);

		// наша группа ?
		$chkGr=( array_key_exists($listInfo["gosparam"],$this->gosParams) );
		if (!$chkGr) $this->_redirect($this->redirectLink);

		$TBS=new Tbs_Tbs;
		// Prepare some data for the demo
		//		$data = array();
		//		$data[] = array('firstname'=>'Sandra', 'name'=>'Hill', 'number'=>'1523d' );
		//		$data[] = array('firstname'=>'Roger', 'name'=>'Smith', 'number'=>'1234f' );
		//		$data[] = array('firstname'=>'William', 'name'=>'Mac Dowell', 'number'=>'5491y' );
		$template='attendanceList.ods';
		$data=$this->data->attendance_details($id);

		// сначала надо загрузить перечень дисциплин - сформируются колонки
		$dis=array();
		//		$marks=array();
		foreach ($data as $d)
		{
			$dis[$d["disTitle"]]["id"]=$d["discipline"];
		}

		// затем уже студни с их данными
		$info=array();
		foreach ($data as $d)
		{
			$info[$d["subgrTitle"]][$d["fio"]]["tit_".$d["discipline"]]=$d["title_letter"];
			if ($d["comment"]!=='') $com=$d["comment"];
			else $com='';
			$info[$d["subgrTitle"]][$d["fio"]]["com_".$d["discipline"]]=$d["comment"];
			//			$info[$d["fio"]]["title_letter"]=
			//			$info[$d["subgrTitle"]][$d["fio"]]["tit_".$d["discipline"]."_com"]=$d["comment"];
		}
		//		echo "<pre>".print_r($dis,true)."</pre>";
		//		echo "<pre>".print_r($info,true)."</pre>";
		//		die();

		$TBS->LoadTemplateUtf8($template);
		$TBS->MergeBlock('dis', $dis);
		// шобы расставить индексы нужные
		$TBS->MergeBlock('dis2', $dis);
		$TBS->MergeBlock('info', $info);
		//		$TBS->MergeBlock('info', $info);
		$file_name = str_replace('.','_'.date('Y-m-d').'.',$template);

		// вывод
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

	public function formchangedAction()
	{
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";
		$this->view->currentController = $this->_request->getControllerName();

		// узнаем что к нам пришло
		if (!$this->_request->isPost()) 		$this->_redirect($this->redirectLink);
		// узнаем что к нам пришло
		$formData = $this->_request->getPost('formData');
		$oldData=$this->session->getIterator();
		// обновим сессию
		$this->sessionUpdate($formData );
		$criteria=$this->buildCriteria();

		// обновим форму - группы зависят от курса, учебного года и формы обучения
		$groupList=$this->createForm_elementGroupsTreeList($criteria,$this->gosParams);
		$out["group"]=$groupList->render();

		// обновить список листов удовлетворяющих критериям
		// список листов удовлетворяющих критериям
		$list=$criteria["group"]==0
		? array()
		: $this->data->attendance_list($criteria["kurs"],$criteria["group"],$criteria["studyYearStart"]);

		$this->view->list=$list;
		$out["list"]=$this->view->render($this->_request->getControllerName()."/_list.phtml");

		$this->view->out=$out;
	}

	// форма создания нового листа, вызывается с другого контролера
	public function formnewlistAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";
		$this->view->currentController = $this->_request->getControllerName();
		$group=(int)$this->_request->getPost('group');

		$form=$this->createForm_add();
		$this->view->formNew=$form;
		$out["form"]=$this->view->render($this->_request->getControllerName()."/_formNewList.phtml");
		$this->view->out=$out;

	}

	/**
	 * смена состояния в аттестационном листе
	 */
	public function personeditAction()
	{
		// данные, получаются из JS
		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);

		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";
		$this->view->currentController = $this->_request->getControllerName();

		$discipline=(int)$this->_request->getPost('discipline');
		$id=(int)$this->_request->getPost('id'); // ID листа
		$state=(int)$this->_request->getPost('state');
		$userid=(int)$this->_request->getPost('userid');
		$filter = new Zend_Filter_Alnum(array('allowwhitespace' => true));

		$comment=$filter->filter($this->_request->getPost('comment'));

		// наш ли пользователь?
		$studInfo=$this->data->getStudSpecDivOsnov($userid);
		$chkStud=( array_key_exists($studInfo["gosparam"],$this->gosParams) );

		// допустимо ли состояние ?
		$stateList=$this->data->getInfoForSelectList("attendance_states"," 1 ORDER BY id ASC"); // ! важно
		$chkSt=array_key_exists($state,$stateList);

		// терпимо - идем дальше
		$out=array();
		if ($chkSt && $chkStud)
		{
			$utf8=$this->_helper->getHelper('Utf8');
			$aff=$this->data->attendance_personChange($userid,$state,$discipline,$id,$comment);
			$elemName="d".$discipline."u".$userid;

			$title=$utf8->utf8_substr($stateList[$state],0,1);
			switch ($state)
			{
				case 1:
					$title='<span class="done">'.$title."</span>";
					break;

				case 2:
					$title='<span class="warning">'.$title."</span>";
					break;

				default:
					$title='<span class="error">'.$title."</span>";
					break;
			}

			//			$this->view->asdasd=
			$out[$elemName]=$utf8->utf8_strlen($comment) >0
			?	$title."\n<br><small>".$comment."</small>"
			:	$title;
			if ($aff>0) $this->view->out=$out;
		}


	}
	/**
	 * смена состояния в аттестационном листе - массовая версия
	 */
	public function personmasseditAction()
	{
		// данные, получаются из JS
		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);

		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->iconpath= $this->view->baseUrl."/"."public"."/"."images"."/";
		$this->view->currentController = $this->_request->getControllerName();

		$discipline=(int)$this->_request->getPost('discipline');
		$id=(int)$this->_request->getPost('id'); // ID листа
		$state=(int)$this->_request->getPost('state');
		$userid=(int)$this->_request->getPost('userid');

		// допустимо ли состояние ?
		$stateList=$this->data->getInfoForSelectList("attendance_states"," 1 ORDER BY id ASC"); // ! важно
		$chkSt=array_key_exists($state,$stateList);
		// наш ли это лист?
		$listInfo=$this->data->attendance_listInfo($id);
		$chkList=( array_key_exists($listInfo["gosparam"],$this->gosParams) );

		// состояние, пользователь, дисциплина или лист не годяцо - пшнх
		if (!$chkList || !$chkSt || $userid<0 || $discipline<0) $this->_redirect($this->baseLink);
		// список пользователей в листе
		$listUsers=$this->data->attendance_users($id);
		// список дисциплин в листе
		$listDisciplines=$this->data->attendance_disciplines($id);

		$out=array();
		$utf8=$this->_helper->getHelper('Utf8');
		$stateTitle=$utf8->utf8_substr($stateList[$state],0,1);
		switch ($state)
		{
			case 1:
				$stateTitle='<span class="done">'.$stateTitle."</span>";
				break;

			case 2:
				$stateTitle='<span class="warning">'.$stateTitle."</span>";
				break;

			default:
				$stateTitle='<span class="error">'.$stateTitle."</span>";
				break;
		}
		// два варианта - или массово к студенту или массово к дисциплине
		// указана дисциплина
		if ($userid==0 && $discipline>0)
		{
			// есть ли данная дисциплина в листе?
			$chkDiscip=array_key_exists($discipline,$listDisciplines);
			if ($chkDiscip)
			{
				// сменим статус у всех на заданный
				foreach ($listUsers as $_userid=> $fio)
				{
					$aff=$this->data->attendance_personChange($_userid,$state,$discipline,$id);
					if ($aff>0)
					{
						$elemName="d".$discipline."u".$_userid;
						$out[$elemName]=$stateTitle;
					}

				}
			}
		}
		// указан пользователь
		elseif ($userid>0 && $discipline==0)
		{
			$studInfo=$this->data->getStudSpecDivOsnov($userid);

			// наш ли пользователь и есть ли он в листе?
			$chkStud=( array_key_exists($studInfo["gosparam"],$this->gosParams)
					&&  array_key_exists($userid,$listUsers) );
			if ($chkStud)
			{
				// сменим статус у всех на заданный
				foreach ($listDisciplines as $_discipline=> $title)
				{
					$aff=$this->data->attendance_personChange($userid,$state,$_discipline,$id);
					if ($aff>0)
					{
						$elemName="d".$_discipline."u".$userid;
						$out[$elemName]=$stateTitle;

					}
				}
			}
		}
		$this->view->out=$out;



		// терпимо - идем дальше
		$out=array();
		if ($chkSt && $chkStud)
		{
			$utf8=$this->_helper->getHelper('Utf8');
			$aff=$this->data->attendance_personChange($userid,$state,$discipline,$id,$comment);
			$elemName="d".$discipline."u".$userid;

			$title=$utf8->utf8_substr($stateList[$state],0,1);
			//			$this->view->asdasd=
			$out[$elemName]=$utf8->utf8_strlen($comment) >0
			?	$title."\n<br><small>".$comment."</small>"
			:	$title;
			if ($aff>0) $this->view->out=$out;
		}


	}

	public function deleteAction()
	{
		$id= (int)$this->_request->getParam('id',0);
		// @FIXME проверка наш ли лист и верно ли все?
		if ($id<1) $this->_redirect($this->redirectLink);

		$listInfo=$this->data->attendance_listInfo($id);
		// наша группа ?
		$chkGr=( array_key_exists($listInfo["gosparam"],$this->gosParams)
		);
		// если наше - удалить
		if ($chkGr)
		{
			try
			{
				$res=$this->data->attendance_del($id);
			}
			catch (Zend_Exception $e)
			{
				$res=$e->getMessage();
			}
		}
		if (strrpos($res, "1451 Cannot") || strpos($res, "1452 Cannot"))
		{
			$this->view->errMsg="Нельзя удалить заполненный лист";
		}
		else $this->_redirect($this->redirectLink);
	}


	public function editdateAction()
	{
		$id= (int)$this->_request->getPost('id',0);
		// @FIXME проверка наш ли лист и верно ли все?
		if ($id<1) $this->_redirect($this->redirectLink);

		$listInfo=$this->data->attendance_listInfo($id);
		// наша группа ?
		$chkGr=( array_key_exists($listInfo["gosparam"],$this->gosParams) );
		//		if (!$chkGr) $this->_redirect($this->redirectLink);

		//		$begins= $this->_request->getPost('begins','');
		//		$ends= $this->_request->getPost('ends','');
		$filter = new Zend_Filter_StripTags();
		$begins=$filter->filter($this->_request->getPost('begins'));
		$ends=$filter->filter($this->_request->getPost('ends'));
		$begins=$this->hlp->date_DMY2array($begins);
		$ends=$this->hlp->date_DMY2array($ends);
		$chkB=checkdate($begins["month"],$begins["day"],$begins["year"]);
		$chkE=checkdate($ends["month"],$ends["day"],$ends["year"]);

		// не годицо?
		if (!$chkB || !$chkE || !$chkGr) $this->_redirect($this->redirectLink);
		// годицо
		$begins=$begins["year"]."-".$begins["month"]."-".$begins["day"];
		$ends=$ends["year"]."-".$ends["month"]."-".$ends["day"];
		// меняем
		$this->data->attendance_dateEdit($id,$begins,$ends);
		$this->_redirect($this->redirectLink);
	}

	public function editAction()
	{
		$logger=Zend_Registry::get("logger");
		$id= (int)$this->_request->getParam('id',0);
		if ($id<1) $this->_redirect($this->redirectLink);

		$listInfo=$this->data->attendance_listInfo($id);
		// наша группа ?
		// проверка наш ли лист и верно ли все?
		$chkGr=( array_key_exists($listInfo["gosparam"],$this->gosParams));
		if (!$chkGr) $this->_redirect($this->redirectLink);

		$details=$this->data->attendance_details($id);
		//				$logger->log($details, Zend_Log::INFO);

		if (count($details)<1) $this->view->details=array();
		else
		{

			$listInfo=$this->data->attendance_listInfo($id);
			$this->view->listInfo=$listInfo;
			$disciplines=$this->data->attendance_disciplines($id);
			$this->view->disciplinesList=$disciplines;

			$details=$this->detailsPrepare($details);
			//		$disciplines=$this->
			$this->view->details=$details;

			//			$logger->log($listInfo, Zend_Log::INFO);
			// узнаем отличаются ли дисциплины в плане от тех, что в аттестационном листе
			$_info=array(
// 					"spec"=>$listInfo["spec"],
// 					"division"=>$listInfo["division"],
					"osnov"=>$listInfo["osnov"],
					"gosparam"=>$listInfo["gosparam"]
			);
			$planid=$this->data->studyPlans_findPlan($_info,$listInfo["studyYearStart"],$listInfo["kurs"],$listInfo["semestr"]);
			$planDisciplines=$this->data->studyPlans_getDisciplines($planid,true);
			$planDisciplines=$this->disciplineArray($planDisciplines);
			// разница - есть в плане, но нету в листе
			$disDiff=array_diff_key($planDisciplines,$disciplines);
			$this->view->disDiffAdded=is_null($disDiff)?'':$disDiff;
			// есть в листе но нету в плане
			$disDiff2=array_diff_key($disciplines,$planDisciplines);
			$this->view->disDiffDeleted=is_null($disDiff2)?'':$disDiff2;

			// выявим разницу между составом группы и аттестационным листом
			// состав группы - userid=>фио
			$usersInGroup=$this->data->getStudentsInGroup_userids($listInfo["groupid"]);
			$usersInList=$this->data->attendance_users($id);
			// не указаны в листе
			$diffGr=array_diff_key($usersInGroup,$usersInList);
			$this->view->diffGr=$diffGr;
			// лищние в листе
			$diffAtt=array_diff_key($usersInList,$usersInGroup);
			$this->view->diffAtt=$diffAtt;
			// флаг шо есть какаято разницо
			if (!empty($disDiff2) || !empty($disDiff) || !empty($diffGr) || !empty($diffAtt))
			{
				$this->view->diffz=true;
				// ссылка для починки
				$this->view->fixLink=$this->baseLink."/fix/id/".$id;
			}
			else
			{
				$this->view->diffz=false;
			}

			//						$logger->log($diffGr, Zend_Log::INFO);
			//			$logger->log($diffAtt, Zend_Log::INFO);
			//						$logger->log($usersInGroup, Zend_Log::INFO);
			//						$logger->log($this->view->details, Zend_Log::INFO);
			//						$logger->log($disDiff, Zend_Log::INFO);
			//						$logger->log($disDiff2, Zend_Log::INFO);
			// форма редактирования состояния студент-дисциплина
			$this->view->formStateChange=$this->createForm_changeState($id);

			// форма смены состояния массово - студент-дисциплинЫ или дисциплина-студентЫ
			$this->view->formStateChangeMass=$this->createForm_changeStateMass($id);
			// форма смены состояния по предметам у всех студней

		}

	}

	public function fixAction()
	{
		//		$logger=Zend_Registry::get("logger");

		$id= (int)$this->_request->getParam('id',0);
		if ($id<1) $this->_redirect($this->redirectLink);

		//--------------
		// исходные данные
		$listInfo=$this->data->attendance_listInfo($id);
		// наша группа ?
		// проверка наш ли лист и верно ли все?
		$chkGr=( array_key_exists($listInfo["gosparam"],$this->gosParams) );
		if (!$chkGr) $this->_redirect($this->redirectLink);

		// инфо о листе
		$listInfo=$this->data->attendance_listInfo($id);
		$disciplines=$this->data->attendance_disciplines($id);
		// перечень пользователей
		$users=$this->data->attendance_users($id);
		// состав группы
		$usersInGroup=$this->data->getStudentsInGroup_userids($listInfo["groupid"]);

		// узнаем отличаются ли дисциплины в плане от тех, что в аттестационном листе
		$_info=array(
				"osnov"=>$listInfo["osnov"],
				"gosparam"=>$listInfo["gosparam"]
		);
		// план
		$planid=$this->data->studyPlans_findPlan($_info,$listInfo["studyYearStart"],$listInfo["kurs"],$listInfo["semestr"]);
		$planDisciplines=$this->data->studyPlans_getDisciplines($planid,true);
		$planDisciplines=$this->disciplineArray($planDisciplines);

		// ---------------
		// разниы
		// пользователи не указаны в листе
		$diffGr=array_diff_key($usersInGroup,$users);
		// пользователи лищние в листе
		$diffAtt=array_diff_key($users,$usersInGroup);
		// дисциплины есть в плане, но нету в листе
		$disDiff=array_diff_key($planDisciplines,$disciplines);
		// дисциплины есть в листе но нету в плане
		$disDiff2=array_diff_key($disciplines,$planDisciplines);

		// -------------
		// какие то действия
		// 1. уберем лишние дисциплины и пользователей шобы не мешались
		// 1.1. удалим упоминание об этих дисциплинах
		if (!empty($disDiff2)) $this->data->attendance_disciplineRemove($id,array_keys($disDiff2));
		// 1.2. удалим упоминания о пользователях
		if (!empty($diffAtt)) $this->data->attendance_usersRemove($id,array_keys($diffAtt));
		// далее - в листе нет лишних дисциплин и пользователей и их связок

		// 2. Новый состав пользователей указанных в листе

		// 3. Добавим пользователей и дисциплины
		// 3.1. добавим недостающиих пользоваталей с дисциплинами из плана
		if (!empty($diffGr))
		{
			foreach ($diffGr as $userid => $fio)
			{
				$this->data->attendance_userAdd($id,$userid,$planDisciplines);
			}

		}
		// 3.2. добавим недостающиие дисциплины к пользователями, которые уже в листе
		if (!empty($disDiff))
		{
			// добавить к листу
			foreach ($disDiff as $discipline=>$title)
			{
				$this->data->attendance_disciplineAdd($id,$discipline,$users);
			}

		}

		$this->_redirect($this->redirectLink."/edit/id/".$id);
	}

	public function addAction()
	{
		$logger=Zend_Registry::get("logger");

		$semestr= (int)$this->_request->getPost('semestr',0);
		$semestr=($semestr<1 || $semestr >2) ? 1 : $semestr;

		$kurs= (int)$this->_request->getPost('kurs',0);
		$kurs=($semestr<1 || $semestr >6) ? 1 : $kurs;

		$groupid= (int)$this->_request->getPost('group',0);
		$number= (int)$this->_request->getPost('numb',0);

		// учебный год возьмем из сессии
		$criteriaSession=$this->buildCriteria();
		$studyYearStart=$criteriaSession["studyYearStart"];

		$filter = new Zend_Filter_StripTags();
		$begins=$filter->filter($this->_request->getPost('begins'));
		$ends=$filter->filter($this->_request->getPost('ends'));
		$begins=$this->hlp->date_DMY2array($begins);
		$ends=$this->hlp->date_DMY2array($ends);
		$chkB=checkdate($begins["month"],$begins["day"],$begins["year"]);
		$chkE=checkdate($ends["month"],$ends["day"],$ends["year"]);

		$grInfo=$this->data->getGroupInfo($groupid);
		$chkGr=(array_key_exists($grInfo["gosparam"],$this->gosParams)
				&& $this->currentFacultId==$grInfo["facult"]);

		// если даты неверны или группа дроугого факультета - отлуп
		if (!$chkB || !$chkE || !$chkGr) $this->_redirect($this->redirectLink);

		// создадим лист
		$begins=$begins["year"]."-".$begins["month"]."-".$begins["day"];
		$ends=$ends["year"]."-".$ends["month"]."-".$ends["day"];

		// 2. найдем подходящий учебный план
		// по данным группы по нач. уч. года, курсу, семестру, спец., отдел и форме обуч.
		$planid=$this->data->studyPlans_findPlan($grInfo,$studyYearStart,$kurs,$semestr);
		//		echo "<pre>".print_r($kurs,true)."</pre>";
		// если нету плана?
		if ($planid===false)
		{
			$this->assignError=1;
		}
		// 3. узнаем дисциалины плана
		$disciplines=$planid
		?	$this->data->studyPlans_getDisciplines($planid,true)
		:	false;
		// если нету дисциплин?
		if ($disciplines===false )
		{
			$this->assignError=2;
		}
		// 4. выясним состав группы
		$userlist=$this->data->getStudentsInGroup_lightVer($grInfo["id"]);
		if (count($userlist)<1 )
		{
			$this->assignError=3;
		}
		// есть план, есть дисциплины и студни
		$chk=$planid && $disciplines && count($userlist) >0;
		if ($chk)
		{
			// создадим и добавим
			$do=$this->data->attendance_addComplex
			($begins,$ends,$number,$groupid,$studyYearStart,$kurs,$semestr,$disciplines,$userlist);

			if ($do["flag"]===true) // ошибок нет
			{
				$this->view->insertID=$do["insId"];
				$this->assignError=0;
				$this->view->affected=$do["affected"];
			}
		}

		$this->view->assError=$this->getAssignError();
		$this->view->assMsg=$this->getAssignMsg();
		// дополним сообщение об ошибке
		if ($do["flag"]===false) $this->view->assMsg.=": ".$do["errorMsg"];

		// перейти к редактированию еси все получилось
		if ($this->assignError==0) $this->_redirect($this->redirectLink."/edit/id/".$do["insId"]);

		/*
		 // выясним пользователей по № группы
		// @TODO нужна облегченная функция, без лишних данных
		// @TODO нужны тока USERID отсортированный по ФИО
		$students=$this->data->getStudentsInGroup($groupid);
		// выясним дисциплины учебного плана
		// 1.выясним специальность
		$info=$this->data->getSpecAndDivByGroup($groupid);
		// узнаем форму обучения, у ДАННЫХ студней она одинакова
		$osnov=$this->data->getOsnovByUser($students[0]["userid"]);
		// 2.узнаем подходящий учебный план
		$planid=$this->data->studyPlans_findPlan($kurs,$info["spec"],$this->currentDivision,$osnov,$begins,$ends);
		// 3. узнаем дисциплины этого плана
		$disciplines=$this->data->studyPlans_getDisciplines($planid);
		*/
		// внесем все это
	}

	public function getAssignError()
	{
		return $this->assignError;
	}

	public function getAssignMsg()
	{
		return $this->assignErrorMsg[$this->assignError];
	}

	/** переделка массива дисциплин
	 * @param array $array инфо о дисциплине (id,planid,outControl,contrCount,outTitle,disTitle)
	 * @return array (discipline=>disTitle)
	 */
	private function disciplineArray($array)
	{
		$result=array();
		foreach ($array as $elem)
		{
			$result[$elem["discipline"]]=$elem["disTitle"];
		}
		return $result;
	}

	/** детали атт. листа. ВЫХОД: подгруппа->ФИО->дисциплины->детали
	 *
	 * @param array $arr
	 * @return array
	 */
	private function detailsPrepare($arr)
	{
		// исходный массив отсортирован подгруппа, ФИО, дисциплина
		$result=array();

		foreach ($arr as $elem)
		{
			$result[$elem["subgrTitle"]][$elem["fio"]][$elem["discipline"]]=$elem;
		}
		return $result;
	}

	/** привязка студней к аттестационному листу
	 * @param array $groupInfo инфо о группе (обязательно: id,spec,division,osnov)
	 * @param unknown_type $kurs
	 * @param unknown_type $semestr
	 * @param unknown_type $studyYearStart
	 * @param unknown_type $listID
	 * @return integer or FALSE
	 */
	/*
	 private function assign2list($groupInfo,$kurs,$semestr,$studyYearStart,$listID)
	 {
	//			$now=date("Y-m-d H:i:s");

	//					$logger=Zend_Registry::get("logger");
	//$logger->log($groupInfo, Zend_Log::INFO);
	//$logger->log($semestr, Zend_Log::INFO);
	//$logger->log($studyYearStart, Zend_Log::INFO);
	//$logger->log($kurs, Zend_Log::INFO);
	// 2. найдем подходящий учебный план
	// по данным группы по нач. уч. года, курсу, семестру, спец., отдел и форме обуч.
	$planid=$this->data->studyPlans_findPlan($groupInfo,$studyYearStart,$kurs,$semestr);
	// если нету плана?
	if ($planid===false)
	{
	$this->assignError=1;
	return false;
	}
	// 3. узнаем дисциалины плана
	$disciplines=$this->data->studyPlans_getDisciplines($planid,true);
	// если нету дисциплин?
	if ($disciplines===false )
	{
	$this->assignError=2;
	return false;
	}
		
	// 4. выясним состав группы
	$userlist=$this->data->getStudentsInGroup_lightVer($groupInfo["id"]);
	// если состав пуст?
		
	if (count($userlist)<1 )
	{
	$this->assignError=3;
	return false;
	}
		
	//			if (count($userlist)<1) return false;
	// 5. заполним атт. лист пользователями и дисциплинами
	$affected=$this->data->attendance_assign($userlist,$disciplines,$listID);
	if (count($userlist)==$affected["users"]) $this->assignError=0;
	//		$logger->log($planid, Zend_Log::INFO);
	//					$logger->log($userlist, Zend_Log::INFO);
	//					$logger->log($affected, Zend_Log::INFO);
	//					$logger->log(count($userlist), Zend_Log::INFO);


	return $affected;
	;
	}*/

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

	/** форма-пустышка для смены состояния у студня
	 * @param unknown_type $id
	 * @return Formochki
	 */
	private function createForm_changeState($id)
	{
		// форма добавления нового плана, изначально скрыта
		$form=new Formochki();
		$form->setAttrib('name','formStateChange');
		$form->setAttrib('id','formStateChange');
		$form->setMethod('POST');
		//		$form->setAction($this->baseLink."/personedit");
		$form->addElement("hidden","id",array("value"=>$id));
		$form->addElement("hidden","userid",array("value"=>0));
		$form->addElement("hidden","discipline",array("value"=>0));
		$form->addElement("text","comment",array("class"=>"medinput"));

		$states=$this->data->getInfoForSelectList("attendance_states"," 1 ORDER BY title");
		$stateList=$this->hlp->createSelectList("state",$states,"выберите");
		//		$stateList->addOption(array("onClick"=>"attendanceState();"));
		$form->addElement($stateList);
		//		$form->getElement("state")->setOptions(array("onChange"=>"attendanceState();"));

		$form->addElement("button","OK",array(
				"class"=>"apply_text",
				"onClick"=>"attendanceState();"
		));
		$form->getElement("OK")->setName("Сменить");
		return $form;
	}

	/** форма-пустышка для массвовой смены состояний
	 * @param integer $id
	 * @return Formochki
	 */
	private function createForm_changeStateMass($id)
	{
		// форма добавления нового плана, изначально скрыта
		$form=new Formochki();
		$form->setAttrib('name','formStateChangeMass');
		$form->setAttrib('id','formStateChangeMass');
		$form->setMethod('POST');
		//		$form->setAction($this->baseLink."/personedit");
		$form->addElement("hidden","id",array("value"=>$id));
		$form->addElement("hidden","userid",array("value"=>0));
		$form->addElement("hidden","discipline",array("value"=>0));


		$states=$this->data->getInfoForSelectList("attendance_states"," 1 ORDER BY title");
		$stateList=$this->hlp->createSelectList("state",$states,"выберите");
		//		$stateList->addOption(array("onClick"=>"attendanceState();"));
		$form->addElement($stateList);
		//		$form->getElement("state")->setOptions(array("onChange"=>"attendanceState();"));

		$form->addElement("button","OK",array(
				"class"=>"apply_text",
				"onClick"=>"attendanceStateMass();"
		));
		$form->getElement("OK")->setName("Сменить");
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


	private function createForm_add()
	{
		// форма добавления нового плана, изначально скрыта
		$form=new Formochki();
		$form->setAttrib('name','addForm');
		$form->setAttrib('id','addForm');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/add");

		//		$kursSelected=intval($this->session->kurs)<=0?1:intval($this->session->kurs);
		//		$groupSelected=intval($this->session->group)<0?0:intval($this->session->group);
		$form->addElement("hidden","kurs",array("value"=>0));
		$form->addElement("hidden","group",array("value"=>0));
		//		$form->addElement("hidden","osnov",array("value"=>0));

		$semsList=$this->hlp->createSelectList("semestr",array(1=>1,2=>2));
		$form->addElement($semsList);

		//		$form->addElement("hidden","studyYearStart",array("value"=>0));
		// чтобы picker не путал
		// PS. будем брать из сессии
		//		$form->getElement("studyYearStart")->setAttrib("id","studyYearStartNew");

		$form->addElement("text","numb",array(
				"class"=>"inputSmall"
		));
		$form->addElement("text","begins",array(
				"class"=>"typic_input",
				"onMouseDown"=>'picker($(this));'
		));
		$form->getElement("begins")->setAttrib("id","beginsNew");
		$form->addElement("text","ends",array(
				"class"=>"typic_input",
				"onMouseDown"=>'picker($(this));'
		));
		$form->getElement("ends")->setAttrib("id","endsNew");
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

		//		$kursSelected=intval($this->session->kurs)<=0?1:intval($this->session->kurs);
		//		$groupSelected=intval($this->session->group)<0?0:intval($this->session->group);
		//		$osnovSelected=intval($this->session->osnov)<0?1:intval($this->session->osnov);

		//			echo $groupSelected;
		$kursy=$this->hlp->kursy();
		$kursList=$this->hlp->createSelectList("kurs",$kursy,"",$criteria["kurs"]);
		$form->addElement($kursList);

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
	private function createForm_elementGroupsTreeList($criteria,$gosparams)
	{
		$kursSelected=$criteria["kurs"];
		$groupSelected=$criteria["group"];
		$osnSelected=$criteria["osnov"];
		$studyYearStart=$criteria["studyYearStart"];

		// комбинированный список групп и специальностей
		$groups=array();
		// переберем специальности
		foreach ($gosparams as $_gos=>$_params)
		{
			// узнаем группы на данной спец./курсе
			$groups_current=
			$this->data->getGroupsOnSpecKursOsnYearStart
			($_gos,$kursSelected,$osnSelected,$studyYearStart);
			// если есть такие
			if (! is_null($groups_current))
			{
				// то построим массив для списка с <OPTGROUP>
				foreach ($groups_current as $groupInfo)
				{
					$groups[$_params["gosTitle"]][$groupInfo["id"]]=$groupInfo["groupTitle"];
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

		//		$criteria['semestr']=( !isset($in['semestr']) || $in['semestr'] <= 0)
		//		?	1
		//		:	$in['semestr'];

		$criteria['group']=( !isset($in['group']) || $in['group'] < 0)
		?	0
		:	$in['group'];

		//		$criteria['kurs']=$in['kurs']<1?1:$in['kurs'];

		$osnDef=$this->hlp->getFirstElem($this->osnovs);
		$criteria['osnov']=(!isset($in['osnov']) || $in['osnov'] <= 0 )
		?	$osnDef["key"]
		:	$in['osnov'];

		return $criteria;
	}

}