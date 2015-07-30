<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * 目的地数据统计
 */
class CountDes extends CI_Controller {

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
		$condition = ['scores' => ['$exists' => true]];
		$total = $this->cimongo->db->duoqugrabs->count($condition);
		
		$this->load->helper('htmldom');
		
		$ends = [];
		do {
			echo "offset : $offset"."\n";
			$results = $this->cimongo->db->duoqugrabs->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
			
			foreach($products as $prod) {
				if(!empty($prod['end'])) {
					foreach($prod['end'] as $end) {
						if(!in_array($end, $ends)) {
							$ends[] = $end;
						}
					}
				}
			}
		} while($offset < $total);
		
		foreach($ends  as $end) {
			echo $end.PHP_EOL;
		}
		
		echo 'fix data success total'.$count;
	}
	
	public function countExpiredProducts() // 统计过期产品
	{
		$day = 7;
		
		$startGrabTime = new MongoDate(time() - $day * 24 * 60 * 60);
		
		$condition = [];
		$condition['grabtime'] = ['$lt' => $startGrabTime];
		$condition['sourcestatus'] = 'normal';
		$condition['status'] = 'normal';
		
		$count = $this->cimongo->db->duoqugrabs->count($condition);
		
		echo 'count the expired products: '.PHP_EOL;
		var_dump($count);
		
		$codes = [];
		$products = $this->cimongo->db->duoqugrabs->find($condition);
		
		foreach($products as $prod) {
			$codes[] = $prod['code'];
		}
		
		var_dump($codes);
	}
	
}