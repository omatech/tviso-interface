<?php

namespace Omatech\Tviso;

use \Omatech\Tviso\AppBase;

class Tviso extends AppBase {

	var $api_base = '';
	var $id_api = '';
	var $secret = '';
	var $error = '';
	var $user_token = '';
	var $auth_token = '';

	function getToken() {
		$response_array = $this->makeCURL('/auth_token?id_api=' . $this->id_api . '&secret=' . $this->secret);
		$auth_return_code = $response_array['error'];
		if ($auth_return_code == 0) {
			$this->auth_token = $response_array['auth_token'];
			return $response_array['auth_token'];
		} else {
			$this->error = 'Auth error ' . print_r($response_array, true);
			return false;
		}
	}

	function launch($action, $params = null, $method = 'GET') {
		$this->debug('LAUNCH:::'.$action."\n");
		$insert_in_cache = false;
		if ($this->mc !== null) {
			$memcache_key = $this->conn->getDatabase() . ":array:$method:$action:" . serialize($params);
			$this->debug("memcache_key==$memcache_key\n");
			$response_array = $this->mc->get($memcache_key);
			if (!$response_array) {
				$insert_in_cache = true;
			} else {
				$this->debug("CACHE::HIT!!!!\n");
				return $response_array;
			}
		}
		$this->debug("CACHE::MISS\n");

		$response_array = $this->makeCURL($action, $params, $method);

		$return_code = $response_array['error'];
		if ($return_code == 0) {// ha fallat per timestamp, probablement un altre peticio d'un altre batch ha coincidit al mateix moment, fem retry
			if ($insert_in_cache) {
				$this->debug($this->type_of_cache . ":: insertamos el objeto $memcache_key \n");
				$this->setCache($memcache_key, $response_array);
			}
			return $response_array;
		} elseif ($return_code == 1) {// Need access token!
			$this->getToken();
			return $this->launch($action, $params, $method);
		}
	}

	function makeCURL($action, $params = null, $method = 'GET') {
		//TBD
// falta user_token i auth_token
$this->debug("makeCURL $action $method \n");
		$action = $action . '&auth_token=' . $this->auth_token;
		$action = $action . '&user_token=' . $this->user_token;

		$curl = curl_init();
		if ($method != 'GET') {
			$this->method = $method;
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		}
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $this->api_base . $action,
			CURLOPT_USERAGENT => 'Standard cURL Request'
		));

		$header = array();
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

		$resp = curl_exec($curl);
		//echo $resp;
		curl_close($curl);
		$json_array = json_decode($resp, true);
		return $json_array;
	}

}
