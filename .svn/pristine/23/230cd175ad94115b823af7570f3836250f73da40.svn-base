<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * 出发地目的地fix
 */
class FixDes extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->library('curl');

	}
	
	public function index()
	{
		$count = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = [];
		$total = $this->cimongo->db->duoqugrabs->count($condition);
		
		$this->load->helper('htmldom');
		
		do {
			echo "offset : $offset"."\n";
			$results = $this->cimongo->db->duoqugrabs->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
			
			foreach($products as $prod) {
				$updates = [];
				$endKeys = [];
				$startKeys = [];
				if(!empty($prod['start'])) {
					foreach($prod['start'] as $city) {
						$queryPlace = $this->cimongo->db->duoqudicts->findOne(['__t' => 'Place', 'title' => $city]);
						if(!empty($queryPlace)) {
							$startKeys[] = $queryPlace['key'];
						}
					}
				}
				
				if(!empty($prod['end'])) {
					foreach($prod['end'] as $city) {
						$queryPlace = $this->cimongo->db->duoqudicts->findOne(['__t' => 'Place', 'title' => $city]);
						if(!empty($queryPlace)) {
							$endKeys[] = $queryPlace['key'];
						}
					}
				}
				
				$updates = [
					'startKey' => $startKeys,
					'endKey'   => $endKeys
				];
				
				if(empty($startKeys) || empty($endKeys)) {
					$updates['status'] = 'stop';
				}
				
				$count++;
// 				var_dump($updates);
				$this->cimongo->db->duoqugrabs->findAndModify(['_id' => $prod['_id']], ['$set' => $updates]);
			}
		} while($offset < $total);
		
		echo 'fix data success total '.$count;
	}
	
}