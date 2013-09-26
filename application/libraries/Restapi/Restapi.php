<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
* Restapi (ci-rest-api)
* CodeIgniter REST API Library
*
* A REST library that implements REST Resources for CodeIgniter Controllers.
* It provides RestResource for regular resources and RestModelResource for Adding API interface to
* your models. Currently DataMapper Models are supported.
*
* @package Restapi
* @license MIT License https://github.com/mohabusama/ci-rest-api/blob/master/LICENSE
* @link https://github.com/mohabusama/ci-rest-api
* @author Mohab Usama
* @version 0.1.0
*/

define("REQUEST_GET", 'get');
define("REQUEST_POST", 'post');
define("REQUEST_PUT", 'put');
define("REQUEST_DELETE", 'delete');

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


require_once APPPATH . 'libraries/Restapi/Restmodel.php';

/**
 * RestResource Class
 * 
 * The Basic Resource Class. This class should replace CI_Controller in your controller. Once you
 * extend it by your controller, it will provide you with basic API functionality.
 * Supported HTTP Methods: GET, POST, PUT, DELETE
 * 
 * Most of this class methods can be overriden to give the developer full control over the whole
 * process.
 * 
 * A REST API call Life cycle goes as follows:
 * - __construct method initializes the class with Request and Response objects
 * - handle_request method is the entry point. It checks Allowed methods, Authentication,
 *   Authorization and Validation. It responds automatically according to developers overriden class
 *   properties
 * - get_response() method dispatches a call to the method that will handle the request.
 *   The method called is dependent on the HTTP Method detected in the Request object.
 * - rest_get(), rest_post(), rest_put and rest_delete() is where the action is done. Those methods 
 *   should be overriden by the Developer to return the result of the API call.
 *   model_create(), model_get(), model_update() and model_delete() methods can be overriden as well
 *   as they can help in organizing the code.
 * - send_response() method is repsonsible for encapsulating the API call result, and sending it
 *   back to the client.
 *  
 * @package Restapi
 */
class RestResource extends CI_Controller
{

    /**
     * Array of allowed HTTP Methods to be supported by this Resource.
     * If an HTTP Request is made with a not allowed method, RestResource will immidiately respond
     * with METHOD_NOT_ALLOWED 405
     * 
     * @var array
     */
    protected $allowed_methods = array(REQUEST_GET, REQUEST_POST, REQUEST_PUT, REQUEST_DELETE);

    /**
     * Array of allowed Formats that this resouce should support.
     * Default is json
     * 
     * @var array
     */
    protected $api_format = array('json', 'xml');

    /**
     * Add one -or- multiple authentications methods. One successful authentication is enough!
     * Authentication method should be implemented in Resource Class, and should be added as string.
     * Authentication method should return either TRUE or FALSE. TRUE means the request will
     * continue to be processed, FALSE means Authentication failed.
     * 
     * If All Authentication methods failed, then RestResource will immidiately exit with 401 Error.
     * Check @method _authenticate()
     * 
     * @example protected $authentication = array('authenticate_ldap', 'authenticate_session');
     * @var array
     */
    protected $authentication = array();

    /**
     * Add one -or- multiple authorization methods. All Authorization methods should succeed in
     * order for the request to be processed.
     * Authorization method should return either TRUE or FALSE. TRUE means the request will
     * continue to be processed, FALSE means Authorization failed.
     * 
     * If One Authorization method failed, then RestResource will immidiately exit with 401 Error.
     * Check @method _authorize()
     * 
     * @example protected $authorization = array('authorize_owner', 'authorize_action');
     * @var array
     */
    protected $authorization = array();

    /**
     * Add Validation method.
     * 
     * Validation method should return either TRUE or FALSE. TRUE means the request will
     * continue to be processed, FALSE means Validation failed.
     * 
     * If Validation method failed, then RestResource will immidiately exit with BAD REQUEST 400.
     * Check @method _validate()
     * 
     * @example protected $validation = 'validate_input_data';
     * @var string
     */
    protected $validation = NULL;

