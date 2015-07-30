<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * 去哪儿数据统计fix
 */
class FixFlight extends CI_Controller {
	private $codePrefix = 'QN';

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->library('curl');

	}
	
	public function index()
	{
		echo 'no fix';
	}
	
	public function airline()
	{
		$count = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = [];
		$total = $this->cimongo->db->flightbases->count($condition);
		
		$startFlightNos = [];
		$endFlightNos = [];
		
		do {
			echo "offset : $offset"."\n";
			$results = $this->cimongo->db->flightbases->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$flights = iterator_to_array($results);
			$offset += $max;
			
			foreach($flights as $flight) {
				$flightId = $flight['_id'];
				
				if(!empty($flight['flightNo'])) {
					$updates = [];
					if(preg_match('/^[A-Za-z0-9]/i', $flight['flightNo'])) {
						$aircode = strtoupper(substr($flight['flightNo'], 0, 2));
						if(!empty($aircode)) {
							$queryAir = $this->cimongo->db->duoqudicts->findOne(['__t' => 'Airline', 'codes' => $aircode]);
							if(!empty($queryAir)) {
								$updates['airline'] = $queryAir['name'];
								$updates['airlineKey'] = (string) $queryAir['key'];
								
							}
						}
						if(!empty($flight['model'])){
							if(strpos($flight['model'], '777') !== FALSE) {
								$updates['model'] = 'B777';
								$updates['modelKey'] = 'M006';
							} else if(strpos($flight['model'], '380') !== FALSE) {
								$updates['model'] = 'A380';
								$updates['modelKey'] = 'M001';
							}
						}
						if(empty($flight['modelKey'])) {
							$updates['modelKey'] = '-1';
						}

						if(!empty($updates)) {
							$count++;
							$this->cimongo->db->flightbases->findAndModify(['_id' => $flightId], ['$set' => $updates]);
						}
					}

					
					
				}	
			}
		} while($offset < $total);
		
		echo 'fix airline data success total: '.$count;
	}

	public function place()
	{
		$count = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = [];
// 		$condition['flightNo'] = 'CZ6057'; // debug, chenlei
		$total = $this->cimongo->db->flightbases->count($condition);
		
		$this->load->helper('htmldom');
		$startFlightNos = [];
		$endFlightNos = [];
		
		do {
			echo "offset : $offset"."\n";
			$results = $this->cimongo->db->flightbases->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$flights = iterator_to_array($results);
			$offset += $max;
			
			foreach($flights as $flight) {
				$flightId = $flight['_id'];
				
				if(!empty($flight['takeoffCity']) && $flight['takeoffCity'] != 'undefined'
					&& !empty($flight['landingCity']) && $flight['landingCity'] != 'undefined') {
					$takeoffCity = trim($flight['takeoffCity']);
					$landingCity = trim($flight['landingCity']);
					$queryTK = $this->cimongo->db->duoqudicts->findOne(['__t' => 'Place', 'title' => $takeoffCity]);
					$queryLC = $this->cimongo->db->duoqudicts->findOne(['__t' => 'Place', 'title' => $landingCity]);
					$updates = [];
					if(!empty($queryTK)) {
						$updates['takeoffCityKey'] = $queryTK['key'];
					} else {
						if(!in_array($flight['flightNo'], $startFlightNos)) {
							$startFlightNos[] = $flight['flightNo'];
						}
					}
					if(!empty($queryLC)) {
						$updates['landingCityKey'] = $queryLC['key'];
					} else {
						if(!in_array($flight['flightNo'], $endFlightNos)) {
							$endFlightNos[] = $flight['flightNo'];
						}
					}
					
					if(!empty($updates)) {
// 						var_dump($takeoffCity, $landingCity, $updates);
						$count++;
						$this->cimongo->db->flightbases->findAndModify(['_id' => $flightId], ['$set' => $updates]);
					}
				}		
			}
		} while($offset < $total);
		
		echo '出发地绑定key不对: '.PHP_EOL;
		foreach($startFlightNos as $destination) {
			echo $destination.PHP_EOL;
		}
		
		echo '目的地绑定key不对: '.PHP_EOL;
		foreach($endFlightNos as $destination) {
			echo $destination.PHP_EOL;
		}
		
		echo 'fix place data success total: '.$count;
	}
	
	public function countPlace()
	{
		$count = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = [];
		$total = $this->cimongo->db->flightbases->count($condition);
		
		$cities = [];
		
		do {
			echo "offset : $offset"."\n";
			$results = $this->cimongo->db->flightbases->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$flights = iterator_to_array($results);
			$offset += $max;
				
			foreach($flights as $flight) {
				if(!empty($flight['takeoffCity'])) {
					if(!in_array($flight['takeoffCity'], $cities)) {
						$cities[] = $flight['takeoffCity'];
					}
				}
				if(!empty($flight['landingCity'])) {
					if(!in_array($flight['landingCity'], $cities)) {
						$cities[] = $flight['landingCity'];
					}
				}
			}
		} while($offset < $total);
		
		foreach($cities as $city) {
			echo $city.PHP_EOL;
		}
		echo 'count place data success total: '.$count;
	}
}