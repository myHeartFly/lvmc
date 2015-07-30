<?php

class City{
	
	private $startCities; // 出发地
	private $endCities; // 目的地
	
	public function __construct() {
		
		$cities = require_once APPPATH.'/libraries/city/cities_config.php';
		
		$this->startCities = $cities['startCities'];
		$this->endCities = $cities['endCities'];
	}
	
	public function getCities($type, $source) { // 获取城市
		if(empty($source) || empty($type)) {
			exit('get cities must has param source');
		}
		
		return $this->{'get'.$source.'Cities'}($type);	
	}
	
	public function getCountry($cityname)
	{
		foreach($this->endCities as $city => $row){
			if($city == $cityname || (isset($row['alias']) && in_array($cityname, $row['alias']))) {
				if(isset($row['country'])) {
					return $row['country'];
				}
			}
		}
	
		return '';
	}
	
	public function getRealCity($cityname)
	{
		foreach($this->startCities as $city => $row){
			if($city == $cityname) {
				return $city;
			}
			if(isset($row['alias']) && in_array($cityname, $row['alias'])) {
				return $city;
			}
		}
		foreach($this->endCities as $city => $row){
			if($city == $cityname) {
				return $city;
			}
			if(isset($row['alias']) && in_array($cityname, $row['alias'])) {
				return $city;
			}
		}
		
		return $cityname;
	}
	
	public function getTianXunCity($cityname)
	{
		$cities = require APPPATH.'/libraries/city/flight_cities_config.php';
		
		$this->startCities = $cities['startCities'];
		$this->endCities = $cities['endCities'];
		
		$city = $this->getRealCity($cityname);
		
		if(isset($this->startCities[$city]) && isset($this->startCities[$city]['tianxun'])) {
			return $this->startCities[$city]['tianxun'];
		}
		if(isset($this->endCities[$city]) && isset($this->endCities[$city]['tianxun'])) {
			return $this->endCities[$city]['tianxun'];
		}
		
		return null;
	}
	
	private function getlailaihuiCities($type) {
		$source = 'lailaihui';
		
		$cities = [];
		$maps = []; // 去除重复的map映射
		foreach($this->{$type.'Cities'} as $city => $row) {
			if(!empty($row[$source]) && !empty($row[$source]['map']) && !in_array($row[$source]['map'], $maps)) {
				$maps[] = $row[$source]['map'];
				$cities[$city] = $row[$source];
			}
		}
		return $cities;
	}
	
	private function getbaichengCities($type) {
		$source = 'baicheng';
		
		$cities = [];
		$maps = []; // 去除重复的map映射
		foreach($this->{$type.'Cities'} as $city => $row) {
			if(!empty($row[$source]) && (!empty($row[$source]['map']) || isset($row[$source]['country']))) {
				if($type == 'start') {
					$mapKey = $row[$source]['map'];
				} else {
					$mapKey = "{$row[$source]['country']}_{$row[$source]['map']}";
				}
				if(!in_array($mapKey, $maps)) {
					$maps[] = $mapKey;
					$cities[$city] = $row[$source];
				}
			}
		}
		return $cities;
	}
	
	private function getaoyouCities($type) {
		$source = 'aoyou';
		
		$cities = [];
		$maps = []; // 去除重复的map映射
		foreach($this->{$type.'Cities'} as $city => $row) {
			if(!empty($row[$source]) && !empty($row[$source]['map']) && !in_array($row[$source]['map'], $maps)) {
				$maps[] = $row[$source]['map'];
				$cities[$city] = $row[$source];
			}
		}
		return $cities;
	}
	
	private function getqunarCities($type) {
		$source = 'qunar';
		
		$cities = [];
		$maps = []; // 去除重复的map映射
		foreach($this->{$type.'Cities'} as $city => $row) {
			if(!empty($row[$source]) && !empty($row[$source]['map']) && !in_array($row[$source]['map'], $maps)) {
				$maps[] = $row[$source]['map'];
				$cities[$city] = $row[$source];
			}
		}
		return $cities;
	}
	
	private function getmaidouCities($type) {
		$source = 'maidou';
	
		$cities = [];
		$maps = []; // 去除重复的map映射
		foreach($this->{$type.'Cities'} as $city => $row) {
			if(!empty($row[$source]) && !empty($row[$source]['map']) && !in_array($row[$source]['map'], $maps)) {
				$maps[] = $row[$source]['map'];
				$cities[$city] = $row[$source];
			}
		}
		return $cities;
	}
	
	private function getchunqiuCities($type) {
		$source = 'chunqiu';
	
		$cities = [];
		$maps = []; // 去除重复的map映射
		foreach($this->{$type.'Cities'} as $city => $row) {
			if(!empty($row[$source]) && !empty($row[$source]['map']) && !in_array($row[$source]['map'], $maps)) {
				$maps[] = $row[$source]['map'];
				$cities[$city] = $row[$source];
			}
		}
		return $cities;
	}
}