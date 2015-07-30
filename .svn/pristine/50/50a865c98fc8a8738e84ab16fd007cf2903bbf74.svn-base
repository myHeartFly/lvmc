<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * 抓取自订价格
 */
class CustomPrice {
	private $_ci;
	private $periods;
	private $USD2CNYRate; // 美元对人民币汇率
	private $ip; // 伪造ip;
	
	public function __construct()
	{
		$this->_ci = & get_instance();
		
		$this->_ci->load->helper('url');
		$this->_ci->load->library('curl');
		$this->_ci->load->library('cimongo');
		$this->_ci->load->helper('htmldom');
	}
	
	public function index($source = '')
	{
		$offset = 0;
		$total = 0;
		$max = 300;
		
		$results = $this->_ci->cimongo->db->duoqudicts->find(['__t' => 'Period'])->sort(['span.end' => 1, 'span.start' => 1]);
		$this->periods = iterator_to_array($results);
		
		$condition = ['status' => 'normal'];
		// $condition = ['status' => 'normal', 'baseprices' => ['$exists' => true]];
		if(!empty($source)) {
			$condition['source'] = $source;
		}
// 		$condition['code'] = ['$nin' => ['AY58536', 'AY58648', 'AY60197', 'AY48755', 'AY58659', 'AY59965', 'AY60039']]; // debug, chenlei
// 		$condition['code'] = 'AY46775'; // debug, chenlei
// 		$condition['code'] = 'AY58536'; // debug, chenlei
// 		$condition['end'] = '香港'; // debug, chenlei
		$total = $this->_ci->cimongo->db->duoqugrabs->count($condition);
		
// 		$offset = floor($total/5) * 4;
// 		$total = floor($total/5) * 4 + ceil($total/5);
		do {
			$results = $this->_ci->cimongo->db->duoqugrabs->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
			
			foreach($products as $product) {
				echo "deal product: {$product['code']} ". date('Y-m-d H:i:s') . "\n"; // debug, chenlei
				$result = $this->dealProduct($product);
				if($result === FALSE && !empty($product['baseprices'])) {
					$this->deleteBasePrices($product['_id']);
				}
			}
			echo "offset: {$offset}\n"; // debug, chenlei
		} while($offset < $total);

		echo 'grab custom price success';
	}
	
	public function updateFirstPeriodPrices($condition = [])
	{
		$offset = 0;
		$total = 0;
		$max = 300;
		
		$results = $this->_ci->cimongo->db->duoqudicts->find(['__t' => 'Period'])->sort(['span.end' => 1, 'span.start' => 1]);
		$this->periods = iterator_to_array($results);
		
		if(empty($condition)) {
			$condition = ['status' => 'normal', 'baseprices' => ['$exists' => true]];
		}

// 		$condition['code'] = 'AY58536'; // debug, chenlei
		$total = $this->_ci->cimongo->db->duoqugrabs->count($condition);
		
		do {
			$results = $this->_ci->cimongo->db->duoqugrabs->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
				
			foreach($products as $product) {
				if(empty($product['baseprices'])) {
					continue;
				}
				echo "deal product one key price: {$product['code']} ". date('Y-m-d H:i:s') . "\n"; // debug, chenlei
				$result = $this->dealProductOnePrice($product);
// 				if($result === FALSE && !empty($product['baseprices'])) {
// 					$this->deleteBasePrices($product['_id']);
// 				}
			}
			echo "offset: {$offset}\n"; // debug, chenlei
		} while($offset < $total);
		
		echo 'grab custom price one key success';
	}
	
	private function _getDOM($url, $referer = '') {
		$options  = array(
				'http' => array(
						'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36',
						'header' => 'X-Forwarded-For: '.(!empty($this->ip) ? $this->ip : '211.144.106.58'), // 伪造ip (中国)
						'Referer' => $referer,
				)
		);
	
		$context  = stream_context_create($options, ['timeout' => 10]);
		
// 		$fp = fopen($url, 'r', false, $context); // debug, chenlei, 3 rows
// 		$response = stream_get_contents($fp);
// 		file_put_contents(FCPATH.'/public/grab/test.html', $response);
		
		$dom = @file_get_html($url, false, $context);
		
		return $dom;
	}
	
	private function _getText($url, $referer = '') {
		$options  = array(
				'http' => array(
						'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36',
 						'header' => 'X-Forwarded-For: '.(!empty($this->ip) ? $this->ip : '211.144.106.58'), // 伪造ip (中国)
						'Referer' => !empty($referer) ? $referer : $url,
				)
		);
		$context  = stream_context_create($options, ['timeout' => 10]);
		$response = @file_get_contents($url, false, $context);
	
		return $response;
	}
	
	public function dealProductByCode($code) 
	{
		$queryProduct = $this->_ci->cimongo->db->duoqugrabs->findOne(['code' => $code]);
		if(!empty($queryProduct)) {
			$results = $this->_ci->cimongo->db->duoqudicts->find(['__t' => 'Period'])->sort(['span.end' => 1, 'span.start' => 1]);
			$this->periods = iterator_to_array($results);
			
			$result = $this->dealProduct($queryProduct);
			if($result === FALSE && !empty($queryProduct['baseprices'])) {
				$this->deleteBasePrices($queryProduct['_id']);
			}
			return true;
		} else {
			return false;
		}
	}
	
