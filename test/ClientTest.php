<?php

require_once(__DIR__ . '/../lib/Tembo/SocketIOClient.php');

use Tembo\SocketIOClient;

class ClientTest extends PHPUnit_Framework_TestCase
{

	public function testGenerateKey()
	{
		$reflectionMethod = new ReflectionMethod('Tembo\SocketIOClient', 'generateKey');
		$reflectionMethod->setAccessible(true);

		$key = $reflectionMethod->invoke(new SocketIOClient('http://localhost.net'));

		$this->assertEquals(24, strlen($key));
	}


}

