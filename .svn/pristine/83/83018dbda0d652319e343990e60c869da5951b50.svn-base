<?php

defined('BASEPATH') OR exit('No direct script access allowed');

spl_autoload_register(function($class) {
	$file = __DIR__.'/../libraries/'.strtr($class, '\\', '/').'.php';
	//echo $file."<br/>";
	if (file_exists($file)) {
		//echo 'file_exists<br/>';
		require $file;
		return true;
	}
});
/*
 * 抓取booking上简介，评论，与照片
 */
class GrabCustomPrice extends CI_Controller {

	public function __construct()
	{
		date_default_timezone_set('Asia/Shanghai');

		parent::__construct();
		$this->load->library('grab/customprice');

		$this->redisClient = new Predis\Client('tcp://127.0.0.1:6379?read_write_timeout=0');
	}
	
	public function index()
	{
		echo 'grab custom price data start:'.date("Y-m-d H:i:s")."\n";
		$this->customprice->index();
		echo 'grab custom price data end:'.date("Y-m-d H:i:s")."\n";
	}
	
	public function updateFirstPrice($code = '')
	{
		echo 'grab custom price data first key start:'.date("Y-m-d H:i:s")."\n";
		$condition = [];
		if(!empty($code)) {
			$condition['code'] = $code;
		}
		$this->customprice->updateFirstPeriodPrices($condition);
		echo 'grab custom price data first key end:'.date("Y-m-d H:i:s")."\n";
	}

	public function grab($source)
	{
		if(empty($source)) exit('source cant empty');
		$this->customprice->index($source);
	}
	
	public function grabByCode($code)
	{
		if(empty($code)) exit('code cant empty');
		$this->customprice->dealProductByCode($code);
	}
	
	public function grabProductByCode()
	{
		// Initialize a new pubsub consumer.
		$pubsub = $this->redisClient->pubSubLoop();
		
		// Subscribe to your channels
		$pubsub->subscribe('task_grab_customprice');

		// Start processing the pubsup messages. Open a terminal and use redis-cli
		// to push messages to the channels. Examples:
		//   ./redis-cli PUBLISH notifications "this is a test"
		//   ./redis-cli PUBLISH control_channel quit_loop
		foreach ($pubsub as $message) {
		    switch ($message->kind) {
		        case 'subscribe':
		            echo "Subscribed to {$message->channel}", PHP_EOL;
		            break;

		        case 'message':
		            if ($message->channel == 'task_grab_customprice') {
		                if ($message->payload == 'quit_loop') {
		                    echo "Aborting pubsub loop...", PHP_EOL;
		                    $pubsub->unsubscribe();
		                } else {
		                    echo "Received an code : {$message->payload}.", PHP_EOL;
		                    $this->customprice->dealProductByCode($message->payload);
		                }
		            } else {
		                echo "Received the following message from {$message->channel}:",
		                     PHP_EOL, "  {$message->payload}", PHP_EOL, PHP_EOL;
		            }
		            break;
		    }
		}

		// Always unset the pubsub consumer instance when you are done! The
		// class destructor will take care of cleanups and prevent protocol
		// desynchronizations between the client and the server.
		unset($pubsub);

		// Say goodbye :-)
		$version = redis_version($client->info());
		echo "Goodbye from Redis $version!", PHP_EOL;


		//$this->customprice->dealProductByCode($code);
	}
}