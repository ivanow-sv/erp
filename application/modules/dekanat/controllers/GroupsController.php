<?php
class Dekanat_GroupsController extends Zend_Controller_Action
{

	//	protected	$currentSpecId;
	protected	$currentFacultId;
	protected	$session;
	protected	$baseLink;
	protected	$redirectLink; 				// ссылка в этот модуль/контроллер
	private		$confirmDelete="УДАЛИТЬ";	// подтверждение удаления
	private		$data;
	private $hlp;

	public function init()
	{
		$this->hlp=$this->_helper->getHelper('Typic');

		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->currentController = $this->_request->getControllerName();
		$this->baseLink=$this->_request->getBaseUrl()."/".$currentModule."/".$this->_request->getControllerName();
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
		$this->view->baseLink=$this->baseLink;
		//		$this->confirmDelete="УДАЛИТЬ";
		$this->view->confirmDelete=$this->confirmDelete;
		$groupEnv=Zend_Registry::get("groupEnv");
		$this->currentFacultId=$groupEnv['currentFacult'];

		Zend_Loader::loadClass('Dekanat');
		// выяснить по факультету направления подготовки и отделения - параметры ГОСов
		// отделение по умолчанию - первый в списке отделений
		// стараться пользоватья тока GOS_PARAM.ID
		

		$this->data=new Dekanat($this->currentFacultId);

		//        $this->view->specTitles=$this->data->getSpecTitles($this->currentSpecId);
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
		$ajaxContext ->addActionContext('checkboxes', 'json')->initContext('json');
		$ajaxContext ->addActionContext('renamesubgroup', 'json')->initContext('json');
		$ajaxContext ->addActionContext('renamegroup', 'json')->initContext('json');
		//		$ajaxContext ->addActionContext('addgroup', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/dekanat.js');

	}

	public function indexAction ()
	{
		//		echo $this->_request->getBaseUrl();
		//		die();
		if ($this->_request->isPost())
		{
			// получим данные из запроса
			$params = $this->_request->getParams();
			// обновим сессию
			$this->sessionUpdate($params);

		}
		
		$gosParams=$this->data->getGosParams();
		$gosParamDef=$this->data->getGosParamsDef();
		$this->session->gosparam=intval($this->session->gosparam)<=0?$gosParamDef["id"]:intval($this->session->gosparam);
		$this->session->kurs=intval($this->session->kurs)<=0?1:intval($this->session->kurs);

		$filter = new Zend_Filter_StripTags();
		$criteria['gosparam']=$this->session->gosparam;
		$criteria['kurs']=$this->session->kurs;

		// форма поиска студня
		$form=new Formochki();
		$form->setAttrib('name','filterForm');
		$form->setAttrib('id','filterForm');
		$form->setMethod('POST');
		$form->setAction($this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->_request->getControllerName());
		$textOptions=array('class'=>'typic_input');

		$kursy=$this->kursy();
		$kursyList=$this->createSelectList("kurs",$kursy,"№",$criteria['kurs']);
		$form->addElement($kursyList);

		$goss=$this->data->getGosParamsForSelectList();
		$gosList=$this->createSelectList("gosparam",$goss,"Направление подготовки",$criteria['gosparam']);
		$form->addElement($gosList);

		$this->view->form=$form;

		$osnovs=$this->data->getInfoForSelectList("osnov");
		$rows=array();
		//		print_r($criteria);die();
		// имея специальность и курс и форму обучения- вявим дерево групп/подгрупп
		foreach ($osnovs as $osnid=>$osnTitle)
		{
			$r=$this->data->getGroupsSubgroups_v2($criteria['gosparam'],$criteria['kurs'],$osnid);
			if (!is_null($r)) $rows[$osnTitle]=$r;
		}
		$this->view->addForm=$this->createAddForm($osnovs,$criteria['gosparam'],$criteria['kurs']);
		$this->view->deleteFormGroup= $this->createDeleteForm_group();
		$this->view->renameFormGroup= $this->createRenameForm_group();
		$this->view->deleteFormSubGroup= $this->createDeleteForm_subgroup();
		$this->view->renameFormSubGroup= $this->createRenameForm_subgroup();
		if (isset($rows) && count($rows>0) )
		{
			$this->view->list=$rows;


		}

	}

