<?php
Zend_Loader::loadClass('My_Spravtypic');


class Sprav_SpecsController extends My_Spravtypic
{
	private $table='specs';
	private $title='Справочник: Направления подготовки';

	public function init()
	{
		parent::setTable($this->table);
		parent::setTitle($this->title);
		parent::init();
		$this->view->addHelperPath(APPLICATION_PATH . '/views/helpers', 'My_View_Helper');
		// доп. JS скрипты в шаблоне
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/sprav.js');
		//		$layout->myScripts="";
	}

	public function indexAction()
	{
		// это для формы добавления
		$this->view->curact = 'add';
		// данные для списка
		$this->view->entries = $this->data->otherList();
	}

	public function addAction()
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

			if ($title != '') {
				$this->data->otherAdd(
				array(
				"title"=>$title,
				"numeric_title"=>$numeric_title
				));
				return;
			}

			$this->_redirect($this->redirectLink);

		}
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

	public function delAction()
	{
		parent::delAction();
	}


	public function editadvanceAction()
	{
		//							echo "<pre>".$this->_request->isPost()."</pre>";

		$this->view->title = $this->view->title. ' - Детальная информация о специальности';
		$disciplinesList=$this->data->getDisciplinesList();

		// если форма была отправлена
		if ($this->_request->isPost())
		{
			Zend_Loader::loadClass('Zend_Filter_StripTags');
			$filter = new Zend_Filter_StripTags();
			$id = (int)$this->_request->getPost('id');
			//			$numeric_title= trim($filter->filter($this->_request->getPost('numeric_title')));
			//			$title = trim($filter->filter($this->_request->getPost('title')));
			$letter= trim($filter->filter($this->_request->getPost('letter')));
			$disciplines= $this->_request->getPost('disciplines');
// 			print_r($disciplines);
			$disciplines= implode(',',$disciplines);
			
			$discipline_prof=(int)$this->_request->getPost("disciplineProf");
			//			echo $id;
			// если ID существует (значит мы шота правим
			if ($id >0)
			{

				if ($discipline_prof!=0 && $disciplines!='')
				{

					$data = array(
                    'letter' => $letter,
					//                    'facult' => $facult,
                    'disciplines' => $disciplines,
                    'discipline_prof' => $discipline_prof,
					);

					//					$where = 'id = ' . $id;
					$this->data->otherChange($id,$data);
				}
				// перейдем обратно сюда же
				$this->_redirect($this->redirectLink."/editadvance/id/".$id);
			}
		}
		// мы просто зашли - покажем данные и форму
		else
		{
			// entry id should be $params['id']
			// ID специальности
			$id = (int)$this->_request->getParam('id', 0);

			// получаем данные о записи
			if ($id > 0)
			{
				$info=$this->data->specGetInfo($id);
				$this->view->entry = $info;

				// форма для выбора дисциплин
				Zend_Loader::loadClass('Formochki');
				$form=new Formochki();
				$form->setAttrib('name','editForm');
				$form->setAttrib('id','editForm');
				$form->setMethod('POST');
				$form->setAction($this->view->selfLink."/editadvance/id/".$id);
				$textOptions=array('class'=>'inputSmall2 ');
				$form->addElement("hidden","id",array("value"=>$id));
				$form->addElement("text","letter",$textOptions);
				$form->getElement("letter")->setValue($info["letter"]);
				// профильный экзамен, список выбора
				$discProf=$this->createSelectList("disciplineProf",$disciplinesList,"выбрать",$info["discipline_prof"]);
				$form->addElement($discProf);
				// вступ. испытания
				$selectedDisciplines=explode(',',$info["disciplines"]);
				$discExams=$this->createMultipleCheckbox("disciplines",$disciplinesList,$selectedDisciplines);
				$form->addElement($discExams);
				$this->view->editForm=$form;

				// список специализаций
				$this->view->subspecList=$this->data->specGetSubspecList($id);
				// форма добавления специализации
				$formAdd=new Formochki();
				$formAdd->setAttrib('name','SpravChangeForm');
				$formAdd->setAttrib('id','SpravChangeForm');
				$formAdd->setMethod('POST');
				$formAdd->setAction($this->view->selfLink."/subspecadd");
				$formAdd->addElement("hidden","id",array("value"=>$id));
				$formAdd->addElement("textarea","title");
				$formAdd->addElement("submit","ok",array("class"=>"apply_text"));
				$formAdd->getElement("ok")->setName('Добавить');
				$this->view->formAdd=$formAdd;

				// форма удаления специализации
				$formDel=new Formochki();
				$formDel->setAttrib('name','SpravDeleteForm');
				$formDel->setAttrib('id','SpravDeleteForm');
				$formDel->setMethod('POST');
				$formDel->setAction($this->view->selfLink."/subspecdel");
				$formDel->addElement("hidden","specid",array("value"=>$id));
				$formDel->addElement("hidden","id");
				$formDel->addElement("text","confirm",array("class"=>"typic_input"));
				$formDel->addElement("submit","ok",array("class"=>"apply_text"));
				$formDel->getElement("ok")->setName('Подтвердить');
				$this->view->formDel=$formDel;

				// форма переименования специализации
				$formRen=new Formochki();
				$formRen->setAttrib('name','SpravRenameForm');
				$formRen->setAttrib('id','SpravRenameForm');
				$formRen->setMethod('POST');
				$formRen->setAction($this->view->selfLink."/subspecrename");
				$formRen->addElement("hidden","specid",array("value"=>$id));
				$formRen->addElement("hidden","id");
				$formRen->addElement("textarea","title",array("class"=>"medinput"));
				$formRen->addElement("submit","ok",array("class"=>"apply_text"));
				$formRen->getElement("ok")->setName('Сменить');
				$this->view->formRen=$formRen;

			}
			// еси чота не так - редирект на список специальностей
			else
			$this->_redirect($this->redirectLink);
		}

		//		$this->view->buttonText = 'OK';
	}

	public function subspecaddAction()
	{
		$this->view->title = $this->view->title. ' - Добавлена специализация';
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$filter = new Zend_Filter_StripTags();
		$id = (int)$this->_request->getPost('id');
		$title = trim($filter->filter($this->_request->getPost('title')));
		if ($title!=='' && $id>0)
		{
			$this->data->specAddSubspec($id,$title);
		}
		$link=$id >0
		?	$this->redirectLink."/editadvance/id/".$id
		:	$this->redirectLink;
		$this->_redirect($link);
	}

	public function subspecrenameAction()
	{
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$filter = new Zend_Filter_StripTags();

		$specid = (int)$this->_request->getPost('specid');
		$id = (int)$this->_request->getPost('id');
		$title = trim($filter->filter($this->_request->getPost('title')));
		if ($title!=='' && $id>0)
		{
			$this->data->specRenameSubspec($id,$title);
		}
		$link=$specid >0
		?	$this->redirectLink."/editadvance/id/".$specid
		:	$this->redirectLink;
		$this->_redirect($link);
	}

	public function subspecdelAction()
	{
		Zend_Loader::loadClass('Zend_Filter_StripTags');
		$filter = new Zend_Filter_StripTags();
		$specid = (int)$this->_request->getPost('specid');
		$id = (int)$this->_request->getPost('id');
		$confirm= trim($filter->filter($this->_request->getPost('confirm')));
		if ($confirm===$this->confirmWord && $id>0 && $specid>0)
		{
			$this->data->specDeleteSubspec($id);
		}
		$link=$specid >0
		?	$this->redirectLink."/editadvance/id/".$specid
		:	$this->redirectLink;
		$this->_redirect($link);
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

	private function createMultipleCheckbox($elemName,$src,$selected=array())
	{
		Zend_Loader::loadClass('Zend_Form_Element_MultiCheckbox');
		$result = new Zend_Form_Element_MultiCheckbox($elemName);
		//		$result->he
		//		$result	->setOptions(array("multiple"=>""));
		//		$result->addMultiOption(0,$nullTitle);

		foreach ($src as $key=>$value)
		{
			$result ->addMultiOption($key,$value);
		}
		// выбранное значение SELECTED
		if (!empty($selected )) $result  ->setValue($selected);
		$result->helper="FormMultiCheckboxList";
		$result  ->removeDecorator('Label');
		$result  ->removeDecorator('HtmlTag');

		return $result;
	}

}