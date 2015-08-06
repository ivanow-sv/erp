<?php
/**
 * @author zlydden
 *
 */
class Dostup_MenusController extends Zend_Controller_Action
{

	private $_model;
	private $redirectLink;

	public function init()
	{
		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->currentController=$this->_request->getControllerName();
		$this->view->currentAction=$this->_request->getActionName();
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->title='Управление доступом. Генератор меню. ';
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();		
		
		Zend_Loader::loadClass('Menus');
		$this->_model=new Menus;

        $this->view->addHelperPath('./application/views/helpers/','My_View_Helper');
		
		//активируем хэлпер AjaxContext
		//и передаём туда имя action'а, который необходимо интегрировать с AJAX'ом
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext ->addActionContext('edit', 'json')->initContext('json');
	}

	/**
	 * список меню в виде таблицы
	 * @TODO учесть что меню бывают многоуровневые
	 */
	public function indexAction ()
	{
		// построим таблицу списка меню
			
		// выцепим из базы верхний уровень, которые без родителей
		$items=$this->_model->getItems();
		$this->view->items=$items;
			
	}

	/**
	 * Добавление нового
	 */
	public function addAction()
	{
		// куда по умаолчанию пойдем для продолжения работы
		// переход на управление менющками
		$redirectLink=$this->redirectLink;
		// заголовок страницы
		$this->view->title.="Добавлено новое";
		if ($this->_request->isPost()) {
			// получим и отфильтруем название
			Zend_Loader::loadClass('Zend_Filter_StripTags');
			$filter = new Zend_Filter_StripTags();
			$label = $filter->filter($this->_request->getPost('label'));
			$label = trim($label);
			$this->view->fieldLabel=$label;
			// если оно НЕ пусто
			if ($label != '') {
				// добавим и узнаем ID
				$menuId=$this->_model->addItemByLabel($label);
				// добавлено, направим к редактированию
				$redirectLink=$this->redirectLink.'/edit/id/'.$menuId;
			}


		}
		// продолжим работу
		$this->_redirect($redirectLink);
	}

