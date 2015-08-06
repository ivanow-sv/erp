<?php 
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
	public function _initAutoloader()
	{
		//// Create an resource autoloader component
		$autoloader = new Zend_Loader_Autoloader_Resource(array(
            'basePath'    => APPLICATION_PATH,
            'namespace' => 'My'
            ));

            // Add some resources types
            $autoloader->addResourceTypes(array(
            'models' => array(
                'path'           => 'models',
                'namespace' => ''   
                ),
                ));

                // Return to bootstrap resource registry
                return $autoloader;
	}

		protected function _initRoutes()
		{
			$router = $this->bootstrap('frontController')->getResource('frontController')->getRouter();
			// для разработке на DENWER
			$hostnameRoute = new Zend_Controller_Router_Route_Hostname(
					'help.local',
					array(
							'module'		=>	"helpdesk",
							'controller'	=>	'index'
					)
			);
			$plainPathRoute = new Zend_Controller_Router_Route_Static(
					'',
					array(
							'module'		=>	"helpdesk"
							)
					);
			
			// PRODUCTION
			$hostname2Route = new Zend_Controller_Router_Route_Hostname(
					'help.academy21.ru',
					array(
							'module'		=>	"helpdesk",
							'controller'	=>	'index'
					)
			);
			$plainPath2Route = new Zend_Controller_Router_Route_Static(
					'',
					array(
							'module'		=>	"helpdesk"
							)
					);
		
			
			$router->addRoute('help', $hostnameRoute->chain($plainPathRoute));
			$router->addRoute('help2', $hostname2Route->chain($plainPath2Route));
			return $router;
		}
	////
	//    protected function _initRequest()
	//    {
	//		$this->bootstrap('Routes');
	//		$router= $this->getResource('Routes');
	//
	//    	$request = new Zend_Controller_Request_Http();
	//		$router->route($request);
	//        // Обеспечение сохранения запроса в реестре загрузки
	//        return $request;
	//    }


	//	public function _initRestree()
	//	{
	//				$this->bootstrap('Db');
	//				Zend_Registry::set('mvcTree',$resTree);
	//
	//		return $tree;
	//
	//	}


	/*
	 public function _initNavigation()
	 {
		$this->bootstrap('View');
		$view = $this->getResource('View');
		// наша База
		$this->bootstrap('Db');
		$db= $this->getResource('Db');
		//		$this->bootstrap('Auth');

		//				$acl=Zend_Registry::get("ACL");
		// убедимся шо БД уже мона пользоватцо
		//		$this->bootstrap('Db');

		//		$this->bootstrap('Acl');
		//		$this->bootstrap('Acl');
		//		$acl= Zend_Registry::get("acl");
		$logger=Zend_Registry::get("logger");

		//$logger->log($acl, Zend_Log::INFO);
		// @TODO учесть что меню многоуровневое
		// @TODO и менющем может быть несколько
		// @TODO может вытаскивать тока меню определенной группы?

		// наши менюшки
		$sql='SELECT nav';
		$sql.="\n FROM nav_items2";
		$nav=$db->fetchOne($sql);
		$pages=unserialize($nav);
		//		echo "<pre>".print_r($rows,true)."</pre>";
		//				$logger->log($pages, Zend_Log::INFO);

		/*
		$pages=array();
		// пересоберем массив
		// т.к. параметры должны быть массивом
		foreach ($rows as $k=>$row)
		{
		$pages[$k]=$row;
		if(strlen($row["params"])>1)
		{
		//разделим параметры
		$params=explode(',',$row["params"]);
		// перенесем в массив с ключами
		$p=array();
		foreach ($params as $param)
		{
		// ПАРАМЕТР = ЗНАЧЕНИЕ переделаем в ПАРАМЕТР => Значение
		$i=explode('=',$param);
		$p[$i[0]]=$i[1];
		}
		$pages[$k]["params"]=$p;
		}
		else unset ($pages[$k]["params"]);
		}

		//		$logger->log($pages, Zend_Log::INFO);
		//
		$container = new Zend_Navigation($pages);
		$view->menu = $container;

		return $container;
		}
		*/
//	public function _initLocale()
//	{
//
//		$locale = new Zend_Locale('ru_RU');
//
//		Zend_Registry::set('Zend_Locale', $locale);
//	}
	

	public function _initAuth()
	{
		$this->bootstrap('Db');
		//		$db= $this->getResource('Db');

		$auth = Zend_Auth::getInstance();


		Zend_Loader::loadClass('My_Plugin_Auth');
		$this->bootstrap('frontController');
		$front = $this->getResource('frontController');

		// берем объекты доступа
		//		$this->bootstrap('Acl');
		//		$acl= $this->getResource('Acl');

		$front->registerPlugin(new My_Plugin_Auth($auth));
		return $auth;
	}


	}