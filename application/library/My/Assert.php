<?php
class My_Assert implements Zend_Acl_Assert_Interface
{
	/**
	 * This assertion should receive the actual User and BlogPost objects.
	 *
	 * @param Zend_Acl $acl
	 * @param Zend_Acl_Role_Interface $user
	 * @param Zend_Acl_Resource_Interface $blogPost
	 * @param $privilege
	 * @return bool
	 */
	public function assert(Zend_Acl $acl, Zend_Acl_Role_Interface $role = null, Zend_Acl_Resource_Interface $resID = null, $privilege = null)
	{
		$logger=Zend_Registry::get("logger");
		 
		$groupEnv=Zend_Registry::get("groupEnv");
		$groupEnv=array_keys($groupEnv);
		$resReq=Zend_Registry::get("resReq");
		// 1. проверка на наличие у роли нужных для данного ресурса переменных
		// если вообще есть параметры у ресурса
		if (count($resReq)>0)
		{
			// пересечение массивов, наличие среди переменных роли нужных
			$_chk=array_intersect($resReq,$groupEnv);
			// если количество совпадений = кол-ву обязательных - то ДА
			$chk=(count($_chk)==count($resReq));
			if ($chk) return true;
			else return false;
		}
		// если нету - значит можно заходить
		else return true;

		
		// остальное...
		 
		//@TODO возвращать TRUE если ВСЕ условия соблюдены $chk1=true,$chk2=true и т.д.
		
		return true;
	}
}