	/**
	 * Редактирование существующего пункта
	 * @TODO назначение прав
	 */
	public function editAction()
	{
		$this->view->title.="Правка меню";

		// ссылка "сюда же". Нужна после внесения изменений и чтобы перезагрузить форму с данными из БД
		$selfLink=$this->redirectLink.'/'.$this->view->currentAction.'/id/'.(int)$this->_request->getParam('id',0);
//		$selfLink=$this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->view->currentController.'/'.$this->view->currentAction.'/id/'.(int)$this->_request->getParam('id',0);
		$this->view->selfLink=$this->view->baseUrl.'/'.$selfLink;

		// @TODO ссылка на уровень верх
		$parentLink= $this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->view->currentController;
		$this->view->parentLink=$parentLink;

		// если отправлена форма
		// получим параметры и внесем в БД
		if ($this->_request->isPost())
		{
			// получим данные
			$params = $this->_request->getPost();
			Zend_Loader::loadClass('Zend_Filter_StripTags');
			$filter = new Zend_Filter_StripTags();
			foreach ($params as $param)
			{
				$param=$filter->filter($param);
			}
			if ($params["id"]!=0)
			{
				$this->_model->setItemInfo($params);
			}
			// исправили, направим к редактированию этой менюшки
				
			$this->_redirect($selfLink);


		}
		// если просто зашли
		// берем данные и рисуем форму
		else
		{
			$id = (int)$this->_request->getParam('id',0);

			// если не задано - послать обратно
			if ($id==0)
			{
//				$redirectLink=$this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->view->currentController;
				$this->_redirect($this->redirectLink);
			};

			// берем данные из БД
			$itemInfo=$this->_model->getItemInfo($id);

			$this->view->title.=" <".$itemInfo["label"].">";
			//	сообразим форму
			Zend_Loader::loadClass('Formochki');
			$form=new Formochki;
			$form->setAttrib('name','menuEditForm');
			$form->setAttrib('id','menuEditForm');
			$form->setMethod('post');
			$form->setAction($this->view->baseUrl.'/'.$this->redirectLink.'/'.$this->view->currentAction);

			$form->addElement('hidden','id',array('value'=>$id));
			$form->addElement('text','label',array('class'=>'medinput', 'value'=>$itemInfo["label"]));
			$form->addElement('text','title',array('class'=>'medinput', 'value'=>$itemInfo["title"]));
			$form->addElement('text','class',array('class'=>'medinput', 'value'=>$itemInfo["class"]));
			$form->addElement('text','target',array('class'=>'medinput', 'value'=>$itemInfo["target"]));
			$form->addElement('text','order',array('class'=>'medinput', 'value'=>$itemInfo["order"]));
			$form->addElement('text','resource',array('class'=>'medinput', 'value'=>$itemInfo["resource"]));
			$form->addElement('text','privilege',array('class'=>'medinput', 'value'=>$itemInfo["privilege"]));

			// выбор групп (ролей) кому разрешено checkbox'ы
			$roles=$this->_model->getRolesList();
            Zend_Loader::loadClass('Zend_Form_Element_MultiCheckbox');
            $rolesList= new Zend_Form_Element_MultiCheckbox('rolesList');

            $rolesList->helper='FormMultiCheckboxList';
            $rolesList->addMultiOptions($roles);
            $rolesList->removeDecorator('Label');
            $rolesList->removeDecorator('HtmlTag');
            $rolesList->removeDecorator('DtDdWrapper');
            $rolesList->removeDecorator('Tooltip');
            $form->addElement($rolesList);
			
			
			
			$form->addElement('text','parent',array('class'=>'medinput', 'value'=>$itemInfo["parent"]));

			// тута выпадающите списки
			// список модулей. array [_MODULE_DIR_] -> _PATH_TO_CONTROLLERS_OF_THIS_MODULE_
			$modules=$this->getFrontController()->getControllerDirectory();
			Zend_Loader::loadClass('Zend_Form_Element_Select');
			$moduleSelect = new Zend_Form_Element_Select('module');
			foreach ($modules as $k=>$m)
			{
				$moduleSelect ->addMultiOption($k,$k);;
			}

			// добавим "выберите"
			//			$moduleSelect ->addMultiOption(0,'Выберите');


			// выбранное значение SELECTED
			$moduleSelect ->setValue($itemInfo["module"]);
			$moduleSelect ->removeDecorator('Label');
			$moduleSelect ->removeDecorator('HtmlTag');
			$form->addElement($moduleSelect);
			$form->getElement('module')->setAttrib('onChange','formEditMenuModuleChanged()');

			// выбор контроллера зависит от модуля
			// если в форме выбирали модуль через AJAX
			// наши параметры из AJAX (через JSON)
			$this->view->selectedModule=$this->_request->getParam('selectedModule',false);
			$this->view->selectedContr=$this->_request->getParam('selectedContr',false);

			if ($this->view->selectedModule!==false)
			{
				// построим список контроллеров согласно выбранноу модулю в AJAX
				$controllersList =$this->extractControllersList($this->view->selectedModule);
				$_selectedCont='';
				$moduleOp=$this->view->selectedModule;
			}
			// если модуль уже задан из БД
			else
			{
				// построим список контроллеров согласно выбранному в БД модулю
				$controllersList =$this->extractControllersList($itemInfo["module"]);
				$_selectedCont=$itemInfo["controller"];
				$moduleOp=$itemInfo["module"];
			}
			// построим список контроллеров
			$Select = new Zend_Form_Element_Select('controller');
			foreach ($controllersList as $k=>$m)
			{
				$Select ->addMultiOption($m,$m);;
			}

			$Select ->addMultiOption('','Не задано');
			// выбранное значение SELECTED
			$Select ->setValue($_selectedCont);
			$Select ->removeDecorator('Label');
			$Select ->removeDecorator('HtmlTag');
			$form->addElement($Select);
			$form->getElement('controller')->setAttrib('onChange','formEditMenuControllerChanged()');
			// отправим JSON форму выбора контроллеров
			$this->view->controllersList=$form->getElement('controller')->render($this->view);


			// выбор Action зависит от контроллера
			//			$form->addElement('text','action',array('class'=>'medinput', 'value'=>$itemInfo["action"]));
			// если контрроллер получен из AJAX (точнее из GET)
			if ($this->view->selectedContr!==false)
			{
				// построим список экшенов  согласно выбранноу контроллеру и модулю в AJAX
				$actionList =$this->extractActionList($this->view->selectedContr,$moduleOp);
				$_selectedAct='';
			}
			// если контроллер уже задан из БД и модуль через AJAX не получали
			else if ($itemInfo["action"]!=='' && $this->view->selectedModule==false)
			{
				// построим список экшенов согласно выбранному в БД модулю и контроллеру
				$actionList =$this->extractActionList($itemInfo["controller"],$moduleOp);
				$_selectedAct=$itemInfo["action"];
			}
			// когда вообще не задано
			else
			{
				$actionList =array (''=>'');
				$_selectedAct="";

			}
			// построим список Action-ов
			$Select = new Zend_Form_Element_Select('action');
			foreach ($actionList as $k=>$m)
			{
				$Select ->addMultiOption($m,$m);;
			}

			$Select ->addMultiOption('','Не задано');
			// выбранное значение SELECTED
			$Select ->setValue($_selectedAct);
			$Select ->removeDecorator('Label');
			$Select ->removeDecorator('HtmlTag');
			$form->addElement($Select);
			//			$form->getElement('action')->setAttrib('onChange','formEditMenuControllerChanged()');
			// отправим JSON форму выбора контроллеров
			$this->view->actionList=$form->getElement('action')->render($this->view);

			//			=====



			$form->addElement('text','params',array('class'=>'medinput', 'value'=>$itemInfo["params"]));

			$form->addElement('checkbox','disabled');
			$form->getElement('disabled')->setValue($itemInfo["disabled"]);;

			$form->addElement('submit','formSubmit',array('name'=>'Сохранить'));

			// форма готова
			$this->view->form=$form;

		}
		;
	}

