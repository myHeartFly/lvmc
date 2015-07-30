<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * 更新 产品价格日历 及 上下架状态。
 * 1. 更新产品价格日历，及转为自售后得价格日历
 * 2. 更新价格日历时检查是否已下架 及 自动下架
 * 
 */

class UpdateProduct extends CI_Controller {
	
	private $ip = '';
	private $periods;
	const CODEPRELEN = 2; // code pre length;
	
	public function __construct() {
		parent::__construct();
		
		date_default_timezone_set('Asia/Shanghai');
		
		$this->load->helper('htmldom');
		$this->load->library('grab/netutil');
		$this->load->helper('grabformat');
		
		$this->load->library('dataprocess/dataprocess');
	}
	
	public function index() {
		echo 'update index product start:'.date("Y-m-d H:i:s")."\n";
		
		$condition = ['source' => ['$in' => ['来来会', '遨游网', '百程旅行', '麦兜旅行']]];
		
		$this->grabProductList($condition);
		
		echo 'update index product success end:'.date("Y-m-d H:i:s")."\n";
	}
	
	public function checkStopProducts() {
		echo 'update stop products start:'.date("Y-m-d H:i:s")."\n";
	
		$condition['source'] = ['$in' => ['来来会', '遨游网', '去哪儿', '百程旅行', '麦兜旅行']];
		$condition['status'] = 'stop';
		$condition['sourcestatus'] = 'normal';
	
		$this->grabProductList($condition);
	
		echo 'update index product success end:'.date("Y-m-d H:i:s")."\n";
	}
	
	public function source($source = '') {
		echo 'update index product start:'.date("Y-m-d H:i:s")."\n";
	
		$condition = ['source' => $source];
	
		$max = 300;
		$offset = 0;
		//计算产品总数量
		$total = $this->cimongo->db->duoqugrabs->count($condition);
		$periods = $this->cimongo->db->duoqudicts->find(['__t' => 'Period']);
		$this->periods = iterator_to_array($periods);
	
		do {
			//获取产品info
			$results = $this->cimongo->db->duoqugrabs->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
			foreach ($products as $prod) {
				$this->dealProduct($prod);
			}
				
		}while ($offset < $total);
	
		return true;
	
		echo 'update index product success end:'.date("Y-m-d H:i:s")."\n";
	}
	
	public function updateByCode($code)
	{
		$condition = ['code' => $code];
		$periods = $this->cimongo->db->duoqudicts->find(['__t' => 'Period']);
		$this->periods = iterator_to_array($periods);
		//计算产品总数量
		$prod = $this->cimongo->db->duoqugrabs->findOne($condition);
		if(!empty($prod)) {
			$this->dealProduct($prod);
		} else {
			exit('product is not exists');
		}
		
		echo "update product  {$prod['code']} success end:".date("Y-m-d H:i:s")."\n";
	}
	
	/**
	 * @desc 去哪儿  数据单跑
	 */
	public function qunar() {
		echo 'update qunar product start:'.date("Y-m-d H:i:s")."\n";
		
		$condition = ['source' => '去哪儿'];
		$this->grabProductList($condition);
		
		echo 'update qunar product success end:'.date("Y-m-d H:i:s")."\n";
	}
	
	private function grabProductList($condition = []) {
		
		if(empty($condition['status'])) {
			$condition['status'] = 'normal';
		}
		$max = 300;
		$offset = 0;
		//计算产品总数量
		$total = $this->cimongo->db->duoqugrabs->count($condition);
		$periods = $this->cimongo->db->duoqudicts->find(['__t' => 'Period']);
		$this->periods = iterator_to_array($periods);
		
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
			$results = $this->cimongo->db->duoqugrabs->find($condition)->sort(['created' => 1])->limit($max);
			$products = iterator_to_array($results);
			if(!empty($products)) {
				$productCount = count($products);
				
				$j = 0;
				foreach ($products as $prod) {
					$j++;
					$this->dealProduct($prod);

					$codesCount++;
					
					if(!empty($prod['created'])) {
						$startCreatedTime = $prod['created'];
					}
				}
			}
				
			$i++;
		}
		
