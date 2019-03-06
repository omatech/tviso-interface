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
	var $cache_key_prefix = '';
	var $calendar='';

	function getToken() {
		$response_array = $this->makeCURL('/auth_token?id_api=' . $this->id_api . '&secret=' . $this->secret);
		$auth_return_code = $response_array['error'];
		if ($auth_return_code == 0) {
			$this->auth_token = $response_array['auth_token'];
			$memcache_key = $this->cache_key_prefix . ":tviso-token";
			$this->setCache($memcache_key, $this->auth_token);
			return $response_array['auth_token'];
		} else {
			$this->error = 'Auth error ' . print_r($response_array, true);
			return false;
		}
	}

	function getCalendar($date = null) {
		if ($date == null) {
			$date = date("Y-m-d");
		}
		$this->calendar=$this->launch('/schedule/ES/calendar/' . $date . '/summary?mediaType=2&youtubeFallback=true&country=ES');
		return $this->calendar;
	}

	function launch($action, $params = null, $method = 'GET') {
		$this->debug('LAUNCH:::' . $action . "\n");
		$insert_in_cache = false;
		if ($this->mc !== null) {
			$memcache_key = $this->cache_key_prefix . ":array:$method:$action:";
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
		if ($return_code == 0) {
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
		if ($this->auth_token=='')
		{
			$memcache_key = $this->cache_key_prefix . ":tviso-token";
			$this->auth_token = $this->mc->get($memcache_key);
		}
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

	function getTimeInfo($programa) {
		/*
		  Calcularem la mida del bloc de programa en percentatges segons la duraciò del mateix.
		  Per exemple: Duraciò del programa 135mins

		  320 / 60 * 135 = 720

		  320 > Amplada en px de una hora
		  60 > Minuts que té una hora
		  135 > Duraciò del programa

		  Això ens dona 720px

		  Com volem que sigui responsive, tenim que pasar aquesta mida a un percentatge

		  720 / 7680 * 100 = 9.375%

		  720px > Tamany del programa
		  7680px > Tamany total del día en px (1440minuts)
		  100 > Per calcular el %

		  La propietat width serà de 9.375%

		  Nota: Per al primer element de cada día, calcularem el un margin-left fent servir la mateixa fòrmula. Serà espai que quedarà buit d'un programa del día anterior.
		 */

		$start = $programa['start'];
		$end = $programa['stop'];
		$unix_start = strtotime($start);
		$unix_end = strtotime($end);
		$duracion_minutos = ($unix_end - $unix_start) / 60;
		$result = [];
		$result['duracion_minutos'] = $duracion_minutos;
		$result['hora_inicio'] = date('H:i', $unix_start);
		$result['hora_final'] = date('H:i', $unix_end);

		$factor = 320 / 60;
		$mida_pixels = $duracion_minutos * $factor;

		$this->debug("start=$start end=$end unix_start=$unix_start unix_end=$unix_end duracion_minutos=$duracion_minutos mida_pixels=$mida_pixels\n");
		$pct_dia = round(($mida_pixels / 7680) * 100, 4);
		$result['pct_dia'] = $pct_dia;
		return $result;
	}

	function getResumInfo($programa) {
		$result = [];
		if (isset($programa['media']['plot'])) {
			$result['resum'] = $programa['media']['plot'];
		} else {
			$result['resum'] = '';
		}
		return $result;
	}

	function dayArray2Html($array) 
	{
		$html = '';
		foreach ($array as $channel_name => $channel) {


			if ($channel) {
				$html .= '<li class="fila-canal" id="' . $channel_name . '">
';
				$program_index = 0;
				foreach ($channel as $programa) {
					//print_r($programa);die;
					$time_info = $this->getTimeInfo($programa);
					$resum_info = $this->getResumInfo($programa);
					$categoria = 'SIN CATEGORÍA';
					if (isset($programa['c'])) {
						$categoria = $programa['c'];
					}

					$margin_program = '';
					if ($program_index == 0) {
						$margin_program = ' margin-left: 0.347%;';
					}

//$html .= '  <!-- FILA CANAL -->';
//$html.='<!-- .programa item -->';
					$html .= '
    <div class="programa-item" style="width: ' . $time_info['pct_dia'] . '%;' . $margin_program . '">
      <div class="programa-info">
        <span class="categoria">' . $categoria . '</span>
        <h3 class="titulo-programa"><a href="">' . $programa['tit'] . '</a></h3>
        <p>' . $resum_info['resum'] . '</p>
        <ul class="meta">
          <li class="duracion">' . $time_info['duracion_minutos'] . ' minutos</li>
          <li class="horario">' . $time_info['hora_inicio'] . '-' . $time_info['hora_final'] . '</li>
        </ul>
      </div>
    </div>
		';
//$html.='<!-- .programa item -->';

					$program_index++;
				}
				$html .= '
  </li>
	';
			}
//$html.='<!-- .FILA CANAL -->';
		}
		return $html;
	}

}
