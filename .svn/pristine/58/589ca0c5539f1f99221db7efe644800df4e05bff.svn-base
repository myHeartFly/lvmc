<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Grabyoutejia extends CI_Controller {
	private $source = '有特价';
	private $codePrefix = 'YT';
	private $category;
	private $appid = 56;
	private $appKey = 'ka23923kx9k322x6';
	
	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Shanghai');
		
		$this->load->library('dataprocess/dataprocess');
		$this->load->helper('grabformat');
		
	}
	
	public function index()
	{
		
		$startGrabTime = new MongoDate();
		
		$page = 1;
		$totalPage = 1;
		do {
			$params = array(
					'app_id' => $this->appid,
					'app_key' => $this->appKey,
					'per_rows' => 20,
					'page'     => $page,
			);
			
			$url = "http://www.youtejia.com/Agent/getItemList";
			$response = request($url, 'post', $params);
			
			$data = null;
			if(!empty($response)) {
				try{
					$data = json_decode($response, true);
				} catch (Exception $e) {
					echo $e->getMessage().PHP_EOL;
				}
			}
			if(!empty($data) && $data['status'] == 1) {
				if(!empty($data['page_info'])) {
					$totalPage = $data['page_info']['page_nums'];
				}
				foreach($data['data'] as $item) {
					$this->_grabProductItem($item);
				}
			}
			
			$page++;
		} while($page <= $totalPage);
		
		if($totalPage > 1) {
			$this->stopProducts($startGrabTime);
		}
		
		echo date('Y-m-d').' grab youtejia product end!!!'.PHP_EOL;
	}
	
	private function _grabProductItem($item)
	{
		$this->load->helper('htmldom');
		
		$category = $item['category'];
		
		if($category != '自由行') {
			return FALSE;
		}
		
		// 价格日历
		$pricesGroup = [];
		
		if(!empty($item['groups'])) {
			foreach($item['groups'] as $row) {
				$date = $row['date'];
				$departTime = strtotime($date); // 产品出发时间
				if($departTime < (time() + 5 * 24 * 60 * 60)) { // 过期时间或者5天内全部过期的排除在外
					continue;
				}
				$targetName = (string) $row['target_name'];
				if(!empty($row['adult_price']) && !empty($row['ticket_num'])) {
					$price = array(
							'price' => floatval($row['adult_price']),
							'yuwei' => floatval($row['ticket_num']),
					);
					if(!empty($row['child_price'])) {
						$price['childprice'] = floatval($row['child_price']);
					}
					if(!empty($row['single_room_fee'])) {
						$price['diffprice'] = floatval($row['child_price']);
					}
		
					if(!isset($pricesGroup[$targetName])) {
						$pricesGroup[$targetName] = [];
					}
					
					$pricesGroup[$targetName][$date] = $price;
				}
			}
		}
		
		if(empty($pricesGroup)) {
			return FALSE;
		}
		
		$targets = [];
		$isSingle = false;
		if(count($pricesGroup) == 1) {
			foreach($pricesGroup as $targetName => $prices) {
				if(empty($targetName) || $targetName == '0') {
					$isSingle = true;
				}
			}
		}
		if(!$isSingle) {
			$url = $item['url'];
			
			$dom = _getDOM($url);
			
			if(!empty($dom)) {
				$targetDoms = $dom->find('#dataItem_list .set-item');
				foreach($targetDoms as $row) {
					$targetId = trim($row->target_id);
					$targetContent = trim($row->plaintext);
					$targets[$targetContent] = $targetId;
				}
			} else {
				echo date('Y-m-d').' get empty content url: '.$url.PHP_EOL;
			}
		}
		
		foreach($pricesGroup as $targetName => $prices) {
			if($isSingle) {
				$code = $item['item_id'];
				$result = $this->_grabDetail($item, $code, $prices, 0);
				
				return $result;
			} else {
				if(!empty($targets[$targetName])) {
					$code = $item['item_id']. '-' .$targets[$targetName];
					$result = $this->_grabDetail($item, $code, $prices, $targets[$targetName], $targetName);
					return $result;
				} else {
					echo date('Y-m-d').' no target name url: '.$url.PHP_EOL;
					return FALSE;
				}
			}
		}
	}
	
	private function _grabDetail($item, $code, $prices, $targetId = 0, $targetName = '')
	{
		$category = $item['category'];
		
		if($category != '自由行') {
			return FALSE;
		}
			
		$prod = new stdClass();
		$prod->type = ['自由行'];
		$prod->code = $this->codePrefix.$code;
		$prod->source = $this->source;
		$prod->sourceurl = $item['url'];
		empty($prod->vender) AND $prod->vender = $this->source;
			
		echo date("Y-m-d H:i:s")." grab youtejia product: {$prod->code} ".PHP_EOL;
			
		$prod->needConfirm = !empty($item['buy_type']) ? true : false;
		$prod->name = $item['title'];
		$prod->special = new stdClass();
	
		$prod->special->id = (string) $item['item_id'];
		if(!empty($targetId)) {
			$prod->special->targetName = $targetName;
			$prod->special->targetId = (string) $targetId;
		}
		
		$prod->servicephone = '4000026488';
		
		if(!empty($item['duration'])) {
			$travelTime = travelTimeFormat($item['duration']);
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
		
		$prod->start = [];
		$prod->end = [];
		if(!empty($item['startPlace'])) {
			$startPlace = trim($item['startPlace']);
			$prod->start = explode('/', $startPlace);
		}
		if(!empty($item['destPlaces'])) {
			$destPlaces = str_replace(',', ' ', $item['destPlaces']);
				
			$destPlaces = trim($destPlaces);
			$reg = '/\s+/u';
			$destPlaces = preg_replace($reg, ' ', $destPlaces);
				
			$ends = explode(' ', $destPlaces);
			if(!empty($ends)) {
				foreach($ends as $end) {
					if(!empty(trim($end))) {
						$prod->end[] = trim($end);
					}
				}
			}
		}
		
		if(empty($prod->end)) {
			return FALSE;
		}
		
		foreach($prod->end as $city) {
			if(mb_strpos($city, '市') !== FALSE) {
				if(!in_array($city, ['宿雾市', '胡志明市'])) {
					return FALSE;
				}
			}
		}
		
		// 价格日历	
		if(empty($prices)) {
			echo date('Y-m-d').' grab product code: '.$prod->code.' no prices failed'.PHP_EOL;
			return FALSE;
		}
		
		$prod->prices = (object) $prices;
		
		// 航班
		$groupFlights = [];
		
		$direction = 'depart';
		if(!empty($item['triffics']) && !empty($item['triffics'][0]) && !empty($item['triffics'][0][0])) {
			foreach($item['triffics'][0][0] as $row) {
				if($row['ch_name'] != '航空') {
					continue;
				}
				$flight = [];
				if(empty($row['triffic_number'])) continue;
				$flightInfo = explode(' ', $row['triffic_number']);
				if(count($flightInfo) < 0) {
					continue;
				}
				$flightNo = trim(array_pop($flightInfo));
				$flightNo = str_replace('-', '', $flightNo);
				$reg = "/^[0-9]+$/";
				if(preg_match($reg, $flightNo)) {
					if(count($flightNo) > 0) {
						$flightNo = trim(array_pop($flightInfo)).$flightNo;
					}
				}
		
				$reg = "/([A-Za-z0-9]{2,})$/i";
		
				if(preg_match($reg, $flightNo, $flightMatches)) {
					$flightNo = $flightMatches[1];
				} else {
					continue;
				}
		
				if($row['type'] == '3') {
					$direction = 'back';
				}
		
				$flight['flightNo'] = $flightNo;
				$flight['airline'] = trim(implode(' ', $flightInfo));
				$flight['takeoffCity'] = trim($row['departure']);
				$flight['landingCity'] = trim($row['destination']);
				$flight['day'] = floatval(trim($row['line_dates']));
				$flight['trip'] = $direction;
		
				$start_time = trim($row['start_time']);
				$end_time = trim($row['end_time']);
		
				$start_time_coms = explode(':', $start_time);
				$end_time_coms = explode(':', $end_time);
				if(!empty($start_time_coms) && count($start_time_coms) == 3) {
					$flight['takeoff'] = trim($start_time_coms[0]) . ':' . trim($start_time_coms[1]);
				}
				if(!empty($end_time_coms) && count($end_time_coms) == 3) {
					$flight['landing'] = trim($end_time_coms[0]) . ':' . trim($end_time_coms[1]);
					if(strpos('次日', $flight['landing'])) {
						$flight['landing'] = trim(str_replace('次日', '', $flight['landing']));
						$flight['landing'] .= '+1';
					}
				}
		
				$groupFlights[] = (object) $flight;
			}
		}
		
		$prod->flights = $groupFlights;
		
		// 酒店信息
		$hotels = [];
		
		if(!empty($item['hotels'])) {
			$hotel = array(
					'name' => trim($item['hotels']),
			);
			$hotels[] = $hotel;
		}
		
		$prod->hotels = $hotels;
		
		if(!empty($item['plans'])) { // 行程数组
			$trips = []; // 行程
			foreach($item['plans'] as $row) {
				$tripStr = '<div class="row">';
				$tripStr .= '<h4>' . '第' . $row['trip_number'] . '天 <span>'. $row['start_place'] . ' → ' . $row['end_place'] .'</span></h4>';
				$tripStr .= '<p>'. $row['pic_text'].'</p>';
				$tripStr .= '<p>住宿：'. $row['hotel'] .'</p>';
				$tripStr .= '<p>早餐：'. $row['breakfast'] .' 午餐：'.$row['lunch'].' 晚餐'. $row['dinner'] .'</p>';
				$tripStr .= '</div>';
					
				$trips[(int) $row['trip_number']] = $tripStr;
			}
			if(!empty($trips)) {
				$prod->routeplan = implode('', $trips);
			}
		}
		if(!empty($item['visas'])) { // 签证
			$prod->visa = trim($item['visas']);
		}
		
		if(!empty($item['bookingRemark'])) {
			$prod->booking = trim($item['bookingRemark']);
		}
		
		$cost = [];
		if(!empty($item['costInclue'])) {
			$cost[] = '<div><h4>费用包含</h4>'. trim($item['costInclue']) .'</div>';
		}
		if(!empty($item['costExclude'])) {
			$cost[] = '<div><h4>费用不包含</h4>'. trim($item['costExclude']) .'</div>';
		}
		
		if(!empty($cost)) {
			$prod->costInstruction = implode('<br>', $cost);
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
	
	public function grabById($item_id)
	{	
		$params = array(
				'app_id' => $this->appid,
				'app_key' => $this->appKey,
		);
		
		if(!empty($item_id)) {
			$params['item_id'] = $item_id;
		}
		
		$url = "http://www.youtejia.com/Agent/getItemList";
		$response = request($url, 'post', $params);
		
		$data = null;
		if(!empty($response)) {
			try{
				$data = json_decode($response, true);
			} catch (Exception $e) {
				echo $e->getMessage().PHP_EOL;
			}
		}
		if(!empty($data) && $data['status'] == 1) {
			$this->_grabProductItem($data['data'][0]);
		}
	}
	
	public function import() // 导入数据
	{
		$condition = [];
		$condition['source'] = $this->source;
	
		$this->dataprocess->products($condition, FALSE);
	}
	
	public function importByCode($code, $isCover = FALSE) // 导入单个产品
	{
		$condition = [];
		$condition['code'] = $code;
	
		$this->dataprocess->products($condition, TRUE, $isCover);
	}
	
	// 下架产品
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
			//获取产品info
			$results = $this->cimongo->srcdb->duoqusourcedatas->find($condition, ['hotels' => 0, 'flights' => 0])->sort(['created' => 1])->limit($max);
			$srcProducts = iterator_to_array($results);
			$offset += $max;
			
			foreach($srcProducts as $prod) {
				$result = $this->stopProductItem($prod);
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
	
	private function stopProductItem($prod)
	{
		$params = array(
				'app_id' => $this->appid,
				'app_key' => $this->appKey,
		);
		
		if(!empty($item_id)) {
			$params['item_id'] = $prod['targetId'];
		}
		
		$url = "http://www.youtejia.com/Agent/getItemList";
		$response = request($url, 'post', $params);
		
		$data = null;
		if(!empty($response)) {
			try{
				$data = json_decode($response, true);
			} catch (Exception $e) {
				echo $e->getMessage().PHP_EOL;
			}
		}
		if(!empty($data) && $data['status'] == 1) {
				
			$item = $data['data'][0];
			if(empty($item)) {
				continue;
			}
				
			// 价格日历
			$pricesGroup = [];
				
			if(!empty($item['groups'])) {
				foreach($item['groups'] as $row) {
					$date = $row['date'];
					$departTime = strtotime($date); // 产品出发时间
					if($departTime < (time() + 5 * 24 * 60 * 60)) { // 过期时间或者5天内全部过期的排除在外
						continue;
					}
					$targetName = (string) $row['target_name'];
					if(!empty($row['adult_price']) && !empty($row['ticket_num'])) {
						$price = array(
								'price' => floatval($row['adult_price']),
								'yuwei' => floatval($row['ticket_num']),
						);
						if(!empty($row['child_price'])) {
							$price['childprice'] = floatval($row['child_price']);
						}
						if(!empty($row['single_room_fee'])) {
							$price['diffprice'] = floatval($row['child_price']);
						}
							
						if(!isset($pricesGroup[$targetName])) {
							$pricesGroup[$targetName] = [];
						}
		
						$pricesGroup[$targetName][$date] = $price;
					}
				}
			}
			
			$isStop = FALSE;
			$msg = '';
			if(empty($pricesGroup)) {
				$isStop = TRUE;
				$msg = 'empty prices';
			} else {
				$isSingle = false;
				$selectPrices = [];
				if(count($pricesGroup) == 1) {
					foreach($pricesGroup as $targetName => $prices) {
						if(empty($targetName) || $targetName == '0') {
							$isSingle = true;
							$selectPrices = $prices;
						}
					}
				}
				
				if(!$isSingle) { // 多套餐情况
					$isExists = false;
					foreach($pricesGroup as $targetName => $prices) {
						if(isset($prod['targetName']) && $prod['targetName'] == $targetName) {
							$isExists = true;
							$selectPrices = $prices;
							break;
						}
					}
					if(!$isExists) { // 该套餐已下架
						$isStop = TRUE;
						$msg = 'empty target name, so empty prices';
					}
				}
				
				if(!empty($selectPrices)) {
					$filterPrices = [];
					$time = time();
					foreach($selectPrices as $date => $row) {
						$departTime = strtotime($date); // 产品出发时间
						if($departTime < ($time + 5 * 24 * 60 * 60)) { // 过期时间或者5天内全部过期的排除在外
							continue;
						}
						$filterPrices[$date] = (object) $row;
					}
					
					if(empty($filterPrices)) {
						$isStop = TRUE;
						$msg = 'filter empty prices';
					} else {
						$this->dataprocess->priceCal($prod['code'], $filterPrices);
					}
				}
			}

			if($isStop) {
				$this->dataprocess->setProductStatus($prod['code'], 'stop');
				$this->cimongo->srcdb->duoqusourcedatas->remove(['code' => $prod['code']], ['justOne' => TRUE]);
				echo date('Y-m-d H:i:s')." stop products {$prod['code']} empty target name".PHP_EOL;
				return TRUE;
			}
		}
		
		return FALSE;
	}
}