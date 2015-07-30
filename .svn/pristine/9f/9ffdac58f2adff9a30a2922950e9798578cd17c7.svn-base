<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * 数据处理模块
 */
class Dataprocess {
	private $_ci;
	private $taskType; // 任务类型，更新 or 插入
	private $periods; // 日期
	
	public function __construct()
	{
		$this->_ci = & get_instance();
		
		$this->_ci->load->library('cimongo');
		$this->_ci->load->library('city/city');
		
		$periods = $this->_ci->cimongo->db->duoqudicts->find(['__t' => 'Period']);
		$this->periods = iterator_to_array($periods);
		
		date_default_timezone_set('Asia/Shanghai');
	}
	
	/*
	 * 处理单个产品数据插入
	* @params $orgProd String duoqusourcedata 产品数据
	* @params $isInsert Boolean 已存在产品是否覆盖。default: true（插入）
	* @params $isCover Boolean 已存在产品是否覆盖。default: false（不覆盖）
	*/
	public function product($orgProd, $isInsert = TRUE, $isCover = FALSE) {
		if(empty($orgProd)) {
			return FALSE;
		}
		
		$orgProd = (array) $orgProd;
		
		$prod = new stdClass();
		
		$prod->code = $orgProd['code'];
		$prod->type = $orgProd['type'];
		$prod->source = $orgProd['source'];
		$prod->sourceurl = $orgProd['sourceurl'];
		$prod->sourcestatus = !empty($orgProd['sourcestatus']) ? $orgProd['sourcestatus'] : 'normal';
		$prod->vender = !empty($orgProd['vender']) ? $orgProd['vender'] : '';
		$prod->name = $orgProd['name'];
		$prod->start = [];
		$prod->startKey = [];
		$prod->end = [];
		$prod->endKey = [];
		if(!empty($orgProd['start'])) {
			foreach($orgProd['start'] as $city) {
				$realCity = $this->_ci->city->getRealCity($city);
				if(!in_array($realCity, $prod->start)) {
					$prod->start[] = $realCity;
				}
			}
			foreach($prod->start as $k => $city) {
				$queryPlace = $this->_ci->cimongo->db->duoqudicts->findOne(['__t' => 'Place', 'title' => $city]);
				if(!empty($queryPlace)) {
					$prod->startKey[] = (string) $queryPlace['key'];
				}
			}
		}
		if(!empty($orgProd['end'])) {
			foreach($orgProd['end'] as $city) {
				$realCity = $this->_ci->city->getRealCity($city);
				if(!in_array($realCity, $prod->end)) {
					$prod->end[] = $realCity;
				}
			}
			foreach($prod->end as $k => $city) {
				$queryPlace = $this->_ci->cimongo->db->duoqudicts->findOne(['__t' => 'Place', 'title' => $city]);
				if(!empty($queryPlace)) {
					$prod->endKey[] = (string) $queryPlace['key'];
				}
			}
		}
		if(!empty($orgProd['recommend'])) {
			$prod->recommend = $orgProd['recommend'];
		}
		if(!empty($orgProd['days'])) {
			$prod->days = floatval($orgProd['days']);
		}
		if(!empty($orgProd['hotelNight'])) {
			$prod->hotelNight = floatval($orgProd['hotelNight']);
		}
		
		if(empty($prod->days) || empty($prod->hotelNight)) {
			if(!empty($prod->name)) {
				$travelTime = travelTimeFormat($prod->name);
				if(empty($prod->days) && !empty($travelTime['days'])) {
					$prod->days = floatval($travelTime['days']);
				}
				if(empty($prod->hotelNight) && !empty($travelTime['nights'])) {
					$prod->hotelNight = floatval($travelTime['nights']);
				}
			}
		}
		
		if(!empty($orgProd['servicephone'])) {
			$prod->servicephone = $orgProd['servicephone'];
		}
		if(!empty($orgProd['needConfirm'])) {
			$prod->needConfirm = $orgProd['needConfirm'];
		}
		if(!empty($orgProd['visa'])) {
			$prod->visa = $orgProd['visa'];
		}
		if(!empty($orgProd['costInstruction'])) {
			$prod->costInstruction = $orgProd['costInstruction'];
		}
		if(!empty($orgProd['routeplan'])) {
			$prod->routeplan = $orgProd['routeplan'];
		}
		if(!empty($orgProd['returnPolicy'])) {
			$prod->returnPolicy = $orgProd['returnPolicy'];
		}
		if(!empty($orgProd['booking'])) {
			$prod->booking = $orgProd['booking'];
		}
		$prices = [];
		if(!empty($orgProd['prices'])) {
			foreach($orgProd['prices'] as $date => $row) {
				$departTime = strtotime($date); // 产品出发时间
				if($departTime < (time() + 5 * 24 * 60 * 60)) { // 过期时间或者5天内全部过期的排除在外
					continue;
				}
				if(!empty($row['yuwei'])) {
					$row['yuwei'] = floatval($row['yuwei']);
				}
				if(!empty($row['price'])) {
					$row['price'] = floatval($row['price']);
				}
				if(!empty($row['childprice'])) {
					$row['childprice'] = floatval($row['childprice']);
				}
		
				$prices[$date] = $row;
			}
				
		}
		
		if(!empty($prices)) {
			$prod->prices = (object) $prices;
		}
		
		// 出发日期
		$startFestivals = [];
		$startMonths = [];
		$startDays = [];
		if(!empty($prices)) {
			foreach($prices as $date => $row) {
				foreach($this->periods as $period) {
					if(!empty($period['span']) && !empty($period['span']['start'])  && !empty($period['span']['start']->sec)
					&& !empty($period['span']['end']) && !empty($period['span']['end']->sec)) {
						if(trim($period['title']) == '全部') {
							continue;
						}
						$startDate = date('Y-m-d', $period['span']['start']->sec);
						$endDate = date('Y-m-d', $period['span']['end']->sec);
						if($date >= $startDate && $date <= $endDate) {
							if(!in_array($period['title'], $startMonths) && !in_array($period['title'], $startFestivals)) {
								$isMonth = intval(mb_substr($period['title'], 0, 1));
								if($isMonth >= 1 && $isMonth <= 12 ) {
									$startMonths[] = $period['title'];
								} else {
									$startFestivals[] = $period['title'];
								}
							}
						}
		
					}
				}
		
			}
			$startDays = array_merge($startFestivals, $startMonths);
			$prod->startDate = implode($startDays, '、');
		}
		
		$queryProd = $this->_ci->cimongo->db->duoqugrabs->findOne(['code' => $prod->code]);
		if(!empty($queryProd)) {
			$this->taskType = 'update';
		} else {
			$this->taskType = 'insert';
		}
		
		if(!$isCover && $this->taskType == 'update') { // 非覆盖的要检查更新
			$newProd = array(
					'prices'       => !empty($prod->prices) ? $prod->prices : new stdClass(),
					'startDate'    => !empty($prod->startDate) ? $prod->startDate : '',
// 					'servicephone' => !empty($prod->servicephone) ? $prod->servicephone : '',
					'sourcestatus' => 'normal',
					'sourceurl'    => $prod->sourceurl,
					'grabtime'     => new MongoDate(),
			);
			if(!empty($prod->days) && (empty($queryProd['days']) || $queryProd['days'] == 1)) {
				$newProd['days'] = floatval($prod->days);
			}
			$this->_ci->cimongo->db->duoqugrabs->findAndModify(array('code'=>$prod->code), array('$set'=> $newProd));
			
			echo date("Y-m-d H:i:s")." product: {$prod->code} update prices success".PHP_EOL; // debug, chenlei

			return TRUE;
		}
		
		if(!$isInsert && $this->taskType == 'insert') {
			return FALSE;
		}
		
		// 处理酒店
		$prod->hotels = $this->dealHotels($orgProd);
		
		// 处理航班
		$prod->flights = $this->dealFlights($orgProd);
		
		if(empty($prod->hotels) && empty($prod->flights)) {
			return FALSE;
		}
		
		$prod->grabtime = new MongoDate();
		$prod->status = 'stop'; // 设置停售状态，信息补充完成可改为正常状态
		
		if($this->taskType == 'insert') { // 新插入时设置时间
			$prod->created = new MongoDate();
		}
		
		if($isCover && $this->taskType == 'update') { // @TODO 重新覆盖一个产品时先让其下架
			
		}
		
		$result = $this->_ci->cimongo->db->duoqugrabs->findAndModify(array('code'=>$prod->code), array('$set'=>(array)$prod), [], array('upsert'=>true, 'new' => true));
		
		echo date("Y-m-d H:i:s")." product: {$prod->code} update to real database success".PHP_EOL; // debug, chenlei
		return TRUE;
	}
	
