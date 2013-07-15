<?php

namespace Ephp;

/**
 * Ephp is a rough implementation of socket.io protocol.
 * It should ease you dealing with a socket.io server.
 * Based on Tembo by @author Martin Bažík <martin@bazo.sk>
 * Based on ElephantIO by @author Ludovic Barreca <ludovic@balloonup.com>
 * 
 * @author Ephraim Pepe <ephraim.pepe@gmail.com>
 */
class SocketIOClient
{

	/** @var string */
	private $socketIOUrl;

	/** @var string */
	private $serverHost;

	/** @var int */
	private $serverPort = 80;

	/** @var array */
	private $session;

	/** @var resource */
	private $fd;


	/**
	 * @param string $socketIOUrl
	 * @param string $socketIOPath
	 */
	public function __construct($socketIOUrl, $socketIOPath = 'socket.io')
	{
		$protocol = 1; //has to be 1
		$this->socketIOUrl = $socketIOUrl . '/' . $socketIOPath . '/' . (string) $protocol;
		$this->parseUrl();
	}


	/**
	 * Connects using websocket protocol
	 * @param int $handshakeTimeout socket timeout
	 * @param bool $read Whether to check if socket.io responded correctly
	 * @param bool $checkSslPeer
	 * @return string Socket.io session id
	 * @throws \RuntimeException
	 */
	public function connect($handshakeTimeout = NULL, $read = TRUE, $checkSslPeer = TRUE)
	{
		$this->handshake($checkSslPeer, $handshakeTimeout);
		$this->fd = stream_socket_client($this->serverHost . ':' . $this->serverPort, $errno, $errstr);

		if (!$this->fd) {
			throw new \RuntimeException('Could not create socket: ' . $errstr);
		}

		$response = $this->upgradeProtocol();
		$this->verifyUpgradeResponse($response, $read);
		$this->heartbeatStamp = time();

		return $this->session['sid'];
	}


	private function handshake($checkSslPeer, $handshakeTimeout)
	{
		$ch = curl_init($this->socketIOUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $checkSslPeer);

		if (!is_null($handshakeTimeout)) {
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $handshakeTimeout);
			curl_setopt($ch, CURLOPT_TIMEOUT_MS, $handshakeTimeout);
		}

		$response = curl_exec($ch);

		if ($response === FALSE) {
			throw new \RuntimeException(curl_error($ch));
		}
		$this->processHandshakeResponse($response);