	private function dealProduct($prod) // 处理单产品自订价格抓取。
	{
		$flightIds = [];
		$flights = [];
		$hotelIds = [];
		$hotels = [];
		if(empty($prod['prices'])) return false; // 没有价格日历产品
		if(empty($prod['flights']) || empty($prod['hotels'])) return false; // 保证产品可以完整抓取自订价格
		foreach($prod['flights'] as $flight) {
			if(empty($flight['day'])) return false; // 保证航班可以查询出发，返程日期
			$flights[$flight['flight']->{'$id'}] = $flight;
			$flightIds[] = $flight['flight'];
		}
		foreach($prod['hotels'] as $hotel) {
			if(empty($hotel['day']) || empty($hotel['night']) || empty($hotel['hotel'])) return false; // 保证酒店可以查询入住日期和住几晚
			$hotels[$hotel['hotel']->{'$id'}] = $hotel;
			$hotelIds[] = $hotel['hotel'];
		}
		
		if(count($flightIds) > 20 || count($hotelIds) > 20) { // 防止异常导致的查询数量过多，日志超过35K
			return false;
		}
		
		$queryFlightRets = $this->_ci->cimongo->db->flightbases->find(['_id' => ['$in' => $flightIds]]);
		$queryFlights = iterator_to_array($queryFlightRets);
		if(empty($queryFlights) || count($queryFlights) != count($flightIds)) return false; // 航班组有缺少
		
		$queryHotelRets = $this->_ci->cimongo->db->hotelbases->find(['_id' => ['$in' => $hotelIds]]);
		$queryHotels = iterator_to_array($queryHotelRets);
		if(empty($queryHotels) || count($queryHotelRets) != count($hotelIds)) return false;
		
		$flightAirs = [];
		foreach($queryFlights as $flight) {
			if(empty($flight['flightNo'])) return false;
			if(!empty($flight['airline'])) {
				$flight['airline'] = trim(str_replace("\n", '', $flight['airline']));
			}
			$flight['flightNo'] = trim(str_replace("\n", '', $flight['flightNo']));
			$flights[$flight['_id']->{'$id'}]['flight'] = $flight;
			$flightAirs[] = (empty($flight['airline']) ? '' : $flight['airline']. ' '). $flight['flightNo'];
		}
		$flightsAirStr = trim(str_replace("\n", '', implode('、', $flightAirs)));
		foreach($queryHotels as $hotel) {
			if(empty($hotel['url'])) return false; // 保证有链接可抓取
			if(strpos($hotel['url'], 'booking.com') === false) return false; // 保证是booking 上的链接
			
			$hotels[$hotel['_id']->{'$id'}]['hotel'] = $hotel;
		}
		
		// 产品为可抓取自订价格状态
		$flightsPrices = [];
		$priHotels = [];
		$periods = [];
		$periodKeyIndexs = [];
		$hotelsNotMatchNum = [];
		foreach($hotelIds as $_id) {
			$priHotels[$_id->{'$id'}] = [];
			$hotelsNotMatchNum[$_id->{'$id'}] = 0;
		}
		foreach($this->periods as $period) {
			if(trim($period['title']) == '全部') {
				continue;
			}
			$periodKeyIndexs[] = $period['key'];
			$periods[$period['key']] = $period;
		}
		$periodCalPrices = []; // 整理各个时间段内的价格日历
		foreach($prod['prices'] as $date => $row) {
			$departTime = strtotime($date); // 产品出发时间
			if($departTime < time() + 5 * 24 * 60 * 60) { // 过期时间排除在外
				continue;
			}
			foreach($periods as $periodKey => $period) {
				if(!empty($period['span']) && !empty($period['span']['start'])  && !empty($period['span']['start']->sec)
				&& !empty($period['span']['end']) && !empty($period['span']['end']->sec)) {
					$startDate = date('Y-m-d', $period['span']['start']->sec);
					$endDate = date('Y-m-d', $period['span']['end']->sec);
					if($date >= $startDate && $date <= $endDate) {
						if(!isset($periodCalPrices[$periodKey])) {
							$periodCalPrices[$periodKey] = [];
						}
						$periodCalPrices[$periodKey][$date] = $row['price'];
					}
		
				}
			}
		}
		foreach($periodCalPrices as $periodKey => & $priceCal) { // 价格最低的排在最前面，可以取到最低价格日历的自订价格
			asort($priceCal); 
		}
		
		foreach($hotels as $hkey => $v) { // 抓取酒店
			foreach($periodCalPrices as $periodKey => & $priceCal) {
				$hotelNotMatchNum = 0;
				foreach($priceCal as $date => $price) {
					if($hotelNotMatchNum > 10 || !empty($priHotels[$hkey][$periodKey])) {
						break;
					}
					$hotelPriceItems = $this->grabBookingHotel($date, $hotels[$hkey]);
					if(empty($hotelPriceItems)) {
						$hotelNotMatchNum++;
						continue;
					}
						
					$nr = new stdClass();
					$nr->name = $hotels[$hkey]['hotel']['name'];
					$nr->items = $hotelPriceItems;
					$nr->price = $hotelPriceItems[0]->price;
					$nr->type = 'hotel';
					foreach($hotelPriceItems as &$item){
						if($item->price < $nr->price) {
							$nr->price = $item->price;
						}
						$item->price = floatval($item->price);
					}
					
					$nr->price = floatval($nr->price);
					$priHotels[$hkey][$periodKey] = $nr;
				}
			}
		}
		
		$emptyFlightDates = [];
		foreach($periodCalPrices as $periodKey => $priceCal) { // 航班
			$flightNotMatchNum = 0;
			foreach($priceCal as $date => $price) {
				if($flightNotMatchNum > 10 || !empty($flightsPrices[$periodKey])) {
					break;
				}
				$prod['flights'] = $flights;
				if(!empty($emptyFlightDates[$date])) { // 防止日期重复访问
					$flightNotMatchNum++;
					continue;
				}
				$flightsPriceItems = $this->grabTianXunFlights($date, $prod);
				if(empty($flightsPriceItems)) {
					$emptyFlightDates[$date] = true;
					$flightNotMatchNum++;
					continue;
				}
				if(!empty($flightsPriceItems)) {
					$newFlight = new stdClass();
					$newFlight->name = $flightsAirStr;
					$newFlight->items = $flightsPriceItems;
					$newFlight->price = $flightsPriceItems[0]->price;
					$newFlight->type = 'flight';
					foreach($flightsPriceItems as &$item){
						if($item->price < $newFlight->price) {
							$newFlight->price = $item->price;
						}
						$item->price = floatval($item->price);
					}
					
					$newFlight->price = floatval($newFlight->price);
					$flightsPrices[$periodKey] = $newFlight;
				}
			}
		}
		
		$isAllMatch = true;
		
		if(empty($priHotels)) {
			$isAllMatch = false;
		}
		
		if(!empty($priHotels)) {
			foreach($priHotels as $hkey => $v) {
				if(empty($priHotels[$hkey])) {
					$isAllMatch = false;
				}
			}
		}
		
		if(empty($flightsPrices)) {
			$isAllMatch = false;
		}
		
		$periodsCount = count($periods);
		$basePrices = new stdClass();
		foreach($periodCalPrices as $key => $priceCal) {
			$basePrices->{$key} = new stdClass();
			$basePrices->{$key}->detail = [];
			$curFlightPrices = '';
			if(empty($flightsPrices[$key])) {
				$keyIndex = array_search($key, $periodKeyIndexs);
				$isMatchFlight = false;
				for($i = $keyIndex; $i < $periodsCount; $i++) {
					$nextKey = $periodKeyIndexs[$i];
					if(!empty($flightsPrices[$nextKey])) {
						$curFlightPrices = $flightsPrices[$nextKey];
						$isMatchFlight = true;
						break;
					}
				}
				if(!$isMatchFlight) {
					for($i = $keyIndex; $i >= 0; $i--) {
						$previousKey = $periodKeyIndexs[$i];
						if(!empty($flightsPrices[$previousKey])) {
							$curFlightPrices = $flightsPrices[$previousKey];
							break;
						}
					}
				}
			} else {
				$curFlightPrices = $flightsPrices[$key];
			}
			if(!empty($curFlightPrices)) {
				$basePrices->{$key}->detail[] = $curFlightPrices;
			}
			
			foreach($priHotels as $hkey => $v) {
				$curHotelPrices = '';
				if(empty($priHotels[$hkey][$key])) {
					$keyIndex = array_search($key, $periodKeyIndexs);
					$isMatchFlight = false;
					for($i = $keyIndex; $i < $periodsCount; $i++) {
						$nextKey = $periodKeyIndexs[$i];
						if(!empty($flightsPrices[$nextKey]) && !empty($priHotels[$hkey][$nextKey])) {
							$curHotelPrices = $priHotels[$hkey][$nextKey];
							$isMatchFlight = true;
							break;
						}
					}
					if(!$isMatchFlight) {
						for($i = $keyIndex; $i >= 0; $i--) {
							$previousKey = $periodKeyIndexs[$i];
							if(!empty($flightsPrices[$previousKey]) && !empty($priHotels[$hkey][$previousKey])) {
								$curHotelPrices = $priHotels[$hkey][$previousKey];
								break;
							}
						}
					}
				} else {
					$curHotelPrices = $priHotels[$hkey][$key];
				}
				if(!empty($curHotelPrices)) {
					$basePrices->{$key}->detail[] = $curHotelPrices;
				}
			}
		}
		
// 		var_dump($basePrices); // debug, chenlei
		
		$testEmpty = (array) $basePrices;
		if (!empty($testEmpty)) {
			if($isAllMatch) {
				foreach($basePrices as $key => $row){
					$basePrices->{$key}->price = 0;
					if(!isset($row->detail)) {
						unset($basePrices->{$key});
						continue;
					}
					foreach($row->detail as &$child) {
						if(isset($child->price)) {
							$basePrices->{$key}->price += $child->price;
							$child->price = floatval($child->price);
							if(isset($child->items)) {
								foreach($child->items as &$item) {
									if(isset($item->price)) {
										$item->price = floatval($item->price);
									}
								}
							}
						}
					}
					$basePrices->{$key}->price = floatval($basePrices->{$key}->price);
				}
				
				echo date('Y-m-d H:i:s')." updated prod baseprices all, _id: {$prod['_id']} \n";
			} else {
				echo date('Y-m-d H:i:s')." updated prod baseprices, _id: {$prod['_id']} \n";
			}
			
// 			var_dump('$basePrices', $basePrices); // debug, chenlei
			$this->_ci->cimongo->db->duoqugrabs->findAndModify(['_id' => $prod['_id']], ['$set' => ['baseprices' => $basePrices]]);
			
			return true;
		}
		
		return false;
	}

