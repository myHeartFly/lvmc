<?php

if (!class_exists('MongoClient')){
	show_error("The MongoDB PECL extension has not been installed or enabled", 500);
}

class CIMongo extends MongoClient
{
	private $configs; // 数据库配置文件
	public $connection; // 数据库连接
	
	protected $_ci; // CI singleton object
	// public $db; // 数据库
	
	public function __construct()
	{
		$this->_ci =& get_instance();
		$this->_ci->load->config('mongo');
		$this->configs = $this->_ci->config->item('mongo');
		
		$options = array(
			'username' => $this->configs['username'],
			//'password' => $this->configs['password'],
			'db'       => $this->configs['database'],
			'connect'  => false,
		);
		
		parent::__construct($this->configs['hostname'], $options);
	}

	public function __get($dbname) {
		$i = 1;
		$max = 3;
		while (count($this->getConnections()) < 1 && $i < $max) {
			$i++;
			sleep(5);
// 			echo 'try reconntecd '.date('Y-m-d H:i:s');
			$this->connect();
		}
		if ($dbname == 'db') {
			return parent::__get($this->configs['database']);
		}
		else if($dbname == 'srcdb') {
			return parent::__get($this->configs['srcDatabase']);
		}
		else {
			return parent::__get($dbname);
		}
	}
	
}