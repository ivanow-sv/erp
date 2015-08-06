<?php
Zend_Loader::loadClass('My_Spravtypic');

/**
 * @author zlydden
 *
 */
class Sprav_FacultetsController extends My_Spravtypic
{
	private	$table='facult';
	private	$title='Справочник: Факультеты';

	public function init()
	{
		parent::setTable($this->table);
		parent::setTitle($this->title);
		parent::init();
		$this->view->headScript()->appendFile($this->_request->getBaseUrl().'/public/scripts/sprav.js');
		Zend_Loader::loadClass('Formochki');
	}

	public function indexAction()
	{
		parent::indexAction();

	}

	public function addAction()
	{
		parent::addAction();
	}

	public function editAction()
	{
		parent::editAction();
	}

// 	public function editadvanceAction()
// 	{
// 		$this->view->title = $this->view->title. ' - Специальности и отделения факультета. ';
// 		if ($this->_request->isPost())
// 		{
// 			//			Zend_Loader::loadClass('Zend_Filter_StripTags');
// 			//			$filter = new Zend_Filter_StripTags();
// 			$id = (int)$this->_request->getPost('id');
// 			if ($id>0)
// 			{
// 				$specs = $this->_request->getPost('spec');
// 				$divisions = $this->_request->getPost('division');
// 				//проверка данных и внос в БД
// 				$data=array();
// 				foreach ($specs as $key=>$spec)
// 				{
// 					$s=(int)$specs[$key];
// 					$d=(int)$divisions[$key];
// //					echo $d;
// 					//проверка данных
// 					if ($d<=0 || $s <= 0) continue;
// 					$data[]=array(
// 					"facult"=>$id,
// 					"spec"=>$s,
// 					"division"=>$d,					
// 					);
// 				}
// //				echo $id;
// //				echo "<pre>".print_r($data,true)."</pre>";
// //				die();
// 				// если есть данные
// 				if (count($data)>0)
// 				{
// 					// удалим старые
// 					$this->data->facultDelInfo($id);
// 					// и внесем новые
// 					$this->data->facultNewInfo($data);
// 				}
// 			}
// 			$this->_redirect($this->redirectLink."/editadvance/id/".$id);

// 		}
// 		// мы просто зашли - покажем данные и форму
// 		else
// 		{
// 			$id = (int)$this->_request->getParam('id', 0);
// 			// получаем данные о записи
// 			if ($id > 0)
// 			{
// 				// узнаем какой это факультет
// 				$info=$this->data->otherList("facult","id=".$id);
// 				$info=$info[0];
// 				$this->view->title.=$info["title"];
// 				// узнаем какие бывают специальности
// 				$specs=$this->data->getSpecsForSelectList();
// 				// узнаем какие бывают отделения
// 				$divs=$this->data->getListForSelectList("division");

// 				//узнаем какие взаимосвязи есть про этот факультет
// 				$list=$this->data->facultGetInfo($id);
// 				//				echo count($list)
// 				// форма комбинации специальность-отделение
// 				$formEdit=new Formochki();
// 				$formEdit->setAttrib('name','formEdit');
// 				$formEdit->setAttrib('id','formEdit');
// 				//		$formEdit->setAttrib('style','display:none');
// 				$formEdit->setMethod('POST');
// 				$formEdit->setAction($this->view->selfLink."/editadvance");
// 				$formEdit->addElement("hidden","id");
// 				$formEdit->getElement("id")->setValue($id);
// 				// форма в виде списка
// 				// еси нету ничего про этот факультет, создадим массив пустой
// 				if (count($list)<1)
// 				{
// 					$list=array();
// 					$list[0]=array(
// 					"spec"=>0,
// 					"division"=>0,					
// 					);
// 				}
// 				// строка описывает спец. и отделение
// 				$k=0;
// 				foreach ($list as $key=>$r)
// 				{
// 					$varname="spec".$k;
// 					$specsList=$this->createSelectList($varname,$specs,'',$r["spec"]);
// 					$formEdit->addElement($specsList);
// 					$this->formElementToArray($formEdit,$varname,"spec");
						
// 					$varname="division".$k;
// 					$divList=$this->createSelectList($varname,$divs,'',$r["division"]);
// 					$formEdit->addElement($divList);
// 					$this->formElementToArray($formEdit,$varname,"division");
						
// 					;
// 					$k++;
// 				}
// 				$this->view->formEdit=$formEdit;
// 				$this->view->list=$list;
// 				$this->view->info=$info;

// 				//
// 			}
// 			else
// 			$this->_redirect($this->redirectLink);

// 		}

// 	}

	public function delAction()
	{
		parent::delAction();
	}
	/** элемент формы - тип массив => VARNAME[]
	 * @param Zend_Form $form сам форма
	 * @param string $varname элемент формы по ID
	 * @param string $newName новое имя формы (для рендеринга, ID тот же)
	 */

	private function formElementToArray(&$form,$varname,$newName)
	{
		$form->getElement($varname)->setName($newName)->setIsArray(true);

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


}