	private function dealProductOnePrice($prod) // 处理单产品自订价格第一个时间key抓取。
	{
		$flightIds = [];
		$flights = [];
		$hotelIds = [];
		$hotels = [];
		if(empty($prod['prices'])) return false; // 没有价格日历产品
		if(empty($prod['flights']) || empty($prod['hotels'])) return false; // 保证产品可以完整抓取自订价格
		foreach($prod['flights'] as $flight) {
			if(empty($flight['day'])) return false; // 保证航班可以查询出发，返程日期
			$flights[$flight['flight']->{'$id'}] = $flight;
			$flightIds[] = $flight['flight'];
		}
		foreach($prod['hotels'] as $hotel) {
			if(empty($hotel['day']) || empty($hotel['night']) || empty($hotel['hotel'])) return false; // 保证酒店可以查询入住日期和住几晚
			$hotels[$hotel['hotel']->{'$id'}] = $hotel;
			$hotelIds[] = $hotel['hotel'];
		}
		
		if(count($flightIds) > 20 || count($hotelIds) > 20) { // 防止异常导致的查询数量过多，日志超过35K
			return false;
		}
		
		$queryFlightRets = $this->_ci->cimongo->db->flightbases->find(['_id' => ['$in' => $flightIds]]);
		$queryFlights = iterator_to_array($queryFlightRets);
		if(empty($queryFlights) || count($queryFlights) != count($flightIds)) return false; // 航班组有缺少
		
		$queryHotelRets = $this->_ci->cimongo->db->hotelbases->find(['_id' => ['$in' => $hotelIds]]);
		$queryHotels = iterator_to_array($queryHotelRets);
		if(empty($queryHotels) || count($queryHotelRets) != count($hotelIds)) return false;
		
		$flightAirs = [];
		foreach($queryFlights as $flight) {
			if(empty($flight['flightNo'])) return false;
			if(!empty($flight['airline'])) {
				$flight['airline'] = trim(str_replace("\n", '', $flight['airline']));
			}
			$flight['flightNo'] = trim(str_replace("\n", '', $flight['flightNo']));
			$flights[$flight['_id']->{'$id'}]['flight'] = $flight;
			$flightAirs[] = (empty($flight['airline']) ? '' : $flight['airline']. ' '). $flight['flightNo'];
		}
		$flightsAirStr = trim(str_replace("\n", '', implode('、', $flightAirs)));
		foreach($queryHotels as $hotel) {
			if(empty($hotel['url'])) return false; // 保证有链接可抓取
			if(strpos($hotel['url'], 'booking.com') === false) return false; // 保证是booking 上的链接
			
			$hotels[$hotel['_id']->{'$id'}]['hotel'] = $hotel;
		}
		
		// 产品为可抓取自订价格状态
		$periods = [];
		$periodKeyIndexs = [];
		$hotelsNotMatchNum = [];
		$hotelPrices = [];
		foreach($hotelIds as $_id) {
			$hotelPrices[$_id->{'$id'}] = [];
			$hotelsNotMatchNum[$_id->{'$id'}] = 0;
		}
		foreach($this->periods as $period) {
			if(trim($period['title']) == '全部') {
				continue;
			}
			$periodKeyIndexs[] = $period['key'];
			$periods[$period['key']] = $period;
		}
		$firstPeriods = []; // 第一个日期所在的时段，可能属于两个时段， 5月 or 五一
		$issetFirstPeriods = false;
		$periodCalPrices = []; // 整理各个时间段内的价格日历
		foreach($prod['prices'] as $date => $row) {
			$departTime = strtotime($date); // 产品出发时间
			if($departTime < time() + 5 * 24 * 60 * 60) { // 过期时间排除在外
				continue;
			}
			foreach($periods as $periodKey => $period) {
				if(!empty($period['span']) && !empty($period['span']['start'])  && !empty($period['span']['start']->sec)
				&& !empty($period['span']['end']) && !empty($period['span']['end']->sec)) {
					$startDate = date('Y-m-d', $period['span']['start']->sec);
					$endDate = date('Y-m-d', $period['span']['end']->sec);
					if($date >= $startDate && $date <= $endDate) {
						if(!isset($periodCalPrices[$periodKey])) {
							$periodCalPrices[$periodKey] = [];
						}
						if(!$issetFirstPeriods && !in_array($periodKey, $firstPeriods)) {
							$firstPeriods[] = $periodKey;
						}
						$periodCalPrices[$periodKey][$date] = $row['price'];
					}
				}
			}
			if(!empty($firstPeriods)) {
				$issetFirstPeriods = true;
			}
		}
		foreach($periodCalPrices as $periodKey => & $priceCal) { // 价格最低的排在最前面，可以取到最低价格日历的自订价格
			asort($priceCal); 
		}
		
		$hotelPrices = [];
		foreach($hotels as $hkey => $v) { // 抓取酒店
			foreach($periodCalPrices as $periodKey => & $priceCal) {
				if(!empty($hotelPrices[$hkey])) {
					break;
				}
				$hotelNotMatchNum = 0;
				foreach($priceCal as $date => $price) {
					if($hotelNotMatchNum > 10 || !empty($hotelPrices[$hkey][$periodKey])) {
						break;
					}
					$hotelPriceItems = $this->grabBookingHotel($date, $hotels[$hkey]);
					if(empty($hotelPriceItems)) {
						$hotelNotMatchNum++;
						continue;
					}
						
					$nr = new stdClass();
					$nr->name = $hotels[$hkey]['hotel']['name'];
					$nr->items = $hotelPriceItems;
					$nr->price = $hotelPriceItems[0]->price;
					$nr->type = 'hotel';
					foreach($hotelPriceItems as &$item){
						if($item->price < $nr->price) {
							$nr->price = $item->price;
						}
						$item->price = floatval($item->price);
					}

					$nr->price = floatval($nr->price);
					$hotelPrices[$hkey] = $nr;
					break;
				}
			}
		}
		
		$flightsPrice = NULL;
		$emptyFlightDates = [];
		foreach($periodCalPrices as $periodKey => $priceCal) { // 航班
			$flightNotMatchNum = 0;
			if(!empty($flightsPrice)) {
				break;
			}
			foreach($priceCal as $date => $price) {
				if($flightNotMatchNum > 10 || !empty($flightsPrices[$periodKey])) {
					break;
				}
				$prod['flights'] = $flights;
				if(!empty($emptyFlightDates[$date])) { // 防止日期重复访问
					$flightNotMatchNum++;
					continue;
				}
				$flightsPriceItems = $this->grabTianXunFlights($date, $prod);
				if(empty($flightsPriceItems)) {
					$emptyFlightDates[$date] = true;
					$flightNotMatchNum++;
					continue;
				}
				if(!empty($flightsPriceItems)) {
					$newFlight = new stdClass();
					$newFlight->name = $flightsAirStr;
					$newFlight->items = $flightsPriceItems;
					$newFlight->price = $flightsPriceItems[0]->price;
					$newFlight->type = 'flight';
					foreach($flightsPriceItems as &$item){
						if($item->price < $newFlight->price) {
							$newFlight->price = $item->price;
						}
						$item->price = floatval($item->price);
					}
					
					$newFlight->price = floatval($newFlight->price);
					$flightsPrice = $newFlight;
					break;
				}
			}
		}
		
		$isAllMatch = true;
		
		if(empty($priHotels)) {
			$isAllMatch = false;
		}
		
		if(!empty($priHotels)) {
			foreach($priHotels as $hkey => $v) {
				if(empty($priHotels[$hkey])) {
					$isAllMatch = false;
				}
			}
		}
		
		if(empty($flightsPrices)) {
			$isAllMatch = false;
		}
		
		$basePrices = new stdClass();

		$updates = [];

		foreach($firstPeriods as $index => $key) {
			$updates['baseprices.' . $key] = new stdClass();
			$updates['baseprices.' . $key]->price = 0;
			$updates['baseprices.' . $key]->detail = [];
			if(!empty($flightsPrice)) {
				$updates['baseprices.' . $key]->detail[] = $flightsPrice;
			}
			foreach($hotelPrices as $hKey => $hotelPrice) {
				if(!empty($hotelPrice)) {
					$updates['baseprices.' . $key]->detail[] = $hotelPrice;
				}
			}
			foreach($updates['baseprices.' . $key]->detail as &$child) {
				if(isset($child->price)) {
					if($isAllMatch) {
						$updates['baseprices.' . $key]->price += $child->price;
					}
					$child->price = floatval($child->price);
					if(isset($child->items)) {
						foreach($child->items as &$item) {
							if(isset($item->price)) {
								$item->price = floatval($item->price);
							}
						}
					}
				}
			}
			
			if(!empty($updates['baseprices.' . $key]->price)) {
				$updates['baseprices.' . $key]->price = floatval($updates['baseprices.' . $key]->price);
			} else {
				unset($updates['baseprices.' . $key]->price);
			}
			
		}
		
		if(!empty($updates)) {
			$this->_ci->cimongo->db->duoqugrabs->findAndModify(['_id' => $prod['_id']], ['$set' => $updates]);
			echo date('Y-m-d H:i:s')." updated prod baseprices first key, _id: {$prod['_id']} \n";
			
			return true;
		}
		
		return false;
	}
	
	private function deleteBasePrices($prodId)
	{
		echo date('Y-m-d H:i:s')." deleted prod baseprices, _id: {$prodId} \n";
		$this->_ci->cimongo->db->duoqugrabs->findAndModify(['_id' => $prodId], ['$unset' => ['baseprices' => true]]);
		return true;
	}
	
