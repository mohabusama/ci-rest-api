<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
* Http Request and Response Classes
* 
* Implements classes to handle HTTP operations and Defines HTTP related constants.
* 
* @package Restapi
* @subpackage Http
* @license MIT License https://github.com/mohabusama/ci-rest-api/blob/master/LICENSE
* @link https://github.com/mohabusama/ci-rest-api
* @author Mohab Usama
* @version 0.1.0
*/

define("REQUEST_GET", 'get');
define("REQUEST_POST", 'post');
define("REQUEST_PUT", 'put');
define("REQUEST_DELETE", 'delete');
define("REQUEST_HEAD", 'head');
define("REQUEST_PATCH", 'patch');

// 2XX SUCCESS
define("HTTP_RESPONSE_OK", '200');
define("HTTP_RESPONSE_CREATED", '201');
define("HTTP_RESPONSE_NO_CONTENT", '204');

// 4XX CLIENT ERROR
define("HTTP_RESPONSE_BAD_REQUEST", '400');
define("HTTP_RESPONSE_UNAUTHORIZED", '401');
define("HTTP_RESPONSE_FORBIDDEN", '403');
define("HTTP_RESPONSE_NOT_FOUND", '404');
define("HTTP_RESPONSE_NOT_ALLOWED", '405');

// 5XX SERVER ERROR
define("HTTP_RESPONSE_INTERNAL_ERROR", '500');
define("HTTP_RESPONSE_NOT_IMPLEMENTED", '501');


/**
 * Request Class
 * 
 * This class provides all necessary information about the API request.
 * 
 * @package Restapi
 */

class Request
{
    /**
     * Private array holding all input data. Loaded Data is not XSS clean.
     * Use @method data() to retrieve data from this array.
     * 
     * @var array
     */
    private $_data = array();

    /**
     * Private array holding all URL args
     * Use @method args() to retrieve data from this array.
     * 
     * @var array
     */
    private $_args = array();

    /**
     * Private array holding all URI components.
     * Use @method uri() to retrieve data from this array.
     * 
     * @var array
     */
    private $_uri = array();

    /**
     * Private string holding Full URI string
     * Use @method full_uri() to retrieve full uri with optional appended uri segments.
     * 
     * @var string
     */
    private $_full_uri = array();

    /**
     * Used as reference for CI security class.
     * 
     * @var object
     */
    private $security = NULL;

    /**
     * HTTP Request Method
     * Dafult: @link REQUEST_GET
     * 
     * @var string
     */
    public $method = REQUEST_GET;

    /**
     * Force SSL Requests only.
     * 
     * @var bool
     */
    public $ssl = FALSE;

    /**
     * Request Format
     * 
     * @var string
     */
    public $format = NULL;

    /**
     * Array of Request Headers
     * 
     * @var array
     */
    public $header = array();

    /**
     * Construct method
     * 
     * Loads all @link $_data , @link $_args , @link $_uri
     * 
     * @param RestResource $resource
     * @param string $default_format Default format of Request.
     * 
     * @return void
     */
    public function __construct($resource, $default_format='json')
    {
        $this->method = strtolower($resource->input->server('REQUEST_METHOD'));

        // URL args
        $args = $resource->input->get();
        $this->_args = $args ? $args : array();

        $this->_uri = $resource->uri->rsegment_array();
        $this->_full_uri = $resource->uri->uri_string();

        $this->header = $resource->input->request_headers();

        $input_format = RestFormat::get_input_format($this->header);
        $this->format = $input_format ? $input_format : $default_format;

        $this->security = $resource->security;

        $this->load_input_data($resource->input);
    }

    /**
     * Loads input data depending on Request Method
     * 
     * Loads all @link $_data , @link $_args , @link $_uri
     * 
     * @param object $input CodeIgniter Input class
     * 
     * @return void
     */
    public function load_input_data($input)
    {
        if ($this->method == REQUEST_GET)
        {
            // All Data, No XSS filtering!
            $get_data = $input->get();
            $this->_data = $get_data ? $get_data : array();
        }
        else
        {
            // In case of POST, PUT or DELETE
            if($this->format == 'form')
            {
                // Regular Form POST (i.e. application/x-www-form-urlencoded)
                $post_data = $input->post();
                $this->_data = $post_data ? $post_data : array();
            }
            elseif ($this->format)
            {
                $body = file_get_contents('php://input');
                $this->_data = RestFormat::decode_input_data($body, $this->format);
            }
        }
    }

    /**
     * Retrieve All or Specific URL arg
     * 
     * If passed no Inputs, all @link $_args array is returned. If $key not found then returns FALSE
     * 
     * @param string $key The key value in the url args
     * @param bool $xss_clean Clean arg value before
     * 
     * @return bool|string
     */
    public function args($key=NULL, $xss_clean=FALSE)
    {
        if ($key === NULL)
        {
            return $this->_args;
        }

        if (array_key_exists($key, $this->_args))
        {
            $value = $this->_args[$key];
            return $xss_clean ? $this->security->xss_clean($value) : $value;
        }
        
        return FALSE;
    }