	public function changelevelAction()
	{
		;
	}

	public function deleteAction()
	{
		$id = (int)$this->_request->getParam('id',0);
		if ($id!=0)
		{
			$this->_model->deleteItem($id);

		}

		// перенаправим
//		$redirectLink=$this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->view->currentController;
		$this->_redirect($this->redirectLink);

		;
	}

	public function copyAction()
	{
		;
	}

	public function moveAction()
	{
		;
	}

	public function disableAction()
	{
		;
	}

	public function enableAction()
	{
		;
	}

	/**
	 * формирует список контроллеров указанного модуля
	 * @param string $moduleName имя модуля (название директории)
	 * @return array
	 */
	private function extractControllersList($moduleName)
	{
		$pathToControllers=$this->_frontController->getModuleDirectory($moduleName).DIRECTORY_SEPARATOR."controllers";

		// узнаем имена контроллеров выбранного модуля
		$filelist=scandir($pathToControllers);
		$controllersList=array();
		foreach ($filelist AS $name)
		{
			// еси это у нас файл контроллера
			if (is_file($pathToControllers.DIRECTORY_SEPARATOR.$name)&& (stristr($name,'Controller')!=false))
			{
				// имя текущего контроллера
				preg_match("|(.*)Controller|Ui",$name,$cont);
				$controllersList[]=strtolower($cont[1]);
			}
		}
		//		echo "<pre>".print_r($controllersList,true)."</pre>";
		return $controllersList;
	}

	/**
	 * формирует список Action'ов в заданном модуле-контроллере
	 * @param string $controllerName имя контроллера
	 * @param string $moduleName имя модуля
	 * @return array или false если чота не так:
	 */
	private function extractActionList($controllerName,$moduleName)
	{
		// файл нашего контроллера
		$controllerFile=$this->_frontController->getModuleDirectory($moduleName)
		.DIRECTORY_SEPARATOR."controllers"
		.DIRECTORY_SEPARATOR.$controllerName."Controller.php";

		//		$this->view->controllerFile=$controllerFile;
		//		$this->view->moduleOp=$moduleName;
		//		return;

		//				echo $controllerFile;
		//		если файла нет - вернуть пусто
		if (!file_exists ($controllerFile)) return array(''=>'');
		// подключим его
		include_once($controllerFile);
		//				die();

		// имя нашего подключенного класса
		$classname=$moduleName.'_'.$controllerName."Controller";
		// если это модуль по умолчаннию?
		if ($moduleName===$this->_frontController->getDefaultModule()) $classname=$controllerName."Controller";

		$this->view->classname= $classname;
		// методы нашего класса
		$functions=array();
		// переберем и возмем содержащие 'Action'
		foreach (get_class_methods($classname) as $f)
		{
			if (strstr($f,"Action") != false)
			{
				array_push($functions,substr($f,0,strpos($f,"Action")));
			}
		}

		return $functions;

	}
}