<?php
/*
 * 抓取数据 格式化函数
 */

/*
 *  从字符串中解析出游天数和酒店入住天数
 */
function travelTimeFormat($str)
{
	$ret = array(
			'days'       => 0,
			'nights' => 0,
	);
	
	$regA = "/[^\d]*(\d+)[晚夜]([\d\/-]+)[日天].*/iu";
	$isMatchA = preg_match($regA, $str, $matches);
	if($isMatchA) {
		if(empty($ret['nights'])) {
			$ret['nights'] = (int) $matches[1];
		}
		if(empty($ret['days'])) {
			$ret['days'] = (int) $matches[2];
		}
	}
	
	if(empty($ret['nights']) || empty($ret['days'])) {
		$regB = "/[^\d]*([\d\/-]+)[日天](\d+)[晚夜].*/iu";
		$isMatchB = preg_match($regB, $str, $matches);
		if($isMatchB) {
			if(empty($ret['days'])) {
				$ret['days'] = (int) $matches[1];
			}
			if(empty($ret['nights'])) {
				$ret['nights'] = (int) $matches[2];
			}
		}
	}
	
	return $ret;
}

function phoneFormat($phoneText)
{
	$phoneText = str_replace('转', ',', $phoneText);
	$phoneText = str_replace('-', '', $phoneText);
	$phoneText = str_replace(' ', '', $phoneText);
	
	return $phoneText;
}

function _getDOM($url, $opts = []) {
	$options  = array(
			'http' => array(
					'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36',
					'header' => 'X-Forwarded-For: '. (!empty($opts['ip']) ? $opts['ip'] : '192.168.10.3'), // 伪造ip
					'Referer' => !empty($opts['referer']) ? $opts['referer'] : $url,
			)
	);
	$context  = stream_context_create($options);
	$dom = @file_get_html($url, false, $context);

	return $dom;
}

function _getText($url, $opts = []) {
	$options  = array(
			'http' => array(
					'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36',
					'header' => 'X-Forwarded-For: '. (!empty($opts['ip']) ? $opts['ip'] : '192.168.10.3'), // 伪造ip
					'Referer' => !empty($opts['referer']) ? $opts['referer'] : $url,
			)
	);
	
	$context  = stream_context_create($options);
	$dom = @file_get_contents($url, false, $context);

	return $dom;
}

function request($url, $method, $params = [], $opts = []) {
	$ch = curl_init();
	if($method == 'get') {
		if(!empty($params)) {
			$str = '?';
			foreach($params as $k=>$v) {
				$str .= $k . '=' . $v . '&';
			}
			$str = substr($str, 0, -1);
			$url .= $str;
		}
		curl_setopt($ch, CURLOPT_HTTPGET, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
	} else if($method == 'post') {
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	}

	$headers = [];
	
	$headers[] = 'X-Forwarded-For: '. (!empty($opts['ip']) ? $opts['ip'] : '192.168.10.3');
	$headers[] = !empty($opts['referer']) ? $opts['referer'] : $url;
	
	if(!empty($opts['timeout'])) {
		$timeout = (int) $opts['timeout'];
	} else {
		$timeout = 10;
	}
	
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$data = curl_exec($ch);
	curl_close($ch);

	return $data;
}

