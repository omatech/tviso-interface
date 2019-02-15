<?php

namespace Omatech\Tviso;

class AppBase {

	public $debug_messages = '';
	protected $conn;
	protected $params = array();
	protected $cache_expiration = 3600;
	protected $type_of_cache = null;
	protected $mc = null;
	protected $debug = false;
	protected $avoid_cache = false;
	protected $show_inmediate_debug = false;
	protected $timings = false;
	protected $memcache_port = 11211;
	protected $memcache_host = 'localhost';
	
	public function getParams() {
		return $this->params;
	}

	public function setParams($params) {
		$this->params = array_merge($params, $this->params);
		foreach ($params as $key => $value) {
			//echo "Parsing $key=$value\n";
			if (property_exists($this, $key)) {
				$this->$key = $value;
			}
		}
	}

	public function __construct($conn, $params = array()) {

		$this->setParams($params);
		if (is_array($conn)) {
			$config = new \Doctrine\DBAL\Configuration();
			if ($this->debug) {
				$config->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger());
			}
			//print_r($conn);
			$conn = \Doctrine\DBAL\DriverManager::getConnection($conn, $config);
		}
		$this->conn = $conn;
		$ret=$this->setupCache();
		echo "setupcache\n";
		var_dump($ret);
	}
	
	
	function setupCache() {// set up the type_of_cache (memcache or memcached) and a handler or false if cache is not available
		if ($this->mc != null && $this->type_of_cache != null)
			return true;

		$memcacheAvailable = false;
		if (extension_loaded('Memcached')) {
			$this->debug("Cache extension Memcached\n");
			$type_of_cache = 'memcached';
			try {
				$mc = new \Memcached;
				$mc->setOption(\Memcached::OPT_COMPRESSION, true);
				$memcacheAvailable = $mc->addServer($this->memcache_host, $this->memcache_port);
			} catch (Exception $e) {
				$this->debug("Error connecting ".$e->getMessage."\n");
				return false;
			}
		} elseif (extension_loaded('Memcache')) {
			$this->debug("Cache extension Memcache\n");
			$type_of_cache = 'memcache';
			try {
				$mc = new \Memcache;
				$memcacheAvailable = $mc->connect($this->memcache_host, $this->memcache_port);
			} catch (Exception $e) {
				$this->debug("Error connecting ".$e->getMessage."\n");
				return false;
			}
		} else {
		  $this->debug("Error no cache extension loaded in PHP\n");
			return false;
		}

		if ($memcacheAvailable) {
			$this->mc = $mc;
			$this->type_of_cache = $type_of_cache;
			return true;
		} else {
			return false;
		}
	}

	protected function debug($str) {
		$add = '';
		if ($this->debug) {
			if (is_array($str)) {
				$add .= print_r($str, true);
			} else {// cas normal, es un string
				$add .= $str;
			}

			$this->debug_messages .= $add;
			if ($this->show_inmediate_debug)
				echo $add;
		}
	}


	public function startTransaction() {
		$this->conn->executeQuery('start transaction');
	}

	public function commit() {
		$this->conn->executeQuery('commit');
	}

	public function rollback() {
		$this->conn->executeQuery('rollback');
	}	
	
}
