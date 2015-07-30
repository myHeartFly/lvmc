<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * 删除数据
 */
class DelGrab extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->library('curl');

	}
	
	public function index()
	{
		$countProd = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = [];
		
		$countFlight = 0;
		$countHotel = 0;
		
		$condition['source'] = '麦兜旅行';
		$total = $this->cimongo->db->duoqugrabs->count($condition);
		
		$this->load->helper('htmldom');
		
		do {
			echo "offset : $offset"."\n";
			$results = $this->cimongo->db->duoqugrabs->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
			
			foreach($products as $prod) {
				$code = $prod['code'];
				$this->cimongo->db->duoqugrabindexes->remove(['code' => $code], ['justOne' => TRUE]);
				$this->cimongo->db->duoquproducts->remove(['grabcode' => $code], ['justOne' => TRUE]);
				if(!empty($prod['flights'])) {
					foreach($prod['flights'] as $flight) {
						$queryFlightCount = $this->cimongo->db->duoqugrabs->count(['flights.flight' => $flight['flight']]);
						if($queryFlightCount == 1) {
							$countFlight++;
							echo 'find flight'.PHP_EOL;
							$this->cimongo->db->flightbases->remove(['_id' => $flight['flight']], ['justOne' => TRUE]);
						}
					}
				}
				
				if(!empty($prod['hotels'])) {
					foreach($prod['hotels'] as $hotel) {
						if(empty($hotel['hotel'])) {
							echo '**********'.PHP_EOL;
							continue;
						}
						$queryHotelCount = $this->cimongo->db->duoqugrabs->count(['hotels.hotel' => $hotel['hotel']]);
						if($queryHotelCount == 1) { // debug, chenlei
							$countHotel++;
							echo 'find hotel'.PHP_EOL;
							$this->cimongo->db->hotelbases->remove(['_id' => $hotel['hotel']], ['justOne' => TRUE]);
						}
					}
				}
				
				$countProd++;
				$this->cimongo->db->duoqugrabs->remove(['code' => $prod['code']], ['justOne' => TRUE]);
				$this->cimongo->db->duoqugrabindexs->remove(['code' => $prod['code']], ['justOne' => TRUE]);
				$this->cimongo->db->duoquproducts->remove(['grabcode' => $prod['code']], ['justOne' => TRUE]);
			}
		} while($offset < $total);
		
		echo 'fix data success total'.$countProd.PHP_EOL;
		echo 'fix data success hotel'.$countHotel.PHP_EOL;
		echo 'fix data success flight'.$countFlight.PHP_EOL;
	}
	
	/* public function restoreHotel()
	{
		$countProd = 0;
		$countFix = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = [];
	
		$countFlight = 0;
		$countHotel = 0;
	
		$condition['source'] = '麦兜旅行';
		$total = $this->cimongo->srcdb->duoqugrabs->count($condition);
		
		$this->load->helper('htmldom');
	
		do {
			echo "offset : $offset"."\n";
			$results = $this->cimongo->srcdb->duoqugrabs->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
				
			foreach($products as $prod) {
				$code = $prod['code'];
				$this->cimongo->srcdb->duoqugrabindexes->remove(['code' => $code], ['justOne' => TRUE]);
				if(!empty($prod['flights'])) {
					foreach($prod['flights'] as $flight) {
						$count = $this->cimongo->srcdb->duoqugrabs->count(['flights.flight' => $flight['flight']]);
						if($count == 1) {
								$countFlight++;
								echo 'find flight'.PHP_EOL;
								$this->cimongo->srcdb->flightbases->remove(['_id' => $flight['flight']], ['justOne' => TRUE]);
						}
					}
				}
	
				if(!empty($prod['hotels'])) {
					foreach($prod['hotels'] as $hotel) {
						if(empty($hotel['hotel'])) {
							echo '**********'.PHP_EOL;
							continue;
						}
						$this->cimongo->srcdb->duoqugrabs->count(['hotels.hotel' => $hotel['hotel']]);
						if($count == 1) {
							$countHotel++;
							echo 'find hotel'.PHP_EOL;
							$queryHotel = $this->cimongo->srcdb->hotelbases->findOne(['_id' => $hotel['hotel']]);
							if(!empty($queryHotel)) {
								
								$querySrcHotel = $this->cimongo->db->hotelbases->findOne(['_id' => $hotel['hotel']]);
								
								if(isset($queryHotel['__v'])) {
									$queryHotel['__v'] = floatval($queryHotel['__v']);
								}
								if(isset($queryHotel['star'] )) {
									$queryHotel['star'] = (string) $queryHotel['star'];
								}
								if(isset($queryHotel['commentNum'])) {
									$queryHotel['commentNum'] = floatval($queryHotel['commentNum']);
								}
								if(isset($queryHotel['score'])) {
									if(isset($queryHotel['score']['score'])) {
										$queryHotel['score']['score'] = floatval($queryHotel['score']['score']);
									}
									if(isset($queryHotel['score']['items'])) {
										foreach($queryHotel['score']['items'] as &$item) {
											if(isset($item['score'])) {
												$item['score'] = floatval($item['score']);
											}
										}
									}
								}
								
								if(empty($querySrcHotel)) {
									$countFix++;

									$queryResult = $this->cimongo->db->hotelbases->insert($queryHotel, ['upsert' => true, 'new' => true]);
								} else {

									var_dump($hotel['hotel']);
									$queryResult = $this->cimongo->db->hotelbases->findAndModify(['_id' => $hotel['hotel']], $queryHotel,  ['new' => true]);
								}
							}
							$this->cimongo->srcdb->hotelbases->remove(['_id' => $hotel['hotel']], ['justOne' => TRUE]);
						}
					}
				}
	
				$countProd++;
				$this->cimongo->srcdb->duoqugrabs->remove(['code' => $prod['code']], ['justOne' => TRUE]);
			}
		} while($offset < $total);
	
		echo 'fix data success total'.$countProd.PHP_EOL;
		echo 'fix data success hotel'.$countHotel.PHP_EOL;
		echo 'fix data success flight'.$countFlight.PHP_EOL;
		echo 'fix data success fix hotel'.$countFix.PHP_EOL;
	}*/
	
}