<?php
class Kafedra_OcontrolController extends Zend_Controller_Action
{
	protected 	$curKafedra;
	protected  	$_model;
	private 	$kafInfo;
	private 	$hlp; // помощник действий Typic
	private 	$studyYear_now;
// 	private 	$currentDivision	=	1;
	private		$session;
	private		$baseLink;
	private		$redirectLink;
	private 	$_author; // пользователь шо щаз залогинен
	private 	$criteria; // критерии фильтра
	private		$filter; // фильтр Zend_Form


	private 	$disciplines; // дисциплины, преподаваемые текущим пользователем

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

		Zend_Loader::loadClass('Kafedra');
		$groupEnv=Zend_Registry::get("groupEnv");
		//		print_r($roleEnv);
		$this->curKafedra=$groupEnv['kafedra'];
		$this->_model=new Kafedra($this->curKafedra);

		$moduleTitle=Zend_Registry::get("ModuleTitle");
		$modContrTitle=Zend_Registry::get("ModuleControllerTitle");
		$this->kafInfo=$this->_model->getInfo();
		$this->view->title=$moduleTitle." "
		.$this->kafInfo['title']
		.". ".$modContrTitle.'. ';

		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('approvechange', 'json')->initContext('json');
		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');

		$this->studyYear_now=$this->_model->getStudyYear_Now();
		$this->_author=Zend_Auth::getInstance()->getIdentity();
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/kafedra.js');
		// путь к помощщнику вида для правильных CHECKBOX и RADIO в формах
		$this->view->addHelperPath('./application/views/helpers/','My_View_Helper');

