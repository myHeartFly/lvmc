<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Grabreqiqiu extends CI_Controller {
	private $source = '热气球';
	private $codePrefix = 'RQ';
	private $category;
	private $token; // 访问令牌
	private $expireTimeStamp; // 令牌失效时间戳
	private $host;
	
	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Shanghai');
		
		$this->load->library('dataprocess/dataprocess');
		$this->load->helper('grabformat');
		
// 		$this->host = "http://devapi.rqqtrip.com"; // 测试服务器
		$this->host = "http://api.rqqtrip.com"; // 正式服务器
		
		date_default_timezone_set('Asia/Shanghai');
	}
	
	// 获取令牌
	private function getToken()
	{
	
		if(!empty($this->expireTimeStamp) && $this->expireTimeStamp > time() + 60 * 5) {
			return $this->token;
		} else {
			$params = array(
// 					'appid' => 'rqq2b7680e6e1b003', // 测试
// 					'secret' => '65e7d25e64317d05115d55942638cddd',// 测试
					'signkey' => 'OsHSNvsq', // 签名
					'appid' => 'rqq2f5ef2f58a9f03',
					'secret' => '9a2289ca4d29eb2a08d74a9bba372854',
			);
				
			$url = "{$this->host}/token";
			$time = time();
			$response = request($url, 'get', $params);
				
			if(empty($response)) {
				$response = request($url, 'get', $params);
			}
				
			if(empty($response)) {
				return FALSE;
			}
				
			$data = null;
			try {
				$data = json_decode($response, true);
			} catch (Exception $e) {
				echo $e->getMessage().PHP_EOL;
				return FALSE;
			}
				
			if(!empty($data['result']) && isset($data['code']) && $data['code'] == '00' && $data['result'] == "success") {
				$this->token = $data['data']['token'];
				$this->expireTimeStamp = $time + $data['data']['expire'];
	
				return $this->token;
			} else {
				print_r($data);
			}
				
			return FALSE;
		}
	
	}
	
	public function index()
	{
// 		header('Content-Type: application/json'); // debug, chenlei
		
		echo date('Y-m-d').' grab reqiqiu product start!!!'.PHP_EOL;
		
		$startGrabTime = new MongoDate();
		$token = $this->getToken();
		
		if(empty($token)) {
			echo date('Y-m-d H:i:s').' Token get fail'.PHP_EOL;
			return;
		}
		
		$url = "{$this->host}/products?token={$token}";
		
		$response = request($url, 'get');
		
		if(empty($response)) {
			echo date('Y-m-d H:i:s').' Get product list fail'.PHP_EOL;
			return;
		}
		
		$data = null;
		try {
			$data = json_decode($response, true);
		} catch (Exception $e) {
			echo $e->getMessage().PHP_EOL;
			return FALSE;
		}
			
		if(!empty($data['result']) && isset($data['code']) && $data['code'] == '00' && $data['result'] == "success") {
			$tours = $data['data']['tour'];
			
			if(!empty($tours)) {
				
				foreach($tours as $item) {
					$this->_grabDetail($item['id']);
				}
				
				$this->stopProducts($startGrabTime); // 下架产品
			}
			
		} else {
			echo date('Y-m-d H:i:s').' Get product list parse json data fail'.PHP_EOL;
			
			if(isset($data['code'])) {
				echo date('Y-m-d H:i:s').' Get product list fail code:'. $data['code'].PHP_EOL;
			}
			return;
		}
		
		echo date('Y-m-d').' grab reqiqiu product end!!!'.PHP_EOL;
	}
	
	private function _grabDetail($item_id)
	{
		$token = $this->getToken();
		
		if(empty($token)) {
			echo date('Y-m-d H:i:s').' Token get fail'.PHP_EOL;
			return FALSE;
		}
		
		$url = "{$this->host}/products/{$item_id}?token={$token}";
		
		$response = request($url, 'get');
		
		if(empty($response)) {
			echo date('Y-m-d H:i:s').' Get product '. $item_id .' detail fail'.PHP_EOL;
			return FALSE;
		}
		
		$data = null;
		try {
			$data = json_decode($response, true);
		} catch (Exception $e) {
			echo $e->getMessage().PHP_EOL;
			return FALSE;
		}
		
		if(!empty($data['result']) && isset($data['code']) && $data['code'] == '00' && $data['result'] == "success") {

			$data = $data['data'];
		
			if($data['summary']['type'] != 'tour') { // 只获取线路产品
				return FALSE;
			}
		
			if($data['summary']['teamType'] != 0) { // 只获取自由行产品
				return FALSE;
			}
			
			if(!in_array($data['summary']['goodsType'], [1, 2, 3])) { // 1.机票 2.酒店 3.机+酒
				return FALSE;
			}
			
			if(empty($data['summary']['tourNumber'])) {
				return FALSE;
			}
			
			$tourNumber =trim($data['summary']['tourNumber']);
				
			$prod = new stdClass();
			$prod->type = ['自由行'];
			$prod->code = $this->codePrefix.$tourNumber;
			
			echo "grab reqiqiu product: {$prod->code} item id: {$item_id} ".date("Y-m-d H:i:s")."\n";
			
			if(empty($data['arrangementGroups'])) {
				$condition = ['source' => $this->source, 'code' => $prod->code];
				$querySource = $this->cimongo->srcdb->duoqusourcedatas->findOne($condition);
				
				if(!empty($querySource)) {
					$this->dataprocess->setProductStatus($querySource['code'], 'stop');
					$this->cimongo->srcdb->duoqusourcedatas->remove(['code' => $querySource['code']], ['justOne' => TRUE]);
					echo date('Y-m-d H:i:s')." api return error and  stop products {$querySource['code']}".PHP_EOL;
				}
				
				echo 'api return empty arrangementGroups'.PHP_EOL;
				return FALSE;
			}
			
			$prod->source = $this->source;
			$prod->sourceurl = $data['summary']['url'];
			empty($prod->vender) AND $prod->vender = $this->source;
			$prod->needConfirm = $data['summary']['paymentType'] == 1 ? FALSE : TRUE;
			
			$prod->name = $data['summary']['title'];
			$prod->servicephone = '4006586800';
			if(!empty($data['summary']['recommed'])) {
				$prod->recommend = trim($data['summary']['recommed']);
			}
			
			if(!empty($package['days'])) {
				$prod->days = floatval($data['summary']['days']);
			}
			if(!empty($package['nights'])) {
				$prod->hotelNight = floatval($data['summary']['nights']);
			}
			
			if((empty($prod->days) || empty($prod->nights))) {
				$travelTime = travelTimeFormat($prod->name);
				if(empty($prod->days) && !empty($travelTime['days'])) {
					$prod->days = floatval($travelTime['days']);
				}
				if(empty($prod->hotelNight) && !empty($travelTime['nights'])) {
					$prod->hotelNight = floatval($travelTime['nights']);
				}
			}
			
			// 出发地 目的地
			$prod->start = [];
			$prod->end = [];
			
			if(!empty($data['summary']['departures'])) {
				foreach($data['summary']['departures'] as $item) {
					$prod->start[] = trim($item['name']);
				}
			}
			if(!empty($data['summary']['destinations'])) {
				foreach($data['summary']['destinations'] as $item) {
					$prod->end[] = trim($item['name']);
				}
			}
			
			// 价格日历
			$prices = [];
			
			$prices = $this->dealPrices($data['arrangementGroups']);
			
			if(empty($prices)) {
				echo date('Y-m-d').' grab product code: '.$prod->code. ' item id:'. $item_id .' no prices failed'.PHP_EOL;
				return FALSE;
			}
			
			$prod->prices = (object) $prices;
			
			// 航班
			$groupFlights = [];
			
			if(!empty($data['flights'])) {
				
				if(!empty($data['flights'][0]['out'])) {
					foreach($data['flights'][0]['out'] as $item) {
						$flight = array(
								'flightNo' => strtoupper(trim($item['flightNum'])),
								'takeoff'  => trim($item['startTime']),
								'landing'  => trim($item['endTime']),
						);
						if(empty($flight['flightNo'])) {
							continue;
						}
						if(!empty($item['startLocation'])) {
							$flight['takeoffCity'] = trim($item['startLocation']['name']);
						}
						if(!empty($item['endLocation'])) {
							$flight['landingCity'] = trim($item['endLocation']['name']);
						}
						if(!empty($item['startAirport'])) {
							$flight['takeoffAirport'] = trim($item['startAirport']['name']);
						}
						if(!empty($item['endAirport'])) {
							$flight['landingAirport'] = trim($item['endAirport']['name']);
						}
						
						$flight['trip'] = 'depart';
						$groupFlights[] = $flight;
					}
				}
				
				if(!empty($data['flights'][0]['in'])) {
					foreach($data['flights'][0]['in'] as $item) {
						$flight = array(
								'flightNo' => strtoupper(trim($item['flightNum'])),
								'takeoff'  => trim($item['startTime']),
								'landing'  => trim($item['endTime']),
						);
						if(!empty($item['startLocation'])) {
							$flight['takeoffCity'] = trim($item['startLocation']['name']);
						}
						if(!empty($item['endLocation'])) {
							$flight['landingCity'] = trim($item['endLocation']['name']);
						}
						if(!empty($item['startAirport'])) {
							$flight['takeoffAirport'] = trim($item['startAirport']['name']);
						}
						if(!empty($item['endAirport'])) {
							$flight['landingAirport'] = trim($item['endAirport']['name']);
						}
				
						$flight['trip'] = 'back';
						$groupFlights[] = $flight;
					}
				}
			}
			
			$prod->flights = $groupFlights;
			
			// 酒店信息
			$hotels = [];
			
			if(!empty($data['hotel']) && !empty($data['hotel']['base'])) {
				foreach($data['hotel']['base'] as $item) {
					$hotel = array(
							'name' => trim($item['name']),
					);
					if(!empty($item['star'])) {
						$hotel['star'] = (string) intval($item['star']);
					}
					if(!empty($item['destination'])) {
						$hotel['city'] = trim($item['destination']['name']);
					}
					if(!empty($item['address'])) {
						$hotel['address'] = trim($item['address']);
					}
					if(!empty($item['description'])) {
						$hotel['descothers'] = trim($item['description']);
					}
					
					$hotels[] = $hotel;
				}
			}
			
			$prod->hotels = $hotels;
			
			if(!empty($data['schedule'])) { // 行程数组
				$trips = []; // 行程
				
				foreach($data['schedule'] as $row) {
					$tripStr = '<div class="row">';
					$tripStr .= '<h4>' . '第' . $row['day'] . '天 <span>'. $row['title'] .'</span></h4>';
					$tripStr .= '<p>'. $row['description'].'</p>';
					$tripStr .= '<p>交通：'. $row['transport'] .'</p>';
					$tripStr .= '<p>住宿：'. $row['hotel'] .'</p>';
					$tripStr .= '<p>用餐：'. $row['food'] .'</p>';
					$tripStr .= '</div>';
						
					$trips[] = $tripStr;
				}
				if(!empty($trips)) {
					$prod->routeplan = implode('', $trips);
				}
			}
			
			$cost = [];
			if(!empty($data['summary']['feeIncluding'])) {
				$cost[] = '<div><h4>费用包含</h4>'. trim($data['summary']['feeIncluding']) .'</div>';
			}
			if(!empty($data['summary']['feeExcluding'])) {
				$cost[] = '<div><h4>费用不包含</h4>'. trim($data['summary']['feeExcluding']) .'</div>';
			}
			if(!empty($cost)) {
				$prod->costInstruction = implode('<br>', $cost);
			}
			
			if(!empty($data['summary']['importHint'])) { // 出行提示
				$prod->booking = trim($data['summary']['importHint']);
			}
			
			$prod->special = new stdClass();
			$prod->special->id = $data['summary']['id']; // 热气球产品ID跟线路编号是分开的。未来可用ID更新价格日历等
			$prod->special->tourNumber = $tourNumber; // 线路名称
			
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
			
			$this->dataprocess->productCode($prod->code, TRUE); // debug, chenlei
	// 		$this->dataprocess->productCode($prod->code, FALSE); //@TODO remove. //debug, chenlei byford暂时不让入库插入新数据
			return TRUE;
		} else if(!empty($data['result']) && $data['result'] == 'fail') {
			$condition = ['source' => $this->source, 'special.id' => $item_id];
			$querySource = $this->cimongo->srcdb->duoqusourcedatas->findOne($condition);
			
			if(!empty($querySource)) {
				$this->dataprocess->setProductStatus($querySource['code'], 'stop');
				$this->cimongo->srcdb->duoqusourcedatas->remove(['code' => $querySource['code']], ['justOne' => TRUE]);
				echo date('Y-m-d H:i:s')." api return error and  stop products {$querySource['code']}".PHP_EOL;
			}
			
			return FALSE;
		}
		
		
		return FALSE;
		
	}
	
	public function grabById($item_id)
	{
// 		header('Content-Type: application/json'); // debug, chenlei
		
		$this->_grabDetail($item_id);
	}
	
	// 获取产品价格日历
	public function updateByCode($code)
	{	
		$querySource = $this->cimongo->srcdb->duoqusourcedatas->findOne(['source' => $this->source, 'code' => $code]);
		if(!empty($querySource)) {
			$result = $this->dealProdPrices($querySource);
		}
	}
	
	private function dealPrices($arrangementGroups)
	{
		// 价格日历
		$prices = [];
		
		foreach($arrangementGroups as $package) {
			$isChild = FALSE;
			if(mb_strpos($package['groupName'], '儿童', 0, 'UTF8') !== FALSE) {
				$isChild = true;
			}
			foreach($package['arrangements'] as $item) {
				$time = (float) $item['date'];
				$time = (int) $time/1000;
		
				$date = date('Y-m-d', $time);
					
				if($isChild) {
					if(!empty($prices[$date])) {
						$prices[$date]['childprice'] = floatval($item['price']);
					}
					
					continue;
				} else if(!empty($prices[$date])) {
					echo "the date {$date} is set in previous package prices".PHP_EOL;
					return FALSE;
				}
					
				if(!empty($item['availableCount']) && !empty($item['price'])) {
					$prices[$date] = array(
							'price' => floatval($item['price']),
							'yuwei' => floatval($item['availableCount']),
					);
					if(!empty($item['childPrice']) && mb_strpos($package['groupName'], '儿童', 0, 'UTF8') === FALSE && mb_strpos($package['groupName'], '(成人)', 0, 'UTF8') === FALSE) {
						$prices[$date]['childprice'] = floatval($item['childPrice']);
					}
					if(!empty($item['singleSupplement'])) {
						$prices[$date]['diffprice'] = floatval($item['singleSupplement']);
					}
				}
			}
		}
		
		return $prices;
	}
	
	public function grabProductPrices($item_id)
	{
		$url = "{$this->host}/products/{$item_id}/stock";
		
		$token = $this->getToken();
		
		if(empty($token)) {
			echo date('Y-m-d H:i:s').' Token get fail'.PHP_EOL;
			return FALSE;
		}
		
		$response = request($url, 'get', ['token' => $token]);
		
		if(empty($response)) {
			echo date('Y-m-d H:i:s').' Get product prices fail'.PHP_EOL;
			return FALSE;
		}
		
		$data = null;
		try {
			$data = json_decode($response, true);
		} catch (Exception $e) {
			echo $e->getMessage().PHP_EOL;
			return FALSE;
		}
		
		if(!empty($data['result']) && isset($data['code']) && $data['code'] == '00' && $data['result'] == "success") {
			// 价格日历
			$prices = [];
		
			if(!empty($data['data'])) {
				$prices = $this->dealPrices($data['data']);
				if(empty($prices)) {
					$prices = [];
				}
			}
		
			return $prices;
		}  else if(!empty($data['result']) && $data['result'] == 'fail') {
			$condition = ['source' => $this->source, 'special.id' => $item_id];
			$querySource = $this->cimongo->srcdb->duoqusourcedatas->findOne($condition);
			
			if(!empty($querySource)) {
				$this->dataprocess->setProductStatus($querySource['code'], 'stop');
				$this->cimongo->srcdb->duoqusourcedatas->remove(['code' => $querySource['code']], ['justOne' => TRUE]);
				echo date('Y-m-d H:i:s')." api return error and  stop products {$querySource['code']}".PHP_EOL;
			}
			
			return FALSE;
		}
		
		return FALSE;
	}
	
	public function import() // 导入数据
	{
		$condition = [];
		$condition['source'] = $this->source;
	
		$this->dataprocess->products($condition, FALSE);
	}
	
	public function importByCode($code, $isCover = FALSE) // 导入单个产品暑假
	{
		$condition = [];
		$condition['code'] = $code;
	
		$this->dataprocess->products($condition, TRUE, $isCover);
	}
	
	// 下架产品列表
	private function stopProducts($startGrabTime)
	{
		$count = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = [];
		$condition['grabtime']  = ['$lt' => $startGrabTime];
		$condition['source'] = $this->source;
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
				$result = $this->dealProdPrices($prod);
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
		
		$this->dataprocess->stopProducts($this->source, $startGrabTime); // 再次检查
	
		return;
	}
	
	private function dealProdPrices($prod) // 产品价格日历
	{
		$item_id = $prod['special']['id'];
		
		$prices = $this->grabProductPrices($item_id);
		
		if($prices === FALSE) {
			return FALSE;
		}
		
		$isStop = FALSE;
		$msg = '';
		
		if(empty($prices)) {
			$isStop = TRUE;
			$msg = 'empty prices';
		} else {
			$this->dataprocess->priceCal($prod['code'], $prices);
		}
		
		if($isStop) { // 需要下架的操作
			$this->dataprocess->setProductStatus($prod['code'], 'stop');
			$this->cimongo->srcdb->duoqusourcedatas->remove(['code' => $prod['code']], ['justOne' => TRUE]);
			echo date('Y-m-d H:i:s')." stop products {$prod['code']} {$msg}".PHP_EOL;
			
			return TRUE;
		}
		
		return FALSE;
	}
}