    /**
     * Resource fields which will be Automatically stripped out of All response results.
     * 
     * If we consider a Resource with fields ['name', 'email', 'password', 'secret'] and we wish
     * to return the resource while automatically excluding the 'password' and 'secret' fields, then
     * we can just add them to the $excluded_fields array and RestResource will exclude them from
     * any response.
     * @example $excluded_fields = array('password', 'secret');
     * @var array
     */
    protected $excluded_fields = array();

    /**
     * Black list of Protected fields in POST Requests - Existing fields will be Removed from
     * Request Data.
     * Note: That means, the request is still considered valid. If you would like to change
     * this behavior then you can simply Override filter_input_fields()
     * 
     * Sometimes you need to protect some fields during a POST request and prevent the Client from
     * setting their values. Add the fields to the $protected_post_fields and they will be
     * automatically removed from Request payload.
     * @example $protected_post_fields = array('id', 'secret');
     * @var array
     */
    protected $protected_post_fields = array();

    /**
     * Black list of Protected fields in PUT Requests - Existing fields will be Removed from
     * Request Data.
     * Note: That means, the request is still considered valid. If you would like to change
     * this behavior then you can simply Override filter_input_fields()
     * 
     * Sometimes you need to protect some fields during a PUT request and prevent the Client from
     * setting their values. Add the fields to the $protected_put_fields and they will be
     * automatically removed from Request payload.
     * @example $protected_put_fields = array('secret');
     * @var array
     */
    protected $protected_put_fields = array();

    /**
     * Resource META Data
     * 
     * META Data is extra key that can be <i>optionaly</i> added to the Response.
     * It can hold some extra information. For example @link RestModelResource adds the following
     * meta fields:
     * - total: Total number of Objects in DB
     * - count: Count of objects returned in this response
     * - limit: The maximum limit that can be queried
     * - offset: The last offset which returned response data
     * 
     * The developer can add whatever meta data required by overriding get_meta() method.
     * 
     * Adding meta data is optional.
     * 
     * @example JSON Response including meta data can look like:
     * {
     *   "result": [{"name": "jon"}, {"name": "doe"}],
     *   "meta": {"count": 2, "total": 50, "limit": 2, "offset": 0}
     * }
     * @var bool
     */
    protected $add_meta = TRUE;

    /**
     * META Data Key Name
     * Dafault is "meta"
     * 
     * @var string
     */
    protected $meta_name = "meta";

    /**
     * Add META Data timestamp of the response
     * 
     * @var bool
     */
    protected $meta_timestamp = FALSE;

    /**
     * Array of default response code for every Request Method. This array will be checked when
     * sending back the Response after a successful API call. Can be overriden to change behavior.
     * 
     * @var array
     */
    protected $default_response_code = array(
        REQUEST_GET => HTTP_RESPONSE_OK,
        REQUEST_POST => HTTP_RESPONSE_CREATED,
        REQUEST_PUT => HTTP_RESPONSE_OK,
        REQUEST_DELETE => HTTP_RESPONSE_NO_CONTENT
    );

    /**
     * A Private array holding the Response result.
     * 
     * @var array
     */
    private $_result = array();

    /**
     * Request object encaplsulating all Request Data
     * 
     * @var Request
     */
    public $request;

    /**
     * Response object encaplsulating all Response Data and HTTP exit methods.
     * 
     * @var Response
     */
    public $response;


    /**
     * RestResource Constructor.
     * Creates Request and Response objects, and calls handle_request()
    */
    public function __construct()
    {
        parent::__construct();

        $default_format = count($this->api_format) ? $this->api_format[0] : 'json';
        $this->request = new Request($this, $default_format);

        $this->response = new Response($this, $default_format);

        // Start handling the request. This is our entry point to real action!
        $this->handle_request();
    }

    /**
     * Our Main Entry point.
     * Checks Allowed Methods, Authenticates, Authorizes, Validates, Gets the response and send it
     * to our client.
    */
    protected function handle_request()
    {
        // Check if request is accepted
        $this->_check_allowed();

        // Authenticate
        $this->_authenticate();

        // Then Authorize
        $this->_authorize();

        // Validate?!
        $this->_validate();

        // We are Good to Go ...
        $res = $this->get_response();

        // And we send response to our Client ...
        $this->send_response($res);
    }