		return TRUE;
	}


	private function processHandshakeResponse($response)
	{
		$session = explode(':', $response);
		$this->session['sid'] = $session[0];
		$this->session['heartbeat_timeout'] = $session[1];
		$this->session['connection_timeout'] = $session[2];
		$this->session['supported_transports'] = array_flip(explode(',', $session[3]));

		if (!isset($this->session['supported_transports']['websocket'])) {
			throw new \RuntimeException('This socket.io server do not support websocket protocol.');
		}
	}


	private function upgradeProtocol()
	{
		$key = $this->generateKey();

		$out = "GET " . $this->serverPath . "/websocket/" . $this->session['sid'] . " HTTP/1.1\r\n";
		$out .= "Host: " . $this->serverHost . "\r\n";
		$out .= "Upgrade: WebSocket\r\n";
		$out .= "Connection: Upgrade\r\n";
		$out .= "Sec-WebSocket-Key: " . $key . "\r\n";
		$out .= "Sec-WebSocket-Version: 13\r\n";
		$out .= "Origin: *\r\n\r\n";

		fwrite($this->fd, $out);

		return fgets($this->fd);
	}


	private function verifyUpgradeResponse($response, $read)
	{
		if ($response === FALSE) {
			throw new \RuntimeException('Socket.io did not respond properly. Aborting...');
		}

		$subres = substr($response, 0, 12);
		if ($subres != 'HTTP/1.1 101') {
			throw new \RuntimeException('Unexpected Response. Expected HTTP/1.1 101 got ' . $subres);
		}

		while (TRUE) {
			$res = trim(fgets($this->fd));
			if ($res === '') {
				break;
			}
		}

		if ($this) {
			if ($this->read() != '1::') {
				throw new \RuntimeException('Socket.io did not send connect response.');
			}
		}
	}


	/**
	 * Keep the connection alive and dispatch events
	 *
	 * @access public
	 * @todo work on callbacks
	 */
	public function listen(callable $callback = NULL)
	{
		while (TRUE) {
			if ($this->session['heartbeat_timeout'] > 0 && $this->session['heartbeat_timeout'] + $this->heartbeatStamp - 5 < time()) {
				$this->send(Packet::TYPE_HEARTBEAT);
				$this->heartbeatStamp = time();
			}

			$r = array($this->fd);
			$w = $e = NULL;

			if (stream_select($r, $w, $e, 5) == 0) {
				continue;
			}

			$response = $this->read();
			if ($response === NULL) {
				throw new \RuntimeException('Connection to socket.io has been closed forcefully.');
			}

			if (!is_null($callback)) {
				$this->processIncoming($response, $callback);
			}
		}
	}


	private function processIncoming($response, callable $callback)
	{
		switch ($response) {
			case '1::':
				$callback(Packet::TYPE_CONNECT, NULL);
				break;

			case '2::':
				$callback(Packet::TYPE_HEARTBEAT, NULL);
				break;

			default:
				if (strpos($response, '5:') === 0) {
					$json = substr($response, 4);
					$callback(Packet::TYPE_EVENT, new Message($json));
				}
				break;
		}
	}


	/**
	 * Read the buffer and return the oldest event in stack
	 *
	 * @return string
	 * // https://tools.ietf.org/html/rfc6455#section-5.2
	 */
	private function read()
	{
		// Ignore first byte, I hope Socket.io does not send fragmented frames, so we don't have to deal with FIN bit.
		// There are also reserved bit's which are 0 in socket.io, and opcode, which is always "text frame" in Socket.io
		fread($this->fd, 1);

		// There is also masking bit, as MSB, but it's 0 in current Socket.io
		$payloadLength = ord(fread($this->fd, 1));

		if ($payloadLength === 0) {
			return NULL;
		}

		switch ($payloadLength) {
			case 126:
				$payloadLength = unpack("n", fread($this->fd, 2));
				$payloadLength = $payloadLength[1];
				break;
			case 127:
				throw new \RuntimeException("Next 8 bytes are 64bit uint payload length, not yet implemented, since PHP can't handle 64bit longs!");
				break;
		}

		$payload = fread($this->fd, $payloadLength);

		return $payload;
	}


	/**
	 * Send message to the websocket
	 * @param int $type
	 * @param string $message
	 * @param int $id
	 * @param int $endpoint
	 * @return Client
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	public function send($type, $message = NULL, $id = NULL, $endpoint = NULL)
	{
		if (!is_int($type) || $type > 8) {
			throw new \InvalidArgumentException('ElephantIOClient::send() type parameter must be an integer strictly inferior to 9.');
		}

		$raw_message = $type . ':' . $id . ':' . $endpoint . ':' . $message;
		$payload = new Payload;
		$payload->setOpcode(Payload::OPCODE_TEXT)
				->setMask(TRUE)
				->setPayload($raw_message);
		$encoded = $payload->encodePayload();

		$res = @fwrite($this->fd, $encoded);
		if ($res === 0) {
			$error = error_get_last();
			throw new \RuntimeException($error['message'], $error['code']);
		}

		return $this;
	}


	/**
	 * Emit an event
	 *
	 * @param string $event
	 * @param array $args
	 * @param string $endpoint
	 */
	public function emit($event, $args, $endpoint = NULL)
	{
		$message = json_encode(array(
			'name' => $event,
			'args' => $args,
		));
		
		return $this->send(Packet::TYPE_EVENT, $message, NULL, $endpoint);
	}


	/**
	 * Send a message
	 *
	 * @param mixed $message
	 * @param int $endpoint
	 */
	public function message($message, $endpoint = NULL)
	{
		return $this->send(Packet::TYPE_MESSAGE, json_encode($message),  NULL, $endpoint);
	}


	/**
	 * Send heartbeat
	 */
	public function heartbeat()
	{
		return $this->send(Packet::TYPE_HEARTBEAT, NULL, NULL, NULL);
	}


	/**
	 * Close the socket
	 *
	 * @param int $waitTime time to wait in miliseconds before closing socket after sending disconnect signal, minimum seems to be 50ms
	 * @return boolean
	 */
	public function disconnect($waitTime = 50)
	{
		if ($this->fd) {
			$this->send(Packet::TYPE_DISCONNECT);
			usleep($waitTime * 1000); //sleep $waiTimems
			fclose($this->fd);

			return TRUE;
		}

		return FALSE;
	}


	private function generateKey($length = 16)
	{
		$c = 0;
		$tmp = '';

		while ($c++ * 16 < $length) {
			$tmp .= md5(mt_rand(), TRUE);
		}

		return base64_encode(substr($tmp, 0, $length));
	}


	private function parseUrl()
	{
		$url = parse_url($this->socketIOUrl);

		$this->serverPath = $url['path'];
		$this->serverHost = $url['host'];
		$this->serverPort = isset($url['port']) ? $url['port'] : NULL;

		if (array_key_exists('scheme', $url) && $url['scheme'] == 'https') {
			$this->serverHost = 'ssl://' . $this->serverHost;
			if (!$this->serverPort) {
				$this->serverPort = 443;
			}
		}

		return TRUE;
	}


}


