<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Grabmaidou extends CI_Controller {
	private $source = '麦兜旅行';
	private $codePrefix = 'MD';
	
	public function __construct() {
		parent::__construct();
		
		$this->load->library('grab/netutil');
		$this->load->library('dataprocess/dataprocess');
		$this->load->helper('grabformat');
	}
	
	public function index()
	{
		date_default_timezone_set('Asia/Shanghai');
		
		$this->load->library('city/city');
		$this->load->helper('htmldom');
		
		echo 'grab source data start:'.date("Y-m-d H:i:s")."\n";
		
		$startCities = $this->city->getCities('start', 'maidou');
		$endCities = $this->city->getCities('end', 'maidou');
		
		foreach($startCities as $startCity => $startParams) {
			foreach($endCities as $endCity => $endParams) {
				if($startCity == $endCity) {
					continue;
				}
				$this->start = $startCity;
				$this->end = $endCity;
				
				$ip = $this->netutil->getIp();
				$encodeStart = urlencode($startParams['map']);
				$encodeEnd = urlencode($endParams['map']);
						
// 				$entryURL = "http://www.maidou.com/doudouList/index.do?start=&end=%E6%B3%B0%E5%9B%BD&start_time=undefined&end_time=undefined&way_type=1&order_type=0&pay_type=undefined";
				$entryURL = "http://www.maidou.com/doudouList/index.do?start={$encodeStart}&end={$encodeEnd}&start_time=undefined&end_time=undefined&way_type=1&order_type=0&pay_type=undefined";

				$dom = _getDOM($entryURL, ['ip' => $ip]);
				
				if(empty($dom)) {
					sleep(5);
					$dom = _getDOM($entryURL, ['ip' => $ip]);
				}
				if(empty($dom)) {
					echo date('Y-m-d H:i:s').' get product list empty url: '.$entryURL.PHP_EOL;
					continue;
				}
				
				$list = $dom->find('div.sort-result-list ul li');
				if(!empty($list)) {
					foreach($list as $li) {
						$typeDom = $li->find('.pro-tag', 0);			
						if(!empty($typeDom) && trim($typeDom->plaintext) == '自由行') {
							$aDom = $li->find('.maintitle', 0);
							if(!empty($aDom) && !empty($aDom->getAttribute('data-href'))) {
								$prodname = trim($aDom->plaintext);
								$url = $aDom->getAttribute('data-href');
									
								if(strpos($url, 'http:') === FALSE) {
									$url = 'http://www.maidou.com'.$url;
								}
								$this->_grabdetail($url, $entryURL);
								sleep(1);
							}
						}
					}
				}
			}
		}
				
		
		echo date("Y-m-d H:i:s").'grab maidou success end !!!'."\n";
	}
	
	private function _grabdetail($url, $referer)
	{
		mb_internal_encoding("UTF-8");
	
		$ip = $this->netutil->getIp();
		$html = _getText($url, ['ip' => $ip, 'referer' => $referer]);
	
		if(empty($html)) {
			return false;
		}
		
		$dom = str_get_html($html);
		
		if(empty($dom)) {
			return false;
		}
		
		if(!empty($dom->find('.content-404', 0))) {
			return false;
		}
		
		$prod = new stdClass();
		$prod->source = $this->source;
		$prod->sourceurl = $url;
		empty($prod->vender) AND $prod->vender = $this->source;
		
		$titleDom = $dom->find('.pro-title h2 .fl', 0);
		if(!empty($titleDom)) {
			$prod->name = trim($titleDom->plaintext);
		}
		
		$descInfoDom = $dom->find('.price-desc-info', 0);
		if(empty($descInfoDom)) {
			return false;
		}
		$codeDom = $descInfoDom->find('.walkline span', 0);
		if(empty($codeDom)) {
			return false;
		}
		$code = trim($codeDom->plaintext);
		
		if(empty($code)) {
			echo 'empty product code, url: '.$url. PHP_EOL;
			return false;
		}
		
		$prod->code = $this->codePrefix.$code;
		
		echo "grab maidou product {$prod->code} :  ".date("Y-m-d H:i:s")."\n";
		
		$tripDom = $descInfoDom->find('.calendar', 0);
		if(!empty($tripDom)) {
			$infoText = trim($tripDom->parent->plaintext);
			if(!empty($infoText)) {
				$travelTime = travelTimeFormat($infoText);
				if(!empty($travelTime['days'])) {
					$prod->days = floatval($travelTime['days']);
				}
				if(!empty($travelTime['nights'])) {
					$prod->hotelNight = floatval($travelTime['nights']);
				}
			}
		}
		
		$featureDoms = $dom->find('.product-features .features-cont p');
		if(!empty($featureDoms)) { // 参考行程
			$features = [];
			foreach($featureDoms as $p) {
				$features[] = trim($p->plaintext);
			}
			if(!empty($features)) {
				$prod->recommend = implode("\n", $features);
			}
		}
		
		$prod->needConfirm = FALSE;
		
		$phoneDom = $dom->find('.phone-weixin .weixin span', 0);
		if(!empty($phoneDom)) {
			$phoneText = $phoneDom->plaintext;
			$prod->servicephone = phoneFormat($phoneText);
		}
		
		// 出发地及目的地
		$prod->start = [];
		$prod->end = [];
		$mapDom = $descInfoDom->find('.dd-notice-item .map', 0);
		if(!empty($mapDom)) {
			$startStr = trim($mapDom->parent->plaintext);
			$startStr = str_replace('出发地：', '', $startStr);
			$startStr = trim(str_replace('市', '', $startStr));
			$startStr = trim(str_replace('国际机场', '', $startStr));
			$startStr = trim(str_replace('机场', '', $startStr));
			$startStr = trim(str_replace('特别行政区', '', $startStr));
			
			if(!empty($startStr)) {
				$starts = explode('、', $startStr);
				foreach($starts as $city) {
					if(!empty($city)) {
						$prod->start[]= trim($city);
					}
				}
			}
		}
		
		$endCityDom = $descInfoDom->find('.endCity', 0);
		if(!empty($endCityDom)) {
			$endStr = trim($endCityDom->parent->plaintext);
			$endStr = str_replace('目的地：', '', $endStr);
				
			if(!empty($endStr)) {
				$ends = explode('/', $endStr);
				foreach($ends as $city) {
					if(!empty($city)) {
						$prod->end[]= trim($city);
					}
				}
			}
		}
		empty($prod->start) AND $prod->start = [$this->start];
		empty($prod->end) AND $prod->end = [$this->end];
		
		$monthReg = "/[^v]\sfirstMonth =\s*([^;\"]*);/";
		if(preg_match($monthReg, $html, $monthMatches)) {
			$firstMonth = $monthMatches[1];
			$groupIdReg = "/showTourPeriod\(\"([^\"]*)\",firstMonth/";
			if(preg_match($groupIdReg, $html, $groupIdMatches)) {
				$groupId = trim($groupIdMatches[1]);
			}
		}
		
		$prices = [];
		if(!empty($firstMonth) && !empty($groupId)) {
			$time = time().rand(100, 999);
			$priceUrl = "http://www.maidou.com/timeprice/tourPeriod.do?metaGroupId={$groupId}&month={$firstMonth}&timestamp={$time}";
			
			$calendardDom = _getDOM($priceUrl, ['ip' => $ip, 'referer' => $referer]);
			
			if(empty($calendardDom)) {
				return FALSE;
			}
			
			if(empty($calendardDom->find('.month-container', 0))) {
				return FALSE;
			}
			
			if(!empty($calendardDom->find('.month-container .active'))) {
				$activeDoms = $calendardDom->find('.month-container .active');
				if(!empty($activeDoms)) {
					foreach($activeDoms as $active) {
						$date = trim($active->getAttribute('visittime'));
						if(!preg_match("/\d{4}-\d{2}-\d{2}/", $date)) {
							continue;
						}
							
						$li = $active->parent;
						if(!empty($active->find('.tip-content p'))) {
							$nr = new stdClass();
							foreach($active->find('.tip-content p') as $p) {
								$infoText = trim($p->plaintext);
								if(preg_match("/成人价￥(\d+)/iu", $infoText, $priceMatches)) {
									$nr->price = floatval($priceMatches[1]);
								} else if(preg_match("/儿童价￥(\d+)/iu", $infoText, $priceMatches)) {
									$nr->childprice = floatval($priceMatches[1]);
								} else if(preg_match("/房差￥(\d+)/iu", $infoText, $priceMatches)) {
									$nr->diffprice = floatval($priceMatches[1]);
								}
							}
							if(!empty($nr->price)) {
								$dayStr = $li->find('span', 0)->innertext;
								if(!empty($li->find('span i', 0))) {
									$storeStr = trim($li->find('span i', 0)->innertext);
									if($storeStr == '充足') {
										$nr->yuwei = floatval(10);
									} else if($storeStr == '紧张') {
										$nr->yuwei = floatval(6);
									} else if(preg_match('/余(\d+)/u', $storeStr, $storeMatches)) {
										$nr->yuwei = floatval($storeMatches[1]);
									} else if($storeStr == '售罄') {
										continue;
									}
								}
									
								if(!empty($nr->yuwei)) {
									$prices[$date] = $nr;
								}
							}
						}
					}
				}
			}		
		}
		
		if(empty($prices)) { // 没有价格日历
			$querySource = $this->cimongo->srcdb->duoqusourcedatas->findOne(['code'=> $prod->code]);
			if(!empty($querySource)) {
				$this->cimongo->srcdb->duoqusourcedatas->update(['code'=> $prod->code], ['$unset'=> ['prices' => 1]]);
			}
			echo date('Y-m-d').' grab product code: '.$prod->code.' no prices failed'.PHP_EOL;
			return FALSE;
		}
		
		$prod->prices = (object) $prices;
		
		// 航班信息
		$flights = [];
		$trafficReg = "/showTrafficInfo\(\"(\d+)\",?[^\)]*\)/is";
		if(preg_match($trafficReg, $html, $trafficMatches)) {
			$trafficInfoId = $trafficMatches[1];
		}
		
		if(!empty($trafficInfoId)) {
			$flightUrl = "http://www.maidou.com/product/trafficInfo.do?trafficInfoId={$trafficInfoId}&trafficIndex=0";
			
			$flightsDom = _getDOM($flightUrl, ['ip' => $ip, 'referer' => $referer]);
			
			if(!empty($flightsDom)) {
				$groupDom = $flightsDom->find('.flight-detail', 0);
				if(!empty($groupDom)) {
					foreach($groupDom->find('.row') as $gRow) {
						$titleText = trim($gRow->find('.title', 0)->plaintext);
						$direction = trim($gRow->find('.title span', 0)->plaintext);
						$dayStr = trim(str_replace($direction, '', $titleText));
						$dayReg = "/第(\d+)天/u";
						$day = '';
						if(preg_match($dayReg, $dayStr, $dayMatches)) {
							$day = $dayMatches[1];
						}
						
						if(empty($gRow->find('.traffic-row'))) {
							continue;
						}
						
						foreach($gRow->find('.traffic-row') as $row) {
							if(empty($row->class) || strpos($row->class, 'pr') === FALSE) {
								continue;
							}
							
							$flight = [];
							
							if(!empty($day)) {
								$flight['day'] = floatval($day);
							}
							
							if($direction == '去程') {
								$flight['trip'] = 'depart';
							} else {
								$flight['trip'] = 'back';
							}
							
							$flightNo = null;
							if(!empty($row->find('.ico-plane', 0)) && !empty($row->find('.ico-plane', 0)->parent->find('.w100', 0))) {
								$flightNo = trim($row->find('.ico-plane', 0)->parent->find('.w100', 0)->plaintext);
									
								if(empty($flightNo)) {
									continue;
								}
									
								$reg = "/([A-Za-z0-9]{2,})$/i";
								if(preg_match($reg, $flightNo, $flightMatches)) {
									$flightNo = $flightMatches[1];
								} else {
									continue;
								}
									
								$flight['flightNo'] = $flightNo;
									
								$flightRow = $row->find('.ico-plane', 0)->parent;
								if(!empty($flightRow->find('.pr'))) {
									$pr = trim($flightRow->find('.pr', 0)->plaintext);
									if(!empty($flightRow->find('.icon-traffic', 0))) {
										$iconStr = trim($flightRow->find('.icon-traffic', 0)->plaintext);
										$pr = trim(str_replace($iconStr, '', $pr));
									}
									$landingDay = '';
									if(!empty($flightRow->find('.a2', 0))) {
										$a2Str = trim($flightRow->find('.a2', 0)->plaintext);
										$pr = trim(str_replace($a2Str, '', $pr));
											
										$landingDayReg = "/^(\+\d+)/";
										if(preg_match($landingDayReg, $a2Str, $landingDayMatches)) {
											$landingDay = $landingDayMatches[1];
										}
									}
										
									$infos = explode('——', $pr);
										
									if(!empty($infos) && count($infos) == 2) {
										$flight['takeoffCity'] = trim(mb_substr($infos[0], 0, mb_strpos($infos[0], '（', 0, 'UTF8'), 'UTF8'));
										$flight['landingCity'] = trim(mb_substr($infos[1], 0, mb_strpos($infos[1], '（', 0, 'UTF8'), 'UTF8'));
											
										$flight['takeoffCity'] = str_replace('特别行政区', '', $flight['takeoffCity']);
										$flight['landingCity'] = str_replace('特别行政区', '', $flight['landingCity']);
										$flightReg = "/[（\(](\S+)[）\)]/u";
										$timeReg = "/(\d+:\d+)/u";
											
										if(preg_match($flightReg, $infos[0], $flightMatches)) {
											if(preg_match($timeReg, $flightMatches[1], $timeMatches)) {
												$flight['takeoff'] = trim($timeMatches[1]);
												$flight['takeoff'] = str_replace('：', ':', $flight['takeoff']);
												$flight['takeoffAirport'] = trim(str_replace($timeMatches[1], '', $flightMatches[1]));
											}
										}
											
										if(preg_match($flightReg, $infos[1], $flightMatches)) {
											if(preg_match($timeReg, $flightMatches[1], $timeMatches)) {
												$flight['landing'] = trim($timeMatches[1]);
												$flight['landing'] = str_replace('：', ':', $flight['landing']);
												if(!empty($landingDay)) {
													$flight['landing'] .= trim($landingDay);
												}
												$flight['landingAirport'] = trim(str_replace($timeMatches[1], '', $flightMatches[1]));
											}
										}
									}
								}
									
								$flights[] = $flight;
							} else {
								continue;
							}
						}
						
					}
				}
			}
		}
		
		$prod->flights = $flights;
		
		// 酒店信息
		$hotels = [];
		$hotelList = $dom->find('.hotel-list div.item');
		if(!empty($hotelList)) {
			foreach($hotelList as $hotelDom) {
				$hotel = [];
				$hotelNameStr = trim($hotelDom->find('.hotel-name', 0)->plaintext);
				$enNameStr = '';
				if(!empty($hotelDom->find('.hotel-name sub', 0))) {
					$enNameStr = trim($hotelDom->find('.hotel-name sub', 0)->plaintext);
				}
				$hotelNameStr = trim(str_replace($enNameStr, '', $hotelNameStr));
				
				if(empty($hotelNameStr)) continue;
				
				$hotel['name'] = $hotelNameStr;
				if(!empty($enNameStr)) {
					$hotel['englishName'] = trim($enNameStr);
				}
				
				$starLevel = $hotelDom->find('.star-level', 0);
				if(!empty($starLevel)) {
					$starLevelClass = $starLevel->class;
					$reg = "/star-level-(\d+)/i";
					
					if(preg_match($reg, $starLevelClass, $starLevelMatches)) {
						$hotel['star'] = (string) intval($starLevelMatches[1]);
					}
				}
				
				$addressDom = $hotelDom->find('.map-position', 0);
				if(!empty($addressDom)) {
					$hotel['address'] = trim($addressDom->plaintext);
					if(mb_substr($hotel['address'], 0, 1, 'UTF8') == '：') {
						$hotel['address'] = mb_substr($hotel['address'], 1, mb_strlen($hotel['address'], 'UTF8') -1, 'UTF8');
					}
				}
				
				$hotelDescDom = $hotelDom->find('.hotel-desc', 0);
				if(!empty($hotelDescDom)) {
					$descothers = $hotelDescDom->innertext;
					$hotel['descothers'] = trim(preg_replace('/(<[^>]+) style=".*?"/i', '$1', $descothers));
				}
				
				$hotels[] = $hotel;
			}
		}
		
		$prod->hotels = $hotels;
		
		$travelDom = $dom->find('.refer-travel .travel-cont', 0);
		if(!empty($travelDom)) { // 参考行程
			$prod->routeplan = trim($travelDom->innertext);
		}
		
		$feiyongDom = $dom->find('.expense-instru .expense-cont', 0);
		if(!empty($feiyongDom)) {  // 费用
			$prod->costInstruction = trim($feiyongDom->innertext);
		}
		
		$yudingDom = $dom->find('.booking-instru .cont-info', 0);
		if(!empty($yudingDom)) { // 预订
			$prod->booking = trim($yudingDom->innertext);
		}
		
		$tuigaiDom = $dom->find('.cancel-instru .cont-info', 0);
		if(!empty($tuigaiDom)) { // 退改政策
			$prod->returnPolicy = trim($tuigaiDom->innertext);
		}
		
		$visaDom = $dom->find('.visa-instru .cont-info', 0);
		if(!empty($visaDom)) { // 签证政策
			$prod->visa = trim($visaDom->innertext);
		}
	
		$prod->sourcestatus = 'normal';
		$prod->type = ['自由行'];
		$prod->status = 'stop'; // 设置停售状态，信息补充完成可改为正常状态
		
		if(empty($prod->hotels) && empty($prod->flights)) {
			return FALSE;
		}
		
// 		echo json_encode($prod);
// 		exit();
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
	
	// 抓取产品数据
	public function grabById($id)
	{
// 		header('Content-Type: application/json'); // debug, chenlei
		
		$url = "http://www.maidou.com/route/{$id}.html";
		
		$this->load->helper('htmldom');
		$this->_grabdetail($url, $url);
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
	
}