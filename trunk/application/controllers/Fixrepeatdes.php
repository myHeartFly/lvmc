<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * fix 重复目的地 key
 */
class Fixrepeatdes extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->library('curl');

	}
	
	public function index()
	{
		
		$mainKey = '7'; // 澳门
		$repeatKey = '64';
		
		$this->setFlights($mainKey, $repeatKey);
		$this->setProducts($mainKey, $repeatKey);
	}
	
	function setFlights($mainKey, $repeatKey)
	{
		$count = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = ['$or' => [['takeoffCityKey' => $repeatKey], ['landingCityKey' => $repeatKey]]];
		$total = $this->cimongo->db->flightbases->count($condition);
		do {
			echo "offset : $offset"."\n";
			$results = $this->cimongo->db->flightbases->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$flights = iterator_to_array($results);
			$offset += $max;
				
			foreach($flights as $flight) {
				$flightId = $flight['_id'];
			
				$updates = [];
				if($flight['takeoffCityKey'] == $repeatKey) {
					$updates['takeoffCityKey'] = (string) $mainKey;
				}
				if($flight['landingCityKey'] == $repeatKey) {
					$updates['landingCityKey'] = (string) $mainKey;
				}
			
				if(!empty($updates)) {
// 					var_dump($repeatKey, $updates);
					$count++;
					$this->cimongo->db->flightbases->findAndModify(['_id' => $flightId], ['$set' => $updates]);
				}
			}
		} while($offset < $total);
		
		echo 'update flight total: '.$count.PHP_EOL;
	}
	
	function setProducts($mainKey, $repeatKey)
	{
		$count = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = ['$or' => [['startKey' => $repeatKey], ['endKey' => $repeatKey]]];
		$total = $this->cimongo->db->duoqugrabs->count($condition);
		do {
			echo "offset : $offset"."\n";
			$results = $this->cimongo->db->duoqugrabs->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
	
			foreach($products as $prod) {
				$prodId = $prod['_id'];
					
				$updates = [];
				$isUpdateStart = false;
				$startKeys = [];
				if(!empty($prod['startKey'])) {
					foreach($prod['startKey'] as $key) {
						if($key == $repeatKey) {
							$startKeys[] = (string) $mainKey;
							$isUpdateStart = true;
						} else {
							$startKeys[] = (string) $key;
						}
					}
				}
				if($isUpdateStart) {
					$updates['startKey'] = $startKeys;
				}
				
				$isUpdateEnd = false;
				$endKeys = [];
				if(!empty($prod['endKey'])) {
					foreach($prod['endKey'] as $key) {
						if($key == $repeatKey) {
							$endKeys[] = (string) $mainKey;
							$isUpdateEnd = true;
						} else {
							$endKeys[] = (string) $key;
						}
					}
				}
				
				if($isUpdateEnd) {
					$updates['endKey'] = $endKeys;
				}
				
				if(!empty($updates)) {
					$count++;
					$this->cimongo->db->duoqugrabs->findAndModify(['_id' => $prodId], ['$set' => $updates]);
				}
			}
		} while($offset < $total);
		
		echo 'update product total: '.$count.PHP_EOL;
	}
}