<?php
/**
 * Telegram Auto Responder - version 0.1.0 - Copyright 2018
 * Monitoring a group for auto post and forward messages to another group
 * Programmer: Nabi KaramAliZadeh <www.nabi.ir> <nabikaz@gmail.com>
 * License: GNU General Public License v3.0
 *
 * Run in CLI mode:
 *   # php index.php
 * When run it for first time, you get two questions, put "a" and then put "u" and follow to complete.
 * Then you can run it in background with this command:
 *   # nohup php index.php &
 */

//Group link (e.g. https://t.me/joinchat/xxxxxxxxxxx)
$group_source = 'https://t.me/joinchat/xxxxxxxxxxx';

//Target group (e.g. https://t.me/joinchat/yyyyyyyyyyy)
$group_target = 'https://t.me/joinchat/yyyyyyyyyyy';

//`true` or `false` for turn on/off send post message
$post_message = true;

//`true` or `false` for turn on/off forward message
$forward_message = true;

//Include self messages to post or forward
$self_message = true;

//Delay between each get updates request in sec (for prevent to flood)
$delay = 1;

//Limit numbers of items in each get updates request (optimal use of resources)
$limit = 100;

/////////////////////////////////////////////////////////////////

//php configs
error_reporting(E_ALL & ~E_NOTICE);
set_time_limit(0);
@ini_set('memory_limit', '-1');

//include madeline
if (!file_exists('madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}
include 'madeline.php';

//settings
$settings = [
	'logger' => [
		'logger' => 0,
	],
];

//start session
$MadelineProto = new \danog\MadelineProto\API('session.madeline', $settings);
$MadelineProto->start();

//get info source group
$group_source_info = $MadelineProto->get_info($group_source);
$group_source_id = $group_source_info['chat_id'];

//get all updates
$offset = 0;
while (true) {
	//get update
    $updates = $MadelineProto->get_updates(['offset' => $offset, 'limit' => $limit, 'timeout' => 0]);
    foreach ($updates as $update) {
		try {
			//offset counter
			$offset = $update['update_id'] + 1;
			
			//just accept new update messages
			if ($update['update']['_'] != 'updateNewMessage') {
				continue;
			}
			
			//get new message
			$message = $update['update']['message'];
			
			//reject outgoing messages
			if (isset($message['out']) && $message['out']) {
				//just accept self messages
				if (($message['to_id']['chat_id'] != $group_source_id) || !$self_message) {
					continue;
				}
			}
			
			//just accept messages in source group
			if ($message['to_id']['chat_id'] != $group_source_id) {
				continue;
			}
			
			//show new message of source group
			echo date('Y-m-d H:i:s > ');
			echo "ID: " . $message['id'] . "\n";
			echo "Message: " . $message['message'] . "\n";
			
			//post message to another target group
			if ($message['message'] && $post_message) {
				$MadelineProto->messages->sendMessage(['peer' => $group_target, 'message' => $message['message']]);
				echo "Posted message.\n";
			}
			
			//forward message to another target group
			if ($message['id'] && $forward_message) {
				$MadelineProto->messages->forwardMessages(['to_peer' => $group_target, 'id' => [$message['id']]]);
				echo "Forwarded message.\n";
			}
			
		} catch (\danog\MadelineProto\Exception | \danog\MadelineProto\RPCErrorException $e) {
			//errors handling
			echo "ERROR: " . $e->getMessage() . "\n";
			preg_match('/FLOOD_WAIT_([0-9]+)/', $e->getMessage(), $match);
			$wait = @$match[1];
			if ($wait > 0) {
				//wait if flood
				echo "Wait for $wait sec...\n";
				sleep($wait);
			}
		}//try
		
		//separator
		echo "===================================\n";
		
    }//foreach
	
	//sleep for each try to get new updates
	sleep($delay);
	
}//while
