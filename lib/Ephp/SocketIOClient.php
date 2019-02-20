<?php
/**
 * This file is part of the Elephant.io package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Wisembly
 * @license   http://www.opensource.org/licenses/MIT-License MIT License
 */

namespace Ephp;

use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

use Ephp\Exception\SocketException;

/**
 * Represents the IO Client which will send and receive the requests to the
 * websocket server. It basically suggercoat the Engine used with loggers.
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 */
class SocketIOClient
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

    /** @var EngineInterface */
    private $engine;

    /** @var LoggerInterface */
    private $logger;

    private $isConnected = false;

    public function __construct(EngineInterface $engine, LoggerInterface $logger = null)
    {
        $this->engine = $engine;
        $this->logger = $logger ?: new NullLogger;
    }

    public function __destruct()
    {
        if (!$this->isConnected) {
            return;
        }

        $this->close();
    }

    /**
     * Connects to the websocket
     *
     * @return $this
     */
    public function initialize()
    {
        try {
            $this->logger->debug('Connecting to the websocket');
            $this->engine->connect();
            $this->logger->debug('Connected to the server');

            $this->isConnected = true;
        } catch (SocketException $e) {
            $this->logger->error('Could not connect to the server', ['exception' => $e]);

            throw $e;
        }

        return $this;
    }

    /**
     * Reads a message from the socket
     *
     * @return string Message read from the socket
     */
    public function read()
    {
        $this->logger->debug('Reading a new message from the socket');
        return $this->engine->read();
    }

    /**
     * Emits a message through the engine
     *
     * @param string $event
     * @param array  $args
     *
     * @return $this
     */
    public function emit($event, array $args)
    {
        $this->logger->debug('Sending a new message', ['event' => $event, 'args' => $args]);
        $this->engine->emit($event, $args);

        return $this;
    }

    /**
     * Sets the namespace for the next messages
     *
     * @param string namespace the name of the namespace
     * @return $this
     */
    public function of($namespace)
    {
        $this->logger->debug('Setting the namespace', ['namespace' => $namespace]);
        $this->engine->of($namespace);

        return $this;
    }

    /**
     * Closes the connection
     *
     * @return $this
     */
    public function close()
    {
        $this->logger->debug('Closing the connection to the websocket');
        $this->engine->close();

        $this->isConnected = false;

        return $this;
    }

    /**
     * Gets the engine used, for more advanced functions
     *
     * @return EngineInterface
     */
    public function getEngine()
    {
        return $this->engine;
    }






    /**
     * Keep the connection alive and dispatch events
     *
     * @access public
     * @todo work on callbacks
     */
    public function listen(callable $callback = null)
    {
        while (true) {
            if ($this->session['heartbeat_timeout'] > 0 && $this->session['heartbeat_timeout'] + $this->heartbeatStamp - 5 < time()) {
                $this->send(self::TYPE_HEARTBEAT);
                $this->heartbeatStamp = time();
            }

            $r = array($this->fd);
            $w = $e = null;

            if (stream_select($r, $w, $e, 5) == 0) {
                continue;
            }

            $response = $this->read();
            if ($response === null) {
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
                $callback(self::TYPE_CONNECT, null);
                break;

            case '2::':
                $callback(self::TYPE_HEARTBEAT, null);
                break;

            default:
                if (strpos($response, '5:') === 0) {
                    $json = substr($response, 4);
                    $callback(self::TYPE_EVENT, new Message($json));
                }
                break;
        }
    }

}
