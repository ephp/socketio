# Tembo

MIT Licensed - Copyright © 2013. Martin Bažík

## About

Tembo is a rough socket.io client written in PHP. Its goal is to ease communications between your PHP application and a socket.io server.
Protocol version of socket.io currently supported is 1.
Only websocket transport is available at the moment.

## Usage

### Sending messages

```php
use Tembo\Message;
use Tembo\SocketIOClient;

$client = new SocketIOClient('http://localhost:8000');

$client->connect();

//send message
$client->message($message);

//emit event
$args = [...];
$client->emit($event, $args);

$client->disconnect();
```

### Listening to incoming messages

```php
use Tembo\Message;

$callback = function($eventType, Message $message) {
	$eventName = $message->getName();
	$args = $message->getArgs();
};

$client->listen($callback);
```

## Licence

This software is distributed under MIT License. See license.txt file for more info.

## Special Thanks

Special thanks goes to Wisembly team authors of Elephant.io