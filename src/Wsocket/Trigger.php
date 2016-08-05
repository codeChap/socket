<?php

/**
 * Set namespace
 */
namespace CodeChap\Wsocket;

/**
 * Web Socket Server
 */
class Trigger
{
    public $config = array(
        'host' => '127.0.0.1',                  // Host to run on
        'port' => 8081,                         // Port to run on
        'max' =>  10,                           // Maximum amount of clients that can be connected at one time
        'max_per_ip' => 1,                      // Maximum amount of clients that can be connected at one time on the same IP address
        'timeout_ping' => 10,                   // Amount of seconds a client has to send data to the server, before a ping request is sent to the client, if the client has not completed the opening handshake, the ping request is skipped and the client connection is closed
        'timeout_pong' => 5,                    // Amount of seconds a client has to reply to a ping request, before the client connection is closed
        'max_frame_payload_recv' => 100000,     // The maximum length, in bytes, of a frame's payload data (a message consists of 1 or more frames), this is also internally limited to 2,147,479,538
        'max_message_payload_recv' => 500000    // The maximum length, in bytes, of a message's payload data, this is also internally limited to 2,147,483,647
    );

    /**
     * @param string Origional trigger string
     */
    public function __construct($trigger, array $dataArray = array('testing'))
    {
        $fp = fsockopen($this->config['host'], $this->config['port'], $errno, $errstr, 30);
        if ( ! $fp) {
            echo "$errstr ($errno)<br />\n";
        } else {

            $data = array($trigger => $dataArray);

            fwrite($fp, json_encode($data));
            fclose($fp);
        }
    }
}