	private function grabTianXunFlights($date, $prod) // 获取天巡机票价格
	{
		echo "deal flight: {$prod['code']} ". date('Y-m-d H:i:s') . "\n"; // debug, chenlei
		
		if(empty($prod['flights']) || count($prod['flights']) < 2) {
			return false;
		}
		$this->changeIp();
		
		$prices = []; // 产品价格
		
		$this->_ci->load->library('city/city');
		
		$departFlights = $backFlights = [];
		$departFlightNos = $backFlightNos = []; // 航班号
		$departAirCodes = $backAirCodes = []; // 航班号
		$depTakeoffDay;
		$depLandingDay;
		$backTakeoffDay;
		$backLandingDay;
		foreach($prod['flights'] as $row) {		
			$aircode = strtoupper(substr($row['flight']['flightNo'], 0, 2));
			
			if($row['trip'] == 'depart') {
				empty($depTakeoffDay) AND $depTakeoffDay = (int) $row['day'];
				$departFlightNos[] = trim($row['flight']['flightNo']);
				$departFlights[] = $row;
				$departAirCodes[] = $aircode;
			} else {
				empty($backTakeoffDay) AND $backTakeoffDay = (int) $row['day'];
				$backFlightNos[] = trim($row['flight']['flightNo']);
				$backFlights[] = $row;
				$backAirCodes[] = $aircode;
			}
		}
		
		if(empty($departFlights) ||  empty($backFlights)) {
			return false;
		}
		if(!empty($departFlights[0]['flight']['takeoffCity'])) {
			$startCityName = $departFlights[0]['flight']['takeoffCity'];
			$cityInfo = $this->_ci->city->getTianXunCity($startCityName);
			if(empty($cityInfo)) {
				$startCityName = $prod['start'][0];
				$cityInfo = $this->_ci->city->getTianXunCity($startCityName);
			}
		}
		if(!empty($departFlights[count($departFlights)-1]['flight']['landingCity'])) {
			$endCityName = $departFlights[count($departFlights)-1]['flight']['landingCity'];
			$cityInfo2 = $this->_ci->city->getTianXunCity($endCityName);
			if(empty($cityInfo2)) {
				$endCityName = $prod['end'][count($prod['end']) -1];
				$cityInfo2 = $this->_ci->city->getTianXunCity($endCityName);
			}
		}
		
		if(empty($cityInfo) || empty($cityInfo2) || empty($cityInfo['code']) || empty($cityInfo2['code'])) {
			return false;
		}
		$startCity = $cityInfo['map'];
		$startCode = $cityInfo['code'];
		$endCity = $cityInfo2['map'];
		$endCode = $cityInfo2['code'];
		
		if(!empty($departFlights[count($departFlights)-1]['day'])) {
			$depLandingDay = $departFlights[count($departFlights)-1]['day'];
		}
		if(!empty($backFlights[count($backFlights)-1]['day'])) {
			$backLandingDay = $backFlights[count($backFlights)-1]['day'];
		}
		
		
		if(empty($depTakeoffDay) || empty($backTakeoffDay)) {
			return false;
		}
		
		$cabin_type = 'Economy';
		$departTime = strtotime($date); // 产品出发时间
		$departTakeoffDate = date('Y-m-d', $departTime + ($depTakeoffDay -1) * (24 * 60 * 60)); // 起程起飞日期
		$backTakeoffDate = date('Y-m-d', $departTime + ($backTakeoffDay - 1) * (24 * 60 * 60)); // 返程起飞日期
		
		 // 精确匹配
		$accuratePrices = [];
		// 相同航空公司
		$isSimilarAirs = false;
		$departTakeoffTime = strtotime($departTakeoffDate . ' ' . trim($departFlights[0]['flight']['takeoff']) . ':00');
		$backTakeoffTime = strtotime($backTakeoffDate . ' ' . trim($backFlights[0]['flight']['takeoff']) . ':00');
		$depTakeoffDiff; // 时间差值
		$backTakeoffDiff;
		$similarFlightGroup; // 相似航班存储
		$similarLowestPrice; // 直飞相同航班的优先价格最低
		// 直飞
		$isDirect = false; // 是否是直飞 (直飞)
		$lowestPrice; // 最低价格 （直飞）
		$lowestFlightGroup; // 最低价格对应航班 (直飞)
		if(count($departFlightNos) == 1 && count($backFlightNos) == 1) {
			$isDirect = true;
		}
		
		// 采集
		$page = 0;
		$totalPage = 1;
		
		// storage cookie
// 		$cookieFolder = FCPATH.'/public/grab/cookies';
// 		if(!file_exists($cookieFolder)) {
// 			mkdir($cookieFolder, 0775, true);
// 		}
// 		$cookieFile = "{$cookieFolder}/tianxun.tmp";
// 		if(file_exists($cookieFile)) {
// 			unlink($cookieFile);
// 		}
		// http://www.tianxun.com/intl-round-pek-sel.html?depdate=2015-03-04&rtndate=2015-03-08&cabin=Economy&adult=1&child=0&infant=0
		$url = "http://www.tianxun.com/intl-round-" . strtolower($startCode) . "-" . strtolower($endCode) . ".html?depdate={$departTakeoffDate}&rtndate={$backTakeoffDate}&cabin={$cabin_type}&adult=1&child=0&infant=0";
		
// 		echo $url."\n";
// 		$response = $this->getResponse($url, $cookieFile); // @TODO remove. this is not get the content. 2015.04.03
		$response = $this->_getText($url, $url);
		$reg = "/var PARAMS\s*=\s*([^;]*);/";
		$isMatch = preg_match($reg, $response, $matches);
		if(!$isMatch) {
			return false;
		}
		$propertyStr = $matches[1];
		$properties = json_decode($propertyStr, true);
		
		echo "Cache Key: \n";
		echo "{$properties['cache_key']}\n";
		
		do {
		
			$data;
			$errNum = 0;
			$status = '';
			$maxReqTimes = 100;
			$reqTimes = 0;
			$page++;

			do {
				$time = time().rand(100, 999);
				$reqTimes++;
				
				$depCityEncode = urlencode($properties['depCity']);
				$dstCityEncode = urlencode($properties['dstCity']);
				$cabin_type_name_encode = urlencode($properties['cabin_type_name']);
				// http://www.tianxun.com/flight/ajax_intl_list.php?page=1&sort=price&order=asc&dep_flight_city_code=PEK&dst_flight_city_code=HKG&depart_date=2015-03-04&return_date=2015-03-08&cabin_type=Economy&adults=1&children=0&infants=0&cache_key=ad0312bd-5940-4b3b-a34b-a373e28835e8&depCity=%E5%8C%97%E4%BA%AC&depCityId=1&dstCity=%E9%A6%99%E6%B8%AF&dstCityId=2015&cabin_type_name=%E7%BB%8F%E6%B5%8E%E8%88%B1&status=UpdatesPending&_=1425375072891
				// 直飞http://www.tianxun.com/flight/ajax_intl_list.php?page=1&sort=price&order=asc&dep_flight_city_code=PEK&dst_flight_city_code=HKT&depart_date=2015-03-11&return_date=2015-03-15&cabin_type=Economy&adults=1&children=0&infants=0&cache_key=8950e0cc-f0c9-4c0a-91cb-d4350ab1e8e1&depCity=%E5%8C%97%E4%BA%AC&depCityId=1&dstCity=%E6%99%AE%E5%90%89&dstCityId=4200&cabin_type_name=%E7%BB%8F%E6%B5%8E%E8%88%B1&status=UpdatesPending&filter_stops=0&_=1425960557362
				$dataUrl = "http://www.tianxun.com/flight/ajax_intl_list.php?page={$page}&sort=price&order=asc&dep_flight_city_code={$properties['dep_flight_city_code']}&dst_flight_city_code={$properties['dst_flight_city_code']}&depart_date={$properties['depart_date']}&return_date={$properties['return_date']}&cabin_type={$properties['cabin_type']}&adults=1&children=0&infants=0&cache_key={$properties['cache_key']}&depCity={$depCityEncode}&depCityId={$properties['depCityId']}&dstCity={$dstCityEncode}&dstCityId={$properties['dstCityId']}&cabin_type_name={$cabin_type_name_encode}&status=UpdatesPending";
				if($isDirect) {
					$dataUrl .= "&filter_stops=0&_={$time}";
				} else {
					$dataUrl .= "&_={$time}";
				}
				
				// echo 'list url: '.$dataUrl.PHP_EOL;
// 				$jsonStr = $this->getResponse($dataUrl, $cookieFile); // @TODO remove. this is not get the content. 2015.04.03
				$jsonStr = $this->_getText($dataUrl, $url);
				if(empty($jsonStr)) {
					$this->changeIp();
				}
				if(!empty($jsonStr)) {
					$jsonStr = $this->remove_utf8_bom($jsonStr);
					$data = json_decode($jsonStr, true);
					$status = !empty($data['status']) ? $data['status'] : '';
					if(empty($status)) {
						if(!empty($detailData) && isset($detailData['error']) && $detailData['error'] != 0) {
							$reqTimes = 0;
							$errNum++;
							$this->changeIp();
							if($errNum > 10) {
								break;
							}
						}
					}
					echo 'list status: '.$data['status'].PHP_EOL; // debug, chenlei
					if($status == 'UpdatesComplete' && isset($data['pages'])) {
						$totalPage = $data['pages'];
						break;
					}
				}
				$seconds = $reqTimes < 11 ? 1 : ($reqTimes > 2 ? 3 : 2);
				sleep($seconds);
			} while($status != 'UpdatesComplete' && $reqTimes < $maxReqTimes);
			
			if($status == 'UpdatesComplete' && !empty($data['flights'])) {
				foreach($data['flights'] as $groups) {
					if(empty($groups) || empty($groups['flightInfoList'])) continue;
					
					$isAgent = false;
					
					$grabDepartFlightNos = $grabBackFlightNos = [];
					foreach($groups['flightInfoList'] as $group) {
						if($group['flightAirlineIds'] != $group['flightAirlineIdsOper']) {
							$isAgent = true;
						}
						if($group['directionality'] == 1) {
							$grabDepartFlightNos = explode(',', $group['flightNumber']);
						} else { // 返程
							$grabBackFlightNos = explode(',', $group['flightNumber']);
						}
					}
					
					if(count($grabDepartFlightNos) != count($departFlightNos) || count($grabBackFlightNos) != count($backFlightNos)) { // 数量不一致的可跳过
						continue;
					}
					
					if($isAgent) { // 某些航班天巡上显示的代运营，导致实际航班号跟列表返回的不一致，需要请求详情页的数据
						$errNum = 0;
						$reqTimes = 0;
						$maxReqTimes = 40;
						$status = '';
						$detail = null;

						$this->changeIp();
						
						do {
							$time = time().rand(100, 999);
							if(empty($groups['detailLink'])) { // 有值不存在情况
								break;
							}
							$detailLink = urlencode($groups['detailLink']);
							// http://www.tianxun.com/flight/ajax_intl_detail.php?detail_link=/apiservices/pricing/v1.0/2805bc005aeb43f0aa3cd0972c896448_ecilpojl_7F32039C61A4657CB248AE1F8D3510D1/booking?OutboundLegId=15277-1505051620-MU,FM-1-12195-1505060105&InboundLegId=12195-1505090315-FM,MU-1-15277-1505091445&num=1&cache_key=85e7a8da-b4df-41d0-ade7-e32302bbe3c4&_=1430705676635
							$detailUrl = "http://www.tianxun.com/flight/ajax_intl_detail.php?detail_link={$detailLink}&num={$reqTimes}&cache_key={$properties['cache_key']}&_={$time}";
							// echo 'detail url: '. $detailUrl.PHP_EOL;
							$jsonStr = $this->_getText($detailUrl, $url);
							if(empty($jsonStr)) {
								$this->changeIp();
							}
							if(!empty($jsonStr)) {
								$jsonStr = $this->remove_utf8_bom($jsonStr);
								$detailData = json_decode($jsonStr, true);
								$status = isset($detailData['status']) ? $detailData['status'] : '';

								echo 'detail status: '.$status.PHP_EOL; // debug, chenlei
								if(empty($status)) {
									if(!empty($detailData) && isset($detailData['error']) && $detailData['error'] != 0) {
										$reqTimes = 0;
										$errNum++;
										$this->changeIp();
										if($errNum > 10) {
											break;
										}
									}
									echo 'detail error: '.((!empty($detailData) && isset($detailData['error'])) ? $detailData['error'] : '').PHP_EOL;
								}
								if($status == 'Current') {
									$detail = $detailData;
									break;
								}
							}

							$seconds = $reqTimes < 11 ? 1.5 : ($reqTimes > 20 ? 3 : 2);
							sleep($seconds);
							$reqTimes++;
						} while($status != 'Current' && $reqTimes < $maxReqTimes);
						
						if($status != 'Current' || empty($detail) || empty($detail['flightInfos'])) {
							continue;
						}
						
						$grabDepartFlightNos = $grabBackFlightNos = [];
						foreach($detail['flightInfos'] as $flight) {
							if($flight['directionality'] == 1) {	
								$grabDepartFlightNos[] = $flight['flightNumber'];
							} else { // 返程
								$grabBackFlightNos[] = $flight['flightNumber'];
							}
						}
							
						if(count($grabDepartFlightNos) != count($departFlightNos) || count($grabBackFlightNos) != count($backFlightNos)) { // 数量不一致的可跳过
							continue;
						}
					}
					
					$grabDepartAirCodes = $grabBackAirCodes = [];
					
					foreach($grabDepartFlightNos as $k => $flightNo) {
						$grabDepartAirCodes[] = strtoupper(substr($flightNo, 0, 2));
					}
					foreach($grabBackFlightNos as $k => $flightNo) {
						$grabBackAirCodes[] = strtoupper(substr($flightNo, 0, 2));
					}
					
					$grabDepTakeoffTime = strtotime($departTakeoffDate . ' '. trim($groups['flightInfoList'][0]['depDatetime']) . ':00');
					$grabDepLandingTime = strtotime(trim($groups['flightInfoList'][0]['arrivalDate']) . ' '. trim($groups['flightInfoList'][0]['arrivalDatetime']) . ':00');
					$grabBackTakeoffTime = strtotime($backTakeoffDate . ' '. trim($groups['flightInfoList'][1]['depDatetime']) . ':00');
					$grabBackLandingTime = strtotime(trim($groups['flightInfoList'][1]['arrivalDate']) . ' '. trim($groups['flightInfoList'][1]['arrivalDatetime']) . ':00');
					
					// 除非航班使用的是春秋航空的，否则不选择春秋航空（廉价航空公司）
					if(!in_array('9C', $departAirCodes) && in_array('9C', $grabDepartAirCodes)) {
						continue;
					}
					if(!in_array('9C', $backAirCodes) && in_array('9C', $grabBackAirCodes)) {
						continue;
					}
					
					// 精确匹配航班
					$testIsSame = true;
					$departNosDiff = array_diff($departFlightNos, $grabDepartFlightNos);
					if(!empty($departNosDiff)) {
						$testIsSame = false;
					} else {
						$backNosDiff = array_diff($backFlightNos, $grabBackFlightNos);
						if(!empty($backNosDiff)) {
							$testIsSame = false;
						} else {
							foreach($groups['flightPriceList'] as $row) {
								$nr = new stdClass();
								$nr->source = $row['supplierName'];
								$nr->url = $row['bookingLink'];
								$nr->price = $row['price'];
								$accuratePrices[] = $nr;
							}
							break;
						}
					}
					
					$depTakeoffDiffTest = $grabDepTakeoffTime > $departTakeoffTime ? ($grabDepTakeoffTime - $departTakeoffTime) : ($departTakeoffTime - $grabDepTakeoffTime);
					$backTakeoffDiffTest = $grabBackTakeoffTime > $backTakeoffTime ? ($grabBackTakeoffTime - $backTakeoffTime) : ($backTakeoffTime - $grabBackTakeoffTime);
					
					// 优先取相同航空公司的航班，(转机选择起飞相近的航班优先, 直飞是价格最低的航班优先)
					$testIsSameAirs = true;
					$k = 0;
					foreach($departAirCodes as $v) {
						if($departAirCodes[$k] != $grabDepartAirCodes[$k]) {
							$testIsSameAirs = false;
							break;
						}
						$k++;
					}
					if($testIsSameAirs) {
						$k = 0;
						foreach($backAirCodes as $v) {
							if($backAirCodes[$k] != $grabBackAirCodes[$k]) {
								$testIsSameAirs = false;
								break;
							}
							$k++;
						}
					}
					
					if($testIsSameAirs) {
						if($isDirect) { // 直飞是价格最低的航班优先
							if(!isset($similarLowestPrice)) {
								$similarLowestPrice = $groups['price'];
								$similarFlightGroup = $groups;
							} else {
								if($groups['price'] < $lowestPrice) { // min
									$similarLowestPrice = $groups['price'];
									$similarFlightGroup = $groups;
								}
							}
						} else { // 转机选择起飞相近的航班优先
							if(!isset($depTakeoffDiff)) {
								$depTakeoffDiff = $depTakeoffDiffTest;
								$backTakeoffDiff = $backTakeoffDiffTest;
								$similarFlightGroup = $groups;
							}
								
							if(($depTakeoffDiffTest + $backTakeoffDiffTest) < ($backTakeoffDiff + $depTakeoffDiff)) {
								$depTakeoffDiff = $depTakeoffDiffTest;
								$backTakeoffDiff = $backTakeoffDiffTest;
								$similarFlightGroup = $groups;
							}
						}
					}
					
					// 直飞的航班还可以取最低价格的航班使用
					if($isDirect) {
						if(!isset($lowestPrice)) {
							$lowestPrice = $groups['price'];
							$lowestFlightGroup = $groups;
						} else {
							if($groups['price'] < $lowestPrice) { // min
								$lowestPrice = $groups['price'];
								$lowestFlightGroup = $groups;
							}
						}
					}
				}
			}
			if(!empty($accuratePrices)) {
				break;
			}

		} while ($page < $totalPage);
		
		if(!empty($accuratePrices)) { // 精确匹配
			echo "精确匹配 \n"; // debug, chenlei
			return $accuratePrices;
		} else if(!empty($similarFlightGroup)){ // 优先取相同航空公司的航班，(转机选择起飞相近的航班优先, 直飞是价格最低的航班优先)
			if($isDirect) { // debug, chenlei
				echo "直飞相同航空公司 \n";
			} else {
				echo "转机相同航空公司 \n"; 
			}
			
			$result = [];
			foreach($similarFlightGroup['flightPriceList'] as $row) {
				$nr = new stdClass();
				$nr->source = $row['supplierName'];
				$nr->url = $row['bookingLink'];
				$nr->price = $row['price'];
				$result[] = $nr;
			}
			return $result;
		} else if($isDirect && !empty($lowestFlightGroup)) { // 直飞，取最低价格
			echo "直飞 \n"; // debug, chenlei
			$result = [];
			foreach($lowestFlightGroup['flightPriceList'] as $row) {
				$nr = new stdClass();
				$nr->source = $row['supplierName'];
				$nr->url = $row['bookingLink'];
				$nr->price = $row['price'];
				$result[] = $nr;
			}
			return $result;
		} else {
			echo "无法匹配 \n"; // debug, chenlei
			return [];
		}
	}
	