	/*
	 * 处理单个产品数据插入
	 * @params $code String 产品编号
	 * @params $isInsert Boolean 已存在产品是否覆盖。default: true（插入）
	 * @params $isCover Boolean 已存在产品是否覆盖。default: false（不覆盖）
	 */
	public function productCode($code, $isInsert = TRUE, $isCover = FALSE)
	{
		$orgProd = $this->_ci->cimongo->srcdb->duoqusourcedatas->findOne(['code' => $code]);
		
		if(empty($orgProd)) return FALSE;
		
		$result = $this->product($orgProd, $isInsert, $isCover);
	}
	
	public function products($condition = [], $isInsert = TRUE, $isCover = FALSE)
	{
		$count = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$total = $this->_ci->cimongo->srcdb->duoqusourcedatas->count($condition);
		
		do {
			echo "offset : $offset"."\n";
			$results = $this->_ci->cimongo->srcdb->duoqusourcedatas->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
				
			foreach($products as $orgProd) {
				$this->product($orgProd, $isInsert, $isCover);
			}
		} while($offset < $total);
		
		return TRUE;
	}
	
	private function dealHotels($orgProd)
	{
		$hotels = [];
		if(empty($orgProd['hotels'])) {
			return $hotels;
		}
		
		$hotelIndex = 1;
		foreach($orgProd['hotels'] as $item) {
			$hotel = [];
			$hotelName = trim($item['name']);
			$hotel['name'] = $hotelName;
			if(!empty($item['englishName'])) {
				$hotel['englishName'] = trim($item['englishName']);
			}
			if(isset($item['star'])) {
				$hotel['star'] = (string) $item['star'];
			}
			if(!empty($item['address'])) {
				$hotel['address'] = trim($item['address']);
			}
			if(!empty($item['country'])) {
				$hotel['country'] = trim($item['country']);
			}
			if(!empty($item['city'])) {
				$hotel['city'] = trim($item['city']);
			}
			if(!empty($item['descothers'])) {
				$hotel['descothers'] = trim($item['descothers']);
			}
			
			// 酒店名称匹配
			if(empty($hotel['city']) && !empty($orgProd['end'])) {
				foreach($orgProd['end'] as $end) {
					if(mb_strpos($end, $hotelName, 0, 'UTF8') !== FALSE) {
						$hotel['city'] = $this->_ci->city->getRealCity($end);
					}
				}
			}
				
			if(empty($hotel['city'])) {
				if(!empty($orgProd['end'])) {
					$ends = [];
					foreach($orgProd['end'] as $city) {
						$realCity = $this->_ci->city->getRealCity($city);
						if(!in_array($realCity, $ends)) {
							$ends[] = $realCity;
						}
					}
					if(count($hotels) < count($ends)) {
						$hotel['city'] = $ends[count($hotels)];
					} else if(count($ends) == 1) {
						$hotel['city'] = $orgProd['end'][0];
					}
				}
			}
			
			if(!empty($hotel['city'])) {
				$hotel['city'] = $this->_ci->city->getRealCity($hotel['city']);
				if(empty($hotel['country'])) {
					$hotel['country'] = $this->_ci->city->getCountry($hotel['city']);
				}
			}
			
			$and1 = ['$or' =>[['alias' => $hotelName], ['name' => $hotelName], ['englishName' => $hotelName]]];
			$and2 = ['$or' => []];
			if(!empty($hotel['city'])) {
				if(!empty($orgProd['end'])) {
					$cities = array_merge([$hotel['city']], $orgProd['end']);
				} else {
					$cities = [$hotel['city']];
				}
				$and2['$or'][] = ['city' => ['$in' => $cities] ];
				$and2['$or'][] = ['country' => $hotel['city'] ];
			}
			if(!empty($hotel['address'])) {
				$and2['$or'][] = ['addressAlias' => $hotel['address']];
				$and2['$or'][] = ['address' => $hotel['address']];
			}
			
			if(empty($and2['$or'])) {
				echo 'ERROR: product '.$orgProd['code']. ' hotel: '. $hotelName .' city is not exists!';
				continue;
			}
			
			$hotelCond = ['$and' => [$and1 , $and2]];
				
			$queryHotel = $this->_ci->cimongo->db->hotelbases->findOne($hotelCond);
			if(!empty($queryHotel)) { // 更新
				$hotel['alias'] = $queryHotel['alias'];
				$hotel['addressAlias'] = !empty($queryHotel['addressAlias']) ? $queryHotel['addressAlias'] : [];
				if(!empty($hotel['address']) && !in_array($hotel['address'], $hotel['addressAlias'])) {
					$hotel['addressAlias'][] = $hotel['address'];
				}
					
				$updateRet = $queryHotel;
				if(!in_array($hotelName, $hotel['alias'])) {
					$hotel['alias'][] = $hotelName;
				}
			
				$updateRet = $this->_ci->cimongo->db->hotelbases->findAndModify(['_id' => $queryHotel['_id']], array('$set'=> ['alias' => $hotel['alias'], 'addressAlias' => $hotel['addressAlias']]), ['_id' => true], array('new' => true));
			} else {
				$hotel['alias'] = [$hotelName];
				if(!empty($hotel['address'])) {
					$hotel['addressAlias'] = [$hotel['address']];
				}
				$hotel['created'] = new MongoDate();
				$updateRet = $this->_ci->cimongo->db->hotelbases->findAndModify($hotelCond, array('$set'=> $hotel), [], array('upsert'=>true, 'new' => true));
			}
			$hRow = array();
			if(!empty($updateRet['_id'])) {
				$hRow['hotel'] = $updateRet['_id'];
				if(!empty($item['day'])) {
					$hRow['day'] = floatval($item['day']);
				}
				if(!empty($item['night'])) {
					$hRow['night'] = floatval($item['night']);
				}
				$hotel['index'] = $hotelIndex++;
				$hotel['index'] = $hotel['index'];
				$hotels[] = $hRow;
			}
		}
		
		if(empty($hotels[0]['day'])) {
			$hotels[0]['day'] = new MongoInt32(1);
		}
		if(count($hotels) == 1 && empty($hotels[0]['night']) && !empty($orgProd['hotelNight'])) {
			$hotels[0]['night'] = floatval($orgProd['hotelNight']);
		}
		
		return $hotels;
	}
	
