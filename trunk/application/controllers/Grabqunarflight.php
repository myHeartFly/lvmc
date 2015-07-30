<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class GrabQunarFlight extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->library('curl');

	}
	
	public function index() {
		date_default_timezone_set('Asia/Shanghai');
		$this->load->library('curl');
// 		header('Content-Type: application/json'); // debug, chenlei

		// storage cookie
		$cookieFolder = FCPATH.'/public/grab/qunar/cookies';
		if(!file_exists($cookieFolder)) {
			mkdir($cookieFolder, 0775, true);
		}
		$cookieFile = "{$cookieFolder}/flightcookie.txt";
		if(file_exists($cookieFile)) {
			unlink($cookieFile);
		}

		// http://flight.qunar.com/site/interroundtrip_compare.htm?fromCity=%E5%8C%97%E4%BA%AC&toCity=%E6%99%AE%E5%90%89&fromDate=2015-03-12&toDate=2015-03-19&fromCode=BJS&toCode=HKT&from=fi_re_search&lowestPrice=null&isInter=true&favoriteKey=&showTotalPr=null
		
		// mock cookie
		// step 1
		$url = "http://flight.qunar.com/site/interroundtrip_compare.htm?fromCity=%E5%8C%97%E4%BA%AC&toCity=%E6%99%AE%E5%90%89&fromDate=2015-03-12&toDate=2015-03-19&fromCode=BJS&toCode=HKT&from=fi_re_search&lowestPrice=null&isInter=true&favoriteKey=&showTotalPr=null";
		$this->curl->option('COOKIEJAR', $cookieFile);
		$this->curl->option('COOKIESESSION', true);
		$this->curl->simple_get($url);
		exit();
		// step 2
		$url = 'http://user.qunar.com/passport/addICK.jsp';
		$this->curl->option('COOKIEJAR', $cookieFile);
		$this->curl->option('COOKIEFILE', $cookieFile);
		$this->curl->option('COOKIESESSION', true);
		$this->curl->simple_get($url);
		// step 3
// 		$url = 'http://flight.qunar.com/twelli/longwell?&http%3A%2F%2Fwww.travelco.com%2FsearchArrivalAirport=%E5%B7%B4%E5%8E%98%E5%B2%9B&http%3A%2F%2Fwww.travelco.com%2FsearchDepartureAirport=%E4%B8%8A%E6%B5%B7&http%3A%2F%2Fwww.travelco.com%2FsearchDepartureTime=2015-03-03&http%3A%2F%2Fwww.travelco.com%2FsearchReturnTime=2015-03-03&prePay=true&locale=zh&nextNDays=0&searchLangs=zh&searchType=OneWayFlight&split=true&xd=f1424856355000&www=true&wyf=MCrwCzLAMrtwEzLDu7sGKyDCMJswIz3CMvswLJ3EMFrGDyDFMntGKzLGMFsKovAsu7qKozA%3D&from=fi_re_search&lowestPrice=null&mergeFlag=0&_token=79005';
// 		$this->curl->option('COOKIEJAR', $cookieFile);
// 		$this->curl->option('COOKIEFILE', $cookieFile);
// 		$this->curl->option('COOKIESESSION', true);
// 		$this->curl->simple_get($url);
		// step 4
// 		$url = 'http://bc.qunar.com/js/ga.min.js';
// 		$this->curl->option('COOKIEJAR', $cookieFile);
// 		$this->curl->option('COOKIEFILE', $cookieFile);
// 		$this->curl->option('COOKIESESSION', true);
// 		$this->curl->simple_get($url);
// 		// step 5
// 		$url = 'http://t.agrantsem.com/js/ag.js';
// 		$this->curl->option('COOKIEJAR', $cookieFile);
// 		$this->curl->option('COOKIEFILE', $cookieFile);
// 		$this->curl->simple_get($url);
// 		// step 6
// 		$url = 'http://widget.criteo.com/event?a=17463&v=3.2.0&p0=e%3Dexd%26site_type%3Dd&p1=e%3Dvp%26p%3DSHA%252FDPS&p2=e%3Dvs%26din%3D2015-03-03%26dout%3D&p3=e%3Ddis';
// 		$this->curl->option('COOKIEJAR', $cookieFile);
// 		$this->curl->option('COOKIEFILE', $cookieFile);
// 		$this->curl->simple_get($url);
// 		// step 7
// 		$url = 'http://adx.agrantsem.com/CookieMapping/BaiduCM?baidu_user_id=a83378fe8fa0bf019f4048c08f025077&cookie_version=1&timestamp=1424856356&ext_data=AG_275634_HFVR';
// 		$this->curl->option('COOKIEJAR', $cookieFile);
// 		$this->curl->option('COOKIEFILE', $cookieFile);
// 		$this->curl->simple_get($url);
// 		// step 8
// 		$url = 'http://adx.agrantsem.com/CookieMapping/TanxCM?tanx_ver=1&atscu=AG_275634_HFVR&tanx_tid=i3-cuOaASaQ%3D';
// 		$this->curl->option('COOKIEJAR', $cookieFile);
// 		$this->curl->option('COOKIEFILE', $cookieFile);
// 		$this->curl->simple_get($url);
// 		// step 9
// 		$url = 'http://adx.agrantsem.com/cookieMapping?atscu=AG_275634_HFVR&google_gid=CAESEEb9VTHWdA9vysg2whQmR0Q&google_cver=1';
// 		$this->curl->option('COOKIEJAR', $cookieFile);
// 		$this->curl->option('COOKIEFILE', $cookieFile);
// 		$this->curl->simple_get($url);
		// step 10
// 		$url = 'http://a.qunar.com/vataplan?framId=ifrNTAD_datatop_sec&vataposition=QNR_ZjI=_CN&tag=0&rows=1&cur_page_num=0&rep=1&f=s&callback=QNR._AD.ifrNTAD_datatop_sec&ab=b&tile=14248563556232740197&vatafrom=%E4%B8%8A%E6%B5%B7&vatato=%E5%B7%B4%E5%8E%98%E5%B2%9B&departureTime=2015-03-03&arrivalTime=2015-03-10';
// 		$this->curl->option('COOKIEJAR', $cookieFile);
// 		$this->curl->option('COOKIEFILE', $cookieFile);
// 		$this->curl->option('COOKIESESSION', true);
// 		$this->curl->simple_get($url);
		// step 11
// 		$url = 'http://flight.qunar.com/twelli/flight/onewayflight_groupdata_inter_split.jsp?&departureCity=%E4%B8%8A%E6%B5%B7&arrivalCity=%E5%B7%B4%E5%8E%98%E5%B2%9B&departureDate=2015-03-03&returnDate=2015-03-03&nextNDays=0&searchType=OneWayFlight&searchLangs=zh&prePay=true&locale=zh&from=fi_re_search&lowestPrice=null&mergeFlag=0&queryID=192.168.31.133%3A-7ca55413%3A14bc00d6704%3A-6867&serverIP=n9rJzgk4Gby9%2B4o96PwxrUZuCbTEqqAayso3BMRQPaguHps%2Ba1bfzg%3D%3D&status=1424856355841&_token=27528';
// 		$this->curl->option('COOKIEJAR', $cookieFile);
// 		$this->curl->option('COOKIEFILE', $cookieFile);
// 		$this->curl->option('COOKIESESSION', true);
// 		$response = $this->curl->simple_get($url);
// 		// step 12
// 		$url = 'http://d.agkn.com/pixel/2387/?che=3359288894&col=8422266,883117,113850637,286919504,60736909';
// 		$this->curl->option('COOKIEJAR', $cookieFile);
// 		$this->curl->option('COOKIEFILE', $cookieFile);
// 		$this->curl->option('COOKIESESSION', true);
// 		$this->curl->simple_get($url);
// 		// step 13
// 		$url = 'http://a.qunar.com/vataplan?framId=ifrNTAD_patch&vataposition=QNR_ZTM=_CN&tag=0&cur_page_num=0&rep=1&f=s&rows=15&callback=QNR._AD.ifrNTAD_patch&ab=b&tile=14248563556232740197&vatafrom=%E4%B8%8A%E6%B5%B7&vatato=%E5%B7%B4%E5%8E%98%E5%B2%9B';
// 		$this->curl->option('COOKIEJAR', $cookieFile);
// 		$this->curl->option('COOKIEFILE', $cookieFile);
// 		$this->curl->option('COOKIESESSION', true);
// 		$this->curl->simple_get($url);
		
// 		var_dump($this->curl->info);
// 		exit();
		
		$current = time() . '000';
		$url = "http://flight.qunar.com/twelli/flight/onewayflight_groupdata_inter_split.jsp?&departureCity=%E4%B8%8A%E6%B5%B7&arrivalCity=%E5%B7%B4%E5%8E%98%E5%B2%9B&departureDate=2015-03-03&returnDate=2015-03-03&nextNDays=0&searchType=OneWayFlight&searchLangs=zh&prePay=true&locale=zh&from=fi_re_search&lowestPrice=null&mergeFlag=0&queryID=192.168.31.133%3A-7ca55413%3A14bc00d6704%3A-6867&serverIP=n9rJzgk4Gby9%2B4o96PwxrUZuCbTEqqAayso3BMRQPaguHps%2Ba1bfzg%3D%3D&status={$current}&_token=27528";
		echo $url;
		exit();
		$this->curl->option('COOKIEJAR', $cookieFile);
		$this->curl->option('COOKIEFILE', $cookieFile);
		$response = $this->getResponse($url);
		echo $response;
		exit();
	}
	
	private function getResponse($url) { // 统一获取数据
		$this->load->library('curl');
		$cookies = array(
				'JSESSIONID'  => 'AA5AE8303E97FFD7A5FB06AAEF6AC5A5',
				'QN1'         => 'wKgZEFTth4u9aFNY+areAg==',
				'QN170'       => '111.193.230.208_46e1df_0_5I0uqLEkEDOFHCv26SuFPerWBW6Ry1xc1iVGA9Kpdto',
				'QN99'        => '8785',
				'QunarGlobal' => '10.86.214.153_322426b6_14bbfd91390_-58a3|1424852875367',
				'RT'          => '',
				'__ag_cm_'    => '1424852875656',
				'_i'          => 'RBTW-oYQJSxiQ--RiAYLVFENfvTx',
				'_vi'         => 'MAl6yE3QNQabQvYiO0NfrlsSwL2QnYcLwkYRTTRt_7gxcyWfZAaSDoUyFX60VOm1E9w',
				'ag_fid'      => '1xAUzMwh3RpjicSF',
				'csrfToken'   => 'ReSAmLcRB3lkY1WUUH4dfuYuSJWbrExR',
				'TWELLJSESSIONID' => '3EC8BB7DD2BA472372BD3C63F46BA0FC',
		);
		$this->curl->set_cookies($cookies);
	
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
	
}