    /**
     * Retrieves the response output result.
     * It is responsible for calling one rest_get, rest_post, rest_put and rest_delete depending
     * on the detected method in request.
     * 
     * @return mixed Returns the Response output (body)
    */
    protected function get_response()
    {
        $output = NULL;

        if ($this->request->method)
        {
            $output = $this->{'rest_' . $this->request->method}();
        } else {
            $output = $this->rest_get();
        }

        return $output;
    }

    /**
     * Send response back to client.
     * 
     * This our end-point, where we set response http status, prepare the result, add meta data if
     * required and Exit with the proper Response.
    */
    protected function send_response($output)
    {
        $this->response->status = $this->get_default_status();

        $this->_result['result'] = $output;

        // Add META data to response if required!
        if($this->add_meta)
        {
            $meta = $this->get_meta($output);
            if($meta)
            {
                $this->_result[$this->meta_name] = $meta;
            }
        }

        // Resource is Done ...
        $this->response->http_exit($this->_result);
    }

    /**
     * Returns the HTTP status code for our request depending on Request Method and
     * $default_response_code.
     * 
     * @return string HTTP Status Code
    */
    protected function get_default_status()
    {
        return (array_key_exists($this->request->method, $this->default_response_code)) ? 
            $this->default_response_code[$this->request->method] : HTTP_RESPONSE_OK;
    }

    /**
     * Filters the input data fields based on Request Method and $protected_post_fields and
     * $protected_put_fields.
     * 
     * It actually uses @method filter_data() in Request object. So, Input data will be filtered
     * and all protected fields will be removed.
     * 
     * Note: The existence of protected fields in the Request Input data doesn't mean the request
     * will fail, it will be just removed from the input data (i.e. ignored!)
    */
    protected function filter_input_fields()
    {
        if ($this->request->method == REQUEST_POST)
        {
            $this->request->filter_data($this->protected_post_fields);
        }
        elseif ($this->request->method == REQUEST_PUT)
        {
            $this->request->filter_data($this->protected_put_fields);
        }
    }

    /**
     * Filters the output data fields based on $excluded_fields
     * 
     * @example 
     * if $output = array("name" => "Jon", "secret" => "1234")
     * and $excluded_fields = array('secret')
     * then 'secret' field should be excluded
     * hence, the returned $output will be array("name" => "Jon")
     * 
     * @param mixed $output Output data to be filtered
     * 
     * @return mixed Output Data being after filtering
    */
    protected function filter_output_fields($output)
    {
        if (is_array($output))
        {
            if (is_assoc($output))
            {
                // This is the object details
                foreach ($this->excluded_fields as $field)
                {
                    if (array_key_exists($field, $output))
                    {
                        unset($output[$field]);
                    }
                }
                return $output;
            }
            else
            {
                // This is list of objects
                $filtered_list = array();
                foreach ($output as $obj)
                {
                    $filtered_list[] = $this->filter_output_fields($obj);
                }
                return $filtered_list;
            }
        }

        return $output;
    }

    /**
     * Returns request Input data.
     * 
     * @return mixed XSS cleaned Input data.
    */
    protected function get_data()
    {
        return $this->request->data();
    }

    /**
     * Returns XSS clean input data.
     * 
     * This method is made to be overriden in case Developer needs to process data before they are
     * used by rest_ methods.
     *  
     * @return mixed Output Data being after filtering
    */
    protected function process_input_data()
    {
        // Load Data - XSS Clean!
        return $this->get_data();
    }

    /**
     * Process output data before being sent. Basic implementation filters the output data.
     *  
     * @param mixed $data Output data before filtering
     * 
     * @return mixed Returns filtered Output Data
    */
    protected function process_output_data($data)
    {
        return $this->filter_output_fields($data);
    }
    
