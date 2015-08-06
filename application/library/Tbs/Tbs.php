<?php
include_once(APPLICATION_PATH.DIRECTORY_SEPARATOR
."library".DIRECTORY_SEPARATOR
."Tbs".DIRECTORY_SEPARATOR
."tbs_class.php");
include_once(APPLICATION_PATH.DIRECTORY_SEPARATOR
."library".DIRECTORY_SEPARATOR
."Tbs".DIRECTORY_SEPARATOR
."plugins".DIRECTORY_SEPARATOR
."tbs_plugin_opentbs.php");

class Tbs_Tbs extends clsTinyButStrong
{
	private $templatedir; // путь где хранятся шаблоны OpenOffice

	public function __construct()
	{
		// путь к шаблонам по умолчанию
		$this->templatedir=APPLICATION_PATH.DIRECTORY_SEPARATOR
		.'templatesDocs'
		.DIRECTORY_SEPARATOR;
		
		parent::__construct();
		$this->Plugin(TBS_INSTALL, OPENTBS_PLUGIN); // load OpenTBS plugin
	}

	public function LoadTemplateUtf8($template)
	{

		//		parent::BeforeLoadTemplate($template,OPENTBS_ALREADY_UTF8);
		$this->LoadTemplate($this->templatedir.$template,OPENTBS_ALREADY_UTF8);

	}

	public function setTemplatePath($path)
	{
		$this->templatedir=$path;
	}

}