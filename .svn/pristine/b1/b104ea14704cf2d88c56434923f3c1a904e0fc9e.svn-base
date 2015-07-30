<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Grably extends CI_Controller {

	public function index()
	{
		$this->load->helper('htmldom');

		date_default_timezone_set('Asia/Shanghai');

		$results = array();
		// #travelStyle
		// http://www.ly.com/dujia/AjaxcallTravel.aspx?type=GetListNewInfo&linetype=2&tigid=2930&cityid=53&_dAjax=callback&actionid=39016&pageindex=1&pagesize=1&callback=tc50449062627
		// http://m.ly.com/dujia/chujingdata/gethomedata?BeginCity=%E5%8C%97%E4%BA%AC&Destination=%E6%99%AE%E5%90%89%E5%B2%9B&LineProperty=3&SortType=7&Page=1&callback=jsonp3

		$entryURL = 'http://www.ly.com/dujiatag/beijing/zizhuyou/pujidao/2930.html';
		$dom = $this->_getDOM($entryURL);

		$baseUrl = 'http://www.ly.com/dujia/AjaxcallTravel.aspx?type=GetListNewInfo&linetype=2&tigid=2930&cityid=53&_dAjax=callback&pageindex=1&pagesize=1';
		foreach ($dom->find("#travelStyle a[attr-val!=0]") as $style) {
			$actionid = $style->{'attr-val'};
			if ($actionid != '0') {
				$styleurl = $baseUrl . '&actionid=' . $actionid;
				$prods = $this->_grabstyle($styleurl, $entryURL);
				foreach ($prods as $prod) {
					$this->_grabdetail($prod, $styleurl);
					$results[] = $prod;
				}
			}
		} 

		echo json_encode($results, JSON_UNESCAPED_UNICODE);
		// $this->load->view('grab_llh', array('results'=>$results));
	}

	private function _getDOM($url, $referer = '') {
		$options  = array(
			'http' => array(
				'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36', 
				'Referer' => $referer
			)
		);
		$context  = stream_context_create($options);
		// $dom = file_get_html('http://www.lailaihui.com/Search?GoCity=bj_tj&target=pujidao&price=all&month=all&type=all', false, $context);
		$dom = @file_get_html($url, false, $context);

		return $dom;
	}

	private function _getText($url, $referer = '') {
		$options  = array(
			'http' => array(
				'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36', 
				'header' => 'Content-Type: text/html; charset=gbk', // 
				'Referer' => $referer
			)
		);
		$context  = stream_context_create($options);
		// $dom = file_get_html('http://www.lailaihui.com/Search?GoCity=bj_tj&target=pujidao&price=all&month=all&type=all', false, $context);
		$dom = @file_get_contents($url, false, $context);

		return $dom;
	}

	private function _grabstyle($url, $referer) {
		$cbfunc = 'tc50449062627';

		// $dom = file_get_html('http://www.lailaihui.com/Search?GoCity=bj_tj&target=pujidao&price=all&month=all&type=all', false, $context);
		$content = $this->_getText($url . '&callback=' . $cbfunc, $referer);
		$domstr = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content, 'UTF-8, GBK, GB2312', true));
		$domstr = stripslashes(substr(str_replace($cbfunc . '("', '', $domstr), 0, -2));
		$dom = @str_get_html($domstr);

		$prods = array();
		foreach ($dom->find('.price_bot') as $bot) {
			$prods[] = (object)array('source'=> '同程', 'url'=>$bot->find('a', 0)->href, 'price'=>intval($bot->find('.tc_pri', 0)->{'attr-prace'}));
		}

		return $prods;
	}

	private function _grabdetail(&$prod, $referer) {
		$dom = $this->_getDOM($prod->url, $referer);
		// echo $dom->save();
		// die();
		$infobox = $dom->find('#content .par_l .infobox', 0);
		if (preg_match("/(?<name>.*(?=<?))(?<summary><.*>?)【编号：(?<code>\d+)】/i", trim($infobox->find('h2', 0)->plaintext), $matches)) {
			$prod->name = trim($matches['name']);
			$prod->summary = trim($matches['summary']);
			$prod->code = trim($matches['code']);

			$priceCalUrl = 'http://www.ly.com/dujia/OrderAjaxCall.aspx?type=GetDataListByAjax&lineId=' . $prod->code . '&iid=0.6413594402838498';
			for ($i=0; $i < 3 ; $i+=1) { // 取三个月内的价格日历
				$time = strtotime($i . ' month');
				$priceurl = $priceCalUrl . '&year='. date('Y', $time) . '&month=' . date('n', $time);
				$text = $this->_getText($priceurl, $referer);
				$text = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text, 'UTF-8, GBK, GB2312', true));
				
				$json = json_decode($text);
				if (!is_object($json)) {
					echo $text;
					var_dump($json);
					die();
				}
				foreach ($json->priceList as $date) {
					if ($date->Residual > 0) {
						$prod->priceCal[$date->Date] = (object)array('yw' => $date->Residual);
					}
				}
			}
		}

		$prod->images = array();
		foreach ($dom->find('#rgt_tgList .linka') as $img) {
			$prod->images[] = $img->nsrc;
		}
		foreach ($infobox->find('.infoList li') as $li) {
			$strs = explode('：', $li->plaintext);
			switch ($strs[0]) {
				case '出发城市':
					$prod->start = trim($strs[1]);
					break;
				
				case '抵达城市':
					$prod->dist = trim($strs[1]);
					break;
				
				case '游玩时间':
					$prod->days = intval(trim($strs[1]));
					break;
				
				default:
					break;
			}
		}
		$prod->reason = trim(strip_tags($infobox->find('.recommendBox dd', 0)->plaintext));

		return $prod;
	}
}