<?php

require 'loader.php';

use ElephantIO\Message;
use Tembo\SocketIOClient;

$client = new SocketIOClient('http://localhost:8000');

$client->connect();
$sent = 0;
$start = microtime(true);
$heartbeats = 0;

$heartbeatMark = 20000;

while (true) {

	try {
		$time = microtime(true);
		$elapsedTime = ($time - $start);

		if ($sent % $heartbeatMark === 0) {
			$client->heartbeat();
			$heartbeats++;
		}

		$payload = array(
			"packet" => $sent,
			"time" => $elapsedTime,
			'heartbeats' => $heartbeats
		);

		$message = sprintf('packet: %d, time: %f, heartbeats: %d', $sent, $elapsedTime, $heartbeats);

		$client->message(
				$payload
		);

		$sent++;

		writeDebug($message);
		usleep(1 * 1000);
	} catch (\RuntimeException $e) {
		echo $e . "\n";
		break;
	}
}
$client->disconnect();