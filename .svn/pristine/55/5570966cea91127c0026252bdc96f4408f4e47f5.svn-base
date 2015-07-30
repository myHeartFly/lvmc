<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Grabqulv extends CI_Controller {
	private $source = '趣旅网';
	private $codePrefix = 'QL';
	private $apiConfig = array(
		'alliacneid' => 'qulvTest',
		'key' => '*qfddpiopiadie6jrrevwdizx'	
	);
	
	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Shanghai');
		
		$this->load->helper('url');
		$this->load->library('curl');
		$this->load->library('dataprocess/dataprocess');
		$this->load->helper('grabformat');
		
		$this->apiConfig['sign'] = md5(md5($this->apiConfig['alliacneid'].$this->apiConfig['key']));
	}
	
	public function index()
	{
		
		$startGrabTime = new MongoDate();
		$max = 50;
		$page = 0;
		$totalPage = 0;
		
		echo date('Y-m-d').' grab qulv product start : '.PHP_EOL;
		
		do {
			$params = array(
					'alliacneid' => $this->apiConfig['alliacneid'],
					'sign' => $this->apiConfig['sign'],
					'linetype' => 2, // 自由行
					'pageindex' => $page,
					'pagesize' => $max,
			);
			
			$url = "http://open.qulv.com/line/list";
			$response = request($url, 'post', $params);
			
			if(!empty($response)) {
				$xml = simplexml_load_string($response);
			}
			
			if(!empty($xml) && $xml->status == '0') {
				$count = (int) $xml->count;
				$totalPage = ceil($count/$max);
				if(!empty($xml->list->line)) {
					foreach($xml->list->line as $line) {
						$id = (string) $line->attributes()->id;
						if(empty($id)) {
							continue;
						}
						echo date('Y-m-d').' grab product id: '.$id.PHP_EOL;
						$result = $this->grabDetail($id);
						
						if($result) { // 更新成功
							echo date('Y-m-d').' grab product id: '.$id.' success'.PHP_EOL;
						}
					}
				}
			}
			
			$page++;
		} while ($page < $totalPage);
		
		if($totalPage > 1) {
			$this->stopProducts($startGrabTime);
		}
		
		echo date('Y-m-d').' grab qulv product end!!!'.PHP_EOL;
		
	}
	
	public function grabDetail($id)
	{
		
		mb_internal_encoding("UTF-8");
		$params = array(
				'alliacneid' => $this->apiConfig['alliacneid'],
				'sign' => $this->apiConfig['sign'],
				'id'   => $id,
		);
		
		$url = 'http://open.qulv.com/line/detail';
		
		$response = request($url, 'post', $params);

		$xml = simplexml_load_string($response);
		
		if(!empty($xml) && $xml->status == '0') {
			$line = $xml->line;
			$prod = new stdClass();
			$code = trim((string) $line->attributes()->id);
			
			$prod->code = $this->codePrefix.$code;
			$prod->name = trim((string) $line->name);
			$prod->source = $this->source;
			$prod->sourceurl = trim((string) $line->gourl);
			$prod->sourcestatus = 'normal';
			$prod->vender = $prod->source;
			$type = (int) $line->attributes()->type;
			if($type == 1) {
				$prod->type = ['跟团游'];
			} else if($type == 2) {
				$prod->type = ['自由行'];
			} else if($type == 4) {
				$prod->type = ['当地游'];
			}
			
			$prod->recommend = trim((string) $line->recommend);
			
			if(empty($prod->days) || empty($prod->hotelNight)) {
				$travelTime = travelTimeFormat($prod->name);
				if(empty($prod->days) && !empty($travelTime['days'])) {
					$prod->days = floatval($travelTime['days']);
				}
				if(empty($prod->hotelNight) && !empty($travelTime['nights'])) {
					$prod->hotelNight = floatval($travelTime['nights']);
				}
			}
			
			// 价格日历
			$prices = [];
			$teams = $line->teams->children();
			foreach($teams as $item) {
				$date = trim((string) $item->date);
				$pricestatus = trim((string) $item->status);
				$price = floatval(trim((string) $item->price));
				$childprice = floatval(trim((string) $item->childprice));
				if($pricestatus) {
					$prices[$date] = array(
							'price' => $price,
							'yuwei' => floatval(8),
					);
					if(!empty($childprice)) {
						$prices[$date]['childprice'] = $childprice;
					}
				}
			}
			
			if(empty($prices)) {
				echo date('Y-m-d').' grab product code: '.$prod->code.' no prices failed'.PHP_EOL;
				return FALSE;
			}
			
			$prod->prices = (object) $prices;
			
			// 出发地目的地
			$prod->start = [];
			$prod->end = [];
			$start = trim((string) $line->startcity);
			$end = trim((string) $line->tocity);
			if(!empty($start)) {
				$starts = explode(',', $start);
				if(count($starts) > 1 && !empty($prod->name)) {
					foreach($starts as $city) {
						if(mb_strpos($prod->name, $city, 0, 'UTF8') !== FALSE) {
							$prod->start[] = $city;
						}
					}
				}
				if(empty($prod->start)) {
					$prod->start = $starts;
				}
			}
			if(!empty($end)) {
				$ends = explode(',', $end);
				foreach($ends as $city) {
					if(mb_strpos($city, '马尔代夫', 0, 'UTF-8') !== FALSE && mb_strlen($city, 'UTF8') > 4) {
						$city = str_replace('马尔代夫群岛', '', $city);
						$city = str_replace('马尔代夫—', '', $city);
						$city = str_replace('马尔代夫', '', $city);
					}
					if(!empty($city) && !in_array($city, $prod->end)) {
						$prod->end[] = $city;
					}
				}
				
			}
			$prod->days = floatval(trim((string) $line->day));
			$prod->servicephone = '4006399960';
			$prod->needConfirm = FALSE;
			$cost = [];
			$feeinclude = trim((string) $line->feeinclude);
			$feeexclude = trim((string) $line->feeexclude);
			if(!empty($feeinclude)) {
				$cost[] = '<div><h4>费用包含</h4>'.$feeinclude.'</div>';
			}
			if(!empty($feeexclude)) {
				$cost[] = '<div><h4>费用不包含</h4>'.$feeexclude.'</div>';
			}
			$prod->costInstruction = implode('<br>', $cost);
			$prod->booking = trim((string) $line->ordercontent);
			$prod->booking .= trim((string) $line->attention);
			
			$trips = []; // 行程
			foreach($line->days->day as $item) {
				$tripStr = '<div class="row">';
				$tripStr .= '<h4>' . '第' . (string) $item->attributes()->num . '天 <span>'. (string) $item->title .'</span></h4>';
				$tripStr .= '<p>'.(string) $item->description.'</p>';
				$tripStr .= '<p>交通工具：'. (string) $item->traffic .'</p>';
				$tripStr .= '<p>抵达城市：'. (string) $item->arrcity .'</p>';
				$tripStr .= '<p>住宿：'. (string) $item->stay .'</p>';
				$tripStr .= '<p>用餐：'. (string) $item->meal .'</p>';	
				$tripStr .= '</div>';
				
				$trips[(int) $item->attributes()->num] = $tripStr;
			}
			if(!empty($trips)) {
				$prod->routeplan = implode('', $trips);
			}
			
			$hotels = []; // 酒店
			$hotelIndex = 1;
			foreach($line->hotels->hotel as $item) {
				$hotel = array();
				$hotelName = trim((string) $item->attributes()->name);
				$hotel['name'] = $hotelName;
				$hotel['englishName'] = trim((string) $item->attributes()->ename);
				$star = '0';
				$starStr = trim((string) $item->star);
				if(mb_strpos($starStr, '七', 0, 'UTF8') !== FALSE) {
					$star = '5';
				}
				else if(mb_strpos($starStr, '六', 0, 'UTF8') !== FALSE) {
					$star = '5';
				}
				else if(mb_strpos($starStr, '五', 0, 'UTF8') !== FALSE) {
					$star = '5';
				}
				else if(mb_strpos($starStr, '四', 0, 'UTF8') !== FALSE) {
					$star = '4';
				}
				else if(mb_strpos($starStr, '三', 0, 'UTF8') !== FALSE) {
					$star = '3';
				}
				else if(mb_strpos($starStr, '二', 0, 'UTF8') !== FALSE) {
					$star = '2';
				}
				else if(mb_strpos($starStr, '一', 0, 'UTF8') !== FALSE) {
					$star = '1';
				}
				$hotel['star'] = (string) $star;
				$hotel['descothers'] = trim((string) $item->introduction);
				if(empty($hotel['star'])) {
					$hotel['star'] = '0';
				}
				$hotel['index'] = $hotelIndex++;
				$hotels[] = $hotel;
			}
			$prod->hotels = $hotels;
			
			$groupFlights = []; // 一组航班
			$departFlights = []; // 起程航班
			$backFlights = []; // 回程航班
			$traffics = []; // 交通
			
			if(!empty($line->flights->traffic)) { // 兼容未来单词修复。
				$traffic = $line->flights->traffic;
			} else {
				$traffic = $line->filghts->traffic;
			}
			
			$flightIndex = 1;
			if(!empty($traffic->totraffic)) {
				$item = $traffic->totraffic->flight;
				$flight = [];
				$flightNo = trim((string) $item->attributes()->flightno);
				$flightNo = str_replace(' ', '', $flightNo);
				$flightNo = str_replace('-', '', $flightNo);
				$flight['flightNo'] = $flightNo;
				$flight['takeoff'] = trim((string) $item->attributes()->deptime);
				$flight['landing'] = trim((string) $item->attributes()->arrtime);

				if(!empty($item->attributes()->depcity)) {
					$flight['takeoffCity'] = trim((string) $item->attributes()->depcity);
				}
				if(!empty($item->attributes()->arrcity)) {
					$flight['landingCity'] = trim((string) $item->attributes()->arrcity);
				}
				if(!empty($item->attributes()->depairport)) {
					$flight['airline'] = trim((string) $item->attributes()->depairport);
				}
				
				$flight['trip'] = 'depart';
				$flight['index'] = floatval($flightIndex++);
				$departFlights[] = $flight;

				$transfer = intval(trim((string) $item->attributes()->transefer));
				if(!empty($transfer)) { // 去程有转机
					$trains = $traffic->totraffic->train;
					if(!empty($trains)) {
						if(!is_array($trains)) {
							$trains = [$trains];
						}
						foreach($trains as $item) {
							$flight = [];
							$flightNo = trim((string) $item->attributes()->trainno);
							$flightNo = str_replace(' ', '', $flightNo);
							$flightNo = str_replace('-', '', $flightNo);
							$flight['flightNo'] = $flightNo;
							$flight['takeoff'] = trim((string) $item->attributes()->deptime);
							$flight['landing'] = trim((string) $item->attributes()->arrtime);
						
							if(!empty($item->attributes()->depcity)) {
								$flight['takeoffCity'] = trim((string) $item->attributes()->depcity);
							}
							if(!empty($item->attributes()->arrcity)) {
								$flight['landingCity'] = trim((string) $item->attributes()->arrcity);
							}
							if(!empty($item->attributes()->depairport)) {
								$flight['airline'] = trim((string) $item->attributes()->depairport);
							}
						
							$flight['trip'] = 'depart';
							$flight['index'] = floatval($flightIndex++);
							$departFlights[] = $flight;
						}
					}
					
				}
			}
				
			if(!empty($traffic->backtraffic)) {
				$item = $traffic->backtraffic->flight;
				$flight = [];
				$flightNo = trim((string) $item->attributes()->flightno);
				$flightNo = str_replace(' ', '', $flightNo);
				$flightNo = str_replace('-', '', $flightNo);
				$flight['flightNo'] = $flightNo;
				$flight['takeoff'] = trim((string) $item->attributes()->deptime);
				$flight['landing'] = trim((string) $item->attributes()->arrtime);

				if(!empty($item->attributes()->depcity)) {
					$flight['takeoffCity'] = trim((string) $item->attributes()->depcity);
				}
				if(!empty($item->attributes()->arrcity)) {
					$flight['landingCity'] = trim((string) $item->attributes()->arrcity);
				}
				if(!empty($item->attributes()->depairport)) {
					$flight['airline'] = trim((string) $item->attributes()->depairport);
				}
				
				$flight['trip'] = 'back';
				$departFlights[] = $flight;

				$transfer = intval(trim((string) $item->attributes()->transefer));
				if(!empty($transfer)) { // 去程有转机
					$trains = $traffic->backtraffic->train;
					if(!empty($trains)) {
						if(!is_array($trains)) {
							$trains = [$trains];
						}
						foreach($trains as $item) {
							$flight = [];
							$flightNo = trim((string) $item->attributes()->trainno);
							$flightNo = str_replace(' ', '', $flightNo);
							$flightNo = str_replace('-', '', $flightNo);
							$flight['flightNo'] = $flightNo;
							$flight['takeoff'] = trim((string) $item->attributes()->deptime);
							$flight['landing'] = trim((string) $item->attributes()->arrtime);
						
							if(!empty($item->attributes()->depcity)) {
								$flight['takeoffCity'] = trim((string) $item->attributes()->depcity);
							}
							if(!empty($item->attributes()->arrcity)) {
								$flight['landingCity'] = trim((string) $item->attributes()->arrcity);
							}
							if(!empty($item->attributes()->depairport)) {
								$flight['airline'] = trim((string) $item->attributes()->depairport);
							}
						
							$flight['trip'] = 'back';
							$backFlights[] = $flight;
						}
					}
					
				}
			}
				
			$groupFlights = array_merge($departFlights, $backFlights);
			
			$prod->flights = $groupFlights;
			
			if(empty($prod->hotels) && empty($prod->flights)) {
				echo 'empty hotels and flights product code: '.$prod->code.PHP_EOL;
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
// 			$this->dataprocess->productCode($prod->code, FALSE); //@TODO remove. //debug, chenlei byford暂时不让入库插入新数据
			return TRUE;
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
				$result = $this->stopProductItem($prod['code']);
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
	
	private function stopProductItem($code) // 产品编号
	{
		mb_internal_encoding("UTF-8");
		
		$id = substr($code, strlen($this->codePrefix));
		
		$params = array(
				'alliacneid' => $this->apiConfig['alliacneid'],
				'sign' => $this->apiConfig['sign'],
				'id'   => $id,
		);
		
		$url = 'http://open.qulv.com/line/detail';
		
		$response = request($url, 'post', $params);
		
		if(empty($response)) return FALSE;
		
		$xml = simplexml_load_string($response);
		
		if(!empty($xml) && $xml->status == '0') {
			$line = $xml->line;
		
			// 价格日历
			$prices = [];
			if(!empty($line->teams)) {
				$teams = $line->teams->children();
				if(!empty($teams)) {
					foreach($teams as $item) {
						$date = trim((string) $item->date);
						$pricestatus = trim((string) $item->status);
						$price = floatval(trim((string) $item->price));
						$childprice = floatval(trim((string) $item->childprice));
						if($pricestatus) {
							$prices[$date] = array(
									'price' => $price,
									'yuwei' => floatval(8),
							);
							if(!empty($childprice)) {
								$prices[$date]['childprice'] = $childprice;
							}
						}
					}
				}
			}
			
			$isStop = FALSE;
			$msg = '';
				
			if(empty($prices)) {
				$isStop = TRUE;
				$msg = 'empty prices';
			} else {
				$filterPrices = [];
				$time = time();
				foreach($prices as $date => $row) {
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
					$this->dataprocess->priceCal($code, $filterPrices);
				}
			}
			
			if($isStop) { // 需要下架的操作
				$this->dataprocess->setProductStatus($code, 'stop');
				$this->cimongo->srcdb->duoqusourcedatas->remove(['code' => $code], ['justOne' => TRUE]);
				echo date('Y-m-d H:i:s')." stop products {$code} {$msg}".PHP_EOL;
				
				return TRUE;
			}
			
			return FALSE;
		}
		
		return FALSE;
	}
}