	private function grabBookingHotel($date, $bindHotel) // get booking.com hotel price.
	{
		echo "deal hotel: {$bindHotel['hotel']['name']} ". date('Y-m-d H:i:s') . "\n"; // debug, chenlei
		mb_internal_encoding("UTF-8");
		
		if(empty($bindHotel['hotel']['url']) || empty($bindHotel['day']) || empty($bindHotel['night'])) { // 找不到酒店
			return false;
		}
		
		$this->changeIp();
		
		$reg = "/http:\/\/www\.booking\.com(.*)/i";
		if(!preg_match($reg, $bindHotel['hotel']['url'])) { // url 不是booking
			return false;
		}
		echo "deal match hotel: {$bindHotel['hotel']['name']} \n"; // debug, chenlei
		// get sid, dcid for grab comments.
		$urlComs = parse_url(trim($bindHotel['hotel']['url']));

		if(empty($urlComs) || empty($urlComs['path'])) return false;
		
		$prices = [];
		$departTime = strtotime($date); // 产品出发时间
		$startTime = $departTime + ($bindHotel['day'] -1) * (24 * 60 * 60);
		$startDate = date('Y-m-d', $startTime); // 入住日期
		$startDateComs = explode('-', $startDate);
		$leaveTime = $startTime + $bindHotel['night'] * (24 * 60 * 60);
		$leaveDate = date('Y-m-d', $leaveTime); // 离开日期
		$leaveDateComs = explode('-', $leaveDate);
		
		$key = "{$startDateComs[0]}-{$startDateComs[1]}";
				
		$startDateComs[1] = intval($startDateComs[1]);
		$startDateComs[2] = intval($startDateComs[2]);
		$leaveDateComs[1] = intval($leaveDateComs[1]);
		$leaveDateComs[2] = intval($leaveDateComs[2]);
		
// 		$query = '?'.urlencode("checkin_monthday={$startDateComs[2]}&checkin_year_month={$startDateComs[0]}-{$startDateComs[1]}&checkout_monthday={$leaveDateComs[2]}&checkout_year_month={$leaveDateComs[0]}-{$leaveDateComs[1]}&dist=0&do_availability_check=1&group_adults=2&hp_group_set=0&no_rooms=1&origin=hp&tab=1&selected_currency=CNY&type=total&#availability_target");
// 		$url = "http://www.booking.com{$urlComs['path']}{$query}";		
		// http://www.booking.com/hotel/kr/ramada-encore-seoul-dongdaemun.zh-cn.html?sid=968dfb694ca065040a01554b188c07dd;dcid=1;checkin_monthday=19&checkin_year_month=2015-3&checkout_monthday=22&checkout_year_month=2015-3&dist=0&do_availability_check=1&group_adults=2&hp_group_set=0&no_rooms=1&origin=hp&tab=1&type=total&#availability_target
		$url = "http://www.booking.com{$urlComs['path']}?checkin_monthday={$startDateComs[2]}&checkin_year_month={$startDateComs[0]}-{$startDateComs[1]}&checkout_monthday={$leaveDateComs[2]}&checkout_year_month={$leaveDateComs[0]}-{$leaveDateComs[1]}&dist=0&do_availability_check=1&group_adults=2&hp_group_set=0&no_rooms=1&origin=hp&tab=1&selected_currency=CNY&type=total&#availability_target";

		
		$dom = $this->_getDOM($url);
		if(empty($dom) || empty($dom->find('.roomPrice'))) { // 没有找到房间
			$this->changeIp();
			echo "deal hotel: {$bindHotel['hotel']['name']} 获取booking内容失败.url: {$url} \n"; // debug, chenlei
			return false;
		}
		
		echo "deal hotel: {$bindHotel['hotel']['name']} 获取内容成功 \n"; // debug, chenlei
		
		$pricesIncludeBreakfast = [];
		$pricesNotIncludeBreakfast = [];
		$pricesIncludeBreakfastPersionsGt3 = [];
		$pricesNotIncludeBreakfastPersionsGt3 = [];
		$minPrice = 0;
		
		$roomTypeDoms = $dom->find('tr.maintr .roomType');
		
		$additionalCost = [];
		$rowspanIndex = 1;
		if(!empty($roomTypeDoms)) {
			foreach($roomTypeDoms as $roomTypeDom) {
				$otherCost = 0;
				$taxpencent = 0;
				$rowspan = 1;
				if(!empty($roomTypeDom->getAttribute('rowspan'))) {
					$rowspan = (int) trim($roomTypeDom->getAttribute('rowspan')) -1; // 清除一个额外行
				}
				$incExcInPriceNewDom = $roomTypeDom->find('.incExcInPriceNew', 0);
				$incExcEmphasizeDom = $roomTypeDom->find('.incExcEmphasize', 0);
					
				if(!empty($incExcInPriceNewDom) && !empty($incExcEmphasizeDom)) { // 有没有额外限制
					$incExcInPriceNewText = trim($incExcInPriceNewDom->plaintext);
					$incExcEmphasizeText = trim($incExcEmphasizeDom->plaintext);
// 					var_dump($incExcInPriceNewText, $incExcEmphasizeText, mb_strpos($incExcEmphasizeText, '不包含', 0, 'UTF8'), mb_strpos($incExcEmphasizeText, '不包括', 0, 'UTF8'));
					if(mb_strpos($incExcEmphasizeText, '不包含', 0, 'UTF8') !== FALSE || mb_strpos($incExcEmphasizeText, '不包括', 0, 'UTF8') !== FALSE) {
						$dolarReg = '/US\$\s*(\d+)/iu';
							
						if(preg_match_all($dolarReg, $incExcInPriceNewText, $dolarMatches)) {
							if(empty($this->USD2CNYRate)) {
								$this->USD2CNYRate = $this->getUSD2CNYRate();
							}
							foreach($dolarMatches[1] as $cost) {
								if(!empty($this->USD2CNYRate)) {
									$otherCost = $cost * $this->USD2CNYRate;
								}
									
							}
						}
							
						$taxReg = '/([\d\.]+)%/iu';
							
						if(preg_match_all($taxReg, $incExcInPriceNewText, $taxMatches)) {
							foreach($taxMatches[1] as $taxpercent) {
								$taxpencent += (float) $taxpercent;
							}
						}
					}
				}
					
				for($i = 1; $i <= $rowspan; $i++) {
					$curIndex = $rowspanIndex++;
					if(!empty($otherCost)) {
						$additionalCost[$curIndex]['cost'] = $otherCost;
					}
					if(!empty($taxpencent)) {
						$additionalCost[$curIndex]['taxpercent'] = $taxpencent;
					}
				}
			}
		}
		
		if(!empty($dom->find('td.roomPrice'))) {
			$priceIndex = 0;
			foreach($dom->find('.roomPrice') as $td) {
				$priceIndex++;
				$tr = $td->parent;
				$price = 0;
				
				if(empty($tr->find('td.roomPrice strong.rooms-table-room-price', 0))) { // 指定价格
					continue;
				}

				$pricetext = $tr->find('td.roomPrice strong.rooms-table-room-price', 0)->plaintext;
				$pricetext = str_replace(['元', '&#20803;'], '', $pricetext);
				$pricetext = str_replace(',', '', $pricetext);
				$reg = '/(\d+)/u';
				if(preg_match($reg, $pricetext, $priceMatches)) {
					$price = (int) trim($priceMatches[1]);
				}
				if($price > 0) {
					$maxPersionDom = $tr->find('.roomMaxPersons', 0);
					if(empty($maxPersionDom)) {
						continue;
					}
					
					$persions = (int) $maxPersionDom->getAttribute('data-occupancy-for-tracking');
					
					if(!empty($persions)) {
						if(!empty($additionalCost[$priceIndex]) && !empty($additionalCost[$priceIndex]['taxpercent'])) {
							$price += $price * $additionalCost[$priceIndex]['taxpercent'] / 100;
						}
						$price = ceil($price/$persions);
						if(!empty($additionalCost[$priceIndex]) && !empty($additionalCost[$priceIndex]['cost'])) {
							$price += $additionalCost[$priceIndex]['cost'];
						}
						
						if($persions < 3) {
							if(!empty($tr->find('.ratepolicy',0)) && mb_strpos($tr->find('.ratepolicy',0)->plaintext, '包括早餐')) {
								$pricesIncludeBreakfast[] = $price;
							} else {
								$pricesNotIncludeBreakfast[] = $price;
							}
						} else {
							if(!empty($tr->find('.ratepolicy',0)) && mb_strpos($tr->find('.ratepolicy',0)->plaintext, '包括早餐')) {
								$pricesIncludeBreakfastPersionsGt3[] = $price;
							} else {
								$pricesNotIncludeBreakfastPersionsGt3[] = $price;
							}
						}
						
					}
					
				}
			}
		}
		
		if(!empty($pricesIncludeBreakfast)) {
			$minPrice = min($pricesIncludeBreakfast);
		} else if(empty($minPrice) && !empty($pricesNotIncludeBreakfast)){
			$minPrice = min($pricesNotIncludeBreakfast);
		} else if(empty($minPrice) && !empty($pricesIncludeBreakfastPersionsGt3)) { // 如果没有双人和单人，取多人间
			$minPrice = min($pricesIncludeBreakfastPersionsGt3);
		} else if(empty($minPrice) && !empty($pricesNotIncludeBreakfastPersionsGt3)) {
			$minPrice = min($pricesNotIncludeBreakfastPersionsGt3);
		}
		
		if(!empty($minPrice)) {
			$nr = new stdClass();
			$nr->source = 'booking.com';
			$nr->url = $url;
			$nr->price = ceil($minPrice);
			echo "hotel: {$bindHotel['hotel']['name']} 抓取成功 {$nr->price} \n"; // debug, chenlei
			return [$nr];
		}
		
		echo "hotel: {$bindHotel['hotel']['name']} 无返回最低价 \n"; // debug, chenlei
		return false;
	}
	
