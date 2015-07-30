<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Grabaoyou extends CI_Controller {
	private $source = '遨游网';
	private $codePrefix = 'AY';
	private $start;
	private $end;
	
	public function __construct()
	{
		parent::__construct();
		
		date_default_timezone_set('Asia/Shanghai');
		$this->load->library('grab/netutil');
		$this->load->library('dataprocess/dataprocess');
		$this->load->helper('grabformat');
	}
	
	public function index()
	{
		// header('Content-Type: application/json'); // debug, chenlei
		
		$this->load->library('city/city');
		$this->load->helper('htmldom');
		
		echo 'grab source data start:'.date("Y-m-d H:i:s")."\n";
		
		$results = array();
		
		$startCities = $this->city->getCities('start', 'aoyou');
		$endCities = $this->city->getCities('end', 'aoyou');
		
		foreach($startCities as $startCity => $startParams) {
			foreach($endCities as $endCity => $endParams) {
				if($startCity == $endCity) {
					continue;
				}
				$this->start = $startCity;
				$this->end = $endCity;
				
				$ip = $this->netutil->getIp();
				// http://www.aoyou.com/search/b1-l102-t1-sl198/
				$entryURL = "http://www.aoyou.com/search/{$startParams['map']}-{$endParams['map']}-t1/"; // t1 为自由行
// 				echo $entryURL."<br>\n"; // debug, chenlei
				$readTuijian = true;
				while(1) {
					$dom = _getDOM($entryURL, ['ip' => $ip]);
					if(empty($dom) || empty($dom->find('#divSearch .search_navf ul.search_nav .on a b'))) { // 找不到列表的页面
						break;
					}
					$typeText = trim($dom->find('#divSearch .search_navf ul.search_nav .on a b', 0)->plaintext);
					if($typeText != '自由行') { // 去除显示非自由行的列表
						break;
					}
					foreach ($dom->find('div.subblock div.list') as $list) {
						if ($readTuijian || $list->parent()->class != 'list_f') { // 避免重复读 推荐
							foreach ($list->find('.product') as $product) {
								$url = trim($product->find('.info .tt a[packagesubtype]', 0)->href);
								
								$grabResult = $this->_grabdetail($url, $entryURL);
								sleep(0.2);
							}
						}
					}
					$readTuijian = false;
					$found = false;
					foreach ($dom->find('div.list .act .fy_btn a') as $a) {
						if (trim($a->plaintext) == '下一页') {
							$entryURL = $a->href;
							$found = true;
							break;
						}
					}
					if (!$found) {
						break;
					}
				}
			}
		}
		
// 		echo json_encode($results, JSON_UNESCAPED_UNICODE);
		echo 'update aoyou success'.date("Y-m-d H:i:s")."\n";
	}

	private function _grabdetail($url, $referer) {
		mb_internal_encoding("UTF-8");
		
		$ip = $this->netutil->getIp();
		$html = _getText($url, ['referer' => $referer, 'ip' => $ip]);
		
		if(empty($html)) {
			sleep(2);
			$html = _getText($url, ['referer' => $referer, 'ip' => $ip]);
		}
		if(empty($html)) {
			sleep(5);
			$html = _getText($url, ['referer' => $referer, 'ip' => $ip]);
		}
		if(empty($html)) {
			return FALSE;
		}
		
		$dom = str_get_html($html);
		if(empty($dom)) return FALSE;
		
		if(empty($dom) || empty($dom->find('.productInfoBox'))) { // 已售光产品
			return false;
		}
		
		$prod = new stdClass();
		if(!empty($dom->find('.mainTitle h1.mainTitle-h1', 0))) {
			$prod->name = trim($dom->find('.mainTitle h1.mainTitle-h1', 0)->plaintext);
		}
		if(!empty($dom->find('.mainTitle .mainTitle-subtitleBox p.mainTitle-subtitle-txt', 0))) {
			$prod->recommend = trim($dom->find('.mainTitle .mainTitle-subtitleBox p.mainTitle-subtitle-txt', 0)->plaintext);
		}
		
		$prod->sourceurl = trim($url);
		$prod->source = $this->source;
		
		$productInfoBox = $dom->find('.productInfoBox', 0);
		if(empty($productInfoBox->find('.productInfoBox-des-id', 0))) {
			echo "empty code url: {$prod->sourceurl}".PHP_EOL;
			return FALSE;
		}

		$code = $orgCode = str_replace('产品编号：', '', $productInfoBox->find('.productInfoBox-des-id', 0)->plaintext);
		$prod->code = $this->codePrefix.$code;
		empty($prod->vender) AND $prod->vender = $this->source;
		
		if(empty($prod->code)) {
			return false;
		}

		echo "grab aoyou product: {$prod->code} ".date("Y-m-d H:i:s")."\n"; // debug, chenlei

		$prod->needConfirm = false;
		
		$prod->servicephone = '4008840086,2'; // 运营要求遨游电话统一为这个
		
		if(!empty($prod->name)) {
			$travelTime = travelTimeFormat($prod->name);
			if(empty($prod->days) && !empty($travelTime['days'])) {
				$prod->days = floatval($travelTime['days']);
			}
			if(empty($prod->hotelNight) && !empty($travelTime['nights'])) {
				$prod->hotelNight = floatval($travelTime['nights']);
			}
		}
		
		if(empty($dom->find('#hidProductID', 0)) || empty($dom->find('#hidProFlag', 0)) || empty($dom->find('#hidCalendarUrl', 0))) {
			return FALSE;
		}
		// 价格日历
		$prices = [];
		$time = time().rand(100, 999);
		
		$productId = trim($dom->find('#hidProductID', 0)->value);
		$prodFlag = trim($dom->find('#hidProFlag', 0)->value);
		$calendarUrl = trim($dom->find('#hidCalendarUrl', 0)->value);
		$priceUrl = "http://www.aoyou.com/package{$calendarUrl}?_={$time}&productID={$productId}&proFlag={$prodFlag}";
		$params['referer'] = $url;
		$params['ip'] = $ip;
		$priceText = _getText($priceUrl, $params);
		
		if(empty($priceText)) {
			sleep(2);
			$params['ip'] = $this->netutil->getIp();
			$priceText = _getText($priceUrl, $params);
		}

		if(empty($priceText)) {
			sleep(5);
			$params['ip'] = $this->netutil->getIp();
			$priceText = _getText($priceUrl, $params);
		}

		if(empty($priceText)) {
			echo "get empty prices product {$prod->code} return".PHP_EOL;
			return FALSE;
		}
		
		$priceDom = str_get_html($priceText);
		
		if(empty($priceDom)) {
			echo "get empty prices dom product {$prod->code} return".PHP_EOL;
			return FALSE;
		}
		
		if(empty($priceDom->find('#hidProductStatus', 0))) {
			return FALSE;
		}
		
		if(!empty($priceDom->find('#hidProductStatus', 0)) && $priceDom->find('#hidProductStatus', 0)->value == "30") {
			if(!empty($priceDom->find('#hidFirstDepartDate', 0)) && $priceDom->find('#hidFirstDepartDate', 0)->value != '1900年01月01日') {
				$monthDoms = $priceDom->find('.monthHead');
				$months = [];
				if(!empty($monthDoms)) {
					foreach($monthDoms as $monthDom) {
						$infoText = trim($monthDom->plaintext);
						$year = mb_substr($infoText, 0, 4, 'UTF8');
						$month = mb_substr($infoText, mb_strpos($infoText, '年') + 1, mb_strpos($infoText, '月') - mb_strpos($infoText, '年'));
						if($month < 10) {
							$month = '0'.intval($month);
						}
						$months[] = $year.'-'.$month;
					}
				}
				$monthPriceDoms = $priceDom->find('.calendar-table');
				if(!empty($monthPriceDoms)) {
					foreach($monthPriceDoms as $monthPriceDom) {
						$activeDoms = $monthPriceDom->find('.on');
						if(!empty($activeDoms)) {
							foreach($activeDoms as $td) {
								$inputs = $td->find('input');
								$date = null;
								if(!empty($inputs)) {
									foreach($inputs as $input) {
										if($input->name == 'DepartDate') {
											$date = trim($input->value);
										}
									}
								}
								if($td->find('.jsqr', 0)) {
									$prod->needConfirm = false;
								}
								if(empty($td->find('.price', 0))) {
									continue;
								}
								$price = new stdClass();
								$price->price = floatval(trim(str_replace('&yen;', '', $td->find('.price', 0)->plaintext)));
								
								if(!empty($td->find('.current-boxCont span'))) {
										
									$flightYuwei = '';
									$hotelYuwei = '';
									foreach($td->find('.current-boxCont span') as $span) {
										if(mb_strpos($span->plaintext, '酒店库存') !== FALSE) {
											if(!empty($span->find('em', 0))) {
												$hotelYuwei = trim($span->find('em', 0)->plaintext);
											}
										} else if(mb_strpos($span->plaintext, '机票库存') !== FALSE) {
											if(!empty($span->find('em', 0))) {
												$flightYuwei = trim($span->find('em', 0)->plaintext);
											}
										}
									}
									
									$yuwei = 0;
									if($flightYuwei === '充足' && $hotelYuwei !== '充足') {
										$yuwei = intval($hotelYuwei) * 2;
									} else if($flightYuwei !== '充足' && $hotelYuwei === '充足') {
										$yuwei = (int) $flightYuwei;
									} else if($flightYuwei === '充足' && $hotelYuwei === '充足' ) { // 默认库存8
										$yuwei = 8;
									} else if($hotelYuwei && $flightYuwei) {
										$hotelYuwei = intval($hotelYuwei) * 2; // 酒店库存*2；
										$flightYuwei = (int) $flightYuwei;
										$yuwei = min([$flightYuwei, $hotelYuwei]);
									} else if(!$hotelYuwei && !$flightYuwei) {
										$yuwei = floatval(8);
									}
										
									if(!empty($yuwei)) {
										$price->yuwei = floatval($yuwei);
										$prices[$date] = $price;
									} else {
										continue;
									}
								} else {
									$price->yuwei = floatval(8);
								}
								
								if(!empty($price->price) && !empty($price->yuwei)) {
									$prices[$date] =  $price;
								}
							}
						}
					}
				}
			} else { // 下架
				$this->dataprocess->setProductStatus($prod->code, 'stop');
				$this->cimongo->srcdb->duoqusourcedatas->remove(['code' => $prod->code], ['justOne' => TRUE]);
				echo date('Y-m-d').' line: '.__LINE__.' grab product code: '.$prod->code.' status stop 1, return'.PHP_EOL;
				
				return FALSE;
			}
		} else {
			$this->dataprocess->setProductStatus($prod->code, 'stop');
			$this->cimongo->srcdb->duoqusourcedatas->remove(['code' => $prod->code], ['justOne' => TRUE]);
			echo date('Y-m-d').' line: '.__LINE__.' grab product code: '.$prod->code.' status stop 2, return'.PHP_EOL;
				
			return FALSE;
		}

		if(empty($prices)) { // 网络异常
			return FALSE;
		}
		
		$prod->prices = (object) $prices;

		// 出发地及目的地
		empty($prod->start) AND $prod->start = [$this->start];
		empty($prod->end) AND $prod->end = [$this->end];
		
		// 机票
		$groupFlights = [];
		$trs = $dom->find('.cpdetail_con table.cpdetail_hbxx tr');
		if(!empty($trs) && count($trs) > 1) {
			$isRecmdFlight = FALSE;
			if(!empty($trs[0]->find('th.rec', 0))) {
				$isRecmdFlight = true;
			}
			array_shift($trs); // table header
			$count = count($trs);
			if($isRecmdFlight) {
				$count = (int) trim($trs[0]->find('td', 0)->getAttribute('rowspan'));
			}
			if(!empty($count)) {
				$curDay = '';
				for($i=0; $i < $count; $i++) {
					$tr = $trs[$i];
					$flight = [];
					$offset = 0;

					$tds = $tr->find('td');

					if(count($tds) < 5) {
						continue;
					}
					if($isRecmdFlight && $i == 0) {
						$offset = 1;
					}

					if($isRecmdFlight) {
						$curDay = $tds[$offset++]->plaintext;
					} else if(!empty($tds[0]->getAttribute('rowspan'))) {
						$curDay = $tds[$offset++]->plaintext;
					}

					if(!empty($curDay)) {
						$curDay = preg_replace('/[第天]/u', '', $curDay);
						$curDay = intval(trim($curDay));
						if(!empty($curDay)) {
							$flight['day'] = (float) $curDay;
						}
					}

					$tds[$offset++]; // 交通方式
					$key = trim($tds[$offset++]->plaintext);
					$directions = explode('到', $key);
					$flightInfo = explode(' ', trim($tds[$offset++]->find('div', 0)->plaintext));
					$space = trim($tds[$offset++]->plaintext);
					if(!empty($space)) {
						$flight['space'] = $space;
					}
					$ps = $tds[$offset++]->find('p');
					if(!empty($ps) && count($ps) == 2) {
						$takeoff = $ps[0]->plaintext;
						$takeoff = trim(preg_replace('/[出发]/u', '', $takeoff));
						$landing = $ps[1]->plaintext;
						$landing = trim(preg_replace('/[到达]/u', '', $landing));
						if(!empty($takeoff)) {
							$flight['takeoff'] = $takeoff;
						}
						if(!empty($landing)) {
							$flight['landing'] = $landing;
						}
					}
					$ps = $tds[$offset++]->find('p');
					if(!empty($ps) && count($ps) == 2) {
						$takeoffAirport = $ps[0]->plaintext;
						$takeoffAirport = trim(preg_replace('/[出发]/u', '', $takeoffAirport));
						$landingAirport = $ps[1]->plaintext;
						$landingAirport = trim(preg_replace('/[到达]/u', '', $landingAirport));
						if(!empty($takeoffAirport)) {
							$flight['takeoffAirport'] = $takeoffAirport;
						}
						if(!empty($landingAirport)) {
							$flight['landingAirport'] = $landingAirport;
						}
					}

					if(count($flightInfo) < 1) {
						continue;
					}

					$flightNo = trim(array_pop($flightInfo));
					$reg = "/^[0-9]+$/";
					if(preg_match($reg, $flightNo)) {
						if(count($flightNo) > 0) {
							$flightNo = trim(array_pop($flightInfo)).$flightNo;
						}	
					}
					
					$flightNo = str_replace(' ', '', $flightNo);
					$flightNo = trim(str_replace("\n", '', $flightNo));
					$reg = "/([A-Za-z0-9]{2,})$/i";
						
					if(preg_match($reg, $flightNo, $flightMatches)) {
						$flightNo = $flightMatches[1];
					} else {
						continue;
					}

					$flight['flightNo'] = trim($flightNo);
					$flight['airline'] = trim(implode(' ', $flightInfo));

					if(!empty($directions) && count($directions) == 2) {
						$flight['takeoffCity'] = trim($directions[0]);
						$flight['landingCity'] = trim($directions[1]);
					}
					
					$groupFlights[] = $flight;
				}
			}
		}
		
		$prod->flights = $groupFlights;

		// 酒店信息
		$hotels = [];
		$hotelDoms = $dom->find('.cpdetail_con table.cpdetail_jdxx_zyx .cpdetail_jdxx_zyx_list');

		if(!empty($hotelDoms)) {
			foreach($hotelDoms as $sub) {
				$hotel = [];
				$firstRowLis = $sub->find('ul.cpdetail_jdxx_zyx_list_l_xcfx li');
				if(!empty($firstRowLis)) {
					foreach($firstRowLis as $li) {
						if(mb_strpos(trim($li->plaintext), '行程安排', 0, 'UTF8') !== FALSE) {
							$times = explode('到', $li->find('span', 0)->plaintext);
							if(!empty($times) && count($times) >= 1) {
								$day = (int) trim(preg_replace('/[第天]+/u', '', $times[0]));
								$leaveDay = 0;
								if(count($times) > 1) {
									$leaveDay = (int) trim(preg_replace('/[第天]+/u', '', $times[1]));
								}
								if(!empty($day)) {
									$hotel['day'] = floatval($day);
									if(!empty($leaveDay)) {
										$hotel['night'] = floatval($leaveDay - $day + 1);
									}
								}
							}
						} else if(mb_strpos(trim($li->plaintext), '房型', 0, 'UTF8') !== FALSE) {
							$roomType = trim($li->find('span', 0)->plaintext);
							if(!empty($roomType)) {
								$hotel['roomType'] = $roomType;
							}
						}
					}
				}

				if(!empty($sub->find('.cpdetail_jdxx_zyx_list_l_tit h3', 0))) {
					$hotelTitleStr = trim($sub->find('.cpdetail_jdxx_zyx_list_l_tit h3', 0)->plaintext);
					$reg  = "/(\S+)\s*[\(（]\s*(\S+[\S\s]*\S+)\s*[\)）]/iu";
					if(preg_match($reg, $hotelTitleStr, $matches)) {
						$hotelName = trim($matches[1]);
						$hotel['englishName'] = trim($matches[2]);
					} else {
						$hotelName = trim($hotelTitleStr);
					}
					$hotelName = preg_replace('/\.$/u', '', $hotelName);
					$hotel['name'] = $hotelName;
				}

				if(!empty($sub->find('.cpdetail_jdxx_zyx_list_l_tit p'))) {
					$hotel['address'] = trim($sub->find('.cpdetail_jdxx_zyx_list_l_tit p', 0)->plaintext);
					$hotel['address'] = trim(str_replace('地址：', '', $hotel['address']));

					$p2 = $sub->find('.cpdetail_jdxx_zyx_list_l_tit p', 1);
					if(!empty($p2) && !empty($p2->find('span img'))) {
						$star = count($p2->find('span img'));
						$hotel['star'] = floatval($star);
					}
				}

				if(!empty($sub->find('.cpdetail_jdxx_zyx_list_l_wz', 0))) {
					$hotel['descothers'] = trim($sub->find('.cpdetail_jdxx_zyx_list_l_wz', 0)->innertext);
				}

				if(!empty($hotel['name'])) {
					$hotels[] = $hotel;
				}
			}
		}

		if(!empty($hotels)) {
			for($i=0; $i < count($hotels) - 1; $i++) {
				if(empty($hotels[$i]['night']) && !empty($hotels[$i]['day']) && !empty($hotels[$i+1]['day'])) {
					$hotels[$i]['night'] = (float) intval($hotels[$i+1]['day'] - $hotels[$i]['day']);
				}
			}
		}
		
		$prod->hotels = $hotels;

		if(!empty($dom->find('.cpdetail_fysm', 0))) { // 费用说明
			$cost = trim($dom->find('.cpdetail_fysm', 0)->innertext);
			if(!empty($cost)) {
				$prod->costInstruction = trim($cost);
			}
		}
		if(!empty($dom->find('.cpdetail_ydxz', 0))) { // 预订信息
			$booking = trim($dom->find('.cpdetail_ydxz', 0)->innertext);
			if(!empty($booking)) {
				$prod->booking = trim($booking);
			}
		}
		
		if(!empty($dom->find('.cpdetail_xcjs .cpdetail_xcjs_day', 0))) { // 参考行程
			$routePlan = trim($dom->find('.cpdetail_xcjs .cpdetail_xcjs_day', 0)->innertext);
			if(!empty($routePlan)) {
				$prod->routeplan = trim($routePlan);
			}
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

		if(empty($prod->flights) && empty($prod->hotels)) {
			echo "{$prod->code} empty flights and hotels".PHP_EOL;
			return FALSE;
		}

		if(empty($prod->flights)) { // debug, chenlei
			echo "{$prod->code} empty flights!!!".PHP_EOL;
		}

		if(empty($prod->hotels)) { // debug, chenlei
			echo "{$prod->code} empty hotels!!!".PHP_EOL;
		}

		$this->cimongo->srcdb->duoqusourcedatas->findAndModify(array('code'=>$prod->code), array('$set'=>(array)$prod), null, array('upsert'=>true));
			
// 		$this->dataprocess->productCode($prod->code, TRUE); // debug, chenlei
		$this->dataprocess->productCode($prod->code, FALSE); //@TODO remove. //debug, chenlei byford暂时不让入库插入新数据
		return TRUE;
	}
	
	public function grabById($id, $isInsert = false) // 抓取单个产品
	{
		$url = "http://www.aoyou.com/DomesticPackage/P{$id}i2";
		
		$this->load->helper('htmldom');
		$this->_grabdetail($url, $url);
		
		if($isInsert) {
			$code = $this->codePrefix.$id;
			$this->dataprocess->productCode($code, TRUE);
		}
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
}