	/**
	 * переадресация на контингент
	 */
	public function membersAction()
	{
		// словим группу и подгруппу
		$groupid= (int)$this->_request->getParam('groupid',0);
		$subgroupid= (int)$this->_request->getParam('subgroupid',0);
		//		echo $groupid."|".$subgroupid;
		//		echo "<br>".$this->session->spec."|".$this->session->kurs;
		//		die();
		// установим в сессию специальность, курс, группу и подгруппу
		// специальность и курс

		if ($groupid >0 && $subgroupid >=0 )
		{
			// установим группу и подгруппу
			$this->session->group=$groupid;
			$this->session->subgroup=$subgroupid;
			// @TODO узнаем и установим форму обучения
			$info=$this->data->getGroupInfo($groupid);
			$this->session->osnov=$info["osnov"];
			// очистим из сессии № зачетки, ФИО
			$this->session->zach='';
			$this->session->family='';
			$this->session->name='';
			$this->session->otch='';

			$redirectUrl=$this->_request->getModuleName()."/personal";
		}
		else $redirectUrl=$this->_request->getModuleName();
		$this->_redirect($redirectUrl);

	}

	public function addgroupAction()
	{
		if ($this->_request->isPost())
		{
			//			$this->view->clearVars();
			//			$this->view->baseLink=$this->baseLink;

			$params= $this->_request->getPost('osnov');
			//			$this->view->ppp=$params;

			$osnov= (int)$this->_request->getPost('osnov');
			$gosparam= (int)$this->_request->getPost('gosparam');
			$kurs= (int)$this->_request->getPost('kurs');
// 						echo $gosparam;
			//			die();
			//			$kurs= (int)$this->_request->getParam('kurs');
			if ($gosparam>0 && $kurs>0 && $osnov >0)
			{
				$title=$this->data->getGroupTitle($gosparam,$kurs,$osnov);
				$newID=$this->data->createGroup($gosparam,$kurs,$title,$osnov);
			}
		}
		$this->_redirect($this->redirectLink);
	}

	public function addsubgroupAction()
	{
		$groupid= (int)$this->_request->getParam('id');
		if ($groupid>0)
		{
			$newTitle=$this->data->getSubGroupTitle($groupid);
			$title="нов. подгруппа (".$newTitle.")";
			$newID=$this->data->createSubGroup($groupid,$title);
		}
		// редирект на indexAction $this->baseLink
		//				$redirectUrl=$this->_request->getModuleName()."/".$this->_request->getControllerName();
		$this->_redirect($this->redirectLink);

		//		$this->_redirect($this->baseLink);
	}

	/**
	 *
	 */
	public function renamesubgroupAction()
	{
		// если применяли форму
		if ($this->_request->isPost())
		{
			$id = (int)$this->_request->getPost('id');
			$title = $this->_request->getPost('newTitle');
			$filter = new Zend_Filter_StripTags();
			$title =$filter->filter($title);

			$this->data->renameSubgroup($id,$title);

			// редирект на indexAction $this->redirectLink
			$this->_redirect($this->redirectLink);
		}

	}

	public function deletesubgroupAction()
	{
		// если применяли форму
		if ($this->_request->isPost())
		{
			$id = (int)$this->_request->getPost('id');
			$confirm = $this->_request->getPost('confirm');
			$filter = new Zend_Filter_StripTags();
			$confirm =$filter->filter($confirm);

			// узнаем скока студентов в подгруппе
			$num=$this->data->getStudentsCountInSubGroup($id);
			//			echo $id."|".$confirm;
			//			echo "<br>".$num;
			////			exit();
			//			die();
			if ($num <1 && $confirm === $this->confirmDelete)
			{
				$this->data->deleteSubgroup($id);
				$this->_redirect($this->redirectLink);
			}
			// иначе дать отлуп и выдать предупреждение
			else
			{
				$this->view->message="Операция не возможна. За подгруппой закреплены студенты или подтверждающее слово не совпадает.";
			}
		}
	}

	public function deletegroupAction()
	{
		// если применяли форму
		if ($this->_request->isPost())
		{
			$id = (int)$this->_request->getPost('id');
			$confirm = $this->_request->getPost('confirm');
			$filter = new Zend_Filter_StripTags();
			$confirm =$filter->filter($confirm);

			// узнаем скока подгрупп в группе
			$num=$this->data->getSubgroupCountInGroup($id);
			//			echo $id."|".$confirm;
			//			echo "<br>".$num;
			////			exit();
			//			die();
			// если нету подгрупп в составе и введи подтверждение
			if ($num <1 && $confirm === $this->confirmDelete)
			{
				$this->data->deleteGroup($id);
				$this->_redirect($this->redirectLink);
			}
			// иначе дать отлуп и выдать предупреждение
			else
			{
				$this->view->message="Операция не возможна. За группой закреплены подгруппы или подтверждающее слово не совпадает.";
			}
		}
	}

