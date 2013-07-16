<?php
require( __DIR__ . '/../lib/Ephp/SocketIOClient.php');
require( __DIR__ . '/../lib/Ephp/Message.php');
require( __DIR__ . '/../lib/Ephp/Payload.php');
require( __DIR__ . '/../lib/Ephp/Packet.php');

function writeDebug($message)
{
	static $lastMessageLength = '';
	$length = strlen($message);

	// append whitespace to match the last line's length
	if (NULL !== $lastMessageLength && $lastMessageLength > $length) {
		$message = str_pad($message, $lastMessageLength, "\x20", STR_PAD_RIGHT);
	}

	// carriage return
	echo "\x0D";
	echo $message;

	$lastMessageLength = strlen($message);
	$message = NULL;
}