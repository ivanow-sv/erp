<?php
Zend_Loader::loadClass('My_Spravtypic');

class Sprav_BellsController extends My_Spravtypic
{
	private	$table='bells';
	private	$title='Справочник: Расписание звонков';
	private	$studyYear_now;
	private $session;

	public function init()
	{
		parent::setTable($this->table);
		parent::setTitle($this->title);
		parent::init();
//		$this->setStudyYear_Now();
		$this->studyYear_now=$this->data->getStudyYear_Now();
		
		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/jquery-ui-timepicker-addon.min.js');
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/sprav.js');
	}

	public function indexAction()
	{
		if ($this->_request->isPost())
		{
			// получим данные из запроса
			$params = $this->_request->getParams();
			// обновим сессию
			$this->sessionUpdate($params);
		}

		// форма фильтра учебных годов
		$this->view->formFilter=$this->createForm_filter();
		$studyYearStart=isset($this->session->studyYearStart)?(int)$this->session->studyYearStart:$this->studyYear_now["start"];
		$this->view->studyYearStart=$studyYearStart;
		$this->view->studyYearEnd=(int)($studyYearStart + 1);

		//  список звонков
		$list=$this->data->bellsList($studyYearStart);
		$this->view->list=$list;
		// сгенерить форму добавления
		$this->view->addForm=$this->createForm_add();
		// сгенерить форму удаления
		$this->view->delForm=$this->createForm_delete();
		// сгенерить форму редактирования
		$this->view->editForm=$this->createForm_edit();

	}

	public function formchangedAction()
	{
		// очистим вывод
		$this->view->clearVars();
		// вернем переменные
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->curact = $this->_request->action;
		$this->view->curcont = $this->_request->controller;
		$this->view->selfLink= $this->_request->getBaseUrl()."/".$this->redirectLink;
		$this->view->icoPath=$this->_request->getBaseUrl()."/public/images/";

		// узнаем что к нам пришло
		$formData = $this->_request->getPost('formData');
		//		$oldData=$this->session->getIterator();
		// обновим сессию
		$this->sessionUpdate($formData );

		// сгенерим список звонков
		$list=$this->data->bellsList((int)$this->session->studyYearStart);
		$this->view->list=$list;
		$out["list"]=$this->view->render($this->_request->getControllerName().'/_list.phtml');
		$this->view->out=$out;

	}


	public function addAction()
	{
		if ($this->_request->isPost())
		{
			$filter = new Zend_Filter_StripTags();
			$starts=$this->_request->getPost("starts");
			$starts=$filter->filter($starts);
			$ends=$this->_request->getPost("ends");
			$ends=$filter->filter($ends);

			// @TODO проверка корректности данных
			$data=array("starts"=>$starts,"ends"=>$ends,"yearStart"=>(int)$this->session->studyYearStart);
			//			$this->data->bellsAdd($data);
			$this->data->otherAdd($data);

		}
		$this->_redirect($this->redirectLink);
	}

	public function editAction()
	{
		if ($this->_request->isPost())
		{
			$filter = new Zend_Filter_StripTags();
			$id=(int)$this->_request->getPost("id");
			$starts=$this->_request->getPost("starts");
			$starts=$filter->filter($starts);
			$ends=$this->_request->getPost("ends");
			$ends=$filter->filter($ends);

			// @TODO проверка корректности данных
			$data=array("starts"=>$starts,"ends"=>$ends,"yearStart"=>(int)$this->session->studyYearStart);
			//			$this->data->bellsAdd($data);
			if ($id>0)
			{
				$this->data->otherChange($id,$data);
			}

		}
		$this->_redirect($this->redirectLink);
	}

	public function delAction()
	{
		parent::delAction();
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
		$result	->setOptions(array("multiple"=>""));
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
		// обновим сессию
		foreach ($params as $param=>$value)
		{
			$this->session->$param=$value;
		}
	}

	private function createForm_filter()
	{
		// форма фильтра
		$form=new Formochki();
		$textOptions=array('class'=>'inputSmall');

		$form->setAttrib('name','filterForm');
		$form->setAttrib('id','filterForm');
		$form->setMethod('POST');
		$form->setAction($this->_request->getBaseUrl()."/".$this->redirectLink);

		$yearList=$this->_buildYearList();
		$studyYearSelected=isset($this->session->studyYearStart)?(int)$this->session->studyYearStart:$this->studyYear_now["start"];
		//		$studyYearStart=$this->getStudyYear_Now();
		$yearSelect=$this->createSelectList("studyYearStart",$yearList,"",$studyYearSelected);
		$form->addElement($yearSelect);
		//		$this->view->studyYearStart=$studyYearSelected;
		//		$this->view->studyYearEnd=($studyYearSelected+1);

		return $form;
	}

	private function createForm_add()
	{
		// форма фильтра
		$form=new Formochki();
		$textOptions=array(
		'class'=>'inputSmall2',
		'onmousedown'=>"pickerTime($(this))"
		);

		$form->setAttrib('name','addForm');
		$form->setAttrib('id','addForm');
		$form->setMethod('POST');
		$form->setAction($this->_request->getBaseUrl()."/".$this->redirectLink."/add");

		$studyYearSelected=$this->session->studyYearStart;
		//		$form->addElement("hidden","studyYearStart",array("value"=>$studyYearSelected));
		$form->addElement("text","starts",$textOptions);
		$form->addElement("text","ends",$textOptions);
		$form->addElement("submit","ok",array("class"=>"apply_text"));
		$form->getElement("ok")->setName('Применить');

		return $form;
	}

	private function createForm_edit()
	{
		// форма фильтра
		$form=new Formochki();
		$textOptions=array(
		'class'=>'inputSmall2',
		'onmousedown'=>"pickerTime($(this))"
		);

		$form->setAttrib('name','editForm');
		$form->setAttrib('id','editForm');
		$form->setMethod('POST');
		$form->setAction($this->_request->getBaseUrl()."/".$this->redirectLink."/edit");
		$form->addElement("hidden","id");
		$form->addElement("text","starts",$textOptions);
		$form->addElement("text","ends",$textOptions);
		$form->addElement("submit","ok",array("class"=>"apply_text"));
		$form->getElement("ok")->setName('Применить');

		return $form;
	}

	private function createForm_delete()
	{
		// форма фильтра
		$form=new Formochki();
		$textOptions=array(
		'class'=>'typic_input'
		);

		$form->setAttrib('name','delForm');
		$form->setAttrib('id','delForm');
		$form->setMethod('POST');
		$form->setAction($this->_request->getBaseUrl()."/".$this->redirectLink."/del");
		$form->addElement("hidden","id");
		$form->addElement("text","confirmWord",$textOptions);
		$form->addElement("submit","ok",array("class"=>"apply_text"));
		$form->getElement("ok")->setName('Применить');

		return $form;
	}

	public function _buildYearList()
	{
		$maxKursCount=6;
		$result=array();
		$nowYear=$this->studyYear_now();
		//		echo "<pre>".print_r($nowYear,true)."</pre>";
		for ($i = ($nowYear["start"]-$maxKursCount); $i <= ($nowYear["end"]+$maxKursCount); $i++)
		{
			$result[$i]=$i;
		}
		return $result;
	}

}