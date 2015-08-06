<?php
/**
 * @author zlydden
 *
 * @TODO низя удалять админов - ID =1
 * @TODO сделать наследования ролей
 */

class Dostup_RolesController extends Zend_Controller_Action
{

	private $redirectLink; // переадресация на данный контроллер
	
	public function init()
	{
		// выясним название текущего модуля для путей в ссылках
		$currentModule=$this->_request->getModuleName();
		$this->view->currentModuleName=$currentModule;
		$this->view->currentController=$this->_request->getControllerName();
		$this->view->currentAction=$this->_request->getActionName();
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->title='Управление доступом. Группы (роли) пользователей';
		
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
		
		Zend_Loader::loadClass('Roles');
		$this->_model=new Roles;

		//активируем хэлпер AjaxContext
		//и передаём туда имя action'а, который необходимо интегрировать с AJAX'ом
		//		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		//		$ajaxContext ->addActionContext('edit', 'json')->initContext('json');
			
	}

	/**
	 *
	 * покажем роли
	 * @TODO роли в виде деревьев
	 */
	public function indexAction ()
	{
//		$logger = Zend_Registry::get('logger');

		//		$rows=$this->_model->getRolesList();
		$rows=$this->_model->getRolesAll();

		$this->view->items=$this->treeArray($rows);
//		$logger->log($rows, Zend_Log::INFO);
//		$logger->log($this->view->items, Zend_Log::INFO);
		//		$logger->log($this->view->itemz, Zend_Log::INFO);

	}

	/**
	 * добавим роль
	 */
	public function addAction()
	{
		// куда по умаолчанию пойдем для продолжения работы
		// переход на управление ролями
		$redirectLink=$this->redirectLink;
		// заголовок страницы
		$this->view->title.="Добавлено новое";
		if ($this->_request->isPost()) {
			// получим и отфильтруем название
			Zend_Loader::loadClass('Zend_Filter_StripTags');
			$filter = new Zend_Filter_StripTags();
			$title = $filter->filter($this->_request->getPost('title'));
			$title = trim($title);
			$this->view->fieldtitle=$title;
			// если оно НЕ пусто
			if ($title != '') {
				// добавим и узнаем ID
				$addedId=$this->_model->addRoleByTitle($title);
				// добавлено, направим к редактированию
				$redirectLink=$this->redirectLink.'/edit/id/'.$addedId;
			}


		}
		// продолжим работу
		// перейдем к редактированию новой роли
		$this->_redirect($redirectLink);
	}

	/**
	 * удаление существующего
	 */
	public function deleteAction()
	{
		$id = (int)$this->_request->getParam('id',0);
		if ($id!=0)
		{
			$this->_model->deleteRole($id);

		}

		// перенаправим
//		$redirectLink=$this->view->baseUrl.'/'.$this->view->currentModuleName.'/'.$this->view->currentController;
		$this->_redirect($this->redirectLink);


	}