    /**
     * REST Handler for GET Requests. Returns the response Data.
     *  
     * Note: Basic implementation returns NULL. Needs to be overriden by Developer.
     * 
     * @return mixed Returns Response data
    */
    protected function rest_get()
    {
        // Should be Implemented in Resource
        return NULL;
    }

    /**
     * REST Handler for POST Requests. Returns the response Data.
     *  
     * Note: Basic implementation returns NULL. Needs to be overriden by Developer.
     * 
     * @return mixed Returns Response data
    */
    protected function rest_post()
    {
        // Should be Implemented in Resource
        return NULL;
    }

    /**
     * REST Handler for PUT Requests. Returns the response Data.
     *  
     * Note: Basic implementation returns NULL. Needs to be overriden by Developer.
     * 
     * @return mixed Returns Response data
    */
    protected function rest_put()
    {
        // Should be Implemented in Resource
        return NULL;
    }

    /**
     * REST Handler for DELETE Requests. Returns the response Data.
     *  
     * Note: Basic implementation returns NULL. Needs to be overriden by Developer.
     * 
     * @return mixed Returns Response data
    */
    protected function rest_delete()
    {
        // Should be Implemented in Resource
        return NULL;
    }

    /**
     * Method responsible for New Model (Object/Resource) Creation.
     * Mainly called from @method rest_post.
     *  
     * Notes:
     * Basic implementation returns NULL. Needs to be overriden by Developer.
     * This method exists for the sake of Separation Of Concerns. Overriding it is optional for
     * the developer.
     * 
     * @param mixed $data Sufficient Input data for creating the new Object/Resource
     * 
     * @return mixed Returns representation of newely created Object/Resource (pref. array())
    */
    protected function model_create($data)
    {
        // Should be Implemented in Resource
        return NULL;
    }

    /**
     * Method responsible for Model (Object/Resource) Retrieval.
     * Mainly called from @method rest_get.
     *  
     * Notes:
     * Basic implementation returns NULL. Needs to be overriden by Developer.
     * This method exists for the sake of Separation Of Concerns. Overriding it is optional for
     * the developer.
     * 
     * @param mixed $id Expected to be a unique ID for the requested Object
     * 
     * @return mixed Returns representation of retrieved Object/Resource (pref. array())
    */
    protected function model_get($id)
    {
        // Should be Implemented in Resource
        return NULL;
    }

    /**
     * Method responsible for Model (Object/Resource) Update.
     * Mainly called from @method rest_put.
     *  
     * Notes:
     * Basic implementation returns NULL. Needs to be overriden by Developer.
     * This method exists for the sake of Separation Of Concerns. Overriding it is optional for
     * the developer.
     * 
     * @param mixed $id Expected to be a unique ID for the requested Object to be updated
     * @param mixed $data Sufficient Input data for updating the Object/Resource
     * 
     * @return mixed Returns representation of updated Object/Resource (pref. array())
    */
    protected function model_update($id, $data)
    {
        // Should be Implemented in Resource
        return NULL;
    }

    /**
     * Method responsible for Model (Object/Resource) Deletion.
     * Mainly called from @method rest_delete.
     *  
     * Notes:
     * Basic implementation returns NULL. Needs to be overriden by Developer.
     * This method exists for the sake of Separation Of Concerns. Overriding it is optional for
     * the developer.
     * 
     * @param mixed $id Expected to be a unique ID for the requested Object to be deleted
     * 
     * @return null Returns NULL
    */
    protected function model_delete($id)
    {
        // Should be Implemented in Resource
        return NULL;
    }

    /**
     * Constructs the initail meta data array, if required.
     *  
     * Notes: Override to add extra key/values in meta array.
     * 
     * @example array('timestamp' => 123456789)
     * 
     * @return array Returns Meta Data array
    */
    protected function get_meta()
    {
        $meta = array();
        if($this->meta_timestamp)
        {
            $meta['timestamp'] = time();
        }

        return $meta;
    }