		$this->disciplines=$this->_model->ocontrol_getDisciplines($this->_author->id);

	}

	public function indexAction ()
	{
		// показать список документов вых. контоля
		// по дисциплинам, преподаваемым залогиненным пользователем

		$dis=array_keys($this->disciplines);

		if (count($dis)<1)
		{
			$this->view->msg="Вы не преподаете ни одну дисциплину.";
		}
		else
		{
			$this->filter=$this->createForm_Filter();
			$formData = $this->_request->getPost('formData');
			if ($this->_request->isPost())
			{
				// обновим сессию
				$this->sessionUpdate($formData );
			}
			$this->buildCriteria();
			//			$this->filter->populate($this->criteria);

			//			echo "<pre>".print_r($this->criteria,true)."</pre>";

			$this->view->formFilter=$this->filter;
			$this->view->list=$this->_model->ocontrol_getLists($this->_author->id,$dis,$this->criteria);
		}

	}

	public function formchangedAction()
	{
		if (!$this->_request->isXmlHttpRequest()) $this->_redirect($this->baseLink);
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$dis=array_keys($this->disciplines);

		$this->filter=$this->createForm_Filter();
		$formData = $this->_request->getPost('formData');
		$this->sessionUpdate($formData );
		$this->buildCriteria();
		$this->view->list=$this->_model->ocontrol_getLists($this->_author->id,$dis,$this->criteria);
		$out["list"]=$this->view->render($this->_request->getControllerName().'/_list.phtml');
		$this->view->out=$out;

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
		$listInfo=$this->_model->ocontrol_getListInfo($id);
		// есть лист и он нужного экзаменатора?
		$chk=empty($listInfo) || $listInfo["teacher"]!==$this->_author->id;
		if ($chk) $this->_redirect($this->baseLink); ;

		//а вдруг уже подписано деканом?
		if ($listInfo["approved"]==1)
		{
			
		}
		else
		{
			$stateNew=$listInfo["state"]==1?0:1;
			$this->_model->ocontrol_listStateChange($id,$stateNew);
		}
		// новая инфа о листе
		$listInfo=$this->_model->ocontrol_getListInfo($id);
		$this->view->listState=$this->getListState($listInfo["state"],$listInfo["id"]);
		$this->view->listState["dekan"]=$listInfo["approved"];
		$this->view->elem=$this->view->render($this->_request->getControllerName()."/_stateList.phtml");
		;
	}



	public function editAction()
	{
		if ($this->_request->isPost())
		{
			$id= (int)$this->_request->getPost('id',0);
			if ($id<1) $this->_redirect($this->redirectLink);

			// есть ли такое?
			$listInfo=$this->_model->ocontrol_getListInfo($id);
			// есть лист и он нужного экзаменатора?
			$chk=empty($listInfo) || $listInfo["teacher"]!==$this->_author->id;
			if ($chk) $this->_redirect($this->redirectLink);

			// @FIXME подписан ли деканом?
			if ($listInfo["approved"]==1)
			{
				$this->_redirect($this->redirectLink."/edit/id/".$id);
			}

			// подписан ли уже лист
			if ($listInfo["state"]==1)
			{
				$this->_redirect($this->redirectLink."/edit/id/".$id);

			}

			// шо еще нам поступило?
			$userids= $this->_request->getPost('userid');
			$userids=$this->hlp->arrayINTEGER($userids);
			$results= $this->_request->getPost('result');
			$results= $this->hlp->arrayINTEGER($results);
			$rating_ballys= $this->_request->getPost('rating_bally');
			$rating_ballys= $this->hlp->arrayINTEGER($rating_ballys);
			$itogs= $this->_request->getPost('itog');
			$itogs= $this->hlp->arrayINTEGER($itogs);
			//			$state=(int)$this->_request->getPost('state');
			//			$state=$state===1?1:0;

			//			$chkParams=(count($userids)==count($results)==count($itogs))
			// сравним между собой количество, должно быть одинаково
			$vars = array(count($userids), count($results), count($itogs),count($rating_ballys));
			// если совпадает
			$chkParams=count(array_unique($vars))==1;

			if ( !$chkParams) $this->_redirect($this->redirectLink);

			// сообразим
			$aff=0;
			foreach ($userids as $key=>$userid)
			{
				$res=$results[$key];
				$rat=$rating_ballys[$key];
				$it=$itogs[$key];
				$this->_model->ocontrol_personChange($id,$userid,$rat,$res,$it);
				$af++;
			}


			// перейдем сюда же шобы показать шо получилось
			$this->_redirect($this->redirectLink."/edit/id/".$id);
			//
			//			$logger=Zend_Registry::get("logger");
			//			$logger->log($userids,Zend_Log::INFO);
			//			$logger->log($results,Zend_Log::INFO);
			//			$logger->log($results,Zend_Log::INFO);
			//			$logger->log($af,Zend_Log::INFO);

		}
		else
		{
			$id= (int)$this->_request->getParam('id',0);

			// @FIXME проверка наш ли лист и верно ли все?
			if ($id<1) $this->_redirect($this->redirectLink);

			// есть ли такое?
			$listInfo=$this->_model->ocontrol_getListInfo($id);
			// есть лист и он нужного экзаменатора?
			$chk=empty($listInfo) || $listInfo["teacher"]!==$this->_author->id;
			if ($chk) $this->_redirect($this->redirectLink);
			$this->view->listState=$this->getListState($listInfo["state"],$listInfo["id"]);
			$this->view->listState["dekan"]=$listInfo["approved"];
			$details=$this->_model->ocontrol_listDetails($id);
			//		echo "<pre>".print_r($details,true)."</pre>";die();
			if (count($details)<1) $this->view->details=array();
			else
			{
				$this->view->listInfo=$listInfo;
				//			$details=$this->detailsPrepare($details);
				//		$disciplines=$this->
				$this->view->details=$details;

				// форма редактирования состояния студент-дисциплина
				$this->view->formEdit=$this->createForm_editDoc($details,$listInfo);
			}
		}
		;
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
		// есть ли такое?
		$listInfo=$this->_model->ocontrol_getListInfo($id);
		// есть лист и он нужного экзаменатора?
		$chk=empty($listInfo) || $listInfo["teacher"]!==$this->_author->id;
		if ($chk) $this->_redirect($this->redirectLink);

		$listInfo["studyYearEnd"]=$listInfo["studyYearStart"]+1;
		$TBS=new Tbs_Tbs;
	
		switch ($listInfo["doctype"]) {
			case 2:
				$data=$this->_model->ocontrol_listDetails($id);
				$template='outControl_examList.odt';
				//				echo "<pre>".print_r($data,true)."</pre>";
				//				echo "<pre>".print_r($listInfo,true)."</pre>";
				//				die();
				break;
					
			case 1:
			default:
				$data=$this->_model->ocontrol_listDetails($id);
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
	
	private function createForm_editDoc($content,$listInfo)
	{
		// форма добавления нового плана, изначально скрыта
		$form=new Formochki();
		$form->setAttrib('name','formEdit');
		$form->setAttrib('id','formEdit');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/edit");
		$form->addElement("hidden","id",array("value"=>$listInfo["id"]));
		$form->addElement("hidden","state",array("value"=>$listInfo["state"]));
		$form->addElement("hidden","approved",array("value"=>$listInfo["approved"]));
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
		$form->addElement("button","OK",array(
		"class"=>"apply_text",
		"onClick"=>"listSave('formEdit')"		
		));
		$form->getElement("OK")->setName("Сохранить");

		// радио-кнопка "закрыть ведомость"
		//		$_states=array("0"=>"Открыт","1"=>"Закрыт");
		//		$state=$this->hlp->createRadioList("state",$_states,"",$listInfo["state"],"");
		//		$form->addElement($state);

		return $form;
	}


	private function createForm_assign()
	{
		$formNew=new Formochki();
		$formNew->setAttrib('name','addForm');
		$formNew->setAttrib('id','addForm');
		//		$formNew->setAttrib('enctype', 'multipart/form-data');
		$formNew->setMethod('POST');
		$formNew->setAction($this->baseLink."/"."add");

		$formNew->addElement("hidden","discipline");
		$formNew->getElement("discipline")
		->setRequired(true)
		->addValidator('NotEmpty');

		$src=$this->_model->getUsersList($this->kafInfo["id"]);
		$teachers=$this->hlp->createSelectList("teacher",$this->teachersListPrepare($src));
		//		echo "<pre>".print_r($src,true)."</pre>";
		$formNew->addElement($teachers);
		$formNew->getElement("teacher")
		->setRequired(true)
		->addValidator('NotEmpty');

		$formNew->addElement("submit","OK",array("class"=>"apply_text"));
		$formNew->getElement("OK")->setName("ПРИМЕНИТЬ");


		return $formNew;
		;
	}

	private function createForm_unassign()
	{
		$formNew=new Formochki();
		$formNew->setAttrib('name','delForm');
		$formNew->setAttrib('id','delForm');
		//		$formNew->setAttrib('enctype', 'multipart/form-data');
		$formNew->setMethod('POST');
		$formNew->setAction($this->baseLink."/"."del");

		$formNew->addElement("hidden","discipline");
		$formNew->getElement("discipline")
		->setRequired(true)
		->addValidator('NotEmpty');

		$formNew->addElement("hidden","teacher");
		$formNew->getElement("teacher")
		->setRequired(true)
		->addValidator('NotEmpty');

		$formNew->addElement("text","confirmWord",array("class"=>"typic_input"));
		$formNew->getElement("confirmWord")
		->setRequired(true)
		->addValidator('NotEmpty')
		->addValidator('Identical',true,array("token"=>$this->confirmWord));


		$formNew->addElement("submit","OK",array("class"=>"apply_text"));
		$formNew->getElement("OK")->setName("ПРИМЕНИТЬ");


		return $formNew;
		;
	}

	/**
	 * по статусу листа генерит текст и иконку
	 * @param integer $state
	 * @return array
	 */
	private function getListState($state,$id)
	{
		if ($state==1)
		{
			$result["state"]=1;
			$result["icoClass"]="lockedIcoSmall";
			$result["msg"]="Подписан/Изменения не принимаются";
			$result["class"]="warning";

		}
		else
		{
			$result["state"]=0;
			$result["icoClass"]="unlockedIcoSmall";
			$result["msg"]="Не подписан/Изменения принимаются";
			$result["class"]="done";
		}
		$result["id"]=$id;
		return $result;
	}

	private function teachersListPrepare($list)
	{
		$result=array();
		foreach ($list as $key => $elem)
		{
			$result[$elem["id"]]=$elem["login"].", ".$elem["fio"];
			;
		}
		return $result;
	}

	private function createForm_Filter()
	{
		// фильтр по курсу, спеец., отделению, форме обучения, дисциплине, группе
		$form=new Formochki();
		$textOptions=array('class'=>'inputSmall');

		$form->setAttrib('name','filterForm');
		$form->setAttrib('id','filterForm');
		$form->setMethod('POST');
		$form->setAction($this->baseLink);

		$divs=$this->_model->getInfoForSelectList("division");
		$divList=$this->hlp->createSelectList("division",$divs);
		$form->addElement($divList);
		$form->getElement("division")
		//		->s
		//		->setDescription("Отделение")
		->addValidator("NotEmpty",true)
		->addValidator("digits",true);

		$specs=$this->_model->getSpecsForSelectList("specs");
		$specsList=$this->hlp->createSelectList("spec",$specs);
		$form->addElement($specsList);
		$form->getElement("spec")
		//		->setDescription("Направление подготовки")
		->addValidator("NotEmpty",true)
		->addValidator("digits",true);

		$osnovs=$this->_model->getInfoForSelectList("osnov","");
		$osnovList=$this->hlp->createSelectList("osnov",$osnovs);
		$form->addElement($osnovList);
		$form->getElement("osnov")
		//		->setDescription("Форма обучения")
		->addValidator("NotEmpty",true)
		->addValidator("digits",true);

		$kurs=$this->kursy();
		$_list=$this->hlp->createSelectList("kurs",$kurs);
		$form->addElement($_list);
		$form->getElement("kurs")
		//		->setDescription("Курс")
		->addValidator("NotEmpty",true)
		->addValidator("digits",true);

		$sem=array(
		"1"=>"1",
		"2"=>"2"
		);
		$_list=$this->hlp->createSelectList("semestr",$sem);
		$form->addElement($_list);
		$form->getElement("semestr")
		//		->setDescription("Семестр")
		->addValidator("NotEmpty",true)
		->addValidator("digits",true);

		$_list=$this->hlp->createSelectList("discipline",$this->disciplines);
		$form->addElement($_list);
		$form->getElement("discipline")
		//		->setDescription("Дисциплина")
		->addValidator("NotEmpty",true)
		->addValidator("digits",true);

		// красивая кнопка-картинка "ИСКАТЬ"
		//		$form->addElement('submit','OK',array('title'=>'ИСКАТЬ','class'=>"search_button_large"));
		//		$form->getElement('OK')->setValue('');


		return $form;

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
	 * Обновляет данные в сессии
	 */
	private function sessionUpdate($params)
	{
		// получим данные из запроса

		foreach ($params as $p=>$value)
		{
			$this->session->$p=$value;
			;
		}
	}

	/**
	 * Строит критерии из данных сессии
	 */
	private function buildCriteria()
	{
		$_params=$this->session->getIterator();
		//		$this->filter->populate($_params);
		//		;
		$validValues=$this->filter->getValidValues($_params);
		$values=$this->filter->getValues();
		// если правильных значений меньше, то заполнить значениями по умолчанию
//		$diff=array_diff_key($values, $validValues);
//		if (count($diff)>0)
//		{
//			foreach ($_params as $key)
			foreach ($values as $key=>$val)
			{
				switch ($key)
				{
					case "kurs":
					case "semestr":
					case "division":
					case "osnov":
						$validValues[$key]=isset($validValues[$key])
						? $validValues[$key]
						: 1 
						;
						break;
							
					case "discipline":
						$_disDef=$this->hlp->getFirstElem($this->disciplines);
//						echo $_disDef;
						$validValues["discipline"]=isset($validValues[$key])
						? $validValues[$key]
						: key($_disDef)
						;
						break;

					case "spec":
						$_spec=$this->filter->getElement("spec")->getMultiOptions();
						
						$_spec=$this->hlp->getFirstElem($_spec);
//						echo "<pre>".print_r ($_spec,true)."</pre>";
//						die();
						// @FIXME - вытащить первое значение
						$validValues["spec"]=isset($validValues[$key])
						? $validValues[$key]
						: key($_spec);
						break;
							
						// в остальном - убрать лишнее
					default:
						unset($validValues[$key]);
						break;
				};
			}
//		}
		$this->criteria=$validValues;
	}

}