	private function dealFlights($orgProd)
	{
		$groupFlights = [];
		if(empty($orgProd['flights'])) {
			return $groupFlights;
		}
		
		foreach($orgProd['flights'] as &$item) {
			$flight = [];
			$flightNo = strtoupper(trim($item['flightNo']));
			$flight['flightNo'] = $flightNo;
			if(!empty($item['airline'])) {
				$flight['airline'] = trim($item['airline']);
			}
			if(!empty($item['model'])) {
				$flight['model'] = trim($item['model']);
			}
			if(!empty($item['takeoff'])) {
				$flight['takeoff'] = trim($item['takeoff']);
			}
			if(!empty($item['takeoffCity'])) {
				$flight['takeoffCity'] = trim($item['takeoffCity']);
			}
			if(!empty($item['takeoffAirport'])) {
				$flight['takeoffAirport'] = trim($item['takeoffAirport']);
			}
			if(!empty($item['landing'])) {
				$flight['landing'] = trim($item['landing']);
			}
			if(!empty($item['landingCity'])) {
				$flight['landingCity'] = trim($item['landingCity']);
			}
			if(!empty($item['landingAirport'])) {
				$flight['landingAirport'] = trim($item['landingAirport']);
			}
			
			$flight['airlineKey']  = '-1';
			$flight['modelKey'] = '-1';
			
			$queryFlight = $this->_ci->cimongo->db->flightbases->findOne(['flightNo' => $flightNo]);
			if(!empty($queryFlight)) {
				$updateRet = $queryFlight;
				if(empty($item['takeoffCity']) && !empty($queryFlight['takeoffCity'])) {
					$item['takeoffCity'] = $queryFlight['takeoffCity'];
				}
				if(empty($item['landingCity']) && !empty($queryFlight['landingCity'])) {
					$item['landingCity'] = $queryFlight['landingCity'];
				}
			} else {
				$airCode = substr($flightNo, 0, 2);
				if(!empty($flight['takeoffCity'])) {
					$queryPlace = $this->_ci->cimongo->db->duoqudicts->findOne(['__t' => 'Place', 'title' => $flight['takeoffCity']]);
					if(!empty($queryPlace)) {
						$flight['takeoffCityKey'] = (string) $queryPlace['key'];
					}
				}	
				if(!empty($flight['landingCity'])) {
					$queryPlace = $this->_ci->cimongo->db->duoqudicts->findOne(['__t' => 'Place', 'title' => $flight['landingCity']]);
					if(!empty($queryPlace)) {
						$flight['landingCityKey'] = (string) $queryPlace['key'];
					}
				}
				
				$queryAirline = $this->_ci->cimongo->db->duoqudicts->findOne(['__t' => 'Airline', 'codes' => $airCode]);
				if(!empty($queryAirline)) {
					$flight['airline'] = $queryAirline['name'];
					$flight['airlineKey'] = $queryAirline['key'];
				}
			
				$flight['created'] = new MongoDate();
				$updateRet = $this->_ci->cimongo->db->flightbases->findAndModify(['flightNo' => $flightNo], array('$set'=> $flight), [], array('upsert'=>true, 'new' => true));
			}
			
			$fRow = [];
			if(!empty($updateRet['_id']) && !empty($updateRet['flightNo'])) {
				$fRow['flight'] = $updateRet['_id'];
				$fRow['flightNo'] = $updateRet['flightNo'];
				
				if(!empty($item['day'])) {
					$fRow['day'] = floatval($item['day']);
				}
				if(!empty($item['trip'])) {
					$fRow['trip'] = trim($item['trip']);
				}
				if(!empty($item['space'])) {
					$fRow['space'] = trim($item['space']);
				}
				$groupFlights[] = $fRow;
			}
		}
		
		$departFlightNos = $this->departFlightNos($orgProd['flights'], $orgProd['end']);
		
		if(!empty($groupFlights)) {
			$departFlights = [];
			$backFlights = [];
			foreach($groupFlights as $item) {
				if(in_array($item['flightNo'], $departFlightNos)) {
					unset($item['flightNo']);
					$item['trip'] = 'depart';
					$departFlights[] = $item;
				} else {
					unset($item['flightNo']);
					$item['trip'] = 'back';
					$backFlights[] = $item;
				}
			}
			$groupFlights = array_merge($departFlights, $backFlights);
			
			$flightsCount = count($groupFlights);
			for($i = 0; $i < $flightsCount; $i++) {
				$groupFlights[$i]['index'] = floatval($i + 1);
			}
			if(empty($groupFlights[0]['day'])) {
				$groupFlights[0]['day'] = floatval(1);
				$isUpdate = true;
			}
			if(count($groupFlights) == 2 && !empty($orgProd['days']) && $orgProd['days'] > 1 && !empty($groupFlights[1]['flight']) && empty($groupFlights[1]['day'])) {
				$queryFlight = $this->_ci->cimongo->db->flightbases->findOne(['_id' => $groupFlights[1]['flight']]);
				if(!empty($queryFlight) && !empty($queryFlight['takeoff']) && !empty($queryFlight['landing'])) {
					$fixday = 0;
					if(preg_match("/\d+:\d+\s*\+(\d+)/", $queryFlight['landing'], $timeMatches)) {
						$fixday = (int) $timeMatches[1];
					}
						
					if(preg_match("/(\d+):\d+/", $queryFlight['takeoff'], $takeoffMatches) && preg_match("/(\d+):\d+/", $queryFlight['landing'], $landingMatches)) {
						if($takeoffMatches[1] < $landingMatches[1] || $fixday > 0) {
							if(floatval($orgProd['days'] - $fixday) > 0) {
								$groupFlights[1]['day'] = floatval($orgProd['days'] - $fixday);
							}
						} else if($takeoffMatches[1] > $landingMatches[1]) {
							if(floatval($orgProd['days'] - 1) > 0) {
								$groupFlights[1]['day'] = floatval($orgProd['days'] - 1);
							}
						}
					}
				}
		
			}
		}
		
		return $groupFlights;
	}
	