	private function getResponse($url, $cookieFile = '') { // 统一获取数据
		$this->_ci->load->library('curl');
	
		if(!empty($cookieFile)) {
			$this->_ci->curl->option('COOKIEJAR', $cookieFile);
			$this->_ci->curl->option('COOKIESESSION', true);
			$this->_ci->curl->option('COOKIEFILE', $cookieFile);
		}
		
		$response = $this->_ci->curl->simple_get($url);
		$info = $this->_ci->curl->info;
	
		$retryTimes = 1;
		$maxRetryTimes = 3;
	
		while(empty($response) && $info['http_code'] != 200 &&  $retryTimes < $maxRetryTimes) {
			$second = 1 * $maxRetryTimes;
			sleep($second);
			$response = $this->_ci->curl->simple_get($url);
			$info = $this->_ci->curl->info;
			$retryTimes++;
		}
	
		return $response;
	}
	
	private function getUSD2CNYRate()
	{
		$this->_ci->load->library('curl');
		
		$url = 'http://query.yahooapis.com/v1/public/yql?q='. urlencode('select * from yahoo.finance.xchange where pair in ("USDCNY")').'&format=json&diagnostics=true&env='.urlencode('store://datatables.org/alltableswithkeys').'&callback=';	
		$retry = 0;
		do {
			if($retry > 0) {
				sleep(1);
			}
			$response = $this->_ci->curl->simple_get($url);
			$retry++;
		} while(empty($response) && $retry < 5);
		
		if(empty($response)) return false;
		
		try {
			$data = json_decode($response, true);
		} catch(Exception $e) {
			return false;
		}
		
		
		if(!empty($data['query']) && !empty($data['query']['results']) && !empty($data['query']['results']['rate']) && !empty($data['query']['results']['rate']['Rate'])) {
			$rate = $data['query']['results']['rate']['Rate'];
			return $rate;
		}
		
		return false;
		
	}
	
	
	public function remove_utf8_bom($text)
	{
		$bom = pack('H*','EFBBBF');
		$text = preg_replace("/^$bom/", '', $text);
		return $text;
	}
	
