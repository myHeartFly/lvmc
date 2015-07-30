<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Grabyodobashi extends CI_Controller {
	private $source = 'byodobashi';
	private $codePrefix = 'byodobashi';
	private $start;
	private $end;
	
	public function __construct() {
		parent::__construct();
		
		date_default_timezone_set('Asia/Shanghai');
		$this->load->library('dataprocess/dataprocess');
		$this->load->helper('grabformat');
	}
	
	public function index()
	{
		$this->load->library('city/city');
		$this->load->helper('htmldom');
		
		echo 'grab source data start:'.date("Y-m-d H:i:s")."\n";
		
		$startGrabTime = new MongoDate();		
		
				// http://www.lailaihui.com/Search?startDateBegin=&startDateEnd=&priceBegin=&priceEnd=&GoCity=bj_tj&target=pujidao&price=all&month=all&type=6&SortBy=1&orderColum=&KeyWord=&StartEndDate=&ntype=2
				$entryURL = "http://www.yodobashi.com/ec/store/list/index.html";
				$dom = $this->_getDOM($entryURL);
				$stores;
				$trHead=array(0 => '住所',1 => '最寄駅',2 => '営業時間',3 => '電話番号');
				$storelist = $dom->find('.mainCol .container');
					if(empty($storelist)) {
						echo "wrong";
					}
					foreach($storelist as $item){
						$j=0;
						$store;
						$store['name']=$item->find('h2 a',0);						
						$table=$item->find('.supportBlock table tbody',0);
						$trLeft=$table->find('tr th strong');						
						$trRight=$table->find('tr td');
						for($i=0;$i<count($trLeft);$i++){							
							$indexNum=array_search($trLeft[$i]->plaintext, $trHead);													
							switch ($indexNum) {
								case 0:
									# code...
									$store['position']=$trRight[$i];
									break;
								case 1:
									# code...
									$store['positionDescription']=$trRight[$i];
									break;
								case 2:
									# code...
									$store['shopHoures']=$trRight[$i];
									break;	
							    case 3:
							    	# code...
							    	$store['phone']=$trRight[$i];
							    	break;
								default:
									# code...
									break;
							}
						}
						// echo $store['name'];
						$stores[$j]=$store;
						$j++;
						foreach ($store as $key => $value) {
							# code...
							echo $key."=>".$value;
						}
					}			

		
	}

	private function _getDOM($url, $referer = '') {
		$options  = array(
			'http' => array(
				'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36', 
				'header' => 'X-Forwarded-For: 172.168.10.2', // 伪造ip
				'Referer' => $referer
			)
		);
		
		$context  = stream_context_create($options);
		// $dom = file_get_html('http://www.lailaihui.com/Search?GoCity=bj_tj&target=pujidao&price=all&month=all&type=all', false, $context);
		$dom = @file_get_html($url, false, $context);

		return $dom;
	}
	
	private function _grabdetail($url, $referer) {
		mb_internal_encoding("UTF-8");
		
		$dom = _getDOM($url, ['referer' => $referer]);
		
		if(empty($dom)) {
			return FALSE;
		}
		
		if(!empty($dom->find('.wrap .noreslut', 0))) {
			return FALSE; // 产品找不到
		}
		
		$intro = $dom->find('.productDetail .product_intro', 0);
		
		if(!empty($dom->find('#bookButton', 0))) {
			$bookButtonText = trim($dom->find('#bookButton', 0)->innertext);
			
			if($bookButtonText != '立即预订') {
				echo 'the button is not booking btn'.PHP_EOL;
				echo $url.PHP_EOL;
			}
		
			// http://img.lailaihui.com/ad/stop_sale_btn2.png
			$reg = "/.*http:\/\/img.lailaihui.com\/ad\/stop_sale_btn2\.png.*/iu";
			$isMatch = preg_match($reg, trim($bookButtonText), $matches);
			if ($isMatch) {//已售罄产品不处理
				return FALSE;
			}
		}
		
		$roomTypeDoms = $intro->find('.line .roomType a');
		
		if(empty($roomTypeDoms)) {
			echo date("Y-m-d H:i:s")." product: {$url} no room types return!". PHP_EOL; // debug, chenlei
			return FALSE;
		}
		
		foreach($roomTypeDoms as $rt) {
			$path = $rt->href;
			if(empty($path)) {
				continue;
			}
			if(strpos($path, 'http://') === FALSE) {
				$detailurl = "http://www.lailaihui.com".$path;
				$code = substr($path, strpos($path, '/') + 1);
				
				$this->_grabPackage($code, $detailurl, $url);
			}
		}
	}
	
	private function _grabPackage($code, $url, $referer) { // 抓取单个套餐
		
		$dom = $this->_getDOM($url, $referer);
		mb_internal_encoding("UTF-8");	
		
		if(empty($dom)) {
			return FALSE;
		}
		
		if(!empty($dom->find('.wrap .noreslut', 0))) {
			return FALSE; // 产品找不到
		}
		
		$intro = $dom->find('.productDetail .product_intro', 0);

		if(!empty($dom->find('#bookButton', 0))) {
			$bookButtonText = $dom->find('#bookButton', 0)->innertext;
				
			// http://img.lailaihui.com/ad/stop_sale_btn2.png
			$reg = "/.*http:\/\/img.lailaihui.com\/ad\/stop_sale_btn2\.png.*/iu";
			$isMatch = preg_match($reg, trim($bookButtonText), $matches);
			if ($isMatch) {//已售罄产品不处理
				$tmpCode = $this->codePrefix.$code;
				$this->dataprocess->setProductStatus($tmpCode, 'stop');
				$this->cimongo->srcdb->duoqusourcedatas->remove(['code' => $tmpCode], ['justOne' => TRUE]);
				echo date('Y-m-d H:i:s')." stop products {$tmpCode}".PHP_EOL;
				
				return FALSE;
			}
		}
		
		$mealDom = $intro->find('.line #MealID', 0);
		
		if(empty($mealDom)) {
			return FALSE;
		}
		
		$mealId = trim($mealDom->value);
		
		$prod = new stdClass();
		$prod->source = $this->source;
		$prod->sourceurl = $url;
		$prod->name = trim($intro->find('h1', 0)->plaintext);
		
		if(mb_strpos($prod->name, '跟团游', 0, 'UTF8') !== FALSE) {
			return FALSE;
		}

		$dds = $intro->find('dl dd.w_126');
		foreach ($intro->find('dl dt.w_70') as $index=>$dt) {
			$dttext = $dt->innertext;
			switch ($dttext) {
				case '产品编号':
					$routeCode = trim($dds[$index]->plaintext);
					
					break;
				case '确认类型':
					$type = trim($dds[$index]->plaintext);
					$prod->needConfirm = $type != '一次确认';
					break;
				
				case '出发地':
					$prod->start = explode(',', trim($dds[$index]->plaintext));
					break;
				
				case '目的地':
					if(!empty($dds[$index]->plaintext)) {
						$prod->end = explode(',', trim($dds[$index]->plaintext));
					}
					break;
				
				case '行程天数':
					$prod->days = floatval(trim(str_replace('天', '', $dds[$index]->plaintext)));
					break;
				
				case '供应商':
					$prod->vender = trim($dds[$index]->plaintext);
					break;
				default:
					# code...
					break;
			}
		}
		
		if($routeCode.'_'.$mealId != $code) {
			$curCode = $routeCode.'_'.$mealId;
			echo date("Y-m-d H:i:s")." product: {$curCode} no room types equal {$code} return!". PHP_EOL; // debug, chenlei
			return FALSE;
		}
		
		$prod->code = $this->codePrefix.$code;
		
		$currentPackage = $intro->find('tt#more_packages', 0);
		if(!empty($currentPackage)) {
			$packageStr = trim($currentPackage->plaintext);
			$travelTime = travelTimeFormat($packageStr);
			
			if(empty($prod->days) && !empty($travelTime['days'])) {
				$prod->days = floatval($travelTime['days']);
			}
			if(empty($prod->hotelNight) && !empty($travelTime['nights'])) {
				$prod->hotelNight = floatval($travelTime['nights']);
			}
		}
		
		if(!empty($prod->name)) {
			$travelTime = travelTimeFormat($prod->name);
			if(empty($prod->days) && !empty($travelTime['days'])) {
				$prod->days = floatval($travelTime['days']);
			}
			if(empty($prod->hotelNight) && !empty($travelTime['nights'])) {
				$prod->hotelNight = floatval($travelTime['nights']);
			}
		}
		if(empty($prod->code)) {
			return false;
		}
		
		echo "grab lailaihui product: {$prod->code} ".date("Y-m-d H:i:s")."\n"; // debug, chenlei
		
		$phoneDom = $dom->find('.laiNav .tel', 0);
		if(!empty($phoneDom)) {
			$phoneText = trim($phoneDom->plaintext);
			$phoneText = str_replace('TEL.', '', $phoneText);
			$prod->servicephone = phoneFormat($phoneText);
		}
		
		// 价格日历
		$prices = [];
		$trave_date = $dom->find('.productDetail .img_date .trave_date', 0);
		foreach ($trave_date->find('.dateCon table td[price]') as $td) {
			$td->rel = trim($td->rel);
			$yuwei = floatval(trim(str_replace('余位：', '', $td->find('.yw', 0)->innertext)));
			$price = floatval($td->price);
			if(empty($yuwei) || empty($price)) {
				continue;
			}
			$prices[$td->rel] = array('yuwei' => $yuwei, 'price'=> $price);
		}	
		
		if(empty($prices)) { // 没有价格日历
			return FALSE;
		}
		
		$prod->prices = (object) $prices;
		
		// 出发地及目的地
		empty($prod->start) AND $prod->start = [$this->start];
		empty($prod->end) AND $prod->end = [$this->end];
		
		empty($prod->vender) AND $prod->vender = '来来会';
		$recommendDom = $intro->find('.line .light_info .con', 0);
		if(!empty($recommendDom)) {
			$recommendText = $recommendDom->innertext;
			$recommendText = str_replace('<br />', '<br>', $recommendText);
			$recommendText = str_replace('<br/>', '<br>', $recommendText);
			$recommendText = str_replace('<br>', "\n", $recommendText);
			$recommendText = preg_replace('/<div(\s)*class="lineMore"(.*)<\/div>/im', '', $recommendText);
			$recommendText = preg_replace('/(<style(.*)<\/style>)/mu', '', $recommendText);
			
			$prod->recommend = trim(strip_tags($recommendText));
		}
		
		$lineDetail = $dom->find('div.lineDetail', 0);
		
		if(empty($lineDetail)) {
			echo '__ERROR__: empty lineDetail url: '.$url.PHP_EOL;
			return FALSE;
		}
		
		// 航班信息
		$groupFlights = []; // 组航班
		$direction = 'depart';
		if(!empty($lineDetail->find('div.jtCon td.n_borderR', 0))) {
			foreach ($lineDetail->find('div.jtCon td.n_borderR', 0)->find('table') as $table) { // 暂时只取第一组航班
				$day = trim($table->find('td.w2', 0)->plaintext);
				$airline = explode('/', $table->find('li.w4', 0)->innertext);
				$flightNo = trim($airline[1]);
				$flightNo = str_replace(' ', '', $flightNo);
				$flightNo = trim(str_replace("\n", '', $flightNo));
				
				$reg = "/([A-Za-z0-9]{2,})$/i";
				if(preg_match($reg, $flightNo, $flightMatches)) {
					$flightNo = $flightMatches[1];
				} else {
					continue;
				}
				$segmetStr = trim($table->find('li.w3', 0)->innertext);
				$directions = explode('到', $segmetStr);
				$flight = array(
						'takeoffCity' => trim($directions[0]),
						'landingCity' => trim($directions[1]),
						'airline'     => trim($airline[0]),
						'flightNo'    => $flightNo,
						'model'       => trim(str_replace('&nbsp;', '', $table->find('li.w5', 0)->plaintext)),
						'takeoff'     => str_replace('起飞', '', strip_tags($table->find('li.w6', 0)->innertext)),
						'landing'     => str_replace('抵达', '', strip_tags($table->find('li.w7', 0)->innertext)),
				);
				
				$day = preg_replace('/[第天]/u', '', $day);
				$dayMap = [1 => '一', 2 => '二',  3 => '三',  4 => '四', 5 => '五', 6 => '六', 7 => '七', 8 => '八', 9 => '九', 10 => '十'];
				$dayStr = '';
				for($i=0; $i< mb_strlen($day); $i++) {
					$astr = mb_substr($day, $i, 1);
					$reverseDayMap = array_flip($dayMap);
					if(!empty($reverseDayMap[$astr])) {
						$dayStr .= $reverseDayMap[$astr];
					}
				}
				$dayStr = floatval($dayStr);
				if(!empty($dayStr)) {
					$flight['day'] = floatval($dayStr);
				}
				
				$groupFlights[] = $flight;
			}
		}

		$prod->flights = $groupFlights;
		
		// 酒店信息
		$hotels = [];
		
		foreach ($lineDetail->find('div.hotels div.hotel_Con') as $r) {
			$hotel = array();
			$hotelName = trim($r->find('.title h4', 0)->plaintext);
			$hotelName = html_entity_decode($hotelName, ENT_QUOTES);
			$hotel['name'] = $hotelName;
			$star = strlen(trim($r->find('.title .star', 0)->plaintext))/3;
			$hotel['star'] = $star != 0 ? "{$star}" : '0';
			$hotel['address'] = trim(strip_tags($r->find('.hotel_intro dl dt', 0)->plaintext));
			$hotel['descothers'] = trim(strip_tags($r->find('.hotel_intro dl dd', 0)->plaintext));
			
			$hotels[] = $hotel;
		}
		
		$prod->hotels = $hotels;
		
		if(empty($prod->hotels) && empty($prod->flights)) {
			return FALSE;
		}
		
		if(!empty($lineDetail->find('.fyCon'))) { // 费用
			$feiyong = trim($lineDetail->find('.fyCon', 0)->innertext);
			$prod->costInstruction = $feiyong;
		}
		
		if(!empty($lineDetail->find('.visaCon'))) { // 签证
			$visaCon = trim($lineDetail->find('.visaCon', 0)->innertext);
			$prod->visa = $visaCon;
		}
		
		if(!empty($lineDetail->find('.ydCon'))) { // 预订
			$yudingCon = trim($lineDetail->find('.ydCon', 0)->innertext);
			$prod->booking = $yudingCon;
		}
		
		$prod->sourcestatus = 'normal';
		$prod->type = ['自由行'];
		$prod->status = 'stop'; // 设置停售状态，信息补充完成可改为正常状态
		
		if(empty($prod->hotels) && empty($prod->flights)) {
			return FALSE;
		}
			
		$queryProduct = $this->cimongo->srcdb->duoqusourcedatas->findOne(['code'=>$prod->code]);
		if(empty($queryProduct)) {
			$prod->created = new MongoDate();
		}
		$prod->grabtime = new MongoDate();
		$prod->updated = new MongoDate();
		
		$this->cimongo->srcdb->duoqusourcedatas->findAndModify(array('code'=>$prod->code), array('$set'=>(array)$prod), null, array('upsert'=>true));
			
// 		$this->dataprocess->productCode($prod->code, TRUE); // debug, chenlei
		$this->dataprocess->productCode($prod->code, FALSE); //@TODO remove. //debug, chenlei byford暂时不让入库插入新数据
		return TRUE;
	}
	
	public function import() // 导入数据
	{
		$condition = [];
		$condition['source'] = $this->source;
	
		$this->dataprocess->products($condition, FALSE);
	}
	

	public function grabById($package_id) // debug, chenlei
	{
// 		header('Content-Type: application/json'); // debug, chenlei
	
		$this->load->helper('htmldom');
		$url = "http://www.lailaihui.com/{$package_id}";
		
		$this->_grabPackage($package_id, $url, $url);
	}
	
	private function stopProducts($startGrabTime)
	{
		$count = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = [];
		$condition['grabtime']  = ['$lt' => $startGrabTime];
		$condition['source'] = $this->source;
		$condition['sourcestatus'] = 'normal';
		$total = $this->cimongo->srcdb->duoqusourcedatas->count($condition);
		
		$codesCount = 0;
		$totalCount = ceil($total/$max);
		
		$i = 0;
		$startCreatedTime = NULL;
		while($i < $totalCount) {
			echo 'offset: '.($i * $max).PHP_EOL;
		
			if(!empty($startCreatedTime)) {
				$condition['created'] = ['$gt' => $startCreatedTime];
			}
		
			$results = $this->cimongo->srcdb->duoqusourcedatas->find($condition, ['hotels' => 0, 'flights' => 0])->sort(['created' => 1])->limit($max);
			$srcProducts = iterator_to_array($results);
		
			foreach($srcProducts as $prod) {
				echo 'deal product code: '.$prod['code'].PHP_EOL;
				$result = $this->stopProduct($prod);
				if($result == TRUE) {
					$count++;
				}
		
				$codesCount++;
				if(!empty($prod['created'])) {
					$startCreatedTime = $prod['created'];
				}
			}
		
			$i++;
		}
		
		echo date('Y-m-d H:i:s').' update report : codes count: '.$codesCount. ' count products count: '.$total.PHP_EOL;
		echo date('Y-m-d H:i:s').' stop products success total: '.$count.PHP_EOL;
		
		return;
	}
	
	private function stopProduct($prod)
	{
		mb_internal_encoding("UTF-8");
		$this->load->helper('htmldom');
		
		$prices = [];
		$code = $prod['code']; // codePrefix + orgCode
		$orgCode = substr($code, strlen($this->codePrefix));
		$url = $prod['sourceurl'];
		if(empty($url)) return FALSE;
		
		$this->load->library('grab/netutil');
		$ip = $this->netutil->getIp();
		$params = ['ip' => $ip, 'referer' => $url];
		
		$dom = _getDOM($url, $params);
		if(empty($dom)) return FALSE;
		
		$status = 'normal';
		
		if(!empty($dom->find('.wrap .noreslut', 0))) {
			$status = 'stop';
		}
		
		if(!empty($dom->find('#bookButton', 0))) { // debug, chenlei
			$bookButtonText = trim($dom->find('#bookButton', 0)->innertext);
				
			// http://img.lailaihui.com/ad/stop_sale_btn2.png
			$reg = "/.*http:\/\/img.lailaihui.com\/ad\/stop_sale_btn2\.png.*/iu";
			$isMatch = preg_match($reg, trim($bookButtonText), $matches);
			if ($isMatch) {//已售罄产品不处理
				$status = 'stop';
			}
		}
		
		if(strpos($orgCode, '_') !== FALSE && !empty($dom->find('.line #MealID', 0))) {
			$routeId = trim($dom->find('#freelineId', 0)->value);
			$mealDom = $dom->find('.line #MealID', 0);
			$mealId = trim($mealDom->value);
				
			if($routeId.'_'.$mealId != $orgCode) {
				$status = 'stop';
			}
		}
		
		if($status == 'stop') {
			$this->dataprocess->setProductStatus($code, $status);
			$this->cimongo->srcdb->duoqusourcedatas->remove(['code' => $code], ['justOne' => TRUE]);
			echo date('Y-m-d H:i:s')." stop products {$code}".PHP_EOL;
			
			return TRUE;
		}
		
		return FALSE;
	}
	
}