    /**
     * Retrieve All or Specific Input Data
     * 
     * If passed no Inputs, all @link $_data array is returned. If $key not found then returns FALSE
     * 
     * @param string $key The key value in the url args
     * @param bool $xss_clean Clean arg value before
     * 
     * @return bool|string
     */
    public function data($key=NULL, $xss_clean=TRUE)
    {
        if($key)
        {
            if(array_key_exists($key, $this->_data))
            {
                $value = $this->_data[$key];
                return $xss_clean ? $this->security->xss_clean($value) : $value;
            }
            
            return FALSE;
        }

        return $this->_data;
    }

    /**
     * Retrieve All or Specific URI component
     * 
     * If passed no Inputs, all @link $_uri array is returned. If $key not found then returns FALSE
     * 
     * @param string $key The key value in the url args
     * @param bool $xss_clean Clean arg value before
     * 
     * @return bool|string
     */
    public function uri($index=NULL, $xss_clean=FALSE)
    {
        if($index === NULL)
        {
            return $this->_uri;
        }
        elseif ($index === -1)
        {
            $uri = end($this->_uri);
            reset($this->_uri);
            return $xss_clean ? $this->security->xss_clean($uri) : $uri;
        }
        elseif(count($this->_uri) && count($this->_uri) >= $index+1)
        {
            $uri = $this->_uri[$index+1];
            return $xss_clean ? $this->security->xss_clean($uri) : $uri;
        }

        return NULL;
    }

    /**
     * Retrieve Full URI with optional appended URI segments
     * 
     * @param string|array $segments Extra URI segments. Can be string or array of Strings
     * 
     * @return string
     */
    public function full_uri($segments=NULL)
    {
        if (!$segments)
        {
            return $this->_full_uri;
        }

        $seg = $segments;
        if (is_array($segments))
        {
            $seg = implode("/", $segments);
        }

        return implode("/", array($this->_full_uri, $seg));
    }

    /**
     * Removes any keys that exists in $filter from $_data
     * 
     * @param array $filter Array of strings (Keys) that should be removed from $_data
     * 
     * @return void
     */
    public function filter_data($filter)
    {
        if (is_assoc($this->_data))
        {
            $this->_data = $this->_filter_obj($this->_data, $filter);
        }
        else
        {
            $idx = 0;
            foreach ($this->_data as $obj)
            {
                $this->_data[$idx] = $this->_filter_obj($obj, $filter);
                $idx += 1;
            }
        }
    }

    private function _filter_obj($obj, $filter)
    {
        foreach ($obj as $key => $value)
        {
            if (in_array($key, $filter))
            {
                unset($obj[$key]);
            }
        }

        return $obj;
    }
}

class Response
{
    /**
     * Private array holding all Response Headers.
     * Use @method set_header() to add/update a header.
     * 
     * @var array
     */
    private $_headers = array();

    /**
     * Response format. Default is 'json'.
     * 
     * @var string
     */
    public $format = 'json';

    /**
     * Response Body.
     *
     * Note: Normaly, this value is set by @method http_exit() based on the $output param. It is 
     * kept public just to give the Developer an option to set the reponse body explicitly.
     * 
     * @var string
     */
    public $body = '';

    /**
     * Default HTTP Response Status code.
     * 
     * @var array
     */
    public $status = HTTP_RESPONSE_OK;

    /**
     * Construct method
     * 
     * Detects Response fromat @link $format
     * 
     * @param RestResource $resource
     * @param string $default_format Default format of Request.
     * 
     * @return void
     */
    public function __construct($resource, $default_format='json')
    {
        $output_format = RestFormat::get_output_format($resource->request->args());
        $this->format = $output_format ? $output_format : $default_format;
    }

    /**
     * Adds a new Header to the Response.
     * 
     * @param string $value The new header added.
     * 
     * @example $this->response->set_header('My-Custom-Header: ver-1.0.1');
     * @return void
     */
    public function set_header($value)
    {
        $this->_headers[] = $value;
    }