	private function changeIp()
	{
		$ipPrefixs = array(
			'1.0.1','1.0.2','1.0.8','1.0.32','1.1.0','1.1.2','1.1.4','1.1.8','1.1.16','1.1.32','1.2.0','1.2.2','1.2.4','1.2.5','1.2.6','1.2.8','1.2.9','1.2.10','1.2.12','1.2.16','1.2.32','1.3','1.4.1','1.4.2','1.4.4','1.4.5','1.4.6','1.4.8','1.4.16','1.4.32','1.4.64','1.8','1.10.0','1.10.8','1.10.11','1.10.12','1.10.16','1.10.32','1.10.64','1.12','1.13','1.14','1.15','1.16','1.17','1.18','1.19','1.30','1.45','1.48','1.56','1.68','1.88','1.92','1.94','1.116','1.180','1.184','1.192','1.202','1.204','14.0.12','14.1','14.2','14.16','14.102.128','14.102.156','14.112','14.130','14.134','14.144','14.192','14.196','14.204','14.208','27.8','27.9','27.15','27.16.0','27.16.31','27.34.232','27.36','27.40','27.50.128','27.54.192','27.116.44','36.4','36.5','36.16','36.32','36.192','36.248','36.254','39.128','42.4','42.5','42.6','42.7','58.19','58.20','58.21','58.22','60.176','60.194','60.200','60.204','60.205','61.176','61.178','61.179','101.101.64','101.101.102','101.101.104','101.120','101.121','101.122','101.123','103.3.100','103.3.104','103.3.108','103.3.112','103.14.112','103.21.112','110.16.0','110.72.0','112.109.128','219.154.0','219.156.0','61.180','61.181','61.184','125.211','125.32','125.33','125.34','125.35','202.102',
		);
		
		$key = array_rand($ipPrefixs, 1);
		$ip = $ipPrefixs[$key];
		$params = explode('.', $ip);

		$count = count($params);
		if($count == 2) {
			$ip = $ip . '.' . rand(0, 255). '.'. rand(1, 255);
		}
		else if($count == 3) {
			$ip = $ip . '.' . rand(0, 255);
		} 
		else {
			$ips = array(
				'110.16.0.14','110.52.0.15','110.6.0.15','110.72.0.15','112.109.128.17','112.111.0.16','112.122.0.15','112.132.0.16','112.192.0.14','112.224.0.11','112.64.0.15','112.80.0.13','112.88.0.13','112.96.0.15','113.0.0.13','113.194.0.15','113.200.0.15','113.204.0.14"','113.224.0.12','113.56.0.15','113.58.0.16','113.59.0.17','113.8.0.15','114.240.0.12','115.46.0.16','115.48.0.12','115.85.192.18','116.112.0.14','116.116.0.15','116.2.0.15','116.95.0.16','117.8.0.13','118.212.0.16','118.72.0.13','118.80.0.15','119.108.0.15','119.112.0.13','119.162.0.15','119.164.0.14','119.176.0.12','119.248.0.14','119.36.0.16','119.39.0.16','119.4.0.14','119.48.0.13','119.62.0.16','120.0.0.12','120.80.0.13','121.16.0.13','121.24.0.14','121.28.0.15','121.30.0.16','121.31.0.16','122.136.0.13','122.156.0.14','122.192.0.14','122.96.0.15','123.112.0.12','123.128.0.13','123.138.0.15','123.144.0.14','123.148.0.16','123.152.0.13','123.188.0.14','123.232.0.14','123.4.0.14','123.8.0.13','124.128.0.13','124.160.0.16','124.161.0.16','124.162.0.16','124.163.0.16','124.164.0.14','124.64.0.15','124.66.0.17','124.67.0.16','124.88.0.16','124.89.0.17','124.89.128.17','124.90.0.15','124.92.0.14','125.211.0.16','125.32.0.16','125.33.0.16','125.34.0.16','125.35.0.17','125.35.128.17','125.36.0.14','125.40.0.13','175.42.0.15','202.102.128.21','202.102.136.21','202.102.144.20','202.102.224.21','202.102.232.21','202.102.240.20','202.106.0.16','202.107.0.17','202.108.0.16','202.110.0.18','202.110.192.18','202.110.64.18','202.111.128.19','202.130.224.19','202.38.143.24','202.96.0.18','202.96.64.21','202.96.72.21','202.96.80.20','202.97.128.18','202.97.192.19','202.97.224.21','202.97.232.21','202.97.240.20','202.98.0.21','202.98.8.21','202.99.0.18','202.99.104.21','202.99.112.20','202.99.128.19','202.99.160.21','202.99.168.21','202.99.176.20','202.99.192.21','202.99.200.21','202.99.208.20','202.99.224.21','202.99.232.21','202.99.240.20','202.99.64.19','202.99.96.21','203.93.192.18','203.93.64.18','203.93.8.24','210.13.0.18','210.13.128.17','210.13.64.18','210.14.160.19','210.14.192.19','210.15.128.18','210.15.32.19','210.15.96.19','210.21.0.17','210.51.0.16','210.52.128.17','210.53.0.17','210.53.128.17','210.74.128.19','210.74.96.19','210.78.0.19','210.78.160.19','210.78.192.18','210.82.0.15','211.144.0.15','211.90.0.15','211.92.0.15','211.94.0.15','211.96.0.15','218.10.0.16','218.104.0.17','218.104.128.19','218.104.160.19','218.104.192.21','218.104.200.21','218.104.208.20','218.104.224.19','218.105.0.16','218.106.0.15','218.11.0.16','218.12.0.16','218.21.128.17','218.24.0.15','218.26.0.16','218.27.0.16','218.28.0.15','218.56.0.14','218.60.0.15','218.67.128.17','218.68.0.15','218.7.0.16','218.8.0.15','219.154.0.15','219.156.0.15','219.158.0.17','219.158.128.17','219.159.0.18','220.192.0.15','220.194.0.15','220.196.0.14','220.200.0.13','220.248.0.14','220.252.0.16','221.0.0.15','221.10.0.16','221.11.0.17','221.11.128.18','221.11.192.19','221.11.224.19','221.12.0.17','221.12.128.18','221.13.0.18','221.13.128.17','221.13.64.19','221.13.96.19','221.14.0.15','221.192.0.15','221.194.0.16','221.195.0.16','221.196.0.15','221.198.0.16','221.199.0.19','221.199.128.18','221.199.192.20','221.199.224.19','221.199.32.20','221.199.48.20','221.199.64.18','221.2.0.16','221.200.0.14','221.204.0.15','221.206.0.16','221.207.0.18','221.207.128.17','221.207.64.18','221.208.0.14','221.212.0.16','221.213.0.16','221.214.0.15','221.216.0.13','221.3.0.17','221.3.128.17','221.4.0.16','221.5.0.17','221.5.128.17','221.6.0.16','221.7.0.19','221.7.128.17','221.7.32.19','221.7.64.19','221.7.96.19','221.8.0.15','222.128.0.14','222.132.0.14','222.136.0.13','222.160.0.15','222.162.0.16','222.163.0.19','222.163.128.17','222.163.32.19','222.163.64.18','58.144.0.16','58.16.0.16','58.17.0.17','58.17.128.17','58.18.0.16','58.19.0.16','58.20.0.16','58.21.0.16','58.22.0.15','58.240.0.15','58.242.0.15','58.244.0.15','58.246.0.15','58.248.0.13','60.0.0.13','60.10.0.16','60.11.0.16','60.12.0.16','60.13.0.18','60.13.128.17','60.13.64.18','60.14.0.15','60.16.0.13','60.208.0.13','60.216.0.15','60.218.0.15','60.220.0.14','60.24.0.14','60.28.0.15','60.30.0.16','60.31.0.16','60.8.0.15','61.133.0.17','61.134.128.18','61.134.192.18','61.134.96.19','61.135.0.16','61.136.0.18','61.136.64.18','61.137.128.17','61.138.0.18','61.138.128.18','61.138.64.18','61.139.128.18','61.148.0.15','61.156.0.16','61.158.0.17','61.158.128.17','61.159.0.18','61.161.0.18','61.161.128.17','61.162.0.16','61.163.0.16','61.167.0.16','61.168.0.16','61.176.0.16','61.179.0.16','61.180.128.17','61.181.0.16','61.182.0.16','61.189.0.17','61.240.0.14','61.48.0.14','61.52.0.15','61.54.0.16','61.55.0.16','110.152.0.14','110.156.0.15','110.166.0.15','110.176.0.13','110.80.0.13','110.88.0.14','111.120.0.14','111.124.0.16','112.100.0.14','112.112.0.14','112.116.0.15','112.66.0.15','112.98.0.15','113.112.0.13','113.12.0.14','113.120.0.13','113.128.0.15','113.132.0.14','113.136.0.13','113.16.0.15','113.218.0.15','113.220.0.14','113.24.0.14','113.240.0.13','113.248.0.14','113.62.0.15','113.64.0.11','113.96.0.12','114.104.0.14','114.135.0.16','114.138.0.15','114.216.0.13','114.224.0.12','114.80.0.12','114.96.0.13','115.148.0.14','115.152.0.15','115.168.0.14','115.192.0.11','115.224.0.12','116.1.0.16','116.16.0.12','116.192.0.16','116.207.0.16','116.208.0.14','116.224.0.12','116.246.0.15','116.248.0.15','116.252.0.15','116.4.0.14','116.52.0.14','116.8.0.14','117.21.0.16','117.22.0.15','117.24.0.13','117.32.0.13','117.40.0.14','117.44.0.15','117.57.0.16','117.60.0.14','117.64.0.13','117.80.0.12','118.112.0.13','118.120.0.14','118.124.0.15','118.180.0.14','118.213.0.16','118.239.0.16','118.248.0.13','118.84.0.15','119.0.0.15','119.120.0.13','119.128.0.12','119.144.0.14','119.41.0.16','119.60.0.16','119.84.0.14','119.96.0.13','120.32.0.13','120.40.0.14','120.68.0.14','120.88.8.21','121.204.0.14','121.224.0.12','121.32.0.14','121.56.0.15','121.58.0.17','121.59.0.16','121.60.0.14','121.8.0.13','122.102.80.20','122.224.0.12','122.240.0.13','122.4.0.14','123.103.0.17','123.149.0.16','123.150.0.15','123.160.0.14','123.164.0.14','123.168.0.14','123.172.0.15','123.174.0.15','123.177.0.16','123.178.0.15','123.180.0.14','123.184.0.14','123.244.0.14','123.96.0.15','124.112.0.15','124.114.0.15','124.116.0.16','124.117.0.16','124.118.0.15','124.224.0.16','124.225.0.16','124.226.0.15','124.228.0.14','124.232.0.15','124.234.0.15','124.236.0.14','124.31.0.16','124.72.0.16','124.73.0.16','124.74.0.15','124.76.0.14','125.104.0.13','125.112.0.12','125.64.0.13','125.72.0.16','125.73.0.16','125.74.0.15','125.76.0.17','125.76.128.17','125.77.0.16','125.78.0.15','125.80.0.13','125.88.0.13','180.212.0.15','202.100.0.21','202.100.104.21','202.100.112.20','202.100.128.21','202.100.136.21','202.100.144.20','202.100.16.20','202.100.160.21','202.100.168.21','202.100.176.20','202.100.192.21','202.100.200.21','202.100.208.20','202.100.224.19','202.100.32.19','202.100.64.21','202.100.72.21','202.100.80.20','202.100.96.21','202.101.0.18','202.101.128.18','202.101.224.21','202.101.64.19','202.101.96.19','202.102.0.19','202.102.192.21','202.102.200.21','202.102.208.20','202.102.32.19','202.102.64.18','202.103.0.21','202.103.104.21','202.103.112.20','202.103.128.18','202.103.16.20','202.103.192.19','202.103.224.21','202.103.232.21','202.103.240.20','202.103.32.19','202.103.64.19','202.103.8.21','202.103.96.21','202.104.0.15','202.107.128.17','202.109.0.16','202.110.128.18','202.111.0.17','202.111.160.19','202.111.192.18','202.96.104.21','202.96.112.20','202.96.128.21','202.96.136.21','202.96.144.20','202.96.160.21','202.96.168.21','202.96.176.20','202.96.192.21','202.96.200.21','202.96.208.20','202.96.224.21','202.96.232.21','202.96.240.20','202.96.96.21','202.97.0.21','202.97.112.20','202.97.16.20','202.97.32.19','202.97.64.19','202.97.8.21','202.97.96.20','202.98.104.21','202.98.112.20','202.98.128.19','202.98.16.20','202.98.160.21','202.98.168.21','202.98.176.20','202.98.192.21','202.98.200.21','202.98.208.20','202.98.224.21','202.98.232.21','202.98.240.20','202.98.32.21','202.98.40.21','202.98.48.20','202.98.64.19','202.98.96.21','202.99.192.21','203.130.32.19','203.212.0.20','210.192.96.19','218.0.0.16','218.1.0.16','218.13.0.16','218.14.0.15','218.16.0.14','218.2.0.15','218.20.0.16','218.21.0.17','218.22.0.15','218.30.0.15','218.4.0.15','218.6.0.16','218.62.0.17','218.62.128.17','218.63.0.16','218.64.0.15','218.66.0.16','218.67.0.17','218.70.0.15','218.72.0.14','218.76.0.15','218.78.0.15','218.80.0.14','218.84.0.14','218.88.0.13','219.128.0.12','219.144.0.14','219.148.0.16','219.149.0.17','219.149.128.18','219.149.192.18','219.150.0.19','219.150.112.20','219.150.128.17','219.150.32.19','219.150.64.19','219.150.96.20','219.151.0.19','219.151.128.17','219.151.32.19','219.151.64.18','219.152.0.15','219.159.128.17','219.159.64.18','220.160.0.11','221.224.0.13','221.232.0.14','221.236.0.15','221.238.0.16','221.239.0.17','221.239.128.17','222.168.0.15','222.170.0.15','222.172.0.17','222.172.128.17','222.173.0.16','222.174.0.15','222.176.0.13','222.184.0.13','222.208.0.13','222.216.0.15','222.218.0.16','222.219.0.16','222.220.0.15','222.222.0.15','222.240.0.13','222.64.0.13','222.72.0.15','222.74.0.16','222.75.0.16','222.76.0.14','222.80.0.15','222.82.0.16','222.83.0.17','222.83.128.17','222.84.0.16','222.85.0.17','222.85.128.17','222.86.0.15','222.88.0.15','222.90.0.15','222.92.0.14','58.208.0.12','58.32.0.13','58.40.0.15','58.42.0.16','58.43.0.16','58.44.0.14','58.48.0.13','58.56.0.15','58.58.0.16','58.59.0.17','58.59.128.17','58.60.0.14','59.172.0.15','59.174.0.15','59.32.0.13','59.40.0.15','59.42.0.16','59.43.0.16','59.44.0.14','59.48.0.16','59.49.0.17','59.49.128.17','59.50.0.16','59.51.0.17','59.51.128.17','59.52.0.14','59.56.0.14','59.60.0.15','59.62.0.15','60.160.0.15','60.162.0.15','60.164.0.15','60.166.0.15','60.168.0.13','60.176.0.12','60.235.0.16','61.128.0.15','61.130.0.15','61.132.0.16','61.133.128.17','61.134.0.18','61.134.64.19','61.136.128.17','61.137.0.17','61.138.192.18','61.139.0.17','61.139.192.18','61.140.0.14','61.144.0.14','61.150.0.15','61.152.0.16','61.153.0.16','61.154.0.15','61.157.0.16','61.159.128.17','61.159.64.18','61.160.0.16','61.161.64.18','61.164.0.16','61.165.0.16','61.166.0.16','61.169.0.16','61.170.0.15','61.172.0.14','61.177.0.16','61.178.0.16','61.180.0.17','61.183.0.16','61.184.0.14','61.188.0.16','61.189.128.17','61.190.0.15','112.0.0.10','117.128.0.10','120.192.0.10','121.36.0.16','202.0.176.22','211.103.0.17','211.136.0.14','211.140.0.15','211.142.0.17','211.142.128.17','211.143.0.16','218.200.0.14','218.204.0.15','218.206.0.15','221.130.0.15','221.176.0.13','110.192.0.11','110.96.0.11','122.64.0.11','123.64.0.11','203.90.160.19','211.98.0.15','221.172.0.14','222.32.0.11','61.232.0.14','61.236.0.15',
			);
			$key = array_rand($ips, 1);
			$ip = $ips[$key];
		}

		$this->ip = $ip;
		return $ip;
	}
}