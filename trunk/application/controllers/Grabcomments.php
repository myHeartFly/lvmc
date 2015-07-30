<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * 抓取booking上简介，评论，与照片
 */
class GrabComments extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->library('curl');

	}
	
	public function index()
	{
		$offset = 0;
		$total = 0;
		$max = 300;
		$condition = ['url' => ['$ne' => null]];
		$total = $this->cimongo->db->hotelbases->count($condition);
		$this->load->helper('htmldom');
		
		echo 'grab booking comments start time:'.date('Y-m-d H:i:s')."\n";
		do {
			$results = $this->cimongo->db->hotelbases->find($condition)->sort(['_id' => 1])->skip($offset)->limit($max);
			$hotels = iterator_to_array($results);
			$offset += $max;
			
			foreach($hotels as $item) {
				$reg = "/http:\/\/www\.booking\.com(.*)/i";
				if(!preg_match($reg, $item['url'])) {
					continue;
				}
				
				$this->grabComments($item); // comments
				echo 'updated hotel comments: '.$item['_id']. ' time:'.date('Y-m-d H:i:s')."\n";
				sleep(0.1);
			}
		} while($offset < $total);

		echo 'grab booking comments success finished. time:'.date('Y-m-d H:i:s')."\n";
	}
	
	private function _getDOM($url, $referer = '') {
		$options  = array(
				'http' => array(
						'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36',
						'header' => 'X-Forwarded-For: 58.30.15.2', // 伪造ip (中国)
						'Referer' => $referer,
				)
		);
	
		$context  = stream_context_create($options);
// 		$fp = fopen($url, 'r', false, $context);
// 		$response = stream_get_contents($fp);
// 		file_put_contents(FCPATH.'/public/grab/test.html', $response);
		
		$dom = @file_get_html($url, false, $context);
		
		return $dom;
	}
	
	private function _getText($url, $referer = '') {
		$options  = array(
				'http' => array(
						'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36',
						'header' => 'X-Forwarded-For: 58.30.15.2', // 伪造ip (中国)
						'Referer' => $referer,
				)
		);
		$context  = stream_context_create($options);
		$response = @file_get_contents($url, false, $context);
	
		return $response;
	}
	
	private function grabComments($hotel) {
		// get sid, dcid for grab comments.
		$urlComs = parse_url($hotel['url']);
		
		if(empty($urlComs) || empty($urlComs['path'])) return false;
		// comments
		$path = trim($urlComs['path'], '/');
		$pathComs = explode('/', $path);
		
		if(count($pathComs) != 3) {
			return false;
		}
		
		$page = 1;
		$dom;
		do {
// 			$url = "http://www.booking.com/reviews/hk/hotel/best-western-harbour-view.zh-cn.html?sid={$sid};dcid={$dcid};customer_type=total;order=completed_desc;page={$page};r_lang=zh-cn&";
			// http://www.booking.com/reviews/hk/hotel/best-western-harbour-view.zh-cn.html?sid=f82a50df913a2199b8f6ec4100e9e719;dcid=1
			$url = $urlComs['scheme'].'://'."{$urlComs['host']}/reviews/{$pathComs[1]}/{$pathComs[0]}/{$pathComs[2]}?customer_type=total;order=completed_desc;page={$page};r_lang=zh-cn&";
			$dom = $this->_getDOM($url);
			if(empty($dom) || empty($dom->find('.review_item'))) {
				continue;
			}
			foreach($dom->find('.review_item') as $div) {
				$contents = [];
// 				if(!empty($div->find('.review_item_header_content', 0))) { // 去除标题
// 					$title = trim($div->find('.review_item_header_content', 0)->plaintext);
// 					!empty($title) AND $contents[]= $title;
// 				}
				
				foreach($div->find('.review_item_review_content p') as $p) {
					if($p->class == 'review_neg') {
						$neg = trim($p->plaintext);
						!empty($neg) AND $contents[] = $neg;
					} else {
						$pos = trim($p->plaintext);
						!empty($pos) AND $contents[] = $pos;
					}
				}
				$content = '';
				if(empty($contents)) {
					continue;
				}
				
				$content = implode('<br>', $contents);
				
				if(!empty($div->find('.review_item_header_date', 0))) {
					$date = trim($div->find('.review_item_header_date', 0)->plaintext);
					$date = str_replace(['年', '月', '日'], ['-', '-', ''], $date);
					$dateComs = explode('-', $date);
					foreach($dateComs as &$com) {
						if($com < 10) {
							$com = (int) $com;
							$com = '0'. (string) $com;
						}
					}
					$date = implode('-', $dateComs);
				}
				if(!empty($div->find('.review_item_reviewer h4', 0))) {
					$nickname = trim($div->find('.review_item_reviewer h4', 0)->plaintext);
				}
				if(!empty($div->find('.review_item_reviewer .reviewer_country', 0))) {
					$country = trim($div->find('.review_item_reviewer .reviewer_country', 0)->plaintext);
				} else {
					$country = '';
				}	
				
				if($country == '中国' && !empty($content) && !empty($date)) {
					$comment = array(
							'key'      => 'hotel-'.$hotel['_id']->{'$id'},
							'nickname' => empty($nickname) ? '匿名' : $nickname,
							'content'  => $content,
							'source'   => 'booking.com',
							'created'  => new MongoDate(strtotime("{$date} 00:00:00"))
					);
					$queryComment = $this->cimongo->db->duoqucomments->findOne($comment);
					if(empty($queryComment)) { // 插入新评论
						$this->cimongo->db->duoqucomments->findAndModify($comment, ['$set' => $comment], null, ['upsert'=>true]);
						$updateComment = $this->cimongo->db->hotelbases->findAndModify(['_id' => $hotel['_id']], ['$inc' => ['commentNum' => new MongoInt32(1)]], ['_id' => true, 'commentNum' => true], array('new' => true));
					}
				}
			}
			
			$page++;
			sleep(0.1);
		} while($dom && !empty($dom->find('.review_next_page a', 0)));
		
		$commentNum = $this->cimongo->db->duoqucomments->count(['key' => 'hotel-'.$hotel['_id']->{'$id'}]); // 更新评论总数
		if(!empty($commentNum)) {
			$this->cimongo->db->hotelbases->findAndModify(['_id' => $hotel['_id']], ['$set' => ['commentNum' => $commentNum]], ['_id' => true, 'commentNum' => true], array('new' => true));
			$queryComments = $this->cimongo->db->duoqucomments->find(['key' => 'hotel-'.$hotel['_id']->{'$id'}])->sort(['created' => -1])->limit(10);
			$comments = iterator_to_array($queryComments);
			$firstContent = '';
			$minContent = '';
			$minLen = 0;
			foreach($comments as $comment) {
				if(mb_strlen($comment['content'], 'UTF8') <= 50) {
					$firstContent = $comment['content'];
					break;
				}
				if(empty($minLen)) {
					$minLen = mb_strlen($comment['content'], 'UTF8');
					$minContent = $comment['content'];
				} else {
					if(mb_strlen($comment['content'], 'UTF8') < $minLen) {
						$minLen = mb_strlen($comment['content'], 'UTF8');
						$minContent = $comment['content'];
					}
				}
			}
			if(empty($firstContent)) {
				$firstContent = $minContent;
			}
			if(!empty($firstContent)) {
				$this->cimongo->db->duoqugrabs->update(['hotels.hotel' => $hotel['_id']], ['$set' => ['hotels.$.commentNum' => new MongoInt32($commentNum), 'hotels.$.comment' => $firstContent ]], ['multiple' => true]);
			}
		}
		
		return true;
	}
}