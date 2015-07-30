<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * 目的地数据统计
 */
class FixData extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->library('curl');

		date_default_timezone_set('Asia/Shanghai');
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
		
		$venders = [];
		do {
			echo "offset : $offset"."\n";
			$results = $this->cimongo->db->duoqugrabs->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
			
			foreach($products as $prod) {
				if(!in_array($prod['vender'], $venders)) {
					$venders[] = $prod['vender'];
				}
			}
		} while($offset < $total);
		
		foreach ($venders as $vender) {
			echo $vender.PHP_EOL;
		}
		echo 'fix data success total'.$count;
	}
	
	public function fixstatus()
	{
		$count = 0;
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = ['status' =>'stop', 'sourcestatus' => 'stop'];
		
		$total = $this->cimongo->db->duoqugrabs->count($condition);
		
		
		do {
			echo "offset : $offset"."\n";
			$results = $this->cimongo->db->duoqugrabs->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$products = iterator_to_array($results);
			$offset += $max;
				
			foreach($products as $prod) {
				if(!empty($prod['code'])) {
					$code = $prod['code'];
					$count++;
					$this->cimongo->db->duoqugrabindexes->findAndModify(['code' => $code], ['$set' => ['status' => 'stop']]);
					$this->cimongo->db->duoquproducts->findAndModify(['grabcode' => $code], ['$set' => ['status' => 'stop']]);
				}
				
			}
		} while($offset < $total);
		
		echo 'success: '. $count.PHP_EOL;
	}
	
	public function stopExpireProducts()
	{
		$condition = [];
		$condition['$or'] = [['status' => 'normal'], ['sourcestatus' => 'normal']];
		
		$max = 300;
		$offset = 0;
		//计算产品总数量
		$total = $this->cimongo->db->duoqugrabs->count($condition);
		
		$count = 0;
		$codesCount = 0;
		$totalCount = ceil($total/$max);
		
		$this->load->library('dataprocess/dataprocess');
		
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
				
				foreach ($products as $prod) {
					$codesCount++;
					$status = 'normal';
					if(!empty($prod['created'])) {
						$startCreatedTime = $prod['created'];
					}
					
					if(!empty($prod['prices'])) {
						$current = time();
						
						$isAllExpire = TRUE;
						foreach($prod['prices'] as $date => $item) {
							$time = strtotime($date);
							if($time > $current) {
								$isAllExpire = FALSE;
								break;
							}
						}
						
						if($isAllExpire) {
							$status = 'stop';
						}
					} else {
						$status = 'stop';
					}
					
					if($status == 'stop') {
						$count++;
						$this->dataprocess->setProductStatus($prod['code'], $status);
					}
				}
			}
				
			$i++;
		}
		
		echo date('Y-m-d H:i:s').' update report : codes count: '.$codesCount. ' count products count: '.$total.PHP_EOL;
		echo 'deal stop product counts: '.$count;
		return true;
	}
	
}