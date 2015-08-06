<?php
Zend_Loader::loadClass('My_Spravtypic');

class Sprav_DisciplinestudController extends My_Spravtypic
{
	private $table='disciplinestud';
	private $title='Справочник: Дисциплины преподаваемые';

	private $session;

	function init()
	{
		parent::setTable($this->table);
		parent::setTitle($this->title);
		parent::init();
		Zend_Loader::loadClass('Zend_Session');
		Zend_Loader::loadClass('Formochki');
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$this->session=new Zend_Session_Namespace('my');
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('formchanged', 'json')->initContext('json');

	}

	function indexAction()
	{
		// это для формы добавления
		$this->view->curact = 'add';
		if ($this->_request->isPost())
		{
			// получим данные из запроса
			$params = $this->_request->getParams();
			// обновим сессию
			$this->sessionUpdate($params);
		}

		// данные для списка
		$kafSelected=intval($this->session->kafedra)<0?0:intval($this->session->kafedra);
		$this->view->filterForm=$this->createForm_filterForm();
		if ($kafSelected==0) $this->view->entries=array();
		else $this->view->entries = $this->data->otherList('',"kafedra=".$kafSelected);

	}

	function addAction()
	{
		$this->view->title = $this->view->title. ' - Добавлена запись';

		if ($this->_request->isPost()) {
			Zend_Loader::loadClass('Zend_Filter_StripTags');
			//			Zend_Loader::loadClass('Zend_Filter_Digits');
			$filter = new Zend_Filter_StripTags();
			$title = $filter->filter($this->_request->getPost('title'));
			$title = trim($title);
			$this->view->fieldTitle=$title;
			//			$filterD=new Zend_Filter_Digits();
			$numeric_title=$filter->filter($this->_request->getPost('numeric_title'));
			$kafedra=(int)$this->_request->getPost('kafedra');

			if ($title != '' && $kafedra>0) {
				$this->data->otherAdd(array("kafedra"=>$kafedra, "title"=>$title,"numeric_title"=>$numeric_title));
				return;
			}

			$this->_redirect($this->redirectLink);

		}
	}

	function addlistAction()
	{
		$this->view->title = $this->view->title. '. ';
		if ($this->_request->isPost()) {
			Zend_Loader::loadClass('Zend_Filter_StripTags');
			//			Zend_Loader::loadClass('Zend_Filter_Digits');
			$filter = new Zend_Filter_StripTags();
			$kafedra=(int)$this->_request->getPost('kafedra');
			$list = $filter->filter($this->_request->getPost('pastedList'));
			$list = trim($list);

			if (empty($list)) $this->_redirect($this->redirectLink);
			$list=explode("\n",$list,100);
			if (count($list)<1 || $kafedra<0) $this->_redirect($this->redirectLink);
			foreach ($list as $title)
			{
				$res=$this->data->otherAdd(array(
				"kafedra"=>$kafedra, 
				"title"=>$title)
				);
			}


			$this->view->records=count($list);
			//			$filterD=new Zend_Filter_Digits();
			//			$numeric_title=$filter->filter($this->_request->getPost('numeric_title'));



			$this->_redirect($this->redirectLink);

		}
	}

	public function formchangedAction()
	{
		// очистим вывод
		$this->view->clearVars();
		$this->view->baseLink=$this->baseLink;
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->icoPath= $this->view->baseUrl."/"."public"."/"."images"."/";
		// узнаем что к нам пришло
		$formData = $this->_request->getPost('formData');
		$oldData=$this->session->getIterator();
		// обновим сессию
		$this->sessionUpdate($formData );
		// данные для списка
		$kafSelected=intval($this->session->kafedra)<0?0:intval($this->session->kafedra);
		if ($kafSelected==0) $this->view->entries=array();
		else $this->view->entries = $this->data->otherList('',"kafedra=".$kafSelected);
		
//		$this->view->entries = $this->data->otherList('',"kafedra=".$kafSelected);
		$out=array();
		//		$out["formNewWarp"]=$this->view->render($this->_request->getControllerName().'/_AddForm.phtml');
		$out["list"]=$this->view->render($this->_request->getControllerName().'/_list.phtml');
		$this->view->out=$out;

	}

	public function editAction()
	{
		$this->view->title = $this->view->title. ' - Изменения записи';
		if ($this->_request->isPost()) {
			Zend_Loader::loadClass('Zend_Filter_StripTags');
			//			Zend_Loader::loadClass('Zend_Filter_Digits');

			$filter = new Zend_Filter_StripTags();
			$id = (int)$this->_request->getPost('id');
			$title = trim($filter->filter($this->_request->getPost('title')));
			$this->view->fieldTitle=$title;

			//			$filterD=new Zend_Filter_Digits();
			$numeric_title=$filter->filter($this->_request->getPost('numeric_title'));

			if ($id !== false) {
				if ($title != '') {
					$this
					->data
					->otherChange($id,array
					(
					"numeric_title"=>$numeric_title,
					"title"=>$title
					));
					return;
				}
			}
		}
		$this->_redirect($this->redirectLink);
	}

	function delAction()
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

	private function createForm_filterForm()
	{
		// форма выбора специальностей
		$filterForm=new Formochki();
		$filterForm->setAttrib('name','filterForm');
		$filterForm->setAttrib('id','filterForm');
		$filterForm->setMethod('POST');
		$filterForm->setAction($this->view->selfLink);
		$kaf=$this->data->getListForSelectList('kafedry');
		$kafSelected=intval($this->session->kafedra)<=0?$kaf[0]:intval($this->session->kafedra);
		$kafList=$this->createSelectList("kafedra",$kaf,"выбрать",$kafSelected);
		$filterForm->addElement($kafList);
		return $filterForm;
	}
}