    /**
     * Check if the Request Method is allowed for this Resource.
     * 
     * If Request Method is not allowed, it immidiately exits with 405 METHOD NOT ALLOWED error.
     * 
     * @return void
    */
    private function _check_allowed()
    {
        if (in_array($this->request->method, $this->allowed_methods))
        {
            if(in_array($this->request->format, $this->api_format) &&
                in_array($this->response->format, $this->api_format))
            {
                return;
            }
        }

        //Exit with Immediate "Method Not Allowed" response!
        $this->response->http_405($this->allowed_methods);
    }

    /**
     * Checks if the Request is Authenticated.
     * 
     * Calls methods in @link $authentication
     * If all Authentication methods failed, it immidiately exits with 401 UNAUTHORIZED error.
     * 
     * @return void
    */
    private function _authenticate()
    {
        /*By default, All requests authenticated unless we have authentication methods!*/
        $authenticated = (count($this->authentication)) ? FALSE : TRUE;

        foreach ($this->authentication as $_authenticate) {
            if(is_callable(array($this, $_authenticate)) &&
                call_user_func(array($this, $_authenticate)))
            {
                // One successful Authentication is sufficient!
                return;
            }
        }

        if($authenticated)
        {
            return;
        }
    
        // No Luck for Authentication. Exit with 401!
        $this->response->http_401("Unauthorized");
    }

    /**
     * Checks if the Request is Authorized.
     * 
     * Calls methods in @link $authorization
     * If One Authorization methods failed, it immidiately exits with 401 UNAUTHORIZED error.
     * 
     * @return void
    */
    private function _authorize()
    {
        /*By default, All requests authorized unless we have authorization methods!*/
        $authorized = TRUE;

        foreach ($this->authorization as $_authorize) {
            if(is_callable(array($this, $_authorize)) &&
                ! call_user_func(array($this, $_authorize)))
            {
                $authorized = FALSE;
                break;
            }
        }

        if($authorized)
        {
            return TRUE;
        }
    
        /*TODO: Exit with Immediate "401" response!*/
        $this->response->http_401("Unauthorized");
    }

    /**
     * Checks if the Request is Valid.
     * 
     * Calls method in @link $validate
     * If methods returned FALSE, it immidiately exits with 400 BAD REQUEST error.
     * 
     * @return void
    */
    private function _validate()
    {
        // First, filter our Input Data
        $this->filter_input_fields();

        // Then, call custom Validation Method if exists!
        if($this->validation && is_callable(array($this, $this->validation)))
        {
            if(call_user_func(array($this, $this->validation)))
            {
                return TRUE;
            }

            /*TODO: Exit with Immediate "400" response!*/
            // TODO: Add Validation Error message!
            $this->response->http_400("Bad Request");
        }
        return TRUE;
    }
}

class RestModelResource extends RestResource
{
    /*The Model represented by this reource*/
    protected $model_class = '';

    protected $limit = 50;
    protected $limit_arg_name = 'limit';

    protected $offset = 0;
    protected $offset_arg_name = 'offset';

    // The index of the object ID in the URI. Default is -1 (which means the last element in URI)
    protected $object_id_uri_index = -1;

    // The RestModel Loaded Object!
    private $obj = NULL;

    private $add_model_meta = FALSE;


    // OVERRIDDEN PROPERTIES //

    // By default, caller cannot supply ID for creating new object!
    protected $protected_post_fields = array('id');

    public function __construct()
    {
        parent::__construct();        
    }

    protected function handle_request()
    {
        // Instantiate our Object, doing our Model Specific thingie
        $this->obj = $this->get_object_instance();
        if(! $this->obj)
        {
            $this->response->http_500('Failed to Instantiate object!');
        }

        // Limit and Offset can be useful in pagination
        $this->limit = $this->get_limit();
        $this->offset = $this->get_offset();

        // Call Default Handler
        parent::handle_request();
    }

    private function get_object_instance()
    {
        if (! $this->model_class)
        {
            return NULL;
        }

        return new RestModel($this->model_class);
    }

    protected function get_object_id()
    {
        // Default, is the last URI Arg (e.g. /users/1/ then 1 is the id, as users is index 0)
        // keep it XSS clean, as there will be DB operation here!
        $id = $this->request->uri($this->object_id_uri_index, TRUE);
        if ($id === 'index')
        {
            $id = NULL;
        }
        return $id;
    }

