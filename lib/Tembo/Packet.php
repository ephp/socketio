<?php

namespace Tembo;

/**
 * Packet constants
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
abstract class Packet
{
	const TYPE_DISCONNECT = 0;
	const TYPE_CONNECT = 1;
	const TYPE_HEARTBEAT = 2;
	const TYPE_MESSAGE = 3;
	const TYPE_JSON_MESSAGE = 4;
	const TYPE_EVENT = 5;
	const TYPE_ACK = 6;
	const TYPE_ERROR = 7;
	const TYPE_NOOP = 8;
}

