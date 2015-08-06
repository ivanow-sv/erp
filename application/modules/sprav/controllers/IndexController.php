<?php


class Sprav_IndexController extends Zend_Controller_Action
{
    function init()
    {
        $this->view->baseUrl = $this->_request->getBaseUrl();
        $this->view->curact = $this->_request->action;
        
    }


    function indexAction()
    {
        $this->view->title = "Справочник";

    }
/*
    function aboutAction()
    {
        $this->view->title = "Справочник: общая информация";

    }

    function disciplineAction()
    {
        $this->view->title = "Справочник: Дисциплины вступительных испытаний";
        // таблица дисциплин
        $list = new Spravtypic(array('name'=>'discipline'));
        $this->view->entries = $list->fetchAll();
        //        		echo "<pre>".print_r($this,true)."</pre>";

    }

    function divisionAction()
    {
        $this->view->title = "Справочник: Отделения обучения";
        // укажем шо таблица отделений
        $list = new Spravtypic(array('name'=>'division'));
        $this->view->entries = $list->fetchAll();

    }

    function paymentAction()
    {
        $this->view->title = "Справочник: Способы оплаты";
        // укажем шо таблицу
        $list = new Spravtypic(array('name'=>'payment'));
        $this->view->entries = $list->fetchAll();
    }

  
    function idendocAction()
    {
        $this->view->title = "Справочник: Документы, удостоверяющие личность";
        // укажем шо таблицу
        $list = new Spravtypic(array('name'=>'identity'));
        $this->view->entries = $list->fetchAll();
    }

    function doccopyAction()
    {
        $this->view->title = "Справочник: Типы документов(копия/оригинал)";
        // укажем шо таблицу
        $list = new Spravtypic(array('name'=>'doc_copy'));
        $this->view->entries = $list->fetchAll();
    }

    function socialAction()
    {
        $this->view->title = "Справочник: Категории льгот";
        // укажем шо таблицу
        $list = new Spravtypic(array('name'=>'categories'));
        $this->view->entries = $list->fetchAll();
    }

    function edudocAction()
    {
        $this->view->title = "Справочник: Документы об образовании";
        // укажем шо таблицу
        $list = new Spravtypic(array('name'=>'edu_docs'));
        $this->view->entries = $list->fetchAll();
    }

    function formaobuchAction()
    {
        $this->view->title = "Справочник: Форма обучения";
        // укажем шо таблицу
        $list = new Spravtypic(array('name'=>'osnov'));
        $this->view->entries = $list->fetchAll();
    }

    function addAction()
    {
        $this->view->title = "Add New Album";
        if ($this->_request->isPost()) {
            Zend_Loader::loadClass('Zend_Filter_StripTags');
            $filter = new Zend_Filter_StripTags();
            $artist = $filter->filter($this->_request->getPost('artist'));
            $artist = trim($artist);
            $title = trim($filter->filter($this->_request->getPost('title')));

            if ($artist != '' && $title != '') {
                $data = array(
                'artist' => $artist,
                'title' => $title,
                );
                $album = new Album();
                $album->insert($data);
                $this->_redirect('/');
                return;
            }
        }

        // set up an "empty" album
        $this->view->album = new stdClass();
        $this->view->album->id = null;
        $this->view->album->artist = '';
        $this->view->album->title = '';

        // additional view fields required by form
        $this->view->action = 'add';
        $this->view->buttonText = 'Add';
    }

    function editAction()
    {
        $this->view->title = "Edit Album";
        $album = new Album();

        if ($this->_request->isPost()) {
            Zend_Loader::loadClass('Zend_Filter_StripTags');
            $filter = new Zend_Filter_StripTags();
            $id = (int)$this->_request->getPost('id');
            $artist = $filter->filter($this->_request->getPost('artist'));
            $artist = trim($artist);
            $title = trim($filter->filter($this->_request->getPost('title')));

            if ($id !== false) {
                if ($artist != '' && $title != '') {

                    $data = array(
                    'artist' => $artist,
                    'title' => $title,
                    );

                    $where = 'id = ' . $id;
                    $album->update($data, $where);
                    $this->_redirect('/');
                    return;
                } else {
                    $this->view->album = $album->fetchRow('id='.$id);
                }
            }
        } else {
            // album id should be $params['id']
            $id = (int)$this->_request->getParam('id', 0);

            if ($id > 0) {
                $this->view->album = $album->fetchRow('id='.$id);
            }
        }

        // additional view fields required by form
        $this->view->action = 'edit';
        $this->view->buttonText = 'Update';
    }

    function deleteAction()
    {
        $this->view->title = "Delete Album";
        $album = new Album();

        if ($this->_request->isPost()) {
            Zend_Loader::loadClass('Zend_Filter_Alpha');
            $filter = new Zend_Filter_Alpha();
            $id = (int)$this->_request->getPost('id');
            $del = $filter->filter($this->_request->getPost('del'));

            if ($del == 'Yes' && $id > 0) {
                $where = 'id = ' . $id;
                $rows_affected = $album->delete($where);
            }
        } else {
            $id = (int)$this->_request->getParam('id');

            if ($id > 0) {
                // only render if we have an id and can find the album.
                $this->view->album = $album->fetchRow('id='.$id);

                if ($this->view->album->id > 0) {
                    // render template automatically
                    return;
                }
            }
        }

        // redirect back to the album list unless we have rendered the view
        $this->_redirect('/');
    }
    */  
}