    protected function get_limit()
    {
        $limit = $this->request->args($this->limit_arg_name);
        return ($limit !== FALSE) ? intval($limit) : $this->limit;

    }

    protected function get_offset()
    {
        $offset = $this->request->args($this->offset_arg_name);
        return ($offset !== FALSE) ? intval($offset) : $this->offset;
    }

    // META
    protected function get_meta()
    {
        $meta = parent::get_meta();

        if ($this->add_model_meta)
        {
            $meta['total'] = $this->obj->count();
            $meta['count'] = $this->obj->result_count();
            $meta['limit'] = $this->limit;
            $meta['offset'] = $this->offset;
        }

        return $meta;
    }

    // POST
    protected function rest_post()
    {
        // Load Data
        $data = $this->process_input_data();

        // Create the new object and save it!
        $res = $this->model_create($data);

        // Well, it seems we made it ...
        return $this->process_output_data($res);
    }

    protected function model_create($data)
    {
        // Load Object with Data
        $this->obj->load($data);

        // Validate Loaded Data
        if (! $this->obj->validate())
        {
            $error = $this->obj->error();
            $this->response->http_400($error);
        }

        // Save new Object
        if (! $this->obj->save())
        {
            $error = $this->obj->error();
            $this->response->http_500($error);
        }

        return $this->obj->to_array();
    }

    // GET
    protected function rest_get()
    {
        // Check if there is an `id`. i.e. retrieving specific object
        $id = $this->get_object_id();
        if ($id !== NULL)
        {
            $res = $this->model_get($id);
        }
        else
        {
            // Add Meta to result
            $this->add_model_meta = TRUE;
            $res = $this->model_get_all();
        }

        return $this->process_output_data($res);
    }

    protected function model_get($id)
    {
        // Get the specified object
        if (! $this->obj->get($id))
        {
            // 404 NOT FOUND
            $this->response->http_404('Error 404. Not Found');
        }

        return $this->obj->to_array();
    }

    protected function model_get_all()
    {
        // Get the specified object
        if (! $this->obj->get_all($this->limit, $this->offset))
        {
            // 404 NOT FOUND
            $error = $this->obj->error();
            $this->response->http_404($error);
        }

        return $this->obj->to_array();
    }

    // PUT
    protected function rest_put()
    {
        // Check if there is an `id`. i.e. retrieving specific object
        $id = $this->get_object_id();
        if ($id === NULL)
        {
            // ID is required for PUT operation - 404
            $this->response->http_404();
        }

        // Load Data
        $data = $this->process_input_data();

        $res = $this->model_update($id);

        return $this->process_output_data($res);
    }

    protected function model_update($id, $data)
    {
        // Get the specified object
        if (! $this->obj->exists($id))
        {
            // 404 NOT FOUND
            $this->response->http_404('Error 404. Not Found');
        }

        // Load Object with Data + ID
        $this->obj->load($data, $id);

        // Validate Loaded Data
        if (! $this->obj->validate())
        {
            $error = $this->obj->error();
            $this->response->http_400($error);
        }

        // Save new Object
        if (! $this->obj->update())
        {
            $error = $this->obj->error();
            $this->response->http_500($error);
        }

        return $this->obj->to_array();
    }

    // DELETE
    protected function rest_delete()
    {
        // Check if there is an `id`. i.e. retrieving specific object
        $id = $this->get_object_id();
        if ($id === NULL)
        {
            // ID is required for DELETE operation - 404
            $this->response->http_404('Error 404. Not Found');
        }

        $this->model_delete($id);

        // DELETE returns No Content - 204
        return NULL;
    }

    protected function model_delete($id)
    {
        // Get the specified object
        if (! $this->obj->get($id))
        {
            // 404 NOT FOUND
            $this->response->http_404('Error 404. Not Found');
        }

        $this->obj->delete();
    }
}

class Request
{
    // Better access data items using methods, to force XSS security!
    private $_data = array();