    /**
     * Send Immidiate Response with HTTP Status Code 200 @link HTTP_RESPONSE_OK
     * 
     * @param mixed $output Output Data.
     * 
     * @return void
     */
    public function http_200($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_OK);
    }

    /**
     * Send Immidiate Response with HTTP Status Code 201 @link HTTP_RESPONSE_CREATED
     * 
     * @param mixed $output Output Data.
     * 
     * @return void
     */
    public function http_201($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_CREATED);
    }

    /**
     * Send Immidiate Response with HTTP Status Code 204 @link HTTP_RESPONSE_NO_CONTENT
     * 
     * @param mixed $output Output Data.
     * 
     * @return void
     */
    public function http_204($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_NO_CONTENT);
    }

    /**
     * Send Immidiate Response with HTTP Status Code 400 @link HTTP_RESPONSE_BAD_REQUEST
     * 
     * @param mixed $output Output Data.
     * 
     * @return void
     */
    public function http_400($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_BAD_REQUEST);
    }

    /**
     * Send Immidiate Response with HTTP Status Code 401 @link HTTP_RESPONSE_UNAUTHORIZED
     * 
     * @param mixed $output Output Data.
     * 
     * @return void
     */
    public function http_401($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_UNAUTHORIZED);
    }

    /**
     * Send Immidiate Response with HTTP Status Code 403 @link HTTP_RESPONSE_FORBIDDEN
     * 
     * @param mixed $output Output Data.
     * 
     * @return void
     */
    public function http_403($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_FORBIDDEN);
    }

    /**
     * Send Immidiate Response with HTTP Status Code 404 @link HTTP_RESPONSE_NOT_FOUND
     * 
     * @param mixed $output Output Data.
     * 
     * @return void
     */
    public function http_404($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_NOT_FOUND);
    }

    /**
     * Send Immidiate Response with HTTP Status Code 405 @link HTTP_RESPONSE_NOT_ALLOWED
     * 
     * @param mixed $output Output Data.
     * 
     * @return void
     */
    public function http_405($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_NOT_ALLOWED);
    }

    /**
     * Send Immidiate Response with HTTP Status Code 500 @link HTTP_RESPONSE_INTERNAL_ERROR
     * 
     * @param mixed $output Output Data.
     * 
     * @return void
     */
    public function http_500($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_INTERNAL_ERROR);
    }

    /**
     * Send Immidiate Response with HTTP Status Code 501 @link HTTP_RESPONSE_NOT_IMPLEMENTED
     * 
     * @param mixed $output Output Data.
     * 
     * @return void
     */
    public function http_501($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_NOT_IMPLEMENTED);
    }

    /**
     * Send Immidiate Response to the client.
     * 
     * @param mixed $output Output Data.
     * @param string $status HTTP Status code.
     * 
     * @return void
     */
    public function http_exit($output, $status=NULL)
    {
        if($status)
        {
            $this->status = $status;
        }

        // Initial Headers ...
        header('HTTP/1.1: ' . $this->status);
        header('Status: ' . $this->status);
        header('Content-Type: ' . RestFormat::get_content_type($this->format));

        // Set Headers
        foreach ($this->_headers as $value) {
            header($value);
        }

        // @todo : Add option for Expiration & No cache headers! Might be a Security Req.

        // Format o/p data as desired!
        $this->body = RestFormat::encode_output_data($output, $this->format);

        // ... And GO!
        exit($this->body);
    }
}

class RestFormat
{

    private static $input_mime_types = array(
        'application/x-www-form-urlencoded' => 'form',
        'application/json' => 'json'
    );

    private static $output_mime_types = array(
        'application/json' => 'json',
        'application/csv' => 'csv'
    );

    public static function get_input_format($header)
    {
        $header_low = array_change_key_case($header, CASE_LOWER);

        if (array_key_exists('content-type', $header_low))
        {
            $input_mime = explode(';', $header_low['content-type']);

            if(array_key_exists($input_mime[0], self::$input_mime_types))
            {
                // That should return 'json' or 'xml' etc...
                return self::$input_mime_types[$input_mime[0]];
            }
        }

        // Unknown/Unsupported mime/format!
        return NULL;
    }

    public static function get_output_format($args)
    {
        if(isset($args['format']))
        {
            foreach (self::$output_mime_types as $key => $value) {
                if($value == $args['format'])
                {
                    return $value;
                }
            }
        }

        // Unsupported format,, let the caller decide the o/p format!
        return NULL;
    }

    public static function decode_input_data($data, $format)
    {
        return self::{'_decode_' . $format}($data);
    }

    public static function encode_output_data($data, $format)
    {
        return self::{'_encode_' . $format}($data);
    }

    public static function get_content_type($format)
    {
        foreach (self::$output_mime_types as $mime => $value) {
            if($value == $format)
            {
                return $mime . '; charset=utf-8';
            }
        }

        return NULL;
    }

    private function _to_array($data)
    {
        if (is_array($data) || is_object($data))
        {
            $result = array();
            foreach ($data as $key => $value)
            {
                $result[$key] = self::_to_array($value);
            }
            return $result;
        }
        return $data;
    }

    private function _decode_json($data)
    {
        return self::_to_array(json_decode($data, TRUE));
    }

    private function _encode_json($data)
    {
        return json_encode($data);
    }

    /**
    * Encode Output data to CSV format.
    * Original Implementation from Format Library from "codeigniter-restserver":
    * https://github.com/philsturgeon/codeigniter-restserver
    * 
    * @author      Phil Sturgeon (Implementation)
    * @license     http://philsturgeon.co.uk/code/dbad-license
    * 
    * @return string CSV formatted output
    */
    private function _encode_csv($data)
    {
        if (!is_array($data))
        {
            return $data;
        }

        // Multi-dimensional array
        if (isset($data[0]) && is_array($data[0]))
        {
            $headings = array_keys($data[0]);
        }
        // Single array
        else
        {
            $headings = array_keys($data);
            $data = array($data);
        }

        $output = '"'.implode('","', $headings).'"'.PHP_EOL;
        foreach ($data as $row)
        {
            $output .= '"'.implode('","', $row).'"'.PHP_EOL;
        }

        return $output;
    }
}