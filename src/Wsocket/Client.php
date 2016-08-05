<?php

/**
 * Set namespace
 */
namespace CodeChap\Wsocket;

/**
 * Web Socket Server
 */
class Client
{
    /**
     * 
     */
    public $resource = false;

    /**
     * 
     */
    public $active = false;

    /**
     * 
     */
    public $isWebSocket = true;

    /**
     * 
     */
    public $upgraded = false;

    /**
     * 
     */
    public $buffer = null;

    /**
     * 
     */
    public function __construct($resource)
    {
        // Set the socket resource here
        $this->resource = $resource;
    }
}