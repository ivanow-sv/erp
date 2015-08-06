<?php


class IndexController extends Zend_Controller_Action
{
	private $_author;
	
    function init()
    {
        $this->view->baseUrl = $this->_request->getBaseUrl();
//        $this->view->user = Zend_Auth::getInstance()->getIdentity();
        $this->_author=Zend_Auth::getInstance()->getIdentity();
        $this->view->user =$this->_author;
        //		Zend_Loader::loadClass('Album');
        //       $album = new Album();
        //		$this->view->albums = $album->fetchAll();
    }

    function indexAction()
    {
    	
    	//echo "<pre>".print_r($this->_author,true)."</pre>";
    	if (! Zend_Auth::getInstance()->hasIdentity())  
    	{
    		
//     		$this->_response->setHeader("REFERER", $this->_request->getHeader('REFERER'));
    		$this->_redirect('/default/auth/login');
    	}
//     	$this->_request->getHeader('REFERER'); 
 
//     	print_r($this->_author);
        $this->view->title = "Здравствуйте";
		return;
    }

//     function spravAction()
//     {
//         $this->_redirect('/sprav');
		
//     }

//     function studentAction()
//     {
//         $this->_redirect('/student');

//     }

//     function dostupAction()
//     {
//         $this->_redirect('/dostup');

//     }

}