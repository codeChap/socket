<?php

/**
 * Set namespace
 */
namespace CodeChap\Wsocket;

/**
 * Web Socket Server
 */
class Helper
{
    /**
     * Convert raw headers to a beautifull array
     *
     * http://stackoverflow.com/questions/6368574/how-to-get-the-functionality-of-http-parse-headers-without-pecl
     */
    public static function parse_headers($raw_headers)
    {
        if ( ! function_exists('http_parse_headers')) {
            $headers = array();
            $key = '';
            foreach(explode("\n", $raw_headers) as $i => $h) {
                $h = explode(':', $h, 2);

                if (isset($h[1])) {
                    if (!isset($headers[$h[0]]))
                        $headers[$h[0]] = trim($h[1]);
                    elseif (is_array($headers[$h[0]])) {
                        $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
                    }
                    else {
                        $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
                    }

                    $key = $h[0];
                }
                else { 
                    if (substr($h[0], 0, 1) == "\t")
                        $headers[$key] .= "\r\n\t".trim($h[0]);
                    elseif (!$key) 
                        $headers[0] = trim($h[0]); 
                }
            }
        }

        // Use normal function
        else{
            $headers = http_parse_headers($raw_headers);
        }

        // Done
        return $headers;
    }
}