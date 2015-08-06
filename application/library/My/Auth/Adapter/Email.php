<?php 
/**
 * @author zlydden
 * проверяет существование почты и если найдено сопоставляет с пользователем в БД
 */
class My_Auth_Adapter_Email implements Zend_Auth_Adapter_Interface
{
	private $username; // email user@domain
	private $password;
	private $port;
	private $host;
	private $ssl;
	private $tUsers			=	"acl_users";
	private $tUsersEmails	=	"acl_users_emails";
	private $tUsersPrivate	=	"personal";
	private $userInfo;

	/**
	 * @param string $username логин user@domain
	 * @param string $password
	 * @param string $host
	 * @param integer $port
	 * @param string $ssl
	 */
	function __construct($username, $password,$host,$port,$ssl=null) {
		$this->username = $username;
		$this->password = $password;
		$this->host 	= $host;
		$this->port 	= $port;
		switch ($ssl) {
			case "TLS":
				$this->ssl="TLS";
				;
				break;

			case "SSL":
				$this->ssl="SSL";
				;
				break;
					
			default:
				$this->ssl=null;
				break;
		}

	}

	function authenticate()
	{
		Zend_Loader::loadclass('Zend_Mail');
		Zend_Loader::loadclass('Zend_Mail_Storage_Pop3');
			
		$options=array(
				"host"		=>	$this->host,
				"port"		=> 	$this->port,
				"user"		=>	$this->username,
				"password"	=>	$this->password
		);
		if (!is_null($this->ssl)) $options["ssl"]=$this->ssl;
		try
		{
			$mail=new Zend_Mail_Storage_Pop3($options);

		}
		// словили ошибку
		catch (Zend_Exception $e)
		{
			// 			echo "<pre>".print_r($e->getMessage(),true)."</pre>";
			// 			echo "<pre>".print_r($e->getCode(),true)."</pre>";
			return new Zend_Auth_Result(Zend_Auth_Result::FAILURE, $e->getMessage());
		}
		// закрыли сеанс с сервером
		$mail->close();

		$info=$this->findUser();
		if (empty($info)) return new Zend_Auth_Result(Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND, null);

		$this->userInfo=$info;
		return new Zend_Auth_Result(Zend_Auth_Result::SUCCESS, null);
	}

	// данные о пользователе по аналогии Zend_Auth_Adapter_DbTable
	// считается почтовый сервер принял
	/**
	* getResultRowObject() - Returns the result row as a stdClass object
	*
	* @param  string|array $returnColumns
	* @param  string|array $omitColumns
	* @return stdClass|boolean
	*/
	public function getResultRowObject($returnColumns = null, $omitColumns = null)
	{
		if (!$this->userInfo) {
			return false;
		}

		$returnObject = new stdClass();

		if (null !== $returnColumns) {

			$availableColumns = array_keys($this->userInfo);
			foreach ( (array) $returnColumns as $returnColumn) {
				if (in_array($returnColumn, $availableColumns)) {
					$returnObject->{$returnColumn} = $this->userInfo[$returnColumn];
				}
			}
			return $returnObject;

		} elseif (null !== $omitColumns) {

			$omitColumns = (array) $omitColumns;
			foreach ($this->userInfo as $resultColumn => $resultValue) {
				if (!in_array($resultColumn, $omitColumns)) {
					$returnObject->{$resultColumn} = $resultValue;
				}
			}
			return $returnObject;

		} else {

			foreach ($this->userInfo as $resultColumn => $resultValue) {
				$returnObject->{$resultColumn} = $resultValue;
			}
			return $returnObject;

		}
	}

	private function findUser()
	{
		$db=Zend_Db_Table::getDefaultAdapter();
		$s=$db->select();
		$s->from(array("e"=>$this->tUsersEmails),array("email"));
		$s->joinLeft(array("u"=>$this->tUsers), "u.id=e.userid");
		$s->joinLeft(array("p"=>$this->tUsersPrivate), "p.userid=u.id",array("fio"=>"CONCAT_WS(' ',family,name,otch)"));
		$s->where("e.email='".$this->username."'");
		$s->where("u.disabled=0");
		return $db->fetchRow($s);
		// узнаем пользователя и не отключен ли он
		// [id] => 4417
		// [login] => itboss
		// [role] => _НЕ ТРЕБА
		// [disabled] => 0
		// [comment] => _НЕ ТРЕБА
		// [person] => _НЕ ТРЕБА
		// [fio] =>

		;

		;
	}
}