<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Grabchunqiu extends CI_Controller {
	private $source = '春秋旅游';
	private $codePrefix = 'CQ';
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

		
		header('Content-Type: application/json'); // debug, chenlei

		echo 'grab source data start:'.date("Y-m-d H:i:s")."\n";
		
		$results = array();
		
		$startCities = $this->city->getCities('start', 'chunqiu');
		$endCities = $this->city->getCities('end', 'chunqiu');

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
					// http://www.springtour.com/shanghai-pujidao/y2
					$entryURL = "http://www.springtour.com/{$startParams['map']}-{$endParams['map']}/y2";
					$dom = _getDOM($entryURL);
					
					if(empty($dom->find('.search_main #search-condition', 0)) || !empty($dom->find('.search_main .search_nopro', 0))) {
						continue;
					}
					
					$prodlist = $dom->find('.search_items ul#search-result li');
					if(!empty($prodlist)) {
						foreach($prodlist as $box) {
							$a = $box->find('.stitle a', 0);
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
		
		echo 'import chunqiu success'.date("Y-m-d H:i:s")."\n";
	}

	private function _grabdetail($url, $referer) {
		echo $url.PHP_EOL; // debug, chenlei
		$html = _getText($url, ['referer' => $referer]);
		
		if(empty($html)) {
			return FALSE;
		}
		
		$dom = str_get_html($html);
		
		if(empty($dom)) return FALSE;
		
		$prod = new stdClass();
		$prod->source = $this->source;
		$prod->sourceurl = $url;
		
		$titleDom = $dom->find('.ticket_d_b .ticket_d_b_t h4', 0);
		if(empty($titleDom)) return FALSE;
		
		$titleStr = $titleDom->plaintext;
		$codePos = mb_strpos($titleStr, '产品编号', 0, 'UTF8');
		$prod->name = trim(mb_substr($titleStr, 0, $codePos, 'UTF8'));
		
		$codeStr = trim(mb_substr($titleStr, $codePos, mb_strlen($titleStr, 'UTF8'), 'UTF8'));
		
		$codeReg = "/【(\w+)】/u";
		if(preg_match($codeReg, $codeStr, $codeMatches)) {
			$code = trim($codeMatches[1]);
		}
		
		if(empty($code)) return FALSE;
		
		$prod->code = $this->codePrefix.$code;
		echo date('Y-m-d H:i:s')." grab chunqiu product: {$prod->code} \n"; // debug, chenlei
		
		$phoneDom = $dom->find('.ticket_d_b_s .ewm .kf', 0);
		if(!empty($phoneDom)) {
			$phoneText = trim($phoneDom->plaintext);
			$phoneText = str_replace('(7*24小时)', '', $phoneText);
			
			$prod->servicephone = phoneFormat($phoneText);
		}
		
		$dls = $dom->find('.ticket_d_b_s .abt dl');
		
		$prod->start = [];
		$prod->end = [];
		if(!empty($dls)) {
			foreach($dls as $dl) {
				$dtText = $dl->find('dt', 0)->plaintext;
				switch($dtText) {
					case '出发城市' : 
						$start = trim($dl->find('dd', 0)->plaintext);
						if(!empty($start)) {
							$prod->start = [$start];
						}
						break;
					case '目  的  地' :
					case '目&nbsp;&nbsp;的&nbsp;&nbsp;地':
						$end = trim($dl->find('dd', 0)->plaintext);
						$ends = explode(',', $end);
						if(!empty($ends)) {
							foreach($ends as $city) {
								if(!empty($prod->start) && in_array($city, $prod->start)) {
									continue;
								}
								$prod->end[] = $city;
							}
							
						}
						break;
					case '出游天数':
						$tripStr = trim($dl->find('dd', 0)->plaintext);
						$tripStr = str_replace(' ', '', $tripStr);
						$travelTime = travelTimeFormat($tripStr);
						if(empty($prod->days) && !empty($travelTime['days'])) {
							$prod->days = floatval($travelTime['days']);
						}
						if(empty($prod->hotelNight) && !empty($travelTime['nights'])) {
							$prod->hotelNight = floatval($travelTime['nights']);
						}
						break;
				}
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
		
		empty($prod->vender) AND $prod->vender = $this->source;
		
		$recommendDom = $dom->find('#freeTravel_groom_in', 0);
		if(!empty($recommendDom)) {
			$recommend = $recommendDom->innertext;
			$recommend = str_replace('<br>', '<br/>', $recommend);
			$recommends = explode('<br/>', $recommend);
			if(!empty($recommends)) {
				$prod->recommend = implode("\n", $recommends);
			}
		}
		// 价格日历
		$prices = [];
		$calender = $dom->find('#freeTravel_bigCalendar_panel', 0);
		
		if(!empty($calender)) {
			$calenderJsonStr = $calender->{'data-json'};
			if(!empty($calenderJsonStr)) {
				$calenderJsonStr = str_replace('&quot;', '"', $calenderJsonStr);
				$calenderJson = NULL;
				try {
					$calenderJson = json_decode($calenderJsonStr, true);
				} catch (Exception $e) {
					echo $e->getMessage().PHP_EOL;
				}
				
				if(!empty($calenderJson)) {
					foreach($calenderJson as $date => $item) {
						if(!empty($item['Price']) && !empty($item['Limit'])) {
							$prices[$date] = array(
									'price' => floatval($item['Price']),
									'yuwei' => floatval(8),
							);
						}
					}
				}
			}
		}
		
		if(empty($prices)) {
			return FALSE;
		}
		$prod->prices = (object) $prices;
		
		// 出发地及目的地
		empty($prod->start) AND $prod->start = [$this->start];
		empty($prod->end) AND $prod->end = [$this->end];
		
		$flights = [];
		$hotels = [];
		$checkDate = null;
		foreach($prices as $date => $item) {
			$checkDate = $date;
			break;
		}
		if(empty($checkDate)) {
			return FALSE;
		}
			
		$infoUrl = "http://www.springtour.com/home/FreeTravel/GetFreeTravelProdcutInfo?productId={$code}&saleDate={$checkDate}&adultCount=2&childCount=0";
		$response = request($infoUrl, 'get');
		
		if(empty($response)) {
			continue;
		}
		
		$info = null;
		try {
			$info = json_decode($response, true);
		} catch (Exception $e) {
			echo $e->getMessage();
		}
		if(empty($info)) {
			continue;
		}
		if(!empty($info['Limit'])) {
			$prices[$date]['yuwei'] = $info['Limit'];
		}
		
		if(!empty($info['TrafficResourceInfo']) && empty($flights)) {
			for($i = 0; $i < count($info['TrafficResourceInfo']); $i++) {
				$item = $info['TrafficResourceInfo'][$i];
				if(!empty($item['SelectedPackage']) && !empty($item['SelectedPackage']['TrafficResourceViewModelList'])) {
					$direction = $i == 0 ? 'depart' : 'back';
					foreach($item['SelectedPackage']['TrafficResourceViewModelList'] as $r) {
						if(!empty($r['FlightNumber'])) {
							$flightNo = $r['FlightNumber'];
						} else {
							$flightInfos = explode('/', $r['Description']);
							$flightNo = $flightInfos[0];
						}
						$reg = "/^([A-Za-z0-9]{2,})/i";
						if(preg_match($reg, $flightNo, $flightMatches)) {
							$flightNo = $flightMatches[1];
						} else {
							continue;
						}
						$flight = [];
						
						if(!empty($flightInfos) && count($flightInfos) == 2) {
							$flight['model'] = trim($flightInfos[1]);
						}
						
						$flight['flightNo'] = $flightNo;
						if(!empty($r['DepartTime'])) {
							$flight['takeoff'] = $r['DepartTime'];
						}
						if(!empty($r['ArriveTime'])) {
							$flight['landing'] = $r['ArriveTime'];
							if(!empty($r['RedeyeFlight'])) {
								$flight['landing'] .= $r['RedeyeFlight'];
							}
						}
						if(!empty($r['CabinType'])) {
							$flight['space'] = $r['CabinType'];
						}
						$departStr = $r['DepartAirport'];
						$backStr = $r['ArriveAirport'];
						if(!empty($departStr)) {
							$reg = "/(\S+)\((\S+)\)/u";
							if(preg_match($reg, $departStr, $matches)) {
								$flight['takeoffCity'] = trim($matches[1]);
								$flight['takeoffAirport'] = trim($matches[2]);
							} else {
								$flight['takeoffAirport'] = trim($backStr);
							}
						}
						if(!empty($backStr)) {
							$reg = "/(\S+)\((\S+)\)/u";
							if(preg_match($reg, $backStr, $matches)) {
								$flight['landingCity'] = trim($matches[1]);
								$flight['landingAirport'] = trim($matches[2]);
							} else {
								$flight['landingAirport'] = trim($backStr);
							}
						}
						
						if(!empty($flight)) {
							$flights[] = $flight;
						}
					}
				}
			}
		}
		
		if(!empty($info['HotelResourceInfo']) && empty($hotels)) {
			for($i = 0; $i < count($info['HotelResourceInfo']); $i++) {
				if(empty($info['HotelResourceInfo'][$i]['SelectedHotel'])) {
					continue;
				}
				$item = $info['HotelResourceInfo'][$i]['SelectedHotel'];
				if(empty($item['HotelName'])) {
					continue;
				}
				$hotelNames = explode('/', $item['HotelName']);
				
				$hotel = [];
				if(count($hotelNames) == 2) {
					$hotel['name'] = $hotelNames[0];
					$hotel['englishName'] = $hotelNames[1];
				} else {
					$reg  = "/(\S+)\s*[\(（]\s*(\S+[\S\s]*\S+)\s*[\)）]/iu";
					if(preg_match($reg, $item['HotelName'], $matches)) {
						$hotel['name'] = trim($matches[1]);
						$hotel['englishName'] = trim($matches[2]);
					} else {
						$hotel['name'] = trim($item['HotelName']);
					}
				}
				
				if(!empty($hotelNames[1])) {
					$hotel['englishName'] = $hotelNames[1];
				}
				$hotel['address'] = trim($item['Address']);
				if(!empty($item['SelectedHotel']['StarLevel'])) {
					$hotel['star'] = (string) intval($item['StarLevel']);
				}
				if(!empty($info['HotelResourceInfo'][$i]['HotelAddress'])) {
					$hotel['city'] = $info['HotelResourceInfo'][$i]['HotelAddress'];
				}
				if(!empty($info['HotelResourceInfo'][$i]['StayDays'])) {
					$hotel['stay'] = floatval($info['HotelResourceInfo'][$i]['StayDays']);
				}
				if(!empty($item['Description'])) {
					$hotel['descothers'] = trim($item['Description']);
				}
				if(!empty($item['HotelRoomInfoViewModelList']) && !empty($item['HotelRoomInfoViewModelList'][0])) {
					$row = $item['HotelRoomInfoViewModelList'][0];
					if(!empty($row['RoomName'])) {
						$hotel['roomType'] = trim($row['RoomName']);
					}
				}
				
				$hotels[] = $hotel;
			}
		}
		
		$prod->flights = $flights;
		$prod->hotels = $hotels;

		// 费用说明
		if(!empty($dom->find('#fysm .wp_tab_r', 0))) {
			$prod->costInstruction = trim($dom->find('#fysm .wp_tab_r', 0)->innertext);
		}
		// 预订须知
		if(!empty($dom->find('#ydxz .wp_tab_r', 0))) {
			$prod->booking = trim($dom->find('#ydxz .wp_tab_r', 0)->innertext);
		}
		
		$prod->created = new MongoDate();
		$prod->grabtime = new MongoDate();
		$prod->sourcestatus = 'normal';
		$prod->type = ['自由行'];
		$prod->status = 'stop'; // 设置停售状态，信息补充完成可改为正常状态
		
		if(empty($prod->hotels) && empty($prod->flights)) {
			return FALSE;
		}
		
		$this->cimongo->srcdb->duoqusourcedatas->findAndModify(array('code'=> $prod->code), array('$set'=>(array)$prod), null, array('upsert'=>true));
		
		$this->dataprocess->productCode($prod->code, TRUE); // debug, chenlei
// 		$this->dataprocess->productCode($prod->code, FALSE); //@TODO remove. //debug, chenlei byford暂时不让入库插入新数据
		return true;
	}
	
	public function grabById($id) // 抓取网站单个产品
	{
// 		header('Content-Type: application/json'); // debug, chenlei
		$this->load->helper('htmldom');
		$url =  $referer = "http://www.springtour.com/vacation/{$id}";
		
		$this->_grabdetail($url, $referer);
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