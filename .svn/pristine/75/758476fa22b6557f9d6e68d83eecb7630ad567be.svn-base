<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Grabqunar extends CI_Controller {
	private $source = '去哪儿';
	private $codePrefix = 'QN';
	private $category;
	private $start;
	private $end;
	private $dict;
	private $taskType; // 任务类型，更新 or 插入
	private $periods; // 日期
	
	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->library('curl');
		
	}
	
	public function index()
	{		
		date_default_timezone_set('Asia/Shanghai');
// 		header('Content-Type: application/json'); // debug, chenlei
		
		$this->load->library('city/city');
		$this->load->helper('htmldom');
		
		echo 'grab source data start:'.date("Y-m-d H:i:s")."\n";
		
		$result = [];
		$this->category = '自由行';
		$folder = FCPATH.'/public/grab/qunar/list/';
		
		$periods = $this->cimongo->db->duoqudicts->find(['__t' => 'Period']);
		$this->periods = iterator_to_array($periods);
		
		$startCities = $this->city->getCities('start', 'lailaihui');
		$endCities = $this->city->getCities('end', 'lailaihui');
		
		foreach($startCities as $startCity => $startParams) {
			foreach($endCities as $endCity => $endParams) {
				if($startCity == $endCity) {
					continue;
				}
				$i = 0;
				$this->start = $startCity;
				$this->end = $endCity;
				$result = $this->grabList();
				if(!$result) continue;
				
				$flies = glob("{$folder}*.json");
				foreach($flies as $k => $file) {
					$content = file_get_contents($file);
					$data = json_decode($content, true);
					$list = $data['data']['list']['results'];
					foreach($list as $item) {
						if($item['wrpparid'] == 'jijiajiu') { // self 暂不抓取
							continue;
// 							$prod = $this->grabJiJiaJiuProduct($item);
				
						}
						else if(!$item['b2c']) { // 非B2C 自由行产品 (预订) 暂不抓取
							continue;
// 							$prod = $this->grabPartnerOtherProduct($item);
						}
						else if(!empty($item['summary']) && !empty($item['summary']['supplier']) && !empty($item['summary']['supplier']['name']) 
							&& in_array($item['summary']['supplier']['name'], ['中青旅百变自由行', '百程旅行网', '来来会旗舰店', '爱旅行', '麦兜旅行', '趣旅网'])) {
							continue; // 已单独抓取 aoyou.com, baicheng.com, lailaihui.com
						}
						else if(!empty($item['summary']) && !empty($item['summary']['supplier']) && !empty($item['summary']['supplier']['name'])) { // 有第三方供应商名称
							$this->grabPartnerB2CProduct($item);
							sleep(10);
						}
						
// 						sleep(10);
					}
				}
				
			}
		}
		
		echo 'import qunar success'.date("Y-m-d H:i:s")."\n";
	}
	
	private function grabList() {// 抓取列表
		$this->load->helper('file');
		
		$folder = FCPATH.'/public/grab/qunar/list/';
		
		if(file_exists($folder)) {
			delete_files($folder);
		}
		if(!file_exists($folder)) {
			mkdir($folder, 0775, true);
		}
		
		$max = 20;
		$startOffset = 0;
		$endOffset = $startOffset + $max;
		
		$start = urlencode($this->start);
		$end = urlencode($this->end);
		$category = urlencode($this->category);
		
		$offset = 0;
		$total  = 0;
		
		do {
			$endOffset = $offset + $max;
			// http://dujia.qunar.com/golfz/routeList?isTouch=0&t=travel&f=自由行&o=pop-desc&lm=0,20&fhLimit=0,20&q=普吉岛&d=北京&s=travel&qs_ts=1425522263898&tf=arnoresult&ti=3&tm=l01_travel&sourcepage=list&qssrc=eyJ0cyI6IjE0MjU1MjIyNjM4OTgiLCJzcmMiOiJ0cmF2ZWwiLCJhY3QiOiJmaWx0ZXIifQ==&m=l,lm
			// http://dujia.qunar.com/golfz/routeList?isTouch=0&t=travel&f=自由行&o=pop-desc&lm=20,20&fhLimit=0,20&q=香港&d=上海&s=travel&qs_ts=1425523143396&ti=3&tm=l01_travel&sourcepage=list&qssrc=eyJ0cyI6IjE0MjU1MjMxNDMzOTYiLCJzcmMiOiJ0cmF2ZWwiLCJhY3QiOiJzY3JvbGwifQ==&m=l,lm
			$current = time().'023';
			$lm = urlencode($offset.','.$max);
// 			$srcUrl = "http://dujia.qunar.com/golfz/routeList?isTouch=0&t=travel&f={$this->category}&o=pop-desc&lm={$offset},{$max}&fhLimit=0,20&q={$this->end}&d={$this->start}&s=travel&qs_ts={$current}&tf=arnoresult&ti=3&tm=l01_travel&sourcepage=list&qssrc=eyJ0cyI6IjE0MjU1MjIyNjM4OTgiLCJzcmMiOiJ0cmF2ZWwiLCJhY3QiOiJmaWx0ZXIifQ==&m=l,lm";
			$srcUrl = "http://dujia.qunar.com/golfz/routeList?isTouch=0&t=travel&f={$category}&o=pop-desc&lm={$lm}&fhLimit=0%2C20&q={$end}&d={$start}&s=all&qs_ts={$current}&sourcepage=list&qssrc=eyJ0cyI6IjE0Mjg1NjQwNTI2NTgiLCJzcmMiOiJhbGwiLCJhY3QiOiJmaWx0ZXIifQ==&m=l%2Clm";
			
			$response = $this->getResponse($srcUrl);
			$listJson = "{$folder}{$offset}_{$endOffset}.json";
			if(!empty($response)) {
				file_put_contents($listJson, $response);
			}
			$data = json_decode($response, true);
			
			if(isset($data['data']['limit']['routeCount']) && !empty($data['data']['limit']['routeCount'])) {
				$total = $data['data']['limit']['routeCount'];
			}

			$offset = $endOffset;
			
			sleep(5);
		} while($offset < $total);
		
		return true;
	}
	
	// 获取产品基本信息
	private function grabProductBase($item) {
			
		$prod = array(
				'source'     => $this->source,
				'vender'     => $item['summary']['supplier']['name'],
				'name'       => trim($item['title']),
				'start'      => explode(',', $item['dep']),
				'end'        => $item['citys'],
				'type'       => [$this->category],
				'status'     => 'stop',  // 设置停售状态，信息补充完成可改为正常状态
// 				'images'     => [],
				'days'       => !empty($item['details']['tripTime']) ? floatval(trim(preg_replace('/[天]/u', '', $item['details']['tripTime']))) : floatval(0),
				'hotelNight' => !empty($item['details']['hotelNight']) ? floatval($item['details']['hotelNight']) : floatval(0),
				'hotels'     => [],
				'flights'    => [],
		);
		
		return $prod;
	}
	
	private function grabPartnerB2CProduct($item) {
		$prod = $this->grabProductBase($item);
	
		$url = $item['url'];
		$id = $item['id'];
	
		$response = $this->getResponse($url);
		if(empty($response)) {
			$this->writeLog("curl fetch content empty, url: $url", 'NOTICE');
			return false;
		}
	
		$reg = "/location.href = \'([^\']+)\'/i";
		$isMatch = preg_match($reg, $response, $matches);
		if(empty($isMatch)) {
			$this->writeLog("there is not location 302 url: $url", 'ERROR');
			return false;
		}
		$realUrl = $matches[1];
		$response = $this->getResponse($realUrl);
		if(empty($response)) {
			$this->writeLog("curl fetch content empty, url: {$url}", 'NOTICE');
			return false;
		}
		
		$dom = str_get_html($response);
		mb_internal_encoding("UTF-8");
		if(empty($dom->find('.main .top_show .title h2'))) { // 找不到标题，跳过
			$this->writeLog("curl not found the title, url: {$url}", 'NOTICE');
			return false;
		}
		$title = $dom->find('.main .top_show .title h2', 0)->innertext;
		$prod['name'] = trim(mb_substr($title, 0, mb_strpos($title, '<')));
		$dtls = $dom->find('.main .top_show .right .text_dtl_p');
		if(!empty($dtls)) {
			foreach($dtls as $dtl) {
				if(!empty($dtl) && !empty($dtl->find('.t', 0)) && trim($dtl->find('.t', 0)->plaintext) == '产品编号') {
					if(!empty($dtl->find('.ct', 0))) {
						$code = trim($dtl->find('.ct', 0)->plaintext);
						break;
					}
				}
			}
		}
		if(empty($code)) { // 获取不到产品code.
			return false;
		}
		$prod['code'] = $this->codePrefix.$code; // 真实编号及url
		$prod['sourceurl'] = $realUrl;
// 		echo $prod['sourceurl']."\n"; // debug, chenlei
		
		$queryProd = $this->cimongo->db->duoqugrabs->findOne(['code' => $prod['code']]);
		if(!empty($queryProd)) {
			$this->taskType = 'update';
		} else {
			$this->taskType = 'insert';
		}
		
		$isMatchConfirm = preg_match('/<b([^>]*)>([^<]*)</i', $title, $confirmMatches); // 二次确认
		if($isMatchConfirm && $confirmMatches[2] == '二次确认') {
			$prod['needConfirm'] = true;
		}
		
		if(!empty($prod['name'])) {
			$reg = "/[^\d]*(\d+)晚([\d\/-]+)[日天].*/iu";
			$isMatchNight = preg_match($reg, $prod['name'], $matches);
			if($isMatchNight) {
				if(empty($prod['hotelNight'])) {
					$prod['hotelNight'] = floatval($matches[1]);
				}
				if(empty($prod['days'])) {
					$prod['days'] = floatval($matches[2]);
				}
			}
			if(empty($prod['hotelNight'])) {
				$reg = "/[^\d]*([\d\/-]+)[日天](\d+)晚.*/iu";
				$isMatchNight = preg_match($reg, $prod['name'], $matches);
				if($isMatchNight) {
					if(empty($prod['hotelNight'])) {
						$prod['hotelNight'] = floatval($matches[2]);
					}
					if(empty($prod['days'])) {
						$prod['days'] = floatval($matches[1]);
					}
				}
			}
		}
	
// 		foreach ($dom->find('.down ul li img') as $img) { // 图片
// 			$r = new stdClass();
// 			$r->image = $img->getAttribute('blazyload');
// 			if(!empty($r->image)) {
// 				$prod['images'][] = $r;
// 			}
// 		}
			
		$phoneDom = $dom->find('.online_order_tel span em', 0);
		if(!empty($phoneDom)) {
			$phoneText = trim($phoneDom->plaintext);
			$phoneText = str_replace('转', ',', $phoneText);
			$phoneText = str_replace('-', '', $phoneText);
			$phoneText = str_replace(' ', '', $phoneText);
			$prod['servicephone'] = $phoneText;
		}
	
		$urlComs = parse_url($realUrl);
		$host = "http://{$urlComs['host']}";

		$urlComs = parse_url($prod['sourceurl']);
		if(!empty($urlComs) && !empty($urlComs['query'])) {
			$decodeQuery = urldecode($urlComs['query']);
			$queries = explode('&', $decodeQuery);
			$qParams = [];
			foreach($queries as $q) {
				$qkv = explode('=', $q);
				if(!empty($qkv) && count($qkv) == 2) {
					$qParams[trim($qkv[0])] = trim($qkv[1]);
				}
			}
			if(!empty($qParams) && !empty($qParams['DepDate'])) {
				$priceYear = date('Y', strtotime($qParams['DepDate']));
				$priceMonth = date('m', strtotime($qParams['DepDate']));
			}
		}
		if(empty($priceYear) || empty($priceMonth)) {
			$priceYear = date('Y');
			$priceMonth = date('m');
		}
		// @TODO fix some price clander. maxpricetime may next month;
		// pricecal http://szgl3.package.qunar.com/api/calPrices.json?pId=2694260324&month=2015-02&t=1421661449024
		
		$maxMonth = $priceMonth;
		$priceTimes = 0;
		$maxPriceTimes = 12;
		$prod['prices'] = [];
		do {
			$time = time();
			$priceApi = "{$host}/api/calPrices.json?pId={$code}&month={$priceYear}-{$priceMonth}&t={$time}";
			// 		echo "price api {$priceApi}\n"; // debug, chenlei
			$priceRes = $this->getResponse($priceApi);
			if(!empty($priceRes)) {  // 价格日历
				$priceRet = json_decode($priceRes, true);
				if(isset($priceRet['ret']) && $priceRet['ret']) {
					foreach ($priceRet['data']['team'] as $row) {
						$r = [];
						$departTime = strtotime($row['date']); // 产品出发时间
						if($departTime < (time() + 5 * 24 * 60 * 60)) { // 过期时间或者5天内全部过期的排除在外
							continue;
						}
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
							$prod['prices'][$row['date']] = $r;
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
		
		// 出发日期
		$startFestivals = [];
		$startMonths = [];
		$startDays = [];
		if(!empty($prod['prices'])) {
			foreach($prod['prices'] as $date => $row) {
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
			$prod['startDate'] = implode($startDays, '、');
		} else {
			return false;
		}
		
		if($this->taskType == 'update') { // 更新任务时 只更新价格出发日期等数据
			
			$newProd = array(
					'prices'       => !empty($prod['prices']) ? $prod['prices'] : new stdClass(),
					'startDate'    => !empty($prod['startDate']) ? $prod['startDate'] : '',
					'sourcestatus' => 'normal',
					'sourceurl'    => $prod['sourceurl'],
					'grabtime'     => new MongoDate(),
			);
			
			if(!empty($prod['days']) && (empty($queryProd['days']) || $queryProd['days'] == 1)) {
				$newProd['days'] = floatval($prod['days']);
			}
			$this->cimongo->db->duoqugrabs->findAndModify(array('code'=>$prod['code']), array('$set'=> $newProd));
			echo $prod['code']." updated ".date("Y-m-d H:i:s")."\n";
			return true;
		}
		
		return false; // @TODO remove. // debug, chenlei. byford要求暂时只更新 2015.03.28
		
		// 出发地及目的地
		empty($prod['start']) AND $prod['start'] = [$this->start];
		empty($prod['end']) AND $prod['end'] = [$this->end];
		$prod['startKey'] = []; // 绑定key
		$prod['endKey'] = [];
		foreach($prod['start'] as $k => $city) {
			$queryPlace = $this->cimongo->db->duoqudicts->findOne(['__t' => 'Place', 'title' => $city]);
			if(!empty($queryPlace)) {
				$prod['startKey'][] = (string) $queryPlace['key'];
			}
		}
		foreach($prod['end'] as $k => $city) {
			$queryPlace = $this->cimongo->db->duoqudicts->findOne(['__t' => 'Place', 'title' => $city]);
			if(!empty($queryPlace)) {
				$prod['endKey'][] = (string) $queryPlace['key'];
			}
		}
		
		// 推荐理由
		$reasonDom = $dom->find('div[id=js_top_tjly_w] .text_dtl_p  .ct', 0);
		if(!empty($reasonDom)) {
			$prod['recommend'] = trim($reasonDom->plaintext); // 推荐理由
		}
		
		$direction = 'depart';
		$flightDes = []; // flight destinations
		if($dom->find('.tra_box table', 0)) {
			foreach($dom->find('.tra_box table', 0)->find('tr') as $tr) { // 第一组航班
				$flight = [];
				$tds = $tr->find('td');
				$tdNum = count($tds);
				if($tdNum < 8) {
					continue;
				}
					
				$landing = trim($tds[$tdNum-3]->plaintext);
				$takeoff = trim($tds[$tdNum-4]->plaintext);
				$takeoff = str_replace('起飞', '', $takeoff); // 起飞时间
				$landing = str_replace('抵达', '', $landing);
				if(!empty($landing) && mb_strpos('次日', $landing) !== FALSE) {
					$landing = str_replace('次日', '', $landing);
					$landing .= '+1';
				}
				$day = trim($tds[$tdNum-8]->plaintext);
					
					
				$flight['airline'] = '';
				$flight['takeoff'] = $takeoff;
				$flight['landing'] = $landing;
				$flight['model'] = trim($tds[$tdNum-2]->plaintext);
				$flightNo = trim($tds[$tdNum-5]->plaintext);
				$flightNo = str_replace(' ', '', $flightNo);
				$flightNo = trim(str_replace("\n", '', $flightNo));
				$flight['flightNo'] = $flightNo;
				$flight['airlineKey'] = '-1';
				$flight['modelKey'] = '-1';
				$directionStr = trim($tds[$tdNum-6]->plaintext);
				$cities = explode('-', $directionStr);
				if(empty($cities) || count($cities) < 2) {
					continue;
				}
				$flight['takeoffCity'] = trim($cities[0]) == '普吉' ? '普吉岛' : trim($cities[0]);
				$flight['landingCity'] = trim($cities[1]) == '普吉' ? '普吉岛' : trim($cities[1]);
				if(!in_array($flight['landingCity'], $flightDes)) {
					$flightDes[] = $flight['landingCity'];
				}
				
				$reg = "/^[A-Za-z0-9]*$/i";
				if(!preg_match($reg, $flightNo)) {
					continue;
				}
				
				$queryFlight = $this->cimongo->db->flightbases->findOne(['flightNo' => $flightNo]);
				if(!empty($queryFlight)) {
					$updateRet = $queryFlight;
				} else {
					$airCode = substr($flightNo, 0, 2);
					
					if(!empty($flight['takeoffCity'])) {
						$queryPlace = $this->cimongo->db->duoqudicts->findOne(['__t' => 'Place', 'title' => $flight['takeoffCity']]);
						if(!empty($queryPlace)) {
							$flight['takeoffCityKey'] = (string) $queryPlace['key'];
						}
					}
						
					if(!empty($flight['landingCity'])) {
						$queryPlace = $this->cimongo->db->duoqudicts->findOne(['__t' => 'Place', 'title' => $flight['landingCity']]);
						if(!empty($queryPlace)) {
							$flight['landingCityKey'] = (string) $queryPlace['key'];
						}
					}
					
					$queryAirline = $this->cimongo->db->duoqudicts->findOne(['__t' => 'Airline', 'codes' => $airCode]);
					if(!empty($queryAirline)) {
						$flight['airline'] = $queryAirline['name'];
						$flight['airlineKey'] = $queryAirline['key'];
					}
					
					$flight['created'] = new MongoDate();
					$updateRet = $this->cimongo->db->flightbases->findAndModify(['flightNo' => $flightNo], array('$set'=> $flight), ['_id' => true], array('upsert'=>true, 'new' => true));
				}
					
				$fRow = [];
				if(!empty($updateRet['_id'])) {
					$fRow['flight'] = $updateRet['_id'];
					if(!empty($day)) {
						$day = preg_replace('/[第天]/u', '', $day);
						$fRow['day'] = floatval($day);
					} else {
						if(!empty($prod['flights'][count($prod['flights'])-1]) && !empty($prod['flights'][count($prod['flights'])-1]['day'])) {
							$fRow['day'] = $prod['flights'][count($prod['flights'])-1]['day'];
						}
					}
					$fRow['trip'] = $direction;
					if($direction == 'depart' && $flight['landingCity'] == $this->end) {
						$direction = 'back';
					}
					if($direction == 'back' && $flight['landingCity'] == $this->start) {
						$direction = 'depart';
					}
						
					$prod['flights'][] = $fRow;
				}
					
			}
				
			if(!empty($prod['flights'])) {
				if(empty($prod['flights'][0]['day'])) {
					$prod['flights'][0]['day'] = floatval(1);
					$isUpdate = true;
				}
				if(count($prod['flights']) == 2 && !empty($prod['days']) && $prod['days'] > 1 && !empty($prod['flights'][1]['flight']) && empty($prod['flights'][1]['day'])) {
					$queryFlight = $this->cimongo->db->flightbases->findOne(['_id' => $prod['flights'][1]['flight']]);
					if(!empty($queryFlight) && !empty($queryFlight['takeoff']) && !empty($queryFlight['landing'])) {
						$fixday = 0;
						if(preg_match("/\d+:\d+\s*\+(\d+)/", $queryFlight['landing'], $timeMatches)) {
							$fixday = (int) $timeMatches[1];
						}
							
						if(preg_match("/(\d+):\d+/", $queryFlight['takeoff'], $takeoffMatches) && preg_match("/(\d+):\d+/", $queryFlight['landing'], $landingMatches)) {
							if($takeoffMatches[1] < $landingMatches[1] || $fixday > 0) {
								if(floatval($prod['days'] - $fixday) > 0) {
									$prod['flights'][1]['day'] = floatval($prod['days'] - $fixday);
								}
							} else if($takeoffMatches[1] > $landingMatches[1]) {
								if(floatval($prod['days'] - 1) > 0) {
									$prod['flights'][1]['day'] = floatval($prod['days'] - 1);
								}
							}
						}
					}
						
				}
			}
				
			if(count($prod['flights']) == 2) {
				$prod['flights'][1]['trip'] = 'back';
			}
		}
		
		$hotels = [];
		$hotelDoms = $dom->find('div[id=js_hotel_box] .hotel_det');
		$hotelIndex = 0;
		$allHotelNames = [];
		foreach($hotelDoms as $hotelDom){ // 酒店
			$hotel = [];
			$hotelName = $hotelDom->find('.h_hotel_name em', 0)->plaintext;
			$hotel['name'] = $hotelName = trim($hotelName);
			if(in_array($hotel['name'], $allHotelNames)) {
				continue;
			} else {
				$allHotelNames[] = $hotel['name'];
			}
			$hotel['star'] = '';
			if(!empty($item['hotelInfo'])) {
				foreach($item['hotelInfo'] as $r) {
					if($r['name'] == $hotel['name']) {
						switch(trim($r['star'])) {
							case '五星级'       : $hotel['star'] = '5'; break;
							case '国际五星级'    : $hotel['star'] = '5'; break;
							case '五星或同等酒店' : $hotel['star'] = '5'; break;
							case '四星级'        : $hotel['star'] = '4'; break;
							case '国际四星级'    : $hotel['star'] = '4'; break;
							case '四星或同等酒店' : $hotel['star'] = '4'; break;
							case '三星级'        : $hotel['star'] = '3'; break;
							case '国际三星级'    : $hotel['star'] = '3'; break;
							case '三星或同等酒店' : $hotel['star'] = '3'; break;
							case '二星级'        : $hotel['star'] = '2'; break;
							case '国际二星级'     : $hotel['star'] = '2'; break;
							case '一星级'        : $hotel['star'] = '1'; break;
							case '国际一星级' :  $hotel['star'] = '1'; break;
						}
					}
				}
			}
			$addressDom = $hotelDom->find('.h_hotel_address', 0);
			$addressHtml = !empty($addressDom) ? $addressDom->innertext : '';
			$isMatchAddr = preg_match('/<\/b>([^<]*)</i', $addressHtml, $addrMatches);
			if($isMatchAddr) {
				$hotel['address'] = trim($addrMatches[1]);
			}
			$hotel['descothers'] = trim($hotelDom->find('.hotel_home_sort', 0)->nextSibling()->plaintext);
// 			$hotel['images'] = [];
// 			foreach($hotelDom->find('.hotel_img_list li img') as $img) {
// 				$r = new stdClass();
// 				$r->image = $img->getAttribute('data-lazy');
// 				if(!empty($r->image)) {
// 					$hotel['images'][] = $r;
// 				}
// 			}
			
			if(empty($hotel['city']) && !empty($prod['end'])) {
				foreach($prod['end'] as $end) {
					if(mb_strpos($end, $hotelName) !== FALSE) {
						$hotel['city'] = $end;
					}
				}
			}
			
			if(empty($hotel['city']) && count($hotelDoms) == count($flightDes)) {
				$hotel['city'] = $flightDes[$hotelIndex];
			}
			
			if(empty($hotel['city'])) {
				if(count($hotels) < count($prod['end'])) {
					$hotel['city'] = $prod['end'][count($hotels)];
				} else if(count($prod['end']) == 1) {
					$hotel['city'] = $prod['end'][0];
				} else {
					$hotel['city'] = $this->end;
				}
			}
			
			if(!empty($hotel['city'])) {
				$maerdaifu = ['泰姬珊瑚岛','马累','波杜希蒂岛','香格里拉岛','魔富士岛','白金岛','圣塔拉岛','阿雅达岛','班多士岛','新月岛','绚丽岛','吉哈德岛','月桂岛','巴洛斯岛','薇拉瓦鲁岛','天堂岛','太阳岛','第六感拉姆岛','尼亚玛岛','伊露岛','梦幻岛','安娜塔拉岛'];
				if(in_array($hotel['city'], $maerdaifu)) {
					$hotel['city'] = '马尔代夫';
				}
			}
			
			$and1 = ['$or' =>[['alias' => $hotelName], ['name' => $hotelName], ['englishName' => $hotelName]]];
			if(!empty($hotel['englishName'])) {
				$and2['$or'][] = ['name' => $hotel['englishName']];
				$and2['$or'][] = ['englishName' => $hotel['englishName']];
				$and2['$or'][] = ['alias' => $hotel['englishName']];
			}
			$and2 = ['$or' => []];
			if(!empty($hotel['city'])) {
				if(!empty($prod['end'])) {
					$cities = array_merge([$hotel['city']], $prod['end']);
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
			$hotelCond = ['$and' => [$and1 , $and2]];
			$queryHotel = $this->cimongo->db->hotelbases->findOne($hotelCond);
			if(!empty($queryHotel)) {  // 更新
				$hotel['alias'] = $queryHotel['alias'];
				$hotel['addressAlias'] = !empty($queryHotel['addressAlias']) ? $queryHotel['addressAlias'] : [];
				if(!empty($hotel['address']) && !in_array($hotel['address'], $hotel['addressAlias'])) {
					$hotel['addressAlias'][] = $hotel['address'];
				}
				
				$updateRet = $queryHotel;
				if(!in_array($hotelName, $hotel['alias'])) {
					$hotel['alias'][] = $hotelName;
				}
				$updateRet = $this->cimongo->db->hotelbases->findAndModify(['_id' => $queryHotel['_id']], array('$set'=> ['alias' => $hotel['alias'], 'addressAlias' => $hotel['addressAlias']]), ['_id' => true], array('new' => true));
			} else {
				
				$hotel['alias'] = [$hotelName];
				if(!empty($hotel['address'])) {
					$hotel['addressAlias'] = [$hotel['address']];
				}
				
				$hotel['created'] = new MongoDate();
				$updateRet = $this->cimongo->db->hotelbases->findAndModify($hotelCond, array('$set'=> $hotel), ['_id' => true], array('upsert'=>true, 'new' => true));
			}
			
			$hRow = [];
			if(!empty($updateRet['_id'])) {
				$hRow['hotel'] = $updateRet['_id'];
				
				$hotels[] = $hRow;
			}
			
			$hotelIndex++;
		}
		
		if(empty($hotels[0]['day'])) {
			$hotels[0]['day'] = new MongoInt32(1);
		}
		if(count($hotels) == 1 && empty($hotels[0]['night']) && !empty($prod['hotelNight'])) {
			$hotels[0]['night'] = floatval($prod['hotelNight']);
		}
	
		$prod['hotels'] = $hotels;
		
		// 费用说明
		$cost = $dom->find('.payment', 0)->innertext;
		if(!empty($cost)) {
			$prod['costInstruction'] = $cost;
		}
		// 预订说明
		if(!empty($dom->find('#js_notes')) && !empty($dom->find('#js_notes .notes .block'))) {
			$blocks = [];
			foreach($dom->find('#js_notes .notes .block') as $block) {
				if($block->getAttribute('id') != 'js_process') {
					$blocks[] = '<div class="block">'.$block->innertext.'</div>';
				}
			}
			if(!empty($blocks)) {
				$blockStr = implode('', $blocks);
				$noteStr = '<div class="notes">'.$blockStr.'</div>';
				$prod['booking'] = $noteStr;
			}
		}

		$prod['created'] = new MongoDate();
		$prod['grabtime'] = new MongoDate();
		$prod['sourcestatus'] = 'normal';
		if(!empty($prod['hotels']) && !empty($prod['flights'])) { // 有酒店和航班的才允许保存
			$this->cimongo->db->duoqugrabs->findAndModify(array('code'=>$prod['code']), array('$set'=> $prod), null, array('upsert'=>true, 'new' => true));
			echo $prod['code']." grabed ".date("Y-m-d H:i:s")."\n";
			return true;
		}
		
		return false;
	}
	
	private function getResponse($url) { // 统一获取数据
		$this->load->library('curl');
	
		$response = $this->curl->simple_get($url);
		$info = $this->curl->info;
	
		$retryTimes = 1;
		$maxRetryTimes = 3;
	
		while(empty($response) && $info['http_code'] != 200 &&  $retryTimes < $maxRetryTimes) {
			$second = 5 * $maxRetryTimes;
			sleep($second);
			$response = $this->curl->simple_get($url);
			$info = $this->curl->info;
			$retryTimes++;
		}
	
		return $response;
	}
	
	private function postResponse($url, $params) {
		$this->load->library('curl');
		$response = $this->curl->simple_post($url, $params);
		$info = $this->curl->info;
		
		$retryTimes = 1;
		$maxRetryTimes = 2;
		
		while(empty($response) && $info['http_code'] != 200 && $retryTimes < $maxRetryTimes) {
			$second = 5 * $maxRetryTimes;
			sleep($second);
			$response = $this->curl->simple_post($url, $params);
			$info = $this->curl->info;
			$retryTimes++;
		}
		
		return $response;
	}
	
	// log 记录
	private function writeLog($message, $level = 'DEBUG') {
		$folder = FCPATH.'/logs/grab/qunar';
		if(!file_exists($folder)) {
			mkdir($folder, 0775, true);
		}
		
		$logFile = $folder.'/excute.log';
		$errFile = $folder.'/error.log';
		
		$logFormat = date('Y-m-d H:i:s') .' ['. $level .'] '.__LINE__.' '.$message."\n";
		
// 		if(ENVIRONMENT == 'development' || ENVIRONMENT == 'test') { // @TODO start. debug, chenlei @2015-01-29
			file_put_contents($logFile, $logFormat, FILE_APPEND);
// 		}
		
		if($level == 'NOTICE' || $level == 'ERROR' || $level == 'FATAL') {
			file_put_contents($errFile, $logFormat, FILE_APPEND);
			$level == 'FATAL' AND die();
		}
	}
	
	public function test()
	{
		$url = "http://zgshl.package.qunar.com/user/detail.jsp?id=3330241608&ttsRouteType=%E5%87%BA%E5%A2%83%E6%B8%B8#tm=l01_travel&ts=&DepDate=2015-04-09&vendor=54ix5peF6KGM&tp=&departure=5YyX5Lqs&tf=arnoresult&from=arnoresult&productId=3330241608&function=6Ieq55Sx6KGM&arrive=5Yay57uz&DepLastDate=2015-10-15&searchid=&vendorid=qb2c_zgshlvw&djtf=&route_id=8937485";
	}
}