<?php
//@FIXME работа после выбора фильтра - неадекватное поведение

class Dekanat_PersonalController extends Zend_Controller_Action
{

	protected $gosParams;
	protected $gosParamsDef;
	
	protected 	$currentFacultId;		// текущий факультет
	protected 	$session;
	protected 	$baseLink;				// ссылка на модуль-контроллер начиная от базы
	protected	$redirectLink; 			// ссылка в этот модуль/контроллер
	protected	$studyYear_now;			// текущий уч. год
	private 	$data;					// модель работы с БД
	private 	$osnovs;				// перечень форм обучения

	private $hlp; // помощник действий Typic

	private $_num_len=3; // кол-во знаков в № зачетки
	private $_list1="|";
	private $_listFil="--";
	private $_author; // пользователь шо щаз залогинен
	//	private $data;

	public function init()
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


		Zend_Loader::loadClass('Dekanat');

		// @TODO из реестра взять с каким факультетом работает данный пользователь
		// @TODO а еси может со всем факультетами работать? админ например

		$groupEnv=Zend_Registry::get("groupEnv");
		$this->currentFacultId=$groupEnv['currentFacult'];
		$this->data=new Dekanat($this->currentFacultId);

		// $this->currentDivision=$this->data->getDivisionId();
// 		$this->SpecsOnFacult=$this->data->getSpecsByFacultForSelectList($this->currentDivision);
		//		print_r($this->SpecsOnFacult);
		$this->gosParams=$this->data->getGosParams();
		$this->gosParamsDef=$this->data->getGosParamsDef();
		
// 		$this->specDefault=$this->getFirstElem($this->SpecsOnFacult);
		$this->studyYear_now=$this->data->getStudyYear_Now();
		$this->osnovs=$this->data->getInfoForSelectList("osnov");
		$this->view->facultInfo=$this->data->getFacultInfo();
		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->view->title=$moduleTitle
		.'. Факультет - '.$this->view->facultInfo['title']
		.". ".$modContrTitle.'. ';
		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');

		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');
		$ajaxContext ->addActionContext('freelist', 'json')->initContext('json');
		//		$ajaxContext ->addActionContext('zachchange', 'json')->initContext('json');
		$ajaxContext ->addActionContext('move', 'json')->initContext('json');
		$ajaxContext ->addActionContext('privateview', 'json')->initContext('json');
		$ajaxContext ->addActionContext('privatesave', 'json')->initContext('json');
		$ajaxContext ->addActionContext('personlog', 'json')->initContext('json');
		$ajaxContext ->addActionContext('attendancelog', 'json')->initContext('json');
		$ajaxContext ->addActionContext('ocontrollog', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/dekanat.js');
		//		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/styles/dekanat.css');
		//		Zend_Controller_Action_HelperBroker::addPrefix('My_Helper');
		$this->_author=Zend_Auth::getInstance()->getIdentity();
	}

	public function indexAction ()
	{
		//		$logger=Zend_Registry::get("logger");

		//		$logger->log($user, Zend_Log::INFO);
		if ($this->_request->isPost())
		{
			// получим данные из запроса
			$params = $this->_request->getParams();
			// обновим сессию
			$this->sessionUpdate($params);
		}
		//		echo $params["spec"]."|";
		//		echo $this->session->spec."|";
		//		echo $this->session->zach;die();

		$criteria=$this->buildCriteria();
		// студни по критериям
// 		echo "<pre>".print_r($this->gosParamsDef,true)."</pre>";
// 		echo "<pre>".print_r($criteria,true)."</pre>";
// 		return;;
		$rows=$this->data->getStudentList($criteria);

		// есть ли "свободные" или специальность выбрана - выдать в ВИД, иначе пусто
		//		$this->view->studsFree=(count($studsFree)>0 || $criteria['spec']>0)?$studsFree:array();

		// форма поиска студня
		$form=$this->createForm_Filter($criteria);
		$this->view->form=$form;

		$this->view->subgroupTo=$criteria["subgroup"];

		if (isset($rows) && $rows!=0 )
		{
			$this->view->list=$rows;
		}

		$this->view->formClipboardImport=$this->createForm_clipboardImport();
		$this->view->formNewStud=$this->createForm_NewStud();

	}

