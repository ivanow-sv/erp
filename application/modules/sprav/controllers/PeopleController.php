<?php
/**
 * Управление персоналом, ФИО и должность
 */


class Sprav_PeopleController extends Zend_Controller_Action
{
	private $redirectLink;
	
    function init()
    {
        $this->view->baseUrl = $this->_request->getBaseUrl();
        $this->view->curact = $this->_request->action;
        $this->view->curcont = $this->_request->controller;
        $this->view->title = 'Справочник: Сотрудники';
        $this->tablename='personal';
        Zend_Loader::loadClass('SpravTypic');
		$this->redirectLink=$this->_request->getModuleName()."/".$this->_request->getControllerName();
        
    }

    function indexAction()
    {
        // это для формы добавления
        $this->view->curact = 'add';
        // таблица дисциплин
        $entry = new SpravTypic(array('name'=>$this->tablename));
        $this->view->entries = $entry->fetchAll();
        //        		echo "<pre>".print_r($this,true)."</pre>";

    }

    function addAction()
    {
        $this->view->title = $this->view->title. ' - Добавлена запись';

        if ($this->_request->isPost()) {
            Zend_Loader::loadClass('Zend_Filter_StripTags');
            $filter = new Zend_Filter_StripTags();
            $tabel = (int)$this->_request->getPost('tabel');

            $family = $filter->filter($this->_request->getPost('family'));
            $family = trim($family);
            $this->view->family=$family;
            $name = trim($filter->filter($this->_request->getPost('name')));
            $this->view->name=$name;
            $otch= trim($filter->filter($this->_request->getPost('otch')));
            $this->view->otch=$otch;
            if ($family != '' && $name != '' && $otch != ''  && $tabel > 0) {
                $data = array(
                'tabel' => $tabel,
                'family' => $family,
                'name' => $name,
                'otch' => $otch,
                );
                $entry = new SpravTypic(array('name'=>$this->tablename));
                $entry->insert($data);
                return;
            }

//            $redirectLink=$this->_request->getBaseUrl().'/sprav/'.$this->_request->controller;
            $this->_redirect($this->redirectLink);

        }

    }

    function editAction()
    {
        $this->view->title = $this->view->title. ' - Изменения записи';
        $entry = new SpravTypic(array('name'=>$this->tablename));

        if ($this->_request->isPost()) {
            Zend_Loader::loadClass('Zend_Filter_StripTags');
            $filter = new Zend_Filter_StripTags();
            
            $tabel = (int)$this->_request->getPost('tabel');
            $tabel->view->family=$tabel;
            
            $id= (int)$this->_request->getPost('id');

            $family = $filter->filter($this->_request->getPost('family'));
            $family = trim($family);
            $this->view->family=$family;
            
            $name = trim($filter->filter($this->_request->getPost('name')));
            $this->view->name=$name;
            
            $otch= trim($filter->filter($this->_request->getPost('otch')));
            $this->view->otch=$otch;

            if ($id >0 && $family != '' && $name != '' && $otch != ''  && $tabel > 0) {

                $data = array(
                'tabel' => $tabel,
                'family' => $family,
                'name' => $name,
                'otch' => $otch,
                );

                $where = 'id = ' . $id;
                $entry->update($data, $where);
                return;
            }
        }
        //        $this->view->fieldTitle=$title;
//        $redirectLink=$this->_request->getBaseUrl().'/sprav/'.$this->_request->controller;
        $this->_redirect($this->redirectLink);

    }

    function delAction()
    {
        $this->view->title = $this->view->title. ' - Удаление записи';
        $entry = new SpravTypic(array('name'=>$this->tablename));

        if ($this->_request->isPost()) {
            Zend_Loader::loadClass('Zend_Filter_Alpha');
            $filter = new Zend_Filter_Alpha();
            $id = (int)$this->_request->getPost('id');
            $del = $filter->filter($this->_request->getPost('del'));

            if ($del == 'Yes' && $id > 0) {
                $where = 'id = ' . $id;
                $rows_affected = $entry->delete($where);
            }
        } else {
            $id = (int)$this->_request->getParam('id');

            if ($id > 0) {
                // only render if we have an id and can find the album.
                $this->view->entry = $entry->fetchRow('id='.$id);

                if ($this->view->entry->id > 0) {
                    // render template automatically
                    return;
                }
            }
        }

//        $redirectLink=$this->_request->getBaseUrl().'/sprav/'.$this->_request->controller;
        $this->_redirect($this->redirectLink);
    }
}