	public function renamegroupAction()
	{
		// если применяли форму
		if ($this->_request->isPost())
		{
			$id = (int)$this->_request->getPost('id');
			$title = $this->_request->getPost('newTitle');
			$filter = new Zend_Filter_StripTags();
			$title =$filter->filter($title);

			$this->data->renameGroup($id,$title);

			// редирект на indexAction $this->baseLink
			$this->_redirect($this->redirectLink);
		}

	}


	public function formchangedAction()
	{
		// очистим вывод
		$this->view->clearVars();
		// восстановим фразу
		$this->view->confirmDelete=$this->confirmDelete;
		// и путь
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();

		// узнаем что к нам пришло
		$formData = $this->_request->getPost('formData');
		$oldData=$this->session->getIterator();
		// обновим сессию
		$this->sessionUpdate($formData );

		// обнуленные массивы
		$kurs=0;
		$gosparam=0;

		if ((int)$this->session->gosparam>0 ) $gosparam=(int)$this->session->gosparam;
		if ((int)$this->session->kurs >0 ) $kurs=(int)$this->session->kurs;

		//		$rows=$this->data->getGroupsSubgroups($spec,$kurs);

		$osnovs=$this->data->getInfoForSelectList("osnov");
		$rows=array();
		// имея специальность и курс и форму обучения- вявим дерево групп/подгрупп
		foreach ($osnovs as $osnid=>$osnTitle)
		{
			$r=$this->data->getGroupsSubgroups($gosparam,$kurs,$osnid);
			if (!is_null($r)) $rows[$osnTitle]=$r;
		}


		if (isset($rows) && count($rows>0) )
		{
			$this->view->list=$rows;
		}
		else
		{
			$this->view->list=array();
		}
		$out["peopleList"]=$this->view->render($this->_request->getControllerName().'/_List.phtml');
		$this->view->out=$out;

	}

