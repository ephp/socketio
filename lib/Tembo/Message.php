<?php

namespace Tembo;

/**
 * Description of Message
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class Message
{

	/** @var string Event name */
	private $name;

	/** @var array Event arguments */
	private $args = [];


	public function __construct($json)
	{
		$decoded = json_decode($json);
		$this->name = $decoded->name;
		$this->args = $decoded->args;
	}


	public function getName()
	{
		return $this->name;
	}


	public function getArgs()
	{
		return $this->args;
	}


}