	/**
	 * правка существующего
	 */
	public function editAction()
	{
		$this->view->title.=". Правка";
		// @TODO ссылка на уровень верх
		$parentLink= $this->redirectLink; //$this->view->currentModuleName.'/'.$this->view->currentController;
		$this->view->parentLink=$this->view->baseUrl.'/'.$parentLink;

		$id = (int)$this->_request->getParam('id',0);
		//		echo $id;
		//		die();
		if ($id!==0)
		{
			// ссылка "сюда же". Нужна после внесения изменений и чтобы перезагрузить форму с данными из БД
			$selfLink=$this->redirectLink.'/'.$this->view->currentAction.'/id/'.$id;
			$this->view->selfLink=$this->view->baseUrl.'/'.$selfLink;

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
				$this->_model->setInfo($params);
				// исправили, направим к редактированию сябя же
					
				$this->_redirect($selfLink);


			}
			// если просто зашли
			// берем данные и рисуем форму
			else
			{
				$info=$this->_model->getInfo($id);
				$paramz=$this->_model->getParams($id);
				
				$this->view->title.=" <".$info["title"].">";

				//	сообразим форму
				Zend_Loader::loadClass('Formochki');
				$form=new Formochki;
				$form->setAttrib('name','roleEditForm');
				$form->setAttrib('id','roleEditForm');
				$form->setMethod('post');
				$form->setAction($this->view->baseUrl.'/'.$this->redirectLink.'/'.$this->view->currentAction);

				$form->addElement('hidden','id',array('value'=>$id));


				$form->addElement('text','title',array('class'=>'medinput', 'value'=>$info["title"]));
				// список групп(ролей) для выбора родителя
				// список групп
				$logger = Zend_Registry::get('logger');

				// получим роли
				$roleList=$this->_model->getRolesAll();
				// корень
				$aaa=array();
				$aaa["id"]=-1;
				$aaa["title"]='/';
				$aaa["comment"]='';
				$aaa["disabled"]=0;
				$aaa["parent"]=null;
				array_unshift($roleList,$aaa);

				// сообразим нужный массив
				$roleList =$this->treeArray($roleList);
				$roleList =$this->rolesForSelect($roleList,$info["parent"]);
//				$logger->log($paramz, Zend_Log::INFO);

				Zend_Loader::loadClass('Zend_Form_Element_Select');
				$roleSelect = new Zend_Form_Element_Select('parent');
				//				$roleList=array(0=>"гость", 1=>"Адми","sss"=>array(1=>"aaaa",3=>"bbb"));

				foreach ($roleList as $key=>$value)
				{
					$roleSelect ->addMultiOption($key,$value);
					//					if ($key==$info["parent"]) $roleSelect ->setAttrib('class','roles_selected');
				}
				//				$roleSelect ->addMultiOption(0,'Гость (без группы)');

				//				$info["role"]=(is_null($info["role"]))?0:$info["role"];
				// выбранное значение SELECTED
				$sel=is_null($info["parent"])?"-1":$info["parent"];
				$roleSelect ->setValue($sel);
				$roleSelect ->removeDecorator('Label');
				$roleSelect ->removeDecorator('HtmlTag');
				$form->addElement($roleSelect );

				$form->addElement('textarea','comment',array('class'=>'medinput', 'value'=>$info["comment"]));
				$form->addElement('textarea','paramz',array('class'=>'medinput', 'value'=>$paramz));
				$form->addElement('checkbox','disabled');
				$form->getElement('disabled')->setValue($info["disabled"]);;

				$form->addElement('submit','formSubmit',array('name'=>'Сохранить'));

				$this->view->form=$form;
			}

		}
		//		$this->_redirect($parentLink);
	}

	/**
	 * функция для рекурсии
	 * для переделки из древовидного многоуровневого массива массив для выпадающего списка
	 * @param array $item
	 * @param string $deep показатель глубины
	 * @param array $result
	 */
	private function selecElem(&$item,&$deep,&$result,$selected)
	{
		if (is_array($item))
		{
			$result[$item["id"]]=$deep.$item["title"];
			if ($selected==$item["id"]) $result[$item["id"]].=" <==";
		}
		if (isset($item["child"]))
		{
			// если нет родителей - занчит начало ветки
			if (is_null($item["parent"])) $deep="";

			// переберем "детей"
			$childs=explode(" ",$item["child"]);
			foreach ($childs as $name)
			{
				// если нижеследующий прямой потомок
				if ($item[$name]["parent"]==$item['id']) $dp="|".ltrim($deep."--","|");
				else $dp=$deep;
				$this->selecElem($item[$name],$dp,$result,$selected);
			}


		}

	}

	/**
	 * делает из древовидного многоуровневого массива массив для выпадающего списка
	 * @param array $rows в виде дерева
	 * @return Array
	 */
	private function rolesForSelect($rows,$selected)
	{
		$deep='';
		$result='';
		foreach ($rows as $k=>$item)
		{
			$this->selecElem($item,$deep,$result,$selected);
		}
		return($result);
	}

	/**
	 * делает из входного массива древовидный
	 * @param array $rows данные из БД
	 * @return array
	 */
	private function treeArray($rows)
	{
		$items=array();
		// верхний цикл
		foreach ($rows as $kk=>$row)
		{
			$aaa=array();
			$aaa["id"]=$row['id'];
			$aaa["title"]=$row['title'];
			$aaa["comment"]=$row['comment'];
			$aaa["disabled"]=$row['disabled'];
			$aaa["parent"]=$row['parent'];

			// это чей-то наследник?
			if (!is_null($row['parent']))
			{
				$this->appendChilds(&$items,$aaa,$row['parent']);
			}
			else
			{
				$items[$row['id']]=$aaa;
			}
		}

		return $items;
	}

	/**
	 * добавляет детей к элементу массива
	 * @param array $inArrayв какой массив добавляется
	 * @param array $ArrayAdd массив, который пристыкуется
	 * @param unknown_type $key "точка монтирования" :)
	 */
	private function appendChilds(&$inArray,$ArrayAdd,$key)
	{
		//				if ($ArrayAdd["id"]==18) echo "<pre>".print_r($inArray,true)."</pre>";
		//				if ($key==0) echo "<pre>".print_r($inArray,true)."</pre>";
		// если это массив - переберем его
		if (is_array($inArray))
		{
			//			if ($inArray["id"]===$key ) $inArray[$key][$ArrayAdd["id"]]=$ArrayAdd;
			//			echo "<pre>".print_r($inArray,true)."</pre>";
			// перебор вот он, значок амперсант важен
			foreach ($inArray as $kk=>&$subArray)
			{
				// если искомое - добавим
				if ($subArray["id"]==$key && is_array($subArray))
				{
					$subArray["child"]=trim($subArray["child"]." ".$ArrayAdd["id"]);
					$subArray[$ArrayAdd["id"]]=$ArrayAdd;
				}
				// если и он массив - то опять ввызов
				if (is_array($subArray))$this->appendChilds($subArray,$ArrayAdd,$key);
				//				else $this->appendChild($subArray,$ArrayAdd,$key);
			}
		}
		//		continue;
	}

	/*private function appendChilds2(&$inArray,$ArrayAdd,$key)
	 {
		$logger = Zend_Registry::get('logger');
		//				$logger->log($inArray, Zend_Log::INFO);
		//				$logger->log($ArrayAdd, Zend_Log::INFO);

		// если это массив - переберем его
		if (is_array($inArray))
		{
		//			$logger->log($inArray, Zend_Log::INFO);
		//			$logger->log($ArrayAdd, Zend_Log::INFO);
		// перебор вот он, значок амперсант важен
		foreach ($inArray as $kk=>&$subArray)
		{
		//				$logger->log($ArrayAdd, Zend_Log::INFO);
		// если искомое - добавим

		if (is_array($subArray) && count($subArray)>=1)
		{
		$temp=array_keys($subArray);
		//					$temp=ayy($temp);
		//					$logger->log(($subArray), Zend_Log::INFO);
		if ($temp[0]==$key )
		{
		//					$subArray["child"]=trim($subArray["child"]." ".$ArrayAdd["id"]);
		$subArray[$ArrayAdd['id']]=$ArrayAdd['title'];
		//					array_push($subArray,$ArrayAdd);
		}
		else $this->appendChilds2($subArray,$ArrayAdd,$key);
		}
		//
		// если и он массив - то опять ввызов
		//				if (is_array($subArray))$this->appendChilds2($subArray,$ArrayAdd,$key);
		//				elseif ($kk==$key)
		//				{
		//					$subArray[$key]=$ArrayAdd;
		//				$inArray[$subArray]=array();
		//					$inArray[$subArray][$ArrayAdd["id"]]=$ArrayAdd["title"];
		//				}
		//				else $this->appendChild($subArray,$ArrayAdd,$key);
		}
		}
		//		elseif (key($inArray)===$key) array_push($inArray,$ArrayAdd);
		//		continue;
		}
		*/
}