    private $_args = array();

    private $_uri = array();

    private $security = NULL;

    public $method = REQUEST_GET;

    public $ssl = FALSE;

    public $format = NULL;

    public $header = array();

    public function __construct($resource, $default_format='json')
    {
        $this->method = strtolower($resource->input->server('REQUEST_METHOD'));

        // URL args
        $args = $resource->input->get();
        $this->_args = $args ? $args : array();

        $this->_uri = $resource->uri->rsegment_array();

        $this->header = $resource->input->request_headers();

        $input_format = RestFormat::get_input_format($this->header);
        $this->format = $input_format ? $input_format : $default_format;

        $this->security = $resource->security;

        $this->load_input_data($resource->input);
    }

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
            //In case of POST, PUT or DELETE
            if ($this->format)
            {
                $body = file_get_contents('php://input');
                $this->_data = RestFormat::decode_input_data($body, $this->format);
            }
            elseif($this->method == REQUEST_POST)
            {
                //Regular Form POST (i.e. application/x-www-form-urlencoded)
                $post_data = $input->post();
                $this->_data = $post_data ? $post_data : array();
            }
        }
    }

    public function args($key=NULL, $xss_clean=FALSE)
    {
        if($key === NULL)
        {
            return $this->_args;
        }

        if(array_key_exists($key, $this->_args))
        {
            $value = $this->_args[$key];
            return $xss_clean ? $this->security->xss_clean($value) : $value;
        }
        
        return FALSE;
    }

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

    public function filter_data($filter)
    {
        foreach ($this->_data as $key => $value)
        {
            if (in_array($key, $filter))
            {
                unset($this->_data[$key]);
            }
        }
    }
}

class Response
{
    private $_header = array();

    public $format = 'json';

    public $body = '';

    public $status = HTTP_RESPONSE_OK;

    public function __construct($resource, $default_format='json')
    {
        $output_format = RestFormat::get_output_format($resource->request->args());
        $this->format = $output_format ? $output_format : $default_format;
    }

    public function set_header($value)
    {
        $this->_header[] = $value;
    }

    public function http_200($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_OK);
    }

    public function http_201($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_CREATED);
    }

    public function http_204($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_NO_CONTENT);
    }

    public function http_400($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_BAD_REQUEST);
    }

    public function http_401($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_UNAUTHORIZED);
    }

    public function http_403($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_FORBIDDEN);
    }

    public function http_404($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_NOT_FOUND);
    }

    public function http_405($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_NOT_ALLOWED);
    }

    public function http_500($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_INTERNAL_ERROR);
    }

    public function http_501($output="")
    {
        $this->http_exit($output, HTTP_RESPONSE_NOT_IMPLEMENTED);
    }

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
        foreach ($this->_header as $value) {
            header($value);
        }

        // TODO: Add option for Expiration & No cache headers! Might be a Security Req.

        // Format o/p data as desired!
        $this->body = RestFormat::encode_output_data($output, $this->format);

        // ... And GO!
        exit($this->body);
    }
}

class RestFormat
{
    private static $mime_types = array(
        'text/html' => 'html',
        'application/json' => 'json',
        'application/xml' => 'xml',
        'application/csv' => 'csv'
    );

    public static function get_input_format($header)
    {
        if (array_key_exists('Content-Type', $header))
        {
            $input_mime = explode(';', $header['Content-Type']);

            if(array_key_exists($input_mime[0], self::$mime_types))
            {
                // That should return 'json' or 'xml' etc...
                return self::$mime_types[$input_mime[0]];
            }
        }

        // Unknown/Unsupported mime/format!
        return NULL;
    }

    public static function get_output_format($args)
    {
        if(isset($args['format']))
        {
            foreach (self::$mime_types as $key => $value) {
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
        foreach (self::$mime_types as $mime => $value) {
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
        return self::_to_array(json_decode($data));
    }

    private function _encode_json($data)
    {
        return json_encode($data);
    }
}

// Helper Method
function is_assoc($array)
{
    return (array_values($array) !== $array);
}