	/*
	 * 获取起程的航班号
	*/
	function departFlightNos($flights, $ends) {
		$departFlightNos = [];
		
		// 判断是否已经有方向了
		$isAllHasTrip = TRUE;
		foreach($flights as $item) {
			if(empty($item['trip'])) {
				$isAllHasTrip = FALSE;
			}
		}
		if($isAllHasTrip) {
			foreach($flights as $item) {
				if($item['trip'] == 'depart') {
					$departFlightNos[] = $item['flightNo'];
				}
			}
	
			return $departFlightNos; // 重新排序一下
		}
	
		// 方案一：降落城市正好在目的地中
		if(!empty($ends)) {
			$endCity = '';
			foreach($flights as $item) {
				if(!empty($item['landingCity'])) {
					$realCity = $this->_ci->city->getRealCity($item['landingCity']);
					if(in_array($item['landingCity'], $ends) || in_array($realCity, $ends)) {
						$endCity = $item['landingCity'];
					}
				}
			}
			if(!empty($endCity)) {
				foreach($flights as $item) {
					$departFlightNos[] = $item['flightNo'];
					if($item['landingCity'] == $endCity) {
						return $departFlightNos;
					}
				}
			}
			$departFlightNos = [];
		}
	
		// 方案二：直飞的拆分成两个方向
		if(count($flights) == 2) {
			$departFlightNos[] = $flights[0]['flightNo'];
			return $departFlightNos;
		}
	
		// 方案三：对于起程和回程转机城市都相同的。算出目的地
		$isAllHasCities = true;
		foreach($flights as $item) {
			if(empty($item['takeoffCity'])) {
				$isAllHasCities = false;
			}
		}
		if($isAllHasCities && count($flights) > 2) {
			$departCities = [];
			foreach($flights as $item) {
				if(!in_array($item['takeoffCity'], $departCities)) {
					$departCities[] = $item['takeoffCity'];
				} else {
					break;
				}
			}
			if(count($departCities) != count($flights)) {
				$endCity = $departCities[count($departCities) - 1];
				
				foreach($flights as $item) {
					$departFlightNos[] = $item['flightNo'];
					if($item['landingCity'] == $endCity) {
						return $departFlightNos;
					}
				}
				
			}
			$departFlightNos = [];
		}
	
		// 方案4：默认都是起程，最后一个是返程
		foreach($flights as $item) {
			$departFlightNos[] = $item['flightNo'];
		}
		
		array_pop($departFlightNos);
		
		echo '方案四'.PHP_EOL; // debug, chenlei
		return $departFlightNos;
	}
	