	public function checkboxesAction()
	{
		$this->view->clearVars();
		$this->view->baseUrl = $this->_request->getBaseUrl();

		// получили данные
		$checkBoxes=$this->_request->getPost('checkBoxes');
		// переберем - вытащим подгруппы
		$subgorupIDs=array();
		foreach ($checkBoxes as $item)
		{
			$one=each($item);
			if ($one["key"]==="subgroup") $subgorupIDs[]=intval($one["value"]);
		}
		//		$this->view->sss= $subgorupIDs;
		//и построим форму назначения занятий
		$form=new Formochki();

		$form->setAttrib('name','assignLessonForm');
		$form->setAttrib('id','assignLessonForm');
		$form->setMethod('POST');
		$form->setAction($this->baseLink);

		// занятия
		$lessons=$this->data->getInfoForSelectList('disciplinestud',"1 ORDER BY title ASC");
		$lessonsList=$this->createSelectList("discipline",$lessons,"-- выберите дисциплину");
		$form->addElement($lessonsList);
		// аудитория
		$textOptions=array('class'=>'typic_input');
		$form->addElement('text','place',$textOptions);
		// пара №
		$paras=$this->data->getParaList();
		$paraList=$this->createSelectList("para",$paras,"-- № пары");
		$form->addElement($paraList);
		// типа занятия
		$types=$this->data->getInfoForSelectList('studytype',"1 ORDER BY title ASC");
		$typeList=$this->createSelectList("type",$types,"-- Тип");
		$form->addElement($typeList);
		$this->view->form=$form;
		// аудитория
		$textOptions=array('class'=>'typic_input');
		$form->addElement('text','day_when',$textOptions);

		$out=$this->view->render($this->_request->getControllerName().'/_lessonAssignForm.phtml');
		$this->view->out=$out;
		//		_lessonAssignForm.phtml
		;
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
	private function createSelectList($elemName,$src,$nullTitle="Выберите",$selected=false)
	{
		Zend_Loader::loadClass('Zend_Form_Element_Select');
		$result = new Zend_Form_Element_Select($elemName);
		$result->addMultiOption(0,$nullTitle);

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
// 				case "spec":
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

	private function buildCriteria()
	{
		$filter = new Zend_Filter_StripTags();
		$criteria['zach']=$filter->filter($this->session->zach);
		//		$criteria=array('zach'=>$filter->filter($this->session->zach),
		$criteria['family']=$filter->filter($this->session->family);
		$criteria['name']=$filter->filter($this->session->name);
		$criteria['otch']=$filter->filter($this->session->otch);

		$criteria['group']=intval($this->session->group);
		$criteria['subgroup']=intval($this->session->subgroup);
// 		$criteria['spec']=intval($this->session->spec)<0?0:intval($this->session->spec);
		$criteria['gosparam']=intval($this->session->gosparam)<0?0:intval($this->session->gosparam);
		$criteria['kurs']=intval($this->session->kurs)<0?0:intval($this->session->kurs);
		return $criteria;
	}

	/** на базе списка подгрупп делает формы переименования подгрупп
	 * @param array $rows
	 * @return Ambigous <multitype:, Formochki>
	 */
	private function createRenameForms($rows)
	{
		// построим формы для кнопок удаления подгрупп
		$formz=array();
		foreach ($rows AS $k=>$r)
		{
			$form=new Formochki;
			$form->setAttrib('name','renameForm');
			$form->setAttrib('id','renameForm');
			//				$form->setAttrib('style','display:none');
			$form->setMethod('POST');
			$form->setAction($this->baseLink."/"."renamesubgroup"."/"."id"."/".$r["subgroupid"]);
			$textOptions=array('class'=>'typic_input');
			$form->addElement('hidden','id');
			$form->getElement("id")->setValue($r["subgroupid"]);
			$form->addElement('text','newTitle',$textOptions);
			$form->getElement("newTitle")->setValue($r["subgroupTitle"]);
			$form->addElement('submit','ok');
			$formz[$k]=$form;
		}
		return $formz;
	}

	/** создание формы добавления группы
	 * @param array $osnovs ID->value
	 * @param integer $gosparam
	 * @param integer $kurs
	 */
	private function createAddForm($osnovs,$gosparam,$kurs)
	{
		$form=new Formochki;
		$form->setAttrib('name','addForm');
		$form->setAttrib('id','addForm');
		//				$form->setAttrib('style','display:none');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/"."addgroup");
		$textOptions=array('class'=>'typic_input');
		$form->addElement('hidden','kurs',array("value"=>$kurs));
		$form->addElement('hidden','gosparam',array("value"=>$gosparam));
		$osnovSelect=$this->createSelectList("osnov",$osnovs,"Выберите форму обучения");
		$form->addElement($osnovSelect);

		$form->addElement('text','confirm',$textOptions);
		$form->addElement('submit','ok',array("class"=>"apply_text"));
		$form->getElement('ok')->setName("Добавить");

		return $form;
	}

	/** форма удаления группы с пустым ID, оно будет заполняться через JS
	 * @return Formochki
	 */
	private function createDeleteForm_group()
	{
		$form=new Formochki;
		$form->setAttrib('name','deleteFormGroup');
		$form->setAttrib('id','deleteFormGroup');
		//				$form->setAttrib('style','display:none');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/"."deletegroup");
		$textOptions=array('class'=>'typic_input');
		$form->addElement('hidden','id');
		$form->addElement('text','confirm',$textOptions);
		$form->addElement('submit','ok',array("class"=>"apply_text"));
		$form->getElement('ok')->setName("Удалить");

		return $form;
	}

	/** форма переимнования группы с пустым ID, оно будет заполняться через JS
	 * @return Formochki
	 */
	private function createRenameForm_group()
	{
		$form=new Formochki;
		$form->setAttrib('name','renameFormGroup');
		$form->setAttrib('id','renameFormGroup');
		//				$form->setAttrib('style','display:none');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/"."renamegroup");
		$textOptions=array('class'=>'typic_input');
		$form->addElement('hidden','id');
		$form->addElement('text','newTitle',$textOptions);
		$form->addElement('submit','ok',array("class"=>"apply_text"));
		$form->getElement('ok')->setName("Применить");

		return $form;
	}

	/** форма удаления подгруппы с пустым ID, оно будет заполняться через JS
	 * @return Formochki
	 */
	private function createDeleteForm_subgroup()
	{
		$form=new Formochki;
		$form->setAttrib('name','deleteFormSubGroup');
		$form->setAttrib('id','deleteFormSubGroup');
		//				$form->setAttrib('style','display:none');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/"."deletesubgroup");
		$textOptions=array('class'=>'typic_input');
		$form->addElement('hidden','id');
		$form->addElement('text','confirm',$textOptions);
		$form->addElement('submit','ok',array("class"=>"apply_text"));
		$form->getElement('ok')->setName("Удалить");

		return $form;
	}

	/** форма переимнования подгруппы с пустым ID, оно будет заполняться через JS
	 * @return Formochki
	 */
	private function createRenameForm_subgroup()
	{
		$form=new Formochki;
		$form->setAttrib('name','renameFormSubGroup');
		$form->setAttrib('id','renameFormSubGroup');
		//				$form->setAttrib('style','display:none');
		$form->setMethod('POST');
		$form->setAction($this->baseLink."/"."renamesubgroup");
		$textOptions=array('class'=>'typic_input');
		$form->addElement('hidden','id');
		$form->addElement('text','newTitle',$textOptions);
		$form->addElement('submit','ok',array("class"=>"apply_text"));
		$form->getElement('ok')->setName("Применить");

		return $form;
	}


}