	public function newAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->redirectLink);
		$form=$this->createForm_NewStud();
		//		$form->isValid($_POST);
		$chk=$form->isValid($_POST);
		$this->view->title.="Новый студент";

		if (!$chk) {
			// проверка на корректность не пройдена, выводим форму снова
			//			$this->form = $form;
			$_msg="<p class='error'>Ошибка заполнения!</p>";
			$msg= $form->getMessages();
			foreach ($msg as $var=>$text)
			{
				$_msg.="<p>".$form->getElement($var)->getDescription();
				$_msg.=" : ".implode("; ", array_values($text))."</p>";

			}
			$this->view->ok=false;
			$validValues=$form->getValidValues($_POST);
			$form->setDefaults($validValues);
			$this->view->formNewStud=$form;
		}
		else
		{
			$this->view->ok=true;
			$values=$form->getValues();
			// @TODO приоверки
			// наша ли подгруппа?
			// какая специальность/оотделение у подгруппы
			$subgrInfo=$this->data->getSubGroupInfo($values["subgroup"]);
			//
			//			// нашща подгруппа?
			$_gos=array_keys($this->gosParams);
			//			// если нет - то нефиг делать тут
			if (array_search($subgrInfo["gosparam"], $_gos)===FALSE )  $this->_redirect($this->baseLink);
			//
			/// 1. создать пользователя, внести полученные данные
			$pass=md5($values["zach"]);


			// проверить есть ли такая зачетка?
			$loginInfo=$this->data->getInfoByLogin($values["zach"]);
			if ($loginInfo)
			{
				$this->view->formMsg="Номер зачетки занят" ;
				return;
			}
			$userid=$this->data->createStudentNew($values["zach"], $pass, $values,$this->_author->id);

			// 2. создать и нарисовать форму редактирования личных данных
			$formProfile=$this->createForm_privateInfo();
			$formProfile->setDefaults($values);
			// пропищем в форму USERID
			$formProfile->setDefault("userid", $userid);
			// нарисуем
			$this->view->formPrivateInfo=$formProfile;

		}


		$this->view->formMsg=$_msg;
		//		$this->view->values=$form->getValues();

		;
	}

	public function privatesaveAction()
	{

		// если НЕ AJAX - идет лесом
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
		//		$userid=(int)$this->_request->getParam('userid');
		//		if ($userid<1) $this->_redirect($this->redirectLink);

		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		//		$userInfo=$this->data->getStudInfo_PrivateByUserid($userid);
		$form=$this->createForm_privateInfo();
		$out=array();
		$chk=$form->isValid($_POST["formData"]);
		if (!$chk) {
			// проверка на корректность не пройдена, выводим форму снова
			//			$this->form = $form;
			$_msg="<p class='error'>Ошибка заполнения!</p>";
			$msg= $form->getMessages();
			foreach ($msg as $var=>$text)
			{
				$_msg.="<p>".$form->getElement($var)->getDescription();
				$_msg.=" : ".implode("; ", array_values($text))."</p>";

			}
			$this->view->ok=false;
		}
		else {
			$values = $form->getValues();
			$userid=$values["userid"];

			$oldData=$this->data->getStudInfo_PrivateByUserid($userid);
			// костыль для награды NULL
			if (is_null($oldData["award"])) $oldData["award"]=0;

			$_gos=array_keys($this->gosParams);
			$chk=empty($oldData) || (array_search($oldData["gosparam"] , $_gos)===FALSE );
			if ($chk)
			{
				$_msg="<p class='error'>Ошибка доступа</p>";
				//				$_msg.="<pre>".print_r($oldData["division"],true)."</pre>";
				//				$_msg.="<pre>".print_r($this->currentDivision,true)."</pre>";
				//				$_msg.="<pre>".print_r($oldData["spec"],true)."</pre>";
				//				$_msg.="<pre>".print_r($_specs,true)."</pre>";
				//				$_msg.="<pre>".print_r(array_search($oldData["spec"] , $_specs),true)."</pre>";
				//				$_msg.="<pre>".print_r(array_search($oldData["spec"], $_specs),true)."</pre>";
					
			}
			else
			{
				// выясним что поменялось относительно того шо в БД
				$diff=array_diff_assoc($values,$oldData);
				// если есть разница
				if (!empty($diff))
				{
					// внести новые данные
					// скорректируем даты - в форме они в формате dd-mm-yyyy, а нам надо yyyy-mm-dd
					// дата рождения и дата док. об образовании
					if (isset($diff["birth_date"])) $diff["birth_date"]=$this->hlp->date_DMY2YMD($diff["birth_date"]);
					if (isset($diff["edu_date"])) $diff["edu_date"]=$this->hlp->date_DMY2YMD($diff["edu_date"]);
					$aff=$this->data->personalInfoChange($userid,$diff);
					// внести запись в журнал операция с персоной, код = 23 (изменение личной информации)
					// по каждой позиции
					if ($aff>0)
					{
						foreach ($diff as $varname=>$value)
						{
							$this->data->personProcessAddRecord($userid, 23, 0,'',$varname,$value,$this->_author->id);
						}
						$_msg="Успешно";
						$this->view->ok=TRUE;
					}
					else $_msg="Ошибка при работе с БД";


				}
				else {
					$_msg="Изменений нет";
				}


				//				$oldData["division"]==$this->currentDivision
				//				$_msg.="<pre>".print_r($diff,true)."</pre>";
				//				$_msg.="<pre>".$oldData["iden_live"]."</pre>";
			}
		}
		$this->view->msg=$_msg;
		$out["formMsg"]=$this->view->render($this->_request->getControllerName().'/_formMsgContent.phtml');

		$this->view->out=$out;

	}

	/**
	 * показ персональной информации
	 * генерация формы с данными и отправка JSON
	 */
	public function privateviewAction()
	{
		$userid=(int)$this->_request->getParam('id');
		if ($userid<1) $this->_redirect($this->redirectLink);
		// если НЕ AJAX - идет лесом
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
		//		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();

		// выясним инфо
		$info=$this->data->getStudInfo_PrivateByUserid($userid);
		// костыль для награды в NULL
		if (is_null($info["award"])) $info["award"]=0;
		// наш ли это студент?
// 		$chk=array_search($info["spec"], array_keys($this->SpecsOnFacult));
		$chk=$this->isOurStudent($info);
		if ($chk===FALSE)
		{
			$out["privateDetails"]="Недостаточно прав";
			return;
		}
		// построим форму
		$form=$this->createForm_privateInfo();
		// заполним
		$form->setDefaults($info);

		$this->view->formPrivateInfo=$form;

		// отправим
		//		$out["peopleList"]=$this->view->render($this->_request->getControllerName().'/_studList.phtml');
		$out["privateDetails"]=$this->view->render($this->_request->getControllerName().'/_privateViewForm.phtml');

		$this->view->out=$out;
	}

	public function editAction()
	{
		$userid=(int)$this->_request->getParam('id');

		if ($userid<1) $this->_redirect($this->redirectLink);

		$info=$this->data->getStudInfo_byUserid($userid);
		// проверка на наш ли студень
		if (!$this->isOurStudent($info)) $this->_redirect($this->redirectLink);

		$this->view->info=$info;
		$this->view->title.=" Студент зач. книжка № ".$info["zach"];
		$textOptions=array('class'=>'typic_input');
		$form=new Formochki();
		$form->setAttrib('name','editForm');
		$form->setAttrib('id','editForm');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->_request->getControllerName()."/edit/zach/".$zach);

		$form->addElement('hidden','zach',$textOptions);
		$form->getElement('zach')->setValue($info['zach']);
		$form->addElement('text','family',$textOptions);
		$form->getElement('family')->setValue($info['family']);
		$form->addElement('text','name',$textOptions);
		$form->getElement('name')->setValue($info['name']);
		$form->addElement('text','otch',$textOptions);
		$form->getElement('otch')->setValue($info['otch']);
		$this->view->form=$form;

		// форма смены № зачотки
		$formZach=new Formochki();
		$formZach->setAttrib('name','zachChangeForm');
		$formZach->setAttrib('id','zachChangeForm');
		$formZach->setMethod('POST');
		$formZach->setAction($this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->_request->getControllerName()."/zachchange/id/".$userid);
		$formZach->addElement('hidden','id',array("value"=>$userid));
		$formZach->addElement('text','zach',array("value"=>$info['zach'],"class"=>"typic_input"));
		$formZach->addElement("submit","OK",array("class"=>"apply_text"));
		$formZach->getElement("OK")->setName("Сменить");

		$this->view->formZach=$formZach;



		// движение персоны
		$this->view->personLog=$this->data->getPersonProcessLog($userid);
		//$logger=Zend_Registry::get("logger");
		//$logger->log($this->view->personLog, Zend_Log::INFO);
		// посещаемость - аттестационные листы
		$this->view->attendanceLog=$this->data->student_attendanceLog($userid);
		// успеваемость - документы выходного контроля
		//		$locale = new Zend_Locale('ru_RU');
		$this->view->ocontrolTODO=$this->data->student_ocontrolToDo($userid);
		$ocontrol=$this->data->student_ocontrolLog($userid);
		//		foreach ($ocontrol as $key=>$item)
		//		{
		//
		//			    $date = new Zend_Date($item["docDate"], false, $locale);
		//				$ocontrol[$key]["docDate"]=$date->toString("YYYY, dd MMMM");
		////				$ocontrol[$key]["docDate"]=$locale->$item;
		//		}
		$this->view->ocontrolLog=$ocontrol;
	}

	//	public function personlogAction()
	//	{
	//		// если НЕ AJAX - идет лесом
	//		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
	//		// очистим вывод
	//		$this->view->clearVars();
	//		$this->view->baseLink=$this->baseLink;
	//		$this->view->baseUrl = $this->_request->getBaseUrl();
	//		$userid= (int)$this->_request->getPost('id');
	//		// есть ли ID и можем ли мы его смотреть?
	//
	//		$this->view->personLog=$this->data->getPersonProcessLog($userid);
	//		$this->view->personProcess=$this->view->render($this->_request->getControllerName().'/_personProcess.phtml');
	////		$this->view->out=$out;
	//
	//	}


	public function formchangedAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		// узнаем что к нам пришло
		$formData = $this->_request->getPost('formData');
		// прежние данные
		$oldCriteria=$this->buildCriteria($this->session->getIterator());
		// обновим сессию
		//		$this->view->a1=$this->session->allfacult;
		//		$this->view->a22=isset($formData["allfacult"]);
		//		$this->view->a2=$formData["allfacult"];
		// FIX для чекбокса
		if (!isset($formData["allfacult"])) $formData["allfacult"]=0;
		$this->sessionUpdate($formData );
		//		$this->view->a3=$this->session->allfacult;
		$criteria=$this->buildCriteria();
		//		$this->view->a4=$criteria["allfacult"];
		// разница между тем шо было и тем шо стало
		$diff=array_diff_assoc($criteria,$oldCriteria);

		$this->view->diff=$diff;
		$this->view->aaaaa=$criteria;
		// пустые массивы массивы
		$groups=array();
		$subgroups=array();
		$peopleList=array();
		$out=array();
		// если менялись спец, форма обуч. или курс - перестроить список групп/подгрупп
		// и студней если вообще шото менялось
		if (count($diff)>0)
		{
			// новый список групп
			$groupYear=$this->data->groupYearByKurs($criteria["kurs"]);
			//		echo $where; die();
			$groups=$this->data->getGroupList($criteria,$groupYear);
			// новые группы / подгруппы для формы
			$groupsList=$this->createSelectList("group",$groups,"№",$criteria["group"]);
			// список подгрупп обновится если менялась группа или подгруппа
			if (isset($diff["group"]) || isset($diff["subgroup"]))
			{
				$subgroups=$this->data->getInfoForSelectList("studsubgroups"," groupid=".$criteria["group"]);
			}
			// новые подгруппы для формы
			$subgroupslist=$this->createSelectList('subgroup',$subgroups,"№",$criteria["subgroup"]);


			// если поставлен флажок
			if ($criteria["allfacult"]==999)
			{

				//				$this->view->aaaaa=get_class_methods($subgroups);
				$groupsList->setOptions(array('disabled'=>'disabled'));
				$subgroupslist->setOptions(array('disabled'=>'disabled'));
			}
			//			$form->getElement('allfacult')->setOptions(array('checked'=>'checked'));
			//			//			$form->getElement('spec')->setOptions(array('disabled'=>'disabled'));
			$out["group"]=$groupsList->render();
			$out["subgroup"]=$subgroupslist->render();;



			// список студней новый
			$peopleList=$this->data->getStudentList($criteria);
			$this->view->list=$peopleList;
			$out["peopleList"]=$this->view->render($this->_request->getControllerName().'/_studList.phtml');

			$this->view->out=$out;
		}
	}


	/** отвязка студента от подгруппы
	 *
	 */
	//	public function unassignsubgroupAction()
	//	{
	//		// очистим вывод
	//		$this->view->clearVars();
	//		// ID подгруппы
	//		$selectedSubGroup = (int)$this->_request->getPost('subgroupSelected');
	//		// userid
	//		$ids = $this->_request->getPost('ids');
	//		//		$this->view->studsAssignedList="<pre>".print_r($ids,true)."</pre>";
	//		// получили правдивые данные? :D
	//		if ($selectedSubGroup >0 && count($ids)>0)
	//		{
	//			// назначить каждому студенту подгруппу 0
	//			foreach ($ids as $id)
	//			{
	//				$this->data->unassignsubgroup(intval($id),$selectedSubGroup);
	//			}
	//			// обновим списки свободных и назначенных
	//			// список студней ВСЕЙ подгруппы привязанных
	//			$studsAssigned=$this->data->getStudentsInGroup(0,$selectedSubGroup);
	//			$this->view->studsAssinged=$studsAssigned;
	//			$this->view->studsAssingedList=$this->view->render($this->_request->getControllerName().'\_studentsAssignedList.phtml');
	//			// список студней ВСЕЙ специальности свободных
	//			$studsFree=$this->data->getStudentsFreeOnSpecSameSubGroup($selectedSubGroup);
	//			$this->view->studsFree=$studsFree;
	//			$this->view->studsFreeList=$this->view->render($this->_request->getControllerName().'\_studentsFreeList.phtml');
	//
	//		}
	//	}

	/** построение списка зачисленных абитуров
	 *
	 */
	public function freelistAction()
	{
		// если НЕ AJAX - идет лесом
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
		//		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();

		//		$formData = $this->_request->getPost('formData');
		$subgroup=(int)$this->_request->getPost('subgroup');

		if ($subgroup<0) $this->_redirect($this->baseLink);

		$info=$this->data->getSubGroupInfo($subgroup);

		$out=array();
		$this->view->subgroupInfo=$info;

		$formAbiturs=new Formochki();
		$formAbiturs->setAttrib('name','freeStudsForm');
		$formAbiturs->setAttrib('id','freeStudsForm');
		$formAbiturs->setMethod('POST');
		$formAbiturs->setAction($this->baseLink);
		$zach_year=$this->studyYear_now;
		$zach_year=substr($zach_year["start"],2,2); // последние цифры текущего учебного года
		$formAbiturs->addElement('text','zach_year',array('class'=>'inputSmall','value'=>$zach_year));
		$formAbiturs->addElement('text','zach_num',array('class'=>'inputSmall2'));
		// след. номер зачетки
		$logger=Zend_Registry::get("logger");
		//				$logger->log($this->studyYear_now, Zend_Log::INFO);
		$znum=$this->data->getLastZachNum($this->studyYear_now["start"]);
		$znum=$this->hlp->strIntInc($znum);
		$znum=str_pad($znum,$this->_num_len,"0",STR_PAD_LEFT);
		//		$logger->log($znum, Zend_Log::INFO);
		$formAbiturs->getElement('zach_num')->setValue($znum);
		$formAbiturs->addElement("submit","ok",array("class"=>"apply_text"));
		$formAbiturs->getElement("ok")->setName("Обработать");


		$this->view->formAbiturs=$formAbiturs;

		// найдем абитуров
		//		$studsFree=$this->data->import_getNewbieList($info["spec"],$info["osnov"],$this->studyYear_now["start"],$this->currentDivision);
		$studsFree=$this->data->import_getNewbieList_v2($info["spec"],$info["osnov"],$this->studyYear_now["start"],$info["division"]);
		$this->view->studsFree=$studsFree;
		//		$out["studentsFree"]="sdfsfsd";
		$out["studentsFree"]=$this->view->render($this->_request->getControllerName().'/_studentsFreeList.phtml');
		//		$this->view->studentsFree=$this->view->render($this->_request->getControllerName().'/_studentsFreeList.phtml');
		$this->view->out=$out;
		;
	}

	public function zachchangeAction()
	{
		//		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);

		$this->view->title2="Смена номера зачетки";
		$filter2=new Zend_Filter_Digits();

		$id=(int)$this->_request->getPost('id');
		if ($id<1) $this->_redirect($this->baseLink);
		$info=$this->data->getStudInfo_byUserid($id);
		if (count($info)>0 && $this->isOurStudent($info))
		{
			// узнаем новый номер зачетки
			$zach= $filter2->filter($this->_request->getPost('zach'));
			// узнаем есть ли уже ткакая учетная запись
			$loginInfo=$this->data->getInfoByLogin($zach);
			// узнаем есть ли студент с такой зачеткой
			$studInfo=$this->data->getStudInfo($zach);
			$this->view->oldnum=$info["zach"];
			$this->view->num=$zach;
			// если есть зачотка
			if ($loginInfo || $studInfo)
			{
				// результат - низя
				$this->view->msg="Занято. ";
				$this->view->exists_zach=$studInfo["zach"];
				$this->view->exists_login=$loginInfo["login"];
				$this->view->res=false;

			}
			// еси все нормуль - меняем
			else
			{
				// результат - ок и скока записей изменено
				$res=$this->data->zachAndLoginChange($id, $zach, $this->_author->id);
				//				$aff=$this->data->zachChange($id,$zach,$this->_author->id);
				//				$aff2=$this->data->zachChange_login($id,$zach,$this->_author->id);

				$this->view->msg=$res["status"]===true
				?	"Успешно"
				:	"Ошибка БД. ".$res["errorMsg"];
				$this->view->res=$res["status"];

			}
			// сообщить о результатах и отправить к редактированию
			$this->view->baseLink=$this->view->baseLink."/edit/id/".$id;
		}
		else
		{ // нафиг
			$this->_redirect($this->baseLink);
		}


	}

	/**
	 * @TODO проверить на корректность - добавив в таблицу STUDENTS
	 * импорт свежезачисленных из табулировнного текста скопипастенного из EXCEL
	 * предполагается что личные данные уже в PERSONAL
	 * 1. ищется человек по ФИО, году рождения и району
	 * 		среди зачисленных в начале этого учебного года, подгруппа NULL (т.е. уже прописаны как студни)
	 * 		нужная спец., отделение и форма обучения
	 * 2. назначается № зачетки (журнал)
	 * 3. назначается подгруппа (журнал)
	 * 4. меняется имя учетной записи
	 */
	public function assignbyclipboardAction()
	{
		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);

		// должно распознать ФИО, зачетку, год рождения и населенный пункт
		// по распознанному найти личные дела:
		//		1. сначала ищем по ФИО и году рождения
		//		2. если совпало больше одного, подулючаем регистрацию
		//		3. все неудачи сообщить в виде отчета
		$this->view->title.="Прикрепление студентов к подгруппе";
		$filter=new Zend_Filter_StripTags();
		// ID подгруппы
		$selectedSubGroup = (int)$this->_request->getPost('subgroupTo');
		$subgrInfo=$this->data->getSubGroupInfo($selectedSubGroup);

		// текст для парсинга
		$pastedList=$this->_request->getPost('pastedList');
		// пусто ?
		if (empty($pastedList)) $this->_redirect($this->baseLink);
		// @TODO наша прдгруппа?
		// нашща подгруппа?
		$_goss=array_keys($this->gosParams);
		// если нет - то нефиг делать тут
		if (array_search($subgrInfo["gosparam"], $_goss)===FALSE )  $this->_redirect($this->baseLink);


		// сделаем массиы
		$pastedList=explode("\n",$pastedList,40);

		// лог обработки
		$process=array();

		//		$abitur=array();
		//		$skiped=array();
		foreach ($pastedList as $line)
		{
			if (empty($line)) continue;
			// 0. ФИО
			// 1. форма обучения -  пропуск
			// 2. № зачетки
			// 3. год рождения
			// 4. район
			// 5. № приказ о зачислении
			$data=explode("\t",$line);
			// Уберем "г." и "г. " а также концевые пробелы
			$data[4]=trim(preg_replace("#г\.|г\.\s#ui",'',$data[4]));
			// если чегото нехватает?
			if (empty($data[0]) || empty($data[3]) || empty($data[4]))
			{
				//				$skiped[$data[2]]=$line;
				array_push($process,
				array(
					"line"=>$line,
					"status"=>"warning",
					"reason"=>"не распознаны ФИО, год рождения или район"
					));
					continue;
			}
			// условия для таблицы PERSONAL
			$where=array();
			$where[]="CONCAT_WS(' ',p.family,p.name,p.otch) LIKE '".$data[0]."'";
			$where[]="YEAR(p.birth_date) = ".$data[3];
			$where[]="p.iden_reg LIKE '%".$data[4]."%'";
			// условия для таблицы ABITUR_FILED
			$where[]="af.spec=".$subgrInfo["spec"];
			$where[]="af.division = ".$subgrInfo["division"];
			$where[]="af.osnov =".$subgrInfo["osnov"];
			$_abitur=$this->data->import_getOneNewbieByCriteria($where);
			// не найдено
			if (empty($_abitur))
			{
				array_push($process,
				array(
					"line"=>$line,
					"status"=>"warning",
					"reason"=>"не найден подходящий. Возможно уже числится"
					));
					continue;
			}

			// занято ли?
			$loginInfo=$this->data->getInfoByLogin($data[2]);
			if ($loginInfo)
			{
				array_push($process,
				array(
					"line"=>$line,
					"status"=>"error",
					"reason"=>"номер зачетной книжки занят"
					));
					continue;
			}

			// операции на изменение данных - в транзакцию
			//			$abitur[$data[2]]=$_abitur;
			/*
			2. назначается № зачетки (журнал)
			*/
			//			$afZach=$this->data->zachChange($_abitur["userid"], $data[2], $this->_author->id);
			// 3. назначается подгруппа (журнал)
			//			$afMove=$this->data->move2subgroup($_abitur["userid"], $subgrInfo["id"], $this->_author->id);

			/*
			 4. меняется имя учетной записи
			 */
			//			$_afLogin=$this->data->zachChange_login($_abitur["userid"], $data[2],$this->_author->id);
			$res=$this->data->import_assign2subgroup($_abitur["userid"],$data[2],$subgrInfo["id"],$this->_author->id);
			$afZach=$res["afZach"];
			$afMove=$res["afMove"];
			$_afLogin=$res["afLogin"];
			if ($_afLogin>0) {

			}
			array_push($process,
			array(
					"line"=>$line,
					"status"=>"done",
					"reason"=>"успешно"
					));

		}

		$this->view->process=$process;
		//		$this->view->skipped=$skiped;

	}


	/** привязка студней к подгруппе
	 */
	/*
	 public function assign2subgroup_OldAction()
	 {
		$logger=Zend_Registry::get("logger");
		$this->view->title.="Прикрепление студентов к подгруппе";
		$this->view->title2="Прикрепление студентов к подгруппе";
		$filter=new Zend_Filter_StripTags();
		$filter2=new Zend_Filter_Digits();
		// очистим вывод
		//		$this->view->clearVars();
		// ID подгруппы
		$selectedSubGroup = (int)$this->_request->getPost('subgroupTo');
		// userid = abitur_ID
		$ids = $this->_request->getPost('userid');
		$zach_year = (int)$this->_request->getPost('zach_year');
		$zach_num= $filter2->filter($this->_request->getPost('zach_num'));

		//			$logger->log($zach_num, Zend_Log::INFO);
		//			$logger->log($this->hlp->strIntInc($zach_num), Zend_Log::INFO);

		// получили правдивые данные? :D
		if ($selectedSubGroup >0 && count($ids)>0)
		{
		// переберем наше все
		$idz=array();
		// переведем в INTEGER
		foreach ($ids as $id)
		{
		$idz[]=intval($id);
		}
		// выясним всех из кучи, кого можно прикреплять к данной подгруппе
		// т.е. данная подгруппа и студни относятся к одним и тем же спец + отделение?

		// какая специальность/оотделение у подгруппы
		$subgrInfo=$this->data->getSubGroupInfo($selectedSubGroup);

		// номер зачетки

		//			$idz=$this->data->getStudentsFreeOnSpecDivAndInList($spec_n_div,$idz);
		$affs=0;
		$affp=0;
		$num=$zach_num;
		$existing=array();
		$exists=array();
		$processing=array();
		foreach ($idz as $abitur_id)
		{
		$zach=$zach_year.$num;
		// узнаем инфо об абитуриенте
		$abiturInfo=$this->data->import_getAbiturInfo($abitur_id,$subgrInfo);

		// проверим, есть ли такой логин, если есть - пропускаем
		// @TODO учет уже существующих, чтобы сообщить кто именно
		$loginInfo=$this->data->getInfoByLogin($zach);
		//				$logger->log($loginInfo,Zend_Log::INFO);
		// если есть зачотка
		if ($loginInfo)
		{
		// запишем в лог шо оно занято и пропустим
		$processing[$abitur_id]=
		array(
		"login"=>$zach,
		"fio"=>$abiturInfo["family"]." ".$abiturInfo["name"]." ".$abiturInfo["otch"],
		"vacancy"=>$loginInfo["family"]." ".$loginInfo["name"]." ".$loginInfo["otch"],
		"result"=>false
		);
		$num=$this->hlp->strIntInc($num);

		continue;
		}

		// 	назначим выбранной подгруппе, там же операция с персоной фиксируется
		$c=$this->data->import_assign2subgroup($abitur_id,$abiturInfo,$zach,$subgrInfo,$this->_author->id);
		$processing[$abitur_id]=
		array(
		"login"=>$zach,
		"fio"=>$abiturInfo["family"]." ".$abiturInfo["name"]." ".$abiturInfo["otch"],
		"vacancy"=>"",
		"result"=>true
		);

		$affs=$affs+$c["students"];
		$affp=$affp+$c["personals"];
		//				}
		// инкремент
		$num=$this->hlp->strIntInc($num);
		//				$logger->log($num, Zend_Log::ERROR);
		}

		// статистика обработки
		$this->view->message="Успешно";
		$this->view->studReq=count($ids);
		$this->view->studAff=$affs;
		$this->view->personAff=$affp;
		$this->view->processing=$processing;
		//			$logger->log($processing, Zend_Log::INFO);
		}
		else
		{
		$this->view->message="Не задана подгруппа или не отмечены студенты";
		}

		}
		*/
	/** назначение к подгруппе новичков
	 1. находятся студенты с userid
	 1.2 проверка наши ли
	 2. номер зачетки
	 2.1. занят ли номер?
	 2.2. назначается номер зачетки (журнал)
	 3. переименовывается логин в номер зачетки (журнал)
	 4. перемещение из подгруппы 0 в указанную (журнал)
	 */
	public function assign2subgroupAction()
	{
		$logger=Zend_Registry::get("logger");
		$this->view->title.="Прикрепление студентов к подгруппе";
		$this->view->title2="Прикрепление студентов к подгруппе";
		$logger->log($this->view->title,Zend_Log::INFO);

		$filter=new Zend_Filter_StripTags();
		$filter2=new Zend_Filter_Digits();
		// очистим вывод
		//		$this->view->clearVars();
		// ID подгруппы
		$selectedSubGroup = (int)$this->_request->getPost('subgroupTo');
		// userid = abitur_ID
		$ids = $this->_request->getPost('userid');
		$zach_year = (int)$this->_request->getPost('zach_year');
		$zach_num= $filter2->filter($this->_request->getPost('zach_num'));

		//$logger->log($ids, Zend_Log::INFO);
		//			$logger->log($this->hlp->strIntInc($zach_num), Zend_Log::INFO);

		// получили правдивые данные? :D
		if ($selectedSubGroup >0 && count($ids)>0)
		{
			// какая специальность/оотделение у подгруппы
			$subgrInfo=$this->data->getSubGroupInfo($selectedSubGroup);

			// нашща подгруппа?
			$_specs=array_keys($this->gosParams);
			// если нет - то нефиг делать тут
			if (array_search($subgrInfo["gosparam"], $_specs)===FALSE )  $this->_redirect($this->baseLink);

			// переберем наше все
			unset ($idz);
			// переведем в INTEGER
			foreach ($ids as $id)
			{
				$idz[]=intval($id);
			}

			// номер зачетки
			$affs=0;
			$afLogin=0;
			$afZach=0;
			$afMove=0;
			//$affp=0;
			$num=$zach_num;
			$processing=array();
			//$logger->log("До цикла",Zend_Log::INFO);
			//$logger->log($idz,Zend_Log::INFO);

			//foreach ($idz as $ii=>$userid)
			for ( $ii=0;$ii<count($idz);$ii++)
			{
				$userid=$idz[$ii];
				$zach=$zach_year.$num;
				// узнаем инфо о студенте-новичке
				$abiturInfo=$this->data->import_getStudentNewbieInfo ($userid);
				//$logger->log($userid,Zend_Log::ALERT);
				//$logger->log($abiturInfo,Zend_Log::INFO);
				//$logger->log($subgrInfo,Zend_Log::DEBUG);
				//$logger->log($ii,Zend_Log::EMERG);

				// есть такой? совпадает ли с возможностями пдогруппы?
				// выясним  можно прикреплять к данной подгруппе?
				// т.е. данная подгруппа и студни относятся к одним и тем же спец + отделение?
				$chk=($userid!==FALSE
				&& (is_array($abiturInfo) && count ($abiturInfo)>0)
				&& $abiturInfo["spec"]==$subgrInfo["spec"]
				&& $abiturInfo["division"]==$subgrInfo["division"]
				&& $abiturInfo["osnov"]==$subgrInfo["osnov"]  );
				if ( ! $chk )
				// пропустить и выдать в лог
				{
					$processing[$userid]=array(
						"login"=>$zach,
						"fio"=>$abiturInfo["family"]." ".$abiturInfo["name"]." ".$abiturInfo["otch"],
						"vacancy"=>"Не подходит к подгруппе",
						"result"=>false
					);
					continue ;
				}
				//$logger->log($chk,Zend_Log::EMERG);
				// проверим, есть ли такой логин, если есть - пропускаем
				$loginInfo=$this->data->getInfoByLogin($zach);
				//			$logger->log($loginInfo,Zend_Log::WARN);
				// если есть зачотка
				// 	 2.1. занят ли номер?
				if ($loginInfo)
				{
					// запишем в лог шо оно занято и пропустим
					$processing[$userid]=
					array(
						"login"=>$zach,
						"fio"=>$abiturInfo["family"]." ".$abiturInfo["name"]." ".$abiturInfo["otch"],
						"vacancy"=>$loginInfo["family"]." ".$loginInfo["name"]." ".$loginInfo["otch"],
						"result"=>false
					);
					$num=$this->hlp->strIntInc($num);
					continue;
				}

				$res=$this->data->import_assign2subgroup
				($userid, $zach, $subgrInfo["id"], $this->_author->id);
				if ($res["status"]===true)
				{
					/*
					 3. переименовывается логин в номер зачетки
					 */
					//				$_afLogin=$this->data->zachChange_login($userid, $zach,$this->_author->id);
					$_afLogin=$res["afLogin"];
					if ($_afLogin>0)
					{
						$afLogin++;
					}
					/*
					 2.2. назначается номер зачетки (журнал)
					 */
					//				$_afZach=$this->data->zachChange($userid, $zach, $this->_author->id);
					$_afZach=$res["afZach"];
					if ($_afZach>0)
					{
						$afZach++;
					}
					/*
					 4. перемещение из подгруппы NULL в указанную (журнал)
					 */
					//				$_afMove=$this->data->move2subgroup($userid, $subgrInfo["id"], $this->_author->id);
					$_afMove=$res["afMoved"];
					if ($_afMove>0)
					{
						$afMove++;
					}

					$processing[$userid]=
					array(
					"login"=>$zach,
					"fio"=>$abiturInfo["family"]." ".$abiturInfo["name"]." ".$abiturInfo["otch"],
					"vacancy"=>"",
					"result"=>true
					);
				}
				else
				{
					$processing[$userid]=
					array(
					"login"=>$zach,
					"fio"=>$abiturInfo["family"]." ".$abiturInfo["name"]." ".$abiturInfo["otch"],
					"vacancy"=>"Ошибка БД. ".$res["errorMsg"],
					"result"=>false
					);

				}
				//$affs=$affs+$c["students"];
				//$affp=$affp+$c["personals"];
				//				}
				// инкремент
				$num=$this->hlp->strIntInc($num);
				//				$logger->log($num, Zend_Log::ERROR);
			}
			//$logger->log($processing,Zend_Log::EMERG);

			// статистика обработки
			$this->view->message="Успешно";
			$this->view->studReq=count($ids);
			$this->view->afLogin=$afLogin;
			$this->view->afZach=$afZach;
			$this->view->afMove=$afMove;

			$this->view->processing=$processing;
			//			$logger->log($processing, Zend_Log::INFO);
		}
		else
		{
			$this->view->message="Не задана подгруппа или не отмечены студенты";
		}
		return;

	}

	public function moveAction()
	{

		if (!$this->_request->isPost()) $this->_redirect($this->baseLink);

		$osnov= $this->_request->getPost('osnov',0);
		$kurs= $this->_request->getPost('kurs',0);
		$ids = $this->_request->getPost('ids');
		$gosparam= $this->_request->getPost('gosparam',0);

		$subgroupTo= $this->_request->getPost('subgroupTo',0);
		//					echo $subgroupTo;
		//					echo "<br>".count($ids);
		//			die();

		$allfacult= $this->_request->getPost('allfacult',0);
		if ($allfacult>0 || count($ids)<1) $this->_redirect($this->baseLink);
		// если была выбрана подгруппа, значит надо назначить
		if ($subgroupTo>0)
		{
			$this->view->reqCount=count($ids);
			$ignored=0;
			$affected=0;

			// нифо о подгруппе
			$info=$this->data->getSubGroupInfo($subgroupTo);
			
// 			$_goss=array_keys($this->gosParams);
// 			echo "<pre>".print_r($info,true)."</pre>";
// 			echo "<pre>".print_r($info["gosparam"],true)."</pre>";
// 			echo "<pre>".print_r(array_keys($this->gosParams),true)."</pre>";
// 			echo "<pre>".print_r(array_key_exists($info["gosparam"],$_goss),true)."</pre>";
			// проверим принадлежность подгруппы к этому факультету
			// специальность подгруппы есть в специальностях факультета и совпадает ли отделение?
			$check=(
			array_key_exists($info["gosparam"],$this->gosParams)
			);
			if (!$check)
			{
				$this->view->msg="Недопустимая целевая подгруппа";
				return;
				//				$this->_redirect($this->baseLink);
			}

			// иначе переберем студней
			foreach ($ids as $key=> $id)
			{
				$_id=(int)$id;
				// есть ли такие студни?
				$studInfo=$this->data->getStudSpecDivOsnov($id);
				// если нету - следующий
				if (!$studInfo)
				{
					$ignored++;
					continue;
				}
				// принадлежат ли они к спец. и отлелению факультета? т.е. "нащи" студни?

				$check=(
				array_key_exists($studInfo["gosparam"],$this->gosParams)
				);
				// если нет - следующий
				if (!$check)
				{
					$ignored++;
					continue;
				}

				// если да - "прооперировать" его
				$_res=$this->data->move2subgroup($id,$subgroupTo,$this->_author->id);
				if ($_res["status"]===true) $affected+=$_res["affected"];
				;
			}
			$this->view->ignCount=$ignored;
			$this->view->affCount=$affected;
			$this->view->msg="Завершено";
			return;

			//			$facult=$this->data->getFacultInfo();


			// исполним

		}
		// иначе рисуем форму выбора куда назначить
		else
		{
			$logger=Zend_Registry::get("logger");
			// очистим вывод
			$this->view->clearVars();
			$this->view->baseLink=$this->baseLink;
			$this->view->baseUrl = $this->_request->getBaseUrl();

			$this->view->idCount=count($ids);
			$form=$this->createForm_Move($ids);

			// найдем группы/подгруппы на специальностях
			// специальность => подгруппы
			$tree=array();
			foreach ($this->gosParams as $gosparam => $inf)
			{
				$tree[$inf["gosTitle"]]=$this->data->getGroupsSubgroups($gosparam,$kurs,$osnov);
			}
			// переделаем массив в СПЕЦИАЛЬНОСТЬ => ГРУППА-ПОДГРУППА
			$tree4list=array();
			$disabled=array();
			foreach ($tree as $specTitle=> $subgroups)
			{
				if (!is_array($subgroups) || count($subgroups)<1 )
				{
					$tree4list[$specTitle][]="";
					continue;
				}
				$gr=0; // текущая группа для проверок
				foreach ($subgroups as $subgr)
				{
					if ($subgr["id"] !=$gr)
					{
						$tree4list[$specTitle]["_".$subgr["id"]."-".$subgr["subgroupid"]]=$this->_list1
						.$this->_listFil
						." Группа ".$subgr["groupTitle"];
						$disabled[]="_".$subgr["id"]."-".$subgr["subgroupid"];
						$gr=$subgr["id"];
					}
					$tree4list[$specTitle][$subgr["subgroupid"]]=$this->_list1
					.$this->_listFil.$this->_listFil
					." ".$subgr["subgroupTitle"]
					."  ( ".$subgr["numz"]." )";

				}
			}
			$logger->log($tree, Zend_Log::INFO);
			$logger->log($tree4list, Zend_Log::INFO);
			$logger->log($disabled, Zend_Log::INFO);


			//			$tree=$this->data->getGroupsSubgroupsTree($this->SpecsOnFacult,$this->currentDivision,$osnov,$kurs);
			//			if (count($tree)>0)
			//			{
			//				$treeGr=array();
			//				$this->hlp->array2tree_for_selectList($tree,$treeGr);
			//			}
			$treeGroupList=$this->hlp->createSelectList("subgroupTo",$tree4list);
			$treeGroupList->setAttrib("disable",$disabled);
			//			$treeGroupList->setAttrib(array("class"=>"ss"),$disabled);
			$form->addElement($treeGroupList);
			//
			//			$logger->log($tree, Zend_Log::INFO);
			////			$logger->log($out, Zend_Log::INFO);
			//
			//			$this->view->tree=$tree;
			//
			$this->view->treeGroups=$treeGroupList;
			//
			$this->view->formMove=$form;
			$out["formMoveWarp"]=$this->view->render($this->_request->getControllerName().'/_moveForm.phtml');
			$this->view->out=$out;
		}



	}

	private function kursy()
	{
		// список курсов
		$kursy=array();
		for ($i = 1; $i <= 6; $i++)
		{
			$kursy[$i]=$i;
		}
		return $kursy;
	}
	/**
	 * построить список выбора
	 * @param string $elemName
	 * @param array $src data ID=>value
	 * @param integer $defaultValue selected option
	 * @return Zend_Form_Element_Select
	 */
	private function createSelectList($elemName,$src,$nullTitle="",$selected=false)
	{
		Zend_Loader::loadClass('Zend_Form_Element_Select');
		// ОБЛОМ! зенд тянект тока двухуровневое дерево :(

		$result = new Zend_Form_Element_Select($elemName);
		if ($nullTitle!=='') $result->addMultiOption(0,$nullTitle);

		//		$result->addMultiOptions($src);
		foreach ($src as $key=>$value)
		{
			$result ->addMultiOption($key,$value);
		}
		// выбранное значение SELECTED
		if ($selected !==false) $result  ->setValue($selected);
		$result  ->removeDecorator('Label');
		$result  ->removeDecorator('HtmlTag');
		return $result;
	}

	private function createRadioList($elemName,$src,$nullTitle="",$selected=false)
	{
		Zend_Loader::loadClass('Zend_Form_Element_Radio');
		// ОБЛОМ! OPTGROUP тянект тока двухуровневое дерево :(

		$result = new Zend_Form_Element_Radio($elemName);
		if ($nullTitle!=='') $result->addMultiOption(0,$nullTitle);

		$result->addMultiOptions($src);
		// выбранное значение SELECTED
		if ($selected !==false) $result  ->setValue($selected);
		$result  ->removeDecorator('Label');
		$result  ->removeDecorator('HtmlTag');
		return $result;
	}

	private function sessionUpdate($params)
	{
		$filter = new Zend_Filter_StripTags();
		// обновим сессию
		foreach ($params as $param=>$value)
		{
			// отфильтруем
			switch ($param)
			{
				case "zach":
				case "family":
				case "name":
				case "otch":
					$_value=$filter->filter($value);
					break;

				case "group":
				case "subgroup":
				case "allfacult":
				case "gosparam":
				case "kurs":
				case "osnov":
					$_value=$value;
					if (is_null($_value)) $_value=0;
					$_value=intval($_value);
					break;

				default:
					$_value=$value;
					break;
			}
			$this->session->$param=$_value;
		}
	}

	/** строит критериый поиска из массива или из данных сессии
	 * @return array
	 */
	private function buildCriteria($in=null)
	{
		if (is_null($in)) $in=$this->session->getIterator();
		$criteria['zach']=$in['zach'];
		$criteria['family']=$in['family'];
		$criteria['name']=$in['name'];
		$criteria['otch']=$in['otch'];


		$criteria['allfacult']=(!isset($in['allfacult']) || $in['allfacult']!=999)
		?	0
		:	999;

		$criteria['group']=(!isset($in['group']) || $in['group']<0)
		?	0
		:	$in['group']
		;

		$criteria['subgroup']=(!isset($in['subgroup']) || $in['subgroup']<0)
		?	0
		:	$in['subgroup']
		;

		$criteria['gosparam']=( !isset($in['gosparam']) || $in['gosparam'] <= 0)
		?	$this->gosParamsDef["id"]
		:	$in['gosparam'];

		$criteria['kurs']=$in['kurs']<1?1:$in['kurs'];

		$osnDef=$this->getFirstElem($this->osnovs);
		$criteria['osnov']=(!isset($in['osnov']) || $in['osnov'] <= 0 )
		?	$osnDef["key"]
		:	$in['osnov'];

		return $criteria;
	}

	private function createForm_sendToLesson()
	{
		$form=new Formochki();
		$studytypes=$this->data->getInfoForSelectList("studoperations","1 ORDER BY title ASC");
		$form->setAttrib('name','sendToLessonForm');
		$form->setAttrib('id','sendToLessonForm');
		$form->setMethod('POST');
		$form->setAction($this->baseLink);
		$typeList=$this->createSelectList("type",$studytypes,"Выбрать");
		$form->addElement($typeList);
		$disciplines=$this->data->getDiscipliesStudOrderedByKaf();
		$discList=$this->createSelectList("discipline",$disciplines,'');
		$form->addElement($discList);
		$studyYear=$this->studyYear_now;
		$form->addElement("hidden","studyYearStart",array("value"=>$studyYear["start"]));
		$form->addElement("text","untilDate",array(
		"class"=>"typic_input",
		"onMouseDown"=>'picker($(this));'
		));

		// пара
		$paras=$this->data->getParaList($studyYear["start"]);
		$paraList=$this->createSelectList("para",$paras);
		$form->addElement($paraList);

		$form->addElement("button","ok",array("class"=>"apply_text","onClick"=>"sendStudentsToLesson();"));
		$form->getElement("ok")->setName('Применить');
		return $form;
	}


	/** возвращает первый элемент массива
	 * @param array $array
	 * @return array:
	 */
	private function getFirstElem($array)
	{
		$_ar=$array;
		reset($_ar);
		$result=each($_ar);
		return $result;
	}

	private function createForm_clipboardImport()
	{
		$form=new Formochki();
		$form->setAttrib('name','formClipboardImport');
		$form->setAttrib('id','formClipboardImport');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/assignbyclipboard");
		$form->addElement("hidden","subgroupTo");

		$form->addElement("textarea","pastedList",array(
		"class"=>"wideinput"
		)
		);
		$form->addElement("submit","OK",array(
		"class"=>"apply_text"		
		));
		$form->getElement("OK")->setName("Обработать");

		return $form;
	}


	private function createForm_privateInfo()
	{
		$form=new Formochki();
		$form->setAttrib('name','formPrivateInfo');
		$form->setAttrib('id','formPrivateInfo');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/privatesave");
		$form->addElement("hidden","userid");
		$form->getElement("userid")->addValidator("Digits",true)->setRequired(true);
		$form->addElement("text","family",array("class"=>"typic_input"));
		$form->getElement("family")
		->addValidator("Alpha",true,array('allowWhiteSpace' => true))
		->setRequired(true)
		->setDescription("Фамилия")
		;
		$form->addElement("text","name",array("class"=>"typic_input"));
		$form->getElement("name")
		->setRequired(true)
		->addValidator("Alpha",true,array('allowWhiteSpace' => true))
		->setDescription("Имя");
		$form->addElement("text","otch",array("class"=>"typic_input"));
		$form->getElement("otch")
		->setRequired(true)
		->addValidator("Alpha",true,array('allowWhiteSpace' => true))
		->setDescription("Отчество")
		;
		// справочные списки: таблица => пременная
		$_lists=array(
		"gender"	=>	"gender", // *
		"identity"	=>	"identity", // *
		"iden_live"	=>	"iden_live", // *
		"edu_docs"	=>	"edu_doc", //*
		"awards"	=>	"award", // *
		"room"		=>	"room", //*
		"categories"	=>	"category", // *
		"lang"		=>	"lang" //*
		);
		//		$logger=Zend_Registry::get("logger");
		foreach ($_lists as $tablename=>$varname)
		{
			$src=$this->data->getSpravTypic($tablename);
			if ($tablename==="awards") $src[0]="нет";
			$list=$this->createSelectList($varname, $src);
			$form->addElement($list);
			$form->getElement($varname)->addValidator("Digits",true)->setRequired(true);
		}
		$photos=array(
		"0"=>"не предоставлено",
		"1"=>"предоставлено"
		);
		$army=array(
		"0"=>"не служил",
		"1"=>"служил"
		);
		$list=$this->createSelectList("photos", $photos); // *
		$form->addElement($list);
		$form->getElement("photos")->addValidator("Digits",true)->setRequired(true);

		$list=$this->createSelectList("army", $army); // *
		$form->addElement($list);
		$form->getElement("army")->addValidator("Digits",true)->setRequired(true);


		$form->addElement("text","iden_serial",array("class"=>"typic_input"));
		$form->getElement("iden_serial")
		->addValidator("Alnum",true,array('allowWhiteSpace' => true))
		->setRequired(true)
		->setDescription("Серия удостоверения личности")
		;
		$form->addElement("text","iden_num",array("class"=>"typic_input"));
		$form->getElement("iden_num")
		->addValidator("Alnum",true,array('allowWhiteSpace' => true))
		->setRequired(true)
		->setDescription("Номер удостоверения личности")
		;
		;
		$form->addElement("textarea","iden_give",array("class"=>"littleArea"));
		$form->getElement("iden_give")
		//		->addFilter("PregReplace",array('match'=>$form->getRegExpTextarea(),'replace'=>""));
		->addValidator("Regex",true,array($form->getRegExpText4Valid()))
		->setRequired(true)
		->setDescription("Где/кем выдано удостоверения личности")
		;
		//		->addValidator("Alpha",array('allowWhiteSpace' => true));
		$form->addElement("textarea","iden_reg",array("class"=>"littleArea"));
		//		preg_replace($pattern, $replacement, $subject)
		$form->getElement("iden_reg")
		//		->addFilter("PregReplace",array('match'=>$form->getRegExpTextarea(),'replace'=>""));
		->addValidator("Regex",true,array($form->getRegExpText4Valid()))
		->setDescription("Место регистрации")
		->setRequired(true)
		;
		//		->addValidator("Regex",array('patern'=>"/[\s0-9a-zа-я\.,;\-№]/ui"));
		//		->addValidator("Alnum")
		//		->addValidator("Alpha",array('allowWhiteSpace' => true));
		$form->addElement("text","birth_date",array("class"=>"typic_input",
		"onMouseDown"=>'picker($(this));'
		));
		$form->getElement("birth_date")
		->addValidator("Date",true,array('format'=>'dd-MM-yyyy','locale'=>'ru'))
		->setRequired(true)
		->setDescription("Дата рождения")
		;

		$form->addElement("textarea","birth_place",array("class"=>"littleArea"));
		$form->getElement("birth_place")
		->addValidator("Regex",true,array($form->getRegExpText4Valid()))
		->setRequired(true)
		->setDescription("Место рождения")
		;
		/*
		 edu_serial +
		 edu_num +
		 edu_give +
		 edu_info +
		 edu_date +
		 edu_res +
		 */
		$form->addElement("text","edu_date",array("class"=>"typic_input"
		,"onMouseDown"=>'picker($(this));'
		));
		$form->getElement("edu_date")
		->addValidator("Date",true,array('format'=>'dd-MM-yyyy','locale'=>'ru'))
		->setDescription("Дата выдачи док. об образовании")
		->setRequired(true)
		;

		$form->addElement("text","edu_serial",array("class"=>"typic_input"));
		$form->getElement("edu_serial")
		->addValidator("Alnum",true,array('allowWhiteSpace' => true))
		->setDescription("Серия док. об образовании")
		->setRequired(true)
		;
		$form->addElement("text","edu_num",array("class"=>"typic_input"));
		$form->getElement("edu_num")
		->addValidator("Alnum",true,array('allowWhiteSpace' => true))
		->setRequired(true)
		->setDescription("Номер док. об образовании")
		;
		$form->addElement("textarea","edu_give",array("class"=>"littleArea"));
		$form->getElement("edu_give")
		->addValidator("Regex",true,array($form->getRegExpText4Valid()))
		->setRequired(true)
		->setDescription("Организация, выдавшая док. об образовании")

		;
		$form->addElement("textarea","edu_info",array("class"=>"littleArea"));
		$form->getElement("edu_info")
		->addValidator("Regex",true,array($form->getRegExpText4Valid()))
		->setDescription("доп. сведения об образовании")
		;
		$form->addElement("text","edu_res",array("class"=>"typic_input"));
		$form->getElement("edu_res")
		->addValidator("Float",true,array('locale' => 'ru'))
		->setDescription("Средний балл по документу")
		;

		/*
		 category ++
		 category_detail +
		 olympic_detail +
		 */
		$form->addElement("textarea","category_detail",array("class"=>"littleArea"));
		$form->getElement("category_detail")
		->addValidator("Regex",true,array($form->getRegExpText4Valid()))
		->setDescription("Документ подтверждающий льготы")
		;
		$form->addElement("textarea","olympic_detail",array("class"=>"littleArea"));
		$form->getElement("olympic_detail")
		->addValidator("Regex",true,array($form->getRegExpText4Valid()))
		->setDescription("Олимпиады/конкурсы")
		;


		/*
		 misc +
		 phone +
		 createdate

		 work +
		 work_age +
		 */
		$form->addElement("textarea","work",array("class"=>"littleArea"));
		$form->getElement("work")
		->addValidator("Regex",true,array($form->getRegExpText4Valid()))
		->setDescription("Место работы")
		;
		$form->addElement("text","work_age",array("class"=>"typic_input"));
		$form->getElement("work_age")
		->addValidator("Float",true,array('locale' => 'ru'))
		->setDescription("Стаж работы")
		;
		$form->addElement("text","phone",array("class"=>"typic_input"));
		$form->getElement("phone")->addValidator("Digits",true)->setDescription("Номер телефона");
		$form->addElement("textarea","misc",array("class"=>"littleArea"));
		$form->getElement("misc")
		->addValidator("Regex",true,array($form->getRegExpText4Valid()))
		->setDescription("Прочие сведения")
		;		//
		$form->addElement("reset","RES",array(
		"class"=>"apply_text"		
		));
		$form->getElement("RES")->setName("Вернуть");
		$form->addElement("submit","OK",array(
		"class"=>"apply_text"		
		));
		$form->getElement("OK")->setName("Сохранить");

		return $form;
	}

	/** форма перемещения студня, тока оболочка + перечень ID
	 * @param array $ids
	 * @return Formochki
	 */
	private function createForm_Move($ids)
	{
		$form=new Formochki();
		$form->setAttrib('name','formMove');
		$form->setAttrib('id','formMove');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/move");
		$k=0;
		foreach ($ids as $id)
		{
			$form->addElement("hidden","id".$k,array(
			 'isArray' => true,
			'name'=>"ids",
			'value'=>$id
			));
			$k++;
		}
		//		$elem=new Zend_Form_Element_Hidden("id");
		//			$elem->isArray(true);
		//			$elem->setIsArray(true);
		//			$elem->setValue($ids);
		//			$form->addElement($elem);
		$form->addElement("submit","OK",array(
		"class"=>"apply_text"		
		));
		$form->getElement("OK")->setName("Подтвердить");
		return $form;
	}

	private function createForm_NewStud()
	{
		$form=new Formochki();
		$form->setAttrib('name','newStudForm');
		$form->setAttrib('id','newStudForm');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/new");

		$form->addElement("text","family",array("class"=>"typic_input"));
		$form->getElement("family")
		->addValidator("Alpha",true,array('allowWhiteSpace' => true))
		->addValidator("NotEmpty",true)
		->setDescription("Фамилия")
		->setRequired(true)
		;
		$form->addElement("text","name",array("class"=>"typic_input"));
		$form->getElement("name")
		->addValidator("Alpha",true,array('allowWhiteSpace' => true))
		->addValidator("NotEmpty",true)
		->setDescription("Имя")
		->setRequired(true)
		;
		$form->addElement("text","otch",array("class"=>"typic_input"));
		$form->getElement("otch")
		->addValidator("Alpha",true,array('allowWhiteSpace' => true))
		->addValidator("NotEmpty",true)
		->setDescription("Отчество")
		->setRequired(true)
		;
		$form->addElement("text","zach",array("class"=>"typic_input"));
		$form->getElement("zach")
		->addValidator("Digits",true,array('allowWhiteSpace' => false))
		->addValidator("NotEmpty",true)
		->setDescription("Номер зачетной книжки")
		->setRequired(true)
		;
		$_payment=$this->data->getInfoForSelectList("payment");
		$payment=$this->hlp->createSelectList("payment",$_payment);
		$form->addElement($payment);
		$form->getElement("payment")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("digits",true)
		;
		$_operation=$this->data->getInfoForSelectList("personoperations");
		$operation=$this->hlp->createSelectList("operation",$_operation,"Выберите");
		$form->addElement($operation);
		$form->getElement("operation")
		->setRequired(true)
		->addValidator("NotEmpty",true,array("all"))
		->addValidator("digits",true)
		;

		$_isAdmin=$this->_author->role==1?true:false;
		Zend_Loader::loadClass('Orders');
		$orders=new Orders($this->currentFacultId);
		// подбор документов за последние 14 мес
		$criteria=array(
			"titleLetter"=>"с",
			"titleNum"=>0,
			'titleDate1'=>date("Y-m-d",mktime()-(30*14*$this->hlp->getSecondsInDay())),
			'titleDate2'=>date("Y-m-d"),
			'createDate1'=>date("Y-m-d",mktime()-(30*14*$this->hlp->getSecondsInDay())),
			'createDate2'=>date("Y-m-d")
		);
		//		echo"<pre>".print_r($criteria,true)."</pre>";
		$_docs=$orders->getOrdersList4Select($criteria,$this->_author->id,$_isAdmin);
		$docs=$this->hlp->createSelectList("docid",$_docs,"выбрать");
		$form->addElement($docs);
		$form->getElement("docid")
		->addValidator("NotEmpty",true,array("all"))
		->setRequired(true)
		->addValidator("digits",true)
		;

		//		$form->addElement("hidden","docid");
		//		$form->getElement("docid")
		//		->setRequired(true)
		//		->addValidator("NotEmpty",true)
		//		->addValidator("digits",true)
		//		;
		$form->addElement("hidden","group");
		$form->getElement("group")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("digits",true)
		;
		$form->addElement("hidden","subgroup");
		$form->getElement("subgroup")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("digits",true)
		;
		$form->addElement("hidden","kurs");
		$form->getElement("kurs")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("digits",true)
		;
		$form->addElement("hidden","gosparam");
		$form->getElement("gosparam")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("digits",true)
		;
		$form->addElement("hidden","osnov");
		$form->getElement("osnov")
		->setRequired(true)
		->addValidator("NotEmpty",true)
		->addValidator("digits",true)
		;

		$form->addElement("submit","OK",array(
		"class"=>"apply_text"		
		));
		$form->getElement("OK")->setName("Далее");
		return $form;

	}

	private function createForm_Filter($criteria)
	{
		//		echo "preved";die();
		// форма поиска студня
		$form=new Formochki();
		$form->setAttrib('name','filterForm');
		$form->setAttrib('id','filterForm');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->_request->getControllerName());
		$textOptions=array('class'=>'typic_input');

		$form->addElement('text','zach',$textOptions);
		if (isset($criteria["zach"]) || $criteria["zach"]!=='') $form->getElement('zach')->setValue($criteria['zach']);
		$form->addElement('text','family',$textOptions);
		if (isset($criteria["family"]) || $criteria["family"]!=='') $form->getElement('family')->setValue($criteria['family']);
		$form->addElement('text','name',$textOptions);
		if (isset($criteria["name"]) || $criteria["name"]!=='') $form->getElement('name')->setValue($criteria['name']);
		$form->addElement('text','otch',$textOptions);
		if (isset($criteria["otch"]) || $criteria["otch"]!=='') $form->getElement('otch')->setValue($criteria['otch']);

		$kursy=$this->kursy();
		$kursyList=$this->createSelectList("kurs",$kursy,"",$criteria['kurs']);
		//		$kursyList->setAttrib('onChange',$form->getName().".submit()");
		//		$kursyList->removeMultiOption(0);
		$form->addElement($kursyList);

		$goss=$this->createSelectList("gosparam",$this->data->getGosParamsForSelectList(),"",$criteria['gosparam']);
		$form->addElement($goss);

		// формы обучения
		$osnovs=$this->osnovs;
		$osnovList=$this->createSelectList("osnov",$osnovs,"",$criteria["osnov"]);
		$form->addElement($osnovList);

		//		$allf=new Zend_Form_Element_Checkbox("allfacult");
		//		$allf->setCheckedValue("999")
		//		->setUncheckedValue("0");
		////		$allf->clearDecorators();
		//		$form->addElement($allf);
		//	$form->removeElement("allfacult");


		$form->addElement("checkbox","allfacult",
		array('title'=>"По всему факультету",
		'onclick'=>"disableSelect('filterForm',new Array('spec','group','subgroup','osnov'));"
		));
		$form->getElement("allfacult")->setCheckedValue("999")->setUncheckedValue("0");

		// группы
		$groupYear=$this->data->groupYearByKurs($criteria["kurs"]);

		$where="gosparam=".$criteria['gosparam'];
		// если форма "не важно"
		$where.= " AND osnov=".$criteria["osnov"];
		$where.=" AND facult=".$this->currentFacultId;
		$where.=" AND YEAR(createdate)=".$groupYear;
		//		echo $where; die();
		//		$groups=$this->data->getInfoForSelectList("studgroups",$where);

		$groups=$this->data->getGroupList($criteria,$groupYear);
		//		print_r($groups);
		$groupsList=$this->createSelectList("group",$groups,"№",$criteria["group"]);
		$form->addElement($groupsList);

		// подгруппы
		$subgroups=$this->data->getInfoForSelectList("studsubgroups"," groupid=".$criteria["group"]);
		$subgroupsList=$this->createSelectList("subgroup",$subgroups,"№",$criteria["subgroup"]);
		$form->addElement($subgroupsList);

		// красивая кнопка-картинка "ИСКАТЬ"
		//		$form->addElement('submit','s',array('title'=>'ИСКАТЬ','class'=>"search_button_large"));
		//		$form->getElement('s')->setValue('');

		// если поставлен флажок
		if ($criteria['allfacult']==999)
		{
			$form->getElement('allfacult')->setOptions(array('checked'=>'checked'));
			$form->getElement('gosparam')->setOptions(array('disabled'=>'disabled'));
			$form->getElement('group')->setOptions(array('disabled'=>'disabled'));
			$form->getElement('subgroup')->setOptions(array('disabled'=>'disabled'));
			$form->getElement('osnov')->setOptions(array('disabled'=>'disabled'));
		}

		return $form;

	}

	private function isOurStudent($studInfo)
	{
		$check=(
		array_key_exists($studInfo["gosparam"],$this->gosParams)
		);
		return $check;
	}

}