	public function priceCal($code, $orgPrices) // 处理价格日历
	{
		if(empty($orgPrices)) {
			return FALSE;
		}
		
		$prices = [];
		$time = time();
		foreach($orgPrices as $date => $row) {
			$departTime = strtotime($date); // 产品出发时间
			if($departTime < ($time + 5 * 24 * 60 * 60)) { // 过期时间或者5天内全部过期的排除在外
				continue;
			}
			$prices[$date] = (object) $row;
		}
		
		if(empty($prices)) { // 过滤后价格日历为空时，下架产品
			$this->setProductStatus($code, 'stop');
			return false;
		}
		
		// 出发日期
		$startFestivals = [];
		$startMonths = [];
		$startDays = [];
		foreach($prices as $date => $row) {
			foreach($this->periods as $period) {
				if(!empty($period['span']) && !empty($period['span']['start'])  && !empty($period['span']['start']->sec)
				&& !empty($period['span']['end']) && !empty($period['span']['end']->sec)) {
					if(trim($period['title']) == '全部') {
						continue;
					}
					$startDate = date('Y-m-d', $period['span']['start']->sec);
					$endDate = date('Y-m-d', $period['span']['end']->sec);
					if($date >= $startDate && $date <= $endDate) {
						if(!in_array($period['title'], $startMonths) && !in_array($period['title'], $startFestivals)) {
							$isMonth = intval(mb_substr($period['title'], 0, 1));
							if($isMonth >= 1 && $isMonth <= 12 ) {
								$startMonths[] = $period['title'];
							} else {
								$startFestivals[] = $period['title'];
							}
						}
					}
		
				}
			}
		
		}
		
		$startDays = array_merge($startFestivals, $startMonths);
		$startDate = implode($startDays, '、');
		
		$newProd = array(
				'prices'       => (object) $prices,
				'startDate'    => $startDate,
				'sourcestatus' => 'normal',
				'grabtime'     => new MongoDate(),
		);
		
		$this->_ci->cimongo->db->duoqugrabs->findAndModify(array('code'=>$code), array('$set'=> $newProd));
		
		// 自售产品同步价格日历
		$selfProd = $this->_ci->cimongo->db->duoquproducts->findOne(['grabcode' => $code]);
		if(!empty($selfProd)) { // 自售	
			if(!empty($selfProd['sku'])) {
				if(empty($prices)) {
					$selfProd['sku'] = null;
				} else {
					foreach($selfProd['sku'] as $day => $item) {
						if(empty($prices[$day])) {
							unset($selfProd['sku'][$day]);
						}
					}
				}
				
				foreach($prices as $day => $item) {
					$item = (array) $item;
					if(empty($selfProd['sku'][$day])) {
						$selfProd['sku'][$day] = array(
								'amount'    => floatval(99999),
								'diffprice' => !empty($item['diffprice']) ? floatval($item['diffprice']) : floatval(0),
								'buy_range' => array(
										'max'   => empty($item['yuwei']) ? floatval(1) : floatval($item['yuwei']),
										'min'   => floatval(1),
								),
						);
					}
						
					$selfProd['sku'][$day]['buy_range']['max'] = empty($item['yuwei']) ? floatval(1) : floatval($item['yuwei']);
					$selfProd['sku'][$day]['price'] = floatval($item['price']);
					
					if(!empty($item['childprice'])) {
						$selfProd['sku'][$day]['childprice'] = floatval($item['childprice']);
					} else {
						if(array_key_exists('childprice', $selfProd['sku'][$day])) {
							unset($selfProd['sku'][$day]['childprice']);
						}
					}
					
					$selfProd['sku'][$day]['yuwei'] = !empty($item['yuwei']) ? floatval($item['yuwei']) : floatval(0);
					
					foreach($selfProd['sku'][$day] as $k => $v) {
						if(is_array($v)) {
							foreach($v as $ck => $cv) {
								if(is_int($cv)) {
									$selfProd['sku'][$day][$k][$ck] = floatval($cv);
								}else if(is_array($cv)) {
									foreach($cv as $c2k => $c2v) {
										if(is_int($c2v)) {
											$selfProd['sku'][$day][$k][$ck][$c2k] = floatval($c2v);
										}
									}
								}
							}
						} else if(is_int($v)) {
							$selfProd['sku'][$day][$k] = floatval($v);
						}
					}
				}
				
				$selfProd['sku'] = (object) $selfProd['sku'];
				
				$this->_ci->cimongo->db->duoquproducts->findAndModify(['grabcode' => $code], ['$set' => ['sku' => $selfProd['sku']]]);
			}
		}
		
		echo date('Y-m-d H:i:s')." update product {$code} prices cal success ".PHP_EOL;
		
		return true;
	}
	
