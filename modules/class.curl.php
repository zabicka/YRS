<?php
/*
Sean Huber CURL library

This library is a basic implementation of CURL capabilities.
It works in most modern versions of IE and FF.

==================================== USAGE ====================================
It exports the CURL object globally, so set a callback with setCallback($func).
(Use setCallback(array('class_name', 'func_name')) to set a callback as a func
that lies within a different class)
Then use one of the CURL request methods:

get($url);
post($url, $vars); vars is a urlencoded string in query string format.

Your callback function will then be called with 1 argument, the response text.
If a callback is not defined, your request will return the response text.
*/
class CURL {
	var $callback = false;

	function setCallback($func_name) {
		$this->callback = $func_name;
	}

	function doRequest($method, $url, $vars) {
		// zapnuti curl
		$ch = curl_init();

		// odeslani adresy
		curl_setopt($ch, CURLOPT_URL, $url);
		// hlavicka
		curl_setopt($ch, CURLOPT_HEADER, 1);
		// nastaveni identifikace agenta (prohlizece)
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		// zda se ma nasledovat pozice
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		// co ja vim
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		// ukladani cookies
		if(is_writable(DEFAULT_PATH.'cookie.txt')) {
			curl_setopt($ch, CURLOPT_COOKIEJAR, DEFAULT_PATH.'cookie.txt');
			curl_setopt($ch, CURLOPT_COOKIEFILE, DEFAULT_PATH.'cookie.txt');
		}

		// pokud se odesilaji promenne post
		if ($method == 'POST') {
			// zapnuti
			curl_setopt($ch, CURLOPT_POST, 1);
			// pridani promennych
			curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
		}

		// ziskani dat
		$data = curl_exec($ch);

		// uzavreni spojeni
		curl_close($ch);

		// kontrola, zda data nejsou prazdna
		if ($data) {
			// ? asi zavolani nejake nastavene funkce
			if ($this->callback)
			{
				$callback = $this->callback;
				$this->callback = false;
				return call_user_func($callback, $data);
			} else {
				// vraceni dat
				return $data;
			}
		} else {
			// vraceni chybove hlasky
			return curl_error($ch);
		}
	}

	function get($url) {
		return $this->doRequest('GET', $url, 'NULL');
	}

	function post($url, $vars) {
		return $this->doRequest('POST', $url, $vars);
	}

	function removeHeader($data) {
		$data = strstr($data, "\r\n\r\n");
		$data = substr($data, 4, strlen($data) - 4);

		return $data;
	}
}
?>