		echo date('Y-m-d H:i:s').' update report : codes count: '.$codesCount. ' count products count: '.$total.PHP_EOL;
		return true;
	}
	
	private function dealProduct($prod) {
		mb_internal_encoding("UTF-8");
		//切换ip
		$ip = $this->netutil->getIp();

		$code = $prod['code'];
		$source = $prod['source'];
		
		if(empty($prod['sourceurl'])) {
			return  FALSE;
		}
		
		$maps = [
			'去哪儿'   => 'qunar',
			'来来会'   => 'lailaihui',
			'百程旅行' => 'baicheng',
			'遨游网'   => 'aoyou',
			'麦兜旅行' => 'maidou',
		];
		
		echo date('Y-m-d H:i:s')." deal product {$code} start ".PHP_EOL;
		if(empty($maps[$source])) {
			return false;
		}
		
		$method = "grab".$maps[$source];
		$orgPrices = $this->$method($prod);
		
		if(empty($orgPrices)) {
			return FALSE;
		}
		
		$result = $this->dataprocess->priceCal($code, $orgPrices);
		
		return $result;
	}
	
	// 来来会
	private function grablailaihui($prod)
	{
		mb_internal_encoding("UTF-8");
		$prices = [];
		$code = $prod['code']; // codePrefix + orgCode
		$orgCode = substr($code, self::CODEPRELEN);
		$url = $prod['sourceurl'];
		if(empty($url)) return FALSE;
		
		$ip = $this->netutil->getIp();
		$params = ['ip' => $ip, 'referer' => $url];
		
		$dom = $this->_getDOM($url, $params);
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
		
		// 价格日历
		$trave_date = $dom->find('.productDetail .img_date .trave_date', 0);
		if(!empty($trave_date)) {
			foreach ($trave_date->find('.dateCon table td[price]') as $td) {
				$td->rel = trim($td->rel);
				$yuwei = floatval(trim(str_replace('余位：', '', $td->find('.yw', 0)->innertext)));
				$price = floatval($td->price);
				if(empty($yuwei) || empty($price)) {
					continue;
				}
				$prices[$td->rel] = (object)array('yuwei' => $yuwei, 'price'=> $price);
			}
		}
		
		if($status == 'stop') {
			$this->dataprocess->setProductStatus($code, $status);
		}
		
		return $prices;
	}
	
	// 百程旅行
	private function grabbaicheng($prod)
	{
		mb_internal_encoding("UTF-8");
		$prices = [];
		$code = $prod['code']; // codePrefix + orgCode
		$orgCode = substr($code, self::CODEPRELEN);
		$url = $prod['sourceurl'];
		if(empty($url)) return FALSE;
		
		$ip = $this->netutil->getIp();
		$params = ['ip' => $ip, 'referer' => $url];
		
		$html = _getText($url, $params);
		
		if(empty($html)) {
			return FALSE;
		}
		$dom = str_get_html($html);
		if(empty($dom)) return FALSE;
		
		$status = 'normal';
		if(!empty($dom->find('title', 0))) {
			$title = $dom->find('title', 0)->plaintext;
			$reg = "/.*百程旅行网温馨提示.*/iu";
			if (preg_match($reg, trim($title), $matches)) {
				$status = 'stop';
			}
		}
		
		if(!empty($dom->find('.pro_price .pro_dd a', 0))) {
			$button = $dom->find('.pro_price .pro_dd a', 0)->plaintext;
			$reg = "/.*卖光了.*/iu";
			$isMatchA = preg_match($reg, trim($button), $matches);
			$reg = "/.*立即预订.*/iu";
			$isMatchB = preg_match($reg, trim($button), $matches);
			if ($isMatchA && !$isMatchB) {
				$status = 'stop';
			}
		}
	
		if($status == 'stop') {
			$this->dataprocess->setProductStatus($code, $status);
			return $prices;
		}
		
		// 价格日历
		$reg = "/var DATA\s*=\s*([^;\n]+);?\n/";
		$isMatch = preg_match($reg, $html, $matches);
		if($isMatch) {
			$priceStr = $matches[1];
			$priceStr = str_replace('price', '"price"', $priceStr);
			$priceStr = str_replace('rs', '"rs"', $priceStr);
			$priceStr = str_replace('days', '"days"', $priceStr);
			$priceStr = str_replace('min', '"min"', $priceStr);
			$priceStr = str_replace('max', '"max"', $priceStr);
			$priceStr = str_replace('selected', '"selected"', $priceStr);
			
			try {
				$priceDatas = json_decode($priceStr, true);
			} catch(Exception $e) {
				echo $e->getMessage();
			}
			
			
			if(!empty($priceDatas)) {
				foreach($priceDatas as $dateStr => $item) {
					$year = substr($dateStr, 0, 4);
					$month = substr($dateStr, 4, 2);
					$day = substr($dateStr, 6, 2);
					$date = "{$year}-{$month}-{$day}";
					$yuwei = !empty($item['rs']) ? $item['rs'] : 0;
					$price = !empty($item['price']) ? $item['price'] : 0;
					
					if(!empty($yuwei) && !empty($price)) {
						$prices[$date] = array(
								'price' => floatval($price),
								'yuwei' => floatval($yuwei),
						);
					}
				}
			}
		}
		
		return $prices;
	}
	
	// 遨游网
	private function grabaoyou($prod)
	{
		mb_internal_encoding("UTF-8");
		$prices = [];
		$code = $prod['code']; // codePrefix + orgCode
		$orgCode = substr($code, self::CODEPRELEN);
		$url = $prod['sourceurl'];
		if(empty($url)) return FALSE;
		
		$ip = $this->netutil->getIp();
		$params = ['ip' => $ip, 'referer' => $url];
		
		$html = _getText($url, $params);
		
		if(empty($html)) {
			sleep(2);
			$html = _getText($url, $params);
		}
		if(empty($html)) {
			return FALSE;
		}
		$dom = str_get_html($html);
		if(empty($dom)) return FALSE;
		
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
			echo "get empty prices product {$prod['code']} return".PHP_EOL;
			return FALSE;
		}
		
		$priceDom = str_get_html($priceText);
		
		if(empty($priceDom)) {
			echo "get empty prices dom product {$prod['code']} return".PHP_EOL;
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
				$this->dataprocess->setProductStatus($prod['code'], 'stop');
				$this->cimongo->srcdb->duoqusourcedatas->remove(['code' => $prod['code']], ['justOne' => TRUE]);
				echo date('Y-m-d').' line: '.__LINE__.' grab product code: '.$prod['code'].' status stop 1, return'.PHP_EOL;
				
				return FALSE;
			}
		} else {
			$this->dataprocess->setProductStatus($prod['code'], 'stop');
			$this->cimongo->srcdb->duoqusourcedatas->remove(['code' => $prod['code']], ['justOne' => TRUE]);
			echo date('Y-m-d').' line: '.__LINE__.' grab product code: '.$prod['code'].' status stop 2, return'.PHP_EOL;
				
			return FALSE;
		}
		
		return $prices;
	}
	
	// 去哪儿
	private function grabqunar($prod)
	{
		mb_internal_encoding("UTF-8");
		$prices = [];
		$code = $prod['code']; // codePrefix + orgCode
		$orgCode = substr($code, self::CODEPRELEN);
		$url = $prod['sourceurl'];
		if(empty($url)) return FALSE;
		
		$ip = $this->netutil->getIp();
		$params = ['ip' => $ip, 'referer' => $url];
		
		sleep(10);
		$dom = $this->_getDOM($url, $params);
		if(empty($dom)) return FALSE;
		
		$status = 'normal';
		if(!empty($dom->find('#price_div .order', 0))) {
			$price = $dom->find('#price_div .order', 0)->plaintext;
			$reg = "/.*已经售罄.*/iu";
			$isMatch = preg_match($reg, trim($price), $matches);
			if (trim($price) == '已经售罄' || $isMatch) {
				$status = 'stop';
				$this->dataprocess->setProductStatus($code, $status);
				return $prices;
			}
		}
		
		$urlComs = parse_url($prod['sourceurl']);
		$host = "http://{$urlComs['host']}";
		
		$priceYear = date('Y');
		$priceMonth = date('m');
		
		$maxMonth = $priceMonth;
		$priceTimes = 0;
		$maxPriceTimes = 12;
		do {
			$time = time();
			$priceApi = "{$host}/api/calPrices.json?pId={$orgCode}&month={$priceYear}-{$priceMonth}&t={$time}";
// 			echo "price api {$priceApi}\n"; // debug, chenlei
			$priceRes = $this->_getText($priceApi, $params);
			if(!empty($priceRes)) {  // 价格日历
				$priceRet = json_decode($priceRes, true);
				if(isset($priceRet['ret']) && $priceRet['ret']) {
					foreach ($priceRet['data']['team'] as $row) {
						$r = [];
						if(!empty($row['prices']['adultPrice'])) {
							$r['price'] = floatval($row['prices']['adultPrice']);
						}
						if(!empty($row['prices']['childPrice'])) {
							$r['childprice'] = floatval($row['prices']['childPrice']);
						}
						if(empty($r['price']) && !empty($row['prices']['taocan_price'])) {
							$r['price'] = floatval($row['prices']['taocan_price']);
						}
						if(!empty($row['only'])) {
							$r['yuwei'] = floatval($row['only']);
						}
						if(!empty($r) && !empty($r['price']) && !empty($r['yuwei'])) {
							$prices[$row['date']] = $r;
						}
					}
					$maxDay = (string) $priceRet['data']['maxDay'];
					$maxDay = (int) substr($maxDay, 0, strlen($maxDay) -3);
					$maxYear = date('Y', $maxDay);
					$maxMonth = date('m', $maxDay);
					$currentDate = "{$priceYear}-{$priceMonth}-01 00:00:00";
					$maxDate = "{$maxYear}-{$maxMonth}-01 00:00:00";
					$currentTime = strtotime($currentDate);
					$maxTime = strtotime($maxDate);
					if($currentTime < $maxTime) {
						if($priceMonth < 12) {
							$priceMonth = intval($priceMonth) + 1;
							if($priceMonth < 10) {
								$priceMonth = (string) '0'.$priceMonth;
							} else {
								$priceMonth = (string) $priceMonth;
							}
						} else {
							$priceYear += 1;
							$priceMonth = '01';
						}
					}
				}
			}
			$priceTimes ++;
		} while($maxMonth != $priceMonth && $priceTimes <= $maxPriceTimes);
		
		return $prices;
	}
	
	// 麦兜旅行
	private function grabmaidou($prod)
	{
		mb_internal_encoding("UTF-8");
		$prices = [];
		$code = $prod['code']; // codePrefix + orgCode
		$orgCode = substr($code, self::CODEPRELEN);
		$url = $referer = $prod['sourceurl'];
		if(empty($url)) return FALSE;
		
		$ip = $this->netutil->getIp();
		$params = ['ip' => $ip, 'referer' => $url];
		
		$html = _getText($url, $params);
		
		if(empty($html)) {
			return FALSE;
		}
		$dom = str_get_html($html);
		if(empty($dom)) return FALSE;
		
		if(!empty($dom->find('.content-404', 0))) {
			$status = 'stop';
			$this->dataprocess->setProductStatus($code, $status);
			return $prices;
		}
		
		$monthReg = "/[^v]\sfirstMonth =\s*([^;\"]*);/";
		if(preg_match($monthReg, $html, $monthMatches)) {
			$firstMonth = $monthMatches[1];
			$groupIdReg = "/showTourPeriod\(\"([^\"]*)\",firstMonth/";
			if(preg_match($groupIdReg, $html, $groupIdMatches)) {
				$groupId = trim($groupIdMatches[1]);
			}
		}
		
		// 价格日历
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
		
		return $prices;
	}

	//获取地址html对象
	private function _getDOM($url, $params=[]) {
		$options  = array(
				'http' => array(
						'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36',
						'header' => 'X-Forwarded-For: '.(!empty($params['ip']) ? $params['ip'] : '211.144.106.58'), // 伪造ip (中国)
						'Referer' => !empty($params['referer']) ? $params['referer'] : '',
				)
		);
	
		$context  = stream_context_create($options, ['timeout' => 10]);
	
		$dom = @file_get_html($url, false, $context);
	
		return $dom;
	}
	
	//获取地址内容
	private function _getText($url, $params=[]) {
		$options  = array(
				'http' => array(
						'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36',
						'header' => 'X-Forwarded-For: '.(!empty($params['ip']) ? $params['ip'] : '211.144.106.58'), // 伪造ip (中国)
						'Referer' => !empty($params['referer']) ? $params['referer'] : '',
				)
		);
		$context  = stream_context_create($options);
		$dom = @file_get_contents($url, false, $context);
	
		return $dom;
	}
	
	public function fixstop() // tmp 函数
	{
		$count = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		
		$sources = ['百程旅行', '遨游网']; // debug, chenlei 修复的数据源
		
		$total = $this->cimongo->db->duoquproducts->count(['status' => 'stop']);
		
		$resetCodes = [];
		do {
			echo "offset : $offset"."\n";
			
			$results = $this->cimongo->db->duoquproducts->find(['status' => 'stop'])->sort(['_id' => 1])->skip($offset)->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
			
			$codes = [];
			foreach($products as $prod) {
				if(!empty($prod['grabcode'])) {
					$codes[] = $prod['grabcode'];
				}
			}
			
			if(!empty($codes)) {
				$condition = ['code' => ['$in' => $codes]];
				
				if(empty($sources)) {
					exit('要修复的数据来源不能为空'); // 防止误操作
				} else {
					$condition['source'] = ['$in' => $sources];
				}
				
				$results = $this->cimongo->db->duoqugrabs->find($condition);
				$grabs = iterator_to_array($results);
					
				foreach($grabs as $prod) {
					if(!empty($prod['code']) && $prod['sourcestatus'] == 'normal') {
						$count++;
						$resetCodes[] = $prod['code'];
					}
						
				}
			}
			
		} while($offset < $total);
		
		if(!empty($resetCodes)) {
			$codegroups = array_chunk($resetCodes, 300);
			foreach($codegroups as $codes) {
				$this->cimongo->db->duoqugrabs->update(['code' => ['$in' => $codes]], ['$set' => ['status' => 'normal']], ['multiple' => true]);
				$this->cimongo->db->duoqugrabindexes->update(['code' => ['$in' => $codes]], ['$set' => ['status' => 'normal']], ['multiple' => true]);
				$this->cimongo->db->duoquproducts->update(['grabcode' => ['$in' => $codes]], ['$set' => ['status' => 'normal']], ['multiple' => true]);
				sleep(0.1);
			}
		}
		echo 'total update status normal: '.$count.PHP_EOL;
	}
}