	public function setProductStatus($code, $status)
	{
		// 设置产品状态
		if ($status == 'stop') {
			$updated = array(
					'status'       => 'stop',
					'sourcestatus' => 'stop',
					'grabtime'     => new MongoDate(),
			);
			$queryProd = $this->_ci->cimongo->db->duoqugrabs->findOne(['code' => $code]);
			
			if(!empty($queryProd)) {
				$this->_ci->cimongo->db->duoqugrabs->findAndModify(['code' => $code], ['$set' => $updated]);
				$this->_ci->cimongo->db->duoqugrabindexes->findAndModify(['code' => $code], ['$set' => ['status' => 'stop']]);
				$this->_ci->cimongo->db->duoquproducts->findAndModify(['grabcode' => $code], ['$set' => ['status' => 'stop']]);
				
				echo date('Y-m-d H:i:s') . " update product {$code} set status {$status}".PHP_EOL;
				return TRUE;
			}
			
			return FALSE;
		} else {
			$this->_ci->cimongo->db->duoqugrabs->findAndModify(['code' => $code], ['$set' => ['sourcestatus' => 'normal', 'grabtime' => new MongoDate()]]);
		}
		
		echo date('Y-m-d H:i:s') . " update product {$code} set status {$status}".PHP_EOL;
		return TRUE;
	}
	
