<?php

/**
 * Set namespace
 */
namespace CodeChap\Wsocket;

/**
 * Web Socket Server
 */
class Socket
{
    /**
     * Holds the master Socket
     */
    public $master = false;

    /**
     * Holds client connections
     */
    public $clients = array();

    /**
     * Holds a list of trigger functions
     */
    public $triggers = array();

    /**
     * Indicates that the server is listening
     */
    public $running = false;

    /**
     * Default Configuration
     */
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
     * Sets up the class object
     */
    public function __construct(array $config = array())
    {
        // Order is important - do not change
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Register a function that can be triggered
     */
    public function register(array $function)
    {
        // Loop functions and register them
        foreach($function as $trigger => $callable){

            if( is_callable($callable) ){
                $this->triggers[$trigger] = $callable;
            }
        }        
    }

    /**
     * Creates a websocket server out of pure php
     */
    public function create()
    {
        // Set address and port to bind to
        $host = $this->config['host'];
        $port = $this->config['port'];

        // Create the master socket
        if( ($this->master = @stream_socket_server("tcp://$host:$port", $errno, $errstr)) === false){
            throw new \Exception($errno .': '. $errstr);
        }

        // Master socket to non-blocking
        stream_set_blocking($this->master, 0);

        // What are we doing - running!
        $this->running = true;

        // Loop
        $this->loop();  
    }

    /**
     * The continious loop
     */
    private function loop()
    {
        // Log start of server
        $this->log('Server started.');

        // Loop and listen for connections
        while($this->running){

            // Watched to see if a read will not block
            $read = array($this->master);
            //foreach($this->clients as $client){
            //    $read = array_merge($read, array($client->resource));
            //}

            // Watched to see if a write will not block.
            $write = array(); 

            // Watched for high priority exceptional ("out-of-band") data arriving              
            $except = array();

            // Accepts an array of streams and waits for them to change status.
            if(stream_select($read, $write, $except, 1)){

                /**
                 * There is a new connection
                 *
                 * @param The server socket to accept a connection from.
                 * @param Override the default socket accept timeout
                 * @param $peername Will be set to the name (address) of the client that connected
                 */
                if( ! ($newClientResource = @stream_socket_accept($this->master, null, $peername)) === false){
                    $this->log('Connection from ' . $peername , ', upgrading to websocket.');
                    $this->clients[] = new \CodeChap\Wsocket\Client($newClientResource);
                }
            }

            // If clients are connected, loop over them to perform various tasks
            if( count($this->clients) ){

                // Loop in new connections
                foreach($this->clients as $cid => $client){

                    // Reset trigger
                    $trigger = false;

                    // Read from connected client.
                    if( ($client->buffer = @fread($client->resource, 2048) ) === false) {
                        $this->log('Could not read stream');
                    }

                    // Read the full buffer, will only move on after something changes so client must send something in order to change it
                    //if( ! $client->buffer = trim($client->buffer)) {
                    //    continue;
                    //}

                    // Check for new conenctions
                    if( ! $client->active ){
                        
                        // All connections are asumed to be websocket attempts. Check if client has upgraded to the correct protocol, if not perform the upgrade.
                        if( ! $client->upgraded ){
                            
                            // Perform the upgrade
                            if($this->upgradeProtocol($cid)){
                                
                                // Set some params for this connection
                                $client->isWebSocket = true;
                                $client->active = true;
                                $this->log($peername . ' - Upgraded');

                                // First request is the upgrade request, so dont trigger any command below
                                //continue;
                            }

                            // Not a websocket connection, do not upgrade, mark as active and continue with block of code
                            else{
                                $this->log($peername . ' - Non websocket connection');
                                $client->isWebSocket = false;
                                $client->active = true;
                                $trigger = $client->buffer;
                                //@socket_close($this->clients->resource);
                                //unset($this->clients[$cid]);
                            }
                        }
                    }

                    // Broadcast message to all clients
                    foreach($this->clients as $send){
                        
                        // Run injected functions
                        if($trigger){

                            // Decode pushed json data
                            $pushData = json_decode($trigger);

                            // The key is the trigger function
                            $key = key($pushData);
                            
                            // Execute the trigger
                            //if(array_key_exists($trigger, $this->triggers)){
                            $push = call_user_func($this->triggers[$key], $pushData);
                            ////}
                            @fwrite($send->resource, $this->encode($trigger));
                        }
                    }

                    // Lets communicate via web sockets
                    /*
                    if($client->isWebSocket){
                        $request = $this->unmask($client->buffer);
                        // What was requested
                        switch($request){
                            // Urls
                            case filter_var($request, FILTER_VALIDATE_URL) :
                                $r = @file_get_contents($request);
                            break;
                        }

                        // Send it
                        if( ! ($send = @fwrite($client->resource, $this->encode($r))) === false){
                            
                        }

                        // Move on
                        continue;
                    }

                    // Non websocket connection is used to inject or trigger data calls
                    else{
                        //print 'Push change';
                        //$r = @file_get_contents('http://alertza.dev/alertza/api/crime/update.json');
                        //fwrite($client->resource, $this->encode($r));
                    }
                    */
                }
            }



            // Log
            if(isset($log) and ! empty($log)){
                $this->log($log);
                $log = false;
            }
        
            // Go easy on the CPU
            print count($this->clients);
            sleep(1);
        }
    }

    /**
     * Upgrade the connection from the initial headers
     *
     * @var int Client id in array
     * @var string Initial buffer headers
     */
    private function upgradeProtocol($cid)
    {
        // Find client buffer
        $client = $this->clients[$cid];

        // Convert headers to a pretty array
        $headers = \CodeChap\Wsocket\Helper::parse_headers($client->buffer);

        /**
         * If any header is not understood or has an incorrect value , the server should send a "400 Bad Request" and immediately close the socket. @todo
         * If the server doesn't understand that version of WebSockets, it should send a Sec-WebSocket-Version header back that contains the version(s) it does understand. @todo
         */

        // Check for Sec-WebSocket-Key within headers
        if( ! isset($headers['Sec-WebSocket-Key']) ){
            $this->isWebSocket = false; // Non web socket
            return false;
        }

        /**
         * The server should send a HTTP response as below with a security key
         */

        // Work out hash to use in Sec-WebSocket-Accept reply header
        $hash = base64_encode(sha1($headers['Sec-WebSocket-Key'].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        // Build header
        $return_headers = implode("\r\n", array(
            'HTTP/1.1 101 Switching Protocols',
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Accept: ' . $hash
            )
        ) . "\r\n\r\n";

        // Send the full header                                
        $size = strlen($return_headers);
        while($size){

            if( ($sent = @fwrite($client->resource, $return_headers, $size)) === false ){
                return false;
            }

            $size -= $sent;
            
            if($sent > 0){
                $return_headers = substr($return_headers, $sent);
            }
        }

        // Done
        return true;
    }

    /**
     * Unmask a received payload
     * (http://srchea.com/build-a-real-time-application-using-html5-websockets#unmasking-encoding-data-frames)
     *
     * @param string
     */
    private function unmask($payload)
    {
        $length = ord($payload[1]) & 127;

        if($length == 126) {
            $masks = substr($payload, 4, 4);
            $data = substr($payload, 8);
        }
        elseif($length == 127) {
            $masks = substr($payload, 10, 4);
            $data = substr($payload, 14);
        }
        else {
            $masks = substr($payload, 2, 4);
            $data = substr($payload, 6);
        }

        $text = '';
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i%4];
        }
        return $text;
    }

    /**
     * Encode a text for sending to clients via ws://
     * (http://srchea.com/build-a-real-time-application-using-html5-websockets#unmasking-encoding-data-frames)
     *
     * @param string
     */
    private function encode($message, $type = 'text')
    {
        switch ($type) {
            case 'continuous': $b1 = 0; break;
            case 'text': $b1 = 1; break;
            case 'binary': $b1 = 2; break;
            case 'close': $b1 = 8;  break;
            case 'ping': $b1 = 9; break;
            case 'pong': $b1 = 10; break;
        }

        $b1 += 128;

        $length = strlen($message);
        $lengthField = "";

        if ($length < 126) {
            $b2 = $length;
        }
        elseif ($length <= 65536) {
            $b2 = 126;
            $hexLength = dechex($length);
            //$this->stdout("Hex Length: $hexLength");
            if (strlen($hexLength)%2 == 1) {
                $hexLength = '0' . $hexLength;
            } 

            $n = strlen($hexLength) - 2;

            for ($i = $n; $i >= 0; $i=$i-2) {
                $lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
            }

            while (strlen($lengthField) < 2) {
                $lengthField = chr(0) . $lengthField;
            }

        } else {

            $b2 = 127;
            $hexLength = dechex($length);

            if (strlen($hexLength)%2 == 1) {
                $hexLength = '0' . $hexLength;
            } 

            $n = strlen($hexLength) - 2;

            for ($i = $n; $i >= 0; $i=$i-2) {
                $lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
            }

            while (strlen($lengthField) < 8) {
                $lengthField = chr(0) . $lengthField;
            }
        }

        return chr($b1) . chr($b2) . $lengthField . $message;
    }

    /**
     * FuelPHP Output
     */
    public function log($text)
    {
        \Cli::write($text, 'cyan');
    }
}