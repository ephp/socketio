<?php
require 'loader.php';

use Tembo\Message;
use Tembo\SocketIOClient;

$client = new SocketIOClient('http://localhost:8080');

$client->connect();

$client->emit('subscribe', ['room' => 'test']);

$received = 0;
try {
	$client->listen(function($event, Message $message = null) use (&$received) {
		if($message !== null) {
			$args = json_decode(current($message->getArgs()));
			$message = sprintf('packet: %d, time: %f, heartbeats: %d', $args->packet, $args->time, $args->heartbeats);
			writeDebug($message);
		}
	});
} catch(\RuntimeException $e) {
	echo $e->getMessage();
}