	// 下架产品列表
	public function stopProducts($source, $startGrabTime)
	{
		$count = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = [];
		$condition['grabtime']  = ['$lt' => $startGrabTime];
		$condition['source'] = $source;
		$condition['$or'] = [['status' => 'normal'], ['sourcestatus' => 'normal']];
		
		$total = $this->_ci->cimongo->db->duoqugrabs->count($condition);
	
		$totalCount = ceil($total/$max);
	
		$i = 0;
		while($i < $totalCount) {
			echo 'offset: '.($i * $max).PHP_EOL;
				
			$results = $this->_ci->cimongo->db->duoqugrabs->find($condition, ['hotels' => 0, 'flights' => 0])->sort(['_id' => 1])->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
				
			foreach($products as $prod) {
				echo $prod['code'].PHP_EOL;
				$querySource = $this->_ci->cimongo->srcdb->duoqusourcedatas->findOne(['sourcestatus' => 'normal', 'code' => $prod['code']]);
				echo 'empty source: '.$querySource['code'].PHP_EOL;
				$this->setProductStatus($querySource['code'], 'stop');
				$count++;
			}
				
			$i++;
		}
	
		echo date('Y-m-d H:i:s').' stop duoqugrabs products success total: '.$count.PHP_EOL;
	
		return;
	}
}

?>