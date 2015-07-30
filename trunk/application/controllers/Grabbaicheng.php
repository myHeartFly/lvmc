<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Grabbaicheng extends CI_Controller {
	private $source = '百程旅行';
	private $codePrefix = 'BC';
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

		
// 		header('Content-Type: application/json'); // debug, chenlei

		echo 'grab source data start:'.date("Y-m-d H:i:s")."\n";
		
		$results = array();
		
		$startCities = $this->city->getCities('start', 'baicheng');
		$endCities = $this->city->getCities('end', 'baicheng');

		foreach($startCities as $startCity => $startParams) {
			foreach($endCities as $endCity => $endParams) {
				if($startCity == $endCity) {
					continue;
				}
				$this->start = $startCity;
				$this->end = $endCity;
				
				$page = 1;
				$nextPage = 1;
				do {
					$page = $nextPage;
					// http://dujia.baicheng.com/package/0-2-0-0/p1/?key=%E6%B3%B0%E5%9B%BD
					$entryURL = "http://dujia.baicheng.com/package/0-{$startParams['map']}-{$endParams['country']}-{$endParams['map']}/p{$page}/?key={$endParams['key']}";
					$dom = _getDOM($entryURL);
					$prodlist = $dom->find('.product_list .pro_info');
					if(!empty($prodlist)) {
						foreach($prodlist as $box) {
							$a = $box->find('h2 a', 0);
							$this->_grabdetail($a->href, $entryURL);
						}
					}
					$nextPageDom = $dom->find('.bc_page .next', 0);
					if(!empty($nextPageDom)) {
						$next = (int) $nextPageDom->{'data-page'};
						if(!empty($next)) {
							$nextPage = $next;
						}
					}
				} while($page < $nextPage);
				
			}
		}
		
// 		echo json_encode($results, JSON_UNESCAPED_UNICODE);
		// $this->load->view('grab_llh', array('results'=>$results));
		
		echo 'import baicheng success'.date("Y-m-d H:i:s")."\n";
	}

	private function _grabdetail($url, $referer) {
		$html = _getText($url, ['referer' => $referer]);
		
		if(empty($html)) {
			return FALSE;
		}
		
		$dom = str_get_html($html);
		if(empty($dom)) return FALSE;
		
		$prod = new stdClass();
		$prod->source = $this->source;
		$prod->sourceurl = $url;

		$prodbox = $dom->find('#product_ibox', 0);

		if (!$prodbox) {
			return FALSE;
		}

		$prod->name = trim($prodbox->find('.product_hd .pro_tit h1', 0)->plaintext);
// 		$prod->desc = trim($prodbox->find('.product_hd .pro_tit p', 0)->plaintext);
		$prod->code = $this->codePrefix.trim($dom->find('#BaseID', 0)->value);
		echo date('Y-m-d H:i:s')." grab baicheng product: {$prod->code} \n"; // debug, chenlei
		
		$phoneDom = $dom->find('.bc_header .tel', 0);
		if(!empty($phoneDom)) {
			$phoneText = trim($phoneDom->plaintext);
			$prod->servicephone = phoneFormat($phoneText);;
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
		if(empty($prod->days)) {
			if(!empty($dom->find('.zixun .zx_left ul li'))) {
				$prod->days = floatval(count($dom->find('.zixun .zx_left ul li')));
			}
		}
		
		// $prod->price = floatval(trim($dom->find('#prodrefprice', 0)->value));
// 		$prod->images = [];
// 		foreach ($prodbox->find('.pro_list .pro_items li a') as $a) {
// 			$img = new stdClass();
// 			$img->image = $a->href;
// 			$prod->images[] = $img;
// 		}
		foreach($prodbox->find('.pro_ilist li') as $li) {
			if(trim($li->find('.pro_dt', 0)->plaintext) == '出发城市：') {
				$prod->start = [trim($li->find('.pro_dd', 0)->plaintext)];
			}
		}
		
		empty($prod->vender) AND $prod->vender = '百程旅行';
		$tuijian = $dom->find('#panelTuijian span', 0);
		if ($tuijian) {
			$prod->recommend = trim(strip_tags($tuijian->plaintext));
		}
		
		// 价格日历
		$prices = [];
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
		
		if(empty($prices)) { // 没有价格日历
			$querySource = $this->cimongo->srcdb->duoqusourcedatas->findOne(['code'=> $prod->code]);
			if(!empty($querySource)) {
				$this->cimongo->srcdb->duoqusourcedatas->update(['code'=> $prod->code], ['$unset'=> ['prices' => 1]]);
			}
			echo date('Y-m-d').' grab product code: '.$prod->code.' no prices failed'.PHP_EOL;
			return FALSE;
		}
		
		$prod->prices = (object) $prices;
		
		// 出发地及目的地
		empty($prod->start) AND $prod->start = [$this->start];
		empty($prod->end) AND $prod->end = [$this->end];
		
		// 航班信息
		$groupFlights = [];
		$boxs = $dom->find('.product_pbox_3 .ticket .box');
		if(!empty($boxs)) {
			foreach ($boxs as $box) {
				$direction = trim($box->find('.P_layer', 0)->plaintext);
				foreach ($box->find('.item') as $item) {
					$flight = [];
					$from = $item->find('.airport', 0);
					$fromCity = trim($from->find('span', 0)->plaintext);
					$fromAirport = trim($from->find('p', 0)->plaintext);
					$fromTime = trim($from->find('b', 0)->plaintext);
			
					$to = $item->find('.airport', 1);
					$toCity = trim($to->find('span', 0)->plaintext);
					$toAirport = trim($to->find('p', 0)->plaintext);
					$toTime = trim($to->find('b', 0)->plaintext);
			
					$flightInfo = explode('<br />', trim($item->find('.flight span', 0)->innertext));
					$space = trim($item->find('.space', 0)->plaintext);
			
					$flightNo = trim($flightInfo[1]);
					if(strpos($flightNo, ':') !== FALSE) {
						$flightNo = substr($flightNo, 0, strpos($flightNo, ':') - 2);
					}
					$flightNo = str_replace(' ', '', $flightNo);
					$flightNo = trim(str_replace("\n", '', $flightNo));
					$reg = "/([A-Za-z0-9]{2,})$/i";
					
					if(preg_match($reg, $flightNo, $flightMatches)) {
						$flightNo = $flightMatches[1];
					} else {
						continue;
					}
					
					$flight = array(
							'airline'        => trim($flightInfo[0]),
							'flightNo'       => $flightNo,
							'model'          => '',
							'takeoff'        => $fromTime,
							'takeoffCity'    => $fromCity,
							'takeoffAirport' => $fromAirport,
							'landing'        => trim(str_replace('天', '', $toTime)),
							'landingCity'    => $toCity,
							'landingAirport' => $toAirport,
							'space'          => $space,
							'trip'           => $direction == '去程' ? 'depart' : 'back',
					);
					
					$groupFlights[] = $flight;
				}
			}
		}
		
		$prod->flights = $groupFlights;

		// 酒店信息
		$hotels = [];
		$boxs = $dom->find('#hotelbox .box');
		if(!empty($boxs)) {
			foreach($boxs as $box) {
				if(empty($box->find('.show', 0))) {
					continue; // 排除child box
				}
				$hotel = [];
				$info = $box->find('.info', 0);
				$innertext = $info->innertext;
				$hotel['name'] = $hotelName = trim(substr($innertext, 0, strpos($innertext, '<')));
				$star = intval(str_replace('width:', '', trim($info->find('.star .P_img', 0)->style))) / 100 * 5;
				$hotel['star'] = $star != 0 ? "{$star}" : '0';
				$hotel['address'] = trim($info->find('p.txt', 0)->plaintext);
			
// 				$hotel['price'] = trim($info->find('.tags .price', 0)->price);
// 				$hotel['date'] = trim($info->find('.tags .date', 0)->plaintext);
				if(!empty($box->find('.show .area', 0))) {
					$hotel['city'] = trim($box->find('.show .area', 0)->plaintext);
				}
					
				foreach ($info->find('.tags .fx') as $fx) {
					$tag = str_replace('：', '', trim($fx->find('b', 0)->plaintext));
					if($tag == '房型') {
						$hotel['roomType'] = trim($fx->find('p', 0)->plaintext);
					} else if($tag == '用餐') {
							
					}
					
					$hotel['night'] = floatval(trim($info->find('.date b', 0)->plaintext));
				}
				
				$hotels[] = $hotel;
				$fRow = [];
			}
		}
		
		$prod->hotels = $hotels;
		
		// 线路行程
		$zixun = $dom->find('.zx_right', 0);
		if(!empty($zixun)) {
			$prod->routeplan = trim($zixun->innertext);
		}
		
		// 赠送
		$zengsong = $dom->find('.box_fujia div[tab="zengsong"]', 0);
		$costInstruction = '';
		$visa = '';
		$booking = '';
		$fy_boxs = $dom->find('.fy_box');
		if(!empty($fy_boxs)) {
			foreach($fy_boxs as $fybox) {
				$innertext = trim($fybox->innertext);
				$styleReg = "/(<style[^<]*<\/style>)/i";
				$innertext = preg_replace($styleReg, '', $innertext);
				
				if($fybox->tab == 'feiyong') {
					$costInstruction = trim($innertext);
				} else if($fybox->tab == 'visa') {
					$visa = trim($innertext);
				} else if($fybox->tab == 'yuding' && !empty($fybox->find('.nr span'))) { // 预订
					$booking = trim($innertext);
				}
			}
		}
		
		if(!empty($zengsong)) {
			$zengsong = trim($zengsong->find("dd[class='type']", 0)->innertext) . ' : ' . trim($zengsong->find("dd[class='title']", 0)->innertext);
			$costInstruction = (!empty($costInstruction) ? $costInstruction.'<br><br>' : '').$zengsong;
		}
		if(!empty($costInstruction)) {
			$prod->costInstruction = trim($costInstruction);
		}
		if(!empty($visa)) {
			$prod->visa = trim($visa);
		}
		if(!empty($booking)) {
			$prod->booking = trim($booking);
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
	
	public function importByCode($code, $isCover = FALSE) // 导入单个产品暑假
	{
		$condition = [];
		$condition['code'] = $code;
	
		$this->dataprocess->products($condition, TRUE, $isCover);
	}
}