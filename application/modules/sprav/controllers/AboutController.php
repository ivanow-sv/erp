<?php
class Sprav_AboutController extends Zend_Controller_Action
{
	private $redirectLink;
	private $data;
	private $confirmWord;

	function init()
	{
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$this->view->curact = $this->_request->action;
		$this->view->curcont = $this->_request->controller;
		$this->view->modulename=$this->_request->getModuleName();
		$this->view->title = 'Справочник: Информация о ВУЗе';
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();

		//        $this->tablename='about';
		Zend_Loader::loadClass('Sprav');
		$this->data=new Sprav('about');

		$this->confirmWord="УДАЛИТЬ";
	}

	function indexAction()
	{
		// таблица дисциплин
		$entry = $this->data->about();
		$this->view->entry = $entry;
	}

	function editAction()
	{
		$this->view->title = $this->view->title. ' - Изменения записи';
		if ($this->_request->isPost()) {
			Zend_Loader::loadClass('Zend_Filter_StripTags');
			$filter = new Zend_Filter_StripTags();
			$orgname= trim($filter->filter($this->_request->getPost('orgname')));
			$adress= trim($filter->filter($this->_request->getPost('adress')));
			$rektor_FIO= trim($filter->filter($this->_request->getPost('rektor_FIO')));
// 			$campaignYear= (int)trim($filter->filter($this->_request->getPost('campaignYear')));

			if ($orgname!= '' && $adress !='') {

				$data = array(
                    'orgname' => $orgname,
                    'adress' => $adress,
                    'rektor_FIO' => $rektor_FIO,
//                     'campaignYear' => $campaignYear,
				);

				$this->data->aboutSave($data);
				return;
			}
		}
		$this->_redirect($this->redirectLink);

	}
}