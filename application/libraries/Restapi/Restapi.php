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

require_once APPPATH . 'libraries/Restapi/Http.php';
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
 *   as they can help in organizing the code and Separation of Concerns.
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
    protected $allowed_methods = array(REQUEST_GET, REQUEST_POST, REQUEST_PUT, REQUEST_DELETE,
        REQUEST_HEAD, REQUEST_PATCH);

    /**
     * Array of allowed HTTP Methods to be supported by this Resource to establish Array Operations.
     * Array operations are assumed to be established when @method get_object_id returns NULL.
     * 
     * If an HTTP Request is made with a not allowed method, RestResource will immidiately respond
     * with METHOD_NOT_ALLOWED 405
     * 
     * Array operations include, @method model_get_all, @method model_create_all,
     * @method model_update_all and @method model_delete_all
     * 
     * @var array
     */
    protected $allowed_array_methods = array(
        REQUEST_GET, REQUEST_POST, REQUEST_PUT, REQUEST_DELETE
    );


    /**
     * Array of allowed Formats that this resouce should support.
     * Default is json
     * 
     * @var array
     */
    protected $api_format = array('json');

    /**
     * Number of Results per API call
     * This mainly applies when retrieving multiple objects (using a GET request) and is useful for
     * paging when combined with @link $offset variable
     * 
     * Default is 50 results.
     * 
     * @var int
     */
    protected $limit = 50;

    /**
     * Limit arg name passed as URL arg
     * 
     * @example This is a GET API call that sets the Limit result to 100
     * http://yourhost/users?limit=100
     * @var string
     */
    protected $limit_arg_name = 'limit';

    /**
     * Starting offset of the objects ti be retrieved
     * This mainly applies when retrieving multiple objects (using a GET request) and is useful for
     * paging when combined with @link $limit variable
     * 
     * Default is 0 (i.e. start with first object in DB)
     * 
     * @var int
     */
    protected $offset = 0;

    /**
     * Offset arg name passed as URL arg
     * 
     * @example This is a GET API call that sets the Offset set to 100 and limit to 100
     * http://yourhost/users?limit=100&offset=100
     * 
     * @var string
     */
    protected $offset_arg_name = 'offset';

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
     * Name of the Resource URI field.
     * The Resource URI identifies this resource so that it can be used afterwards directly without
     * the need of clients to build the URI themselves. This field will be added to every object.
     * The field value is built using @method get_resource_uri
     * 
     * Note: If it is set to empty string, it will be ignored.
     * 
     * Default: 'uri'
     * 
     * @example 
     * $resource_uri_field_name = 'href';
     * $resource_uri_field_name = '_uri';
     * 
     * @var string
     */
    protected $resource_uri_field_name = "uri";

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

        $this->limit = $this->get_limit();
        $this->offset = $this->get_offset();

        // Load all necessary libs
        $this->lib_loader();

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
        if ($this->response->format == 'csv')
        {
            // Special case for CSV format, No Result and Meta keys!
            $this->_result = $output;
            $this->add_meta = FALSE;
        }

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
     * Returns request Input data.
     * 
     * @return mixed XSS cleaned Input data.
    */
    protected function get_data()
    {
        return $this->request->data();
    }

    /**
     * Gets the @link $limit value from URL args
     * 
     * @return int
     */
    protected function get_limit()
    {
        $limit = $this->request->args($this->limit_arg_name);
        return ($limit !== FALSE) ? intval($limit) : $this->limit;
    }

    /**
     * Gets the @link $offset value from URL args
     * 
     * @return int
     */
    protected function get_offset()
    {
        $offset = $this->request->args($this->offset_arg_name);
        return ($offset !== FALSE) ? intval($offset) : $this->offset;
    }

    /**
     * Get object ID
     * 
     * @return int|string|null
     */
    protected function get_object_id()
    {
        return NULL;
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
        if (is_assoc($output))
        {
            // This is the object details

            // Add Resource URI if needed!
            // We do this before filtering to make sure all fields that might be needed in building
            // the resource URI to be existing before excluding them (e.g. $output['id'])
            if ($this->resource_uri_field_name)
            {
                $uri = $this->get_resource_uri($output);
                if ($uri !== NULL)
                {
                    $output[$this->resource_uri_field_name] = $uri;
                }
            }

            // Do the filtering.
            foreach ($this->excluded_fields as $field)
            {
                if (array_key_exists($field, $output))
                {
                    unset($output[$field]);
                }
            }
        }

        return $output;
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
        if (is_array($data))
        {
            if (is_assoc($data))
            {
                $filtered = $this->filter_output_fields($data);
                return $this->process_output_object($filtered);
            }
            else
            {
                // This is list of objects
                $filtered_list = array();
                foreach ($data as $obj)
                {
                    $filtered = $this->filter_output_fields($obj);
                    $filtered_list[] = $this->process_output_object($filtered);
                }
                return $filtered_list;
            }
        }

        return $data;
    }

    /**
     * Process output object.
     * This method should be called for result object, and if the result is array, it will be called
     * for each object in the result array.
     *  
     * @param array $obj Output object.
     * 
     * @return array Returns processed output object
    */
    protected function process_output_object($obj)
    {
        return $obj;
    }

    /**
     * Adds resource URI field to the object.
     * This method is not implemented, and must be overriden by developer if needed.
     * 
     * @param array $obj Output object.
     * 
     * @return string Returns resource URI string
    */
    protected function get_resource_uri($obj)
    {
        return NULL;
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
     * REST Handler for HEAD Requests. Returns the Headers only, with No Data.
     * It is mainly used to check if a Resource exist without retrieving the object. Cheaper than
     * GET request specialy with Large resources.
     *  
     * Note: Basic implementation returns NULL. Needs to be overriden by Developer.
     * 
     * @return mixed Returns Response data
    */
    protected function rest_head()
    {
        // Should be Implemented in Resource
        return NULL;
    }

    /**
     * REST Handler for PATCH Requests. It is similar to PUT, but for Partial Updates.
     *  
     * Note: Basic implementation returns NULL. Needs to be overriden by Developer.
     * 
     * @return mixed Returns Response data
    */
    protected function rest_patch()
    {
        // Should be Implemented in Resource
        return NULL;
    }

    /**
     * Method responsible for Creating New Model (Object/Resource).
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
     * Method responsible for Creating New Multiple Models (Object/Resource).
     * Mainly called from @method rest_post.
     *  
     * Notes:
     * Basic implementation returns NULL. Needs to be overriden by Developer.
     * This method exists for the sake of Separation Of Concerns. Overriding it is optional for
     * the developer.
     * 
     * @param array $data Array of Sufficient Input data for creating the new Objects/Resources
     * 
     * @return array Returns Array of representation of newely created Objects/Resources
    */
    protected function model_create_all($data)
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
     * @param array $where Array of Key/Value pairs used as Where condition.
     * 
     * @return mixed Returns representation of retrieved Object/Resource (pref. array())
    */
    protected function model_get($id, $where=NULL)
    {
        // Should be Implemented in Resource
        return NULL;
    }

    /**
     * Method responsible for Multiple Model (Object/Resource) Retrieval.
     * Mainly called from @method rest_get.
     * Retrieves array of objects based on @link $offset and @link $limit
     * 
     * @param array $where Array of Key/Value pairs used as Where condition.
     * 
     * @example $this->model_get_all(array('owner' => 5));
     * 
     * @return array Representation of the retrieved objects
     */
    protected function model_get_all($where=NULL)
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
     * Method responsible for Multiple Model (Objects/Resources) Update.
     * Mainly called from @method rest_put.
     *  
     * Notes:
     * Basic implementation returns NULL. Needs to be overriden by Developer.
     * This method exists for the sake of Separation Of Concerns. Overriding it is optional for
     * the developer.
     * 
     * @param mixed $data Sufficient Input data for updating the Object/Resource
     * @param array $where Associative Array  used as Where condition.
     * 
     * @example $this->model_update_all($data, array('owner' => 5));
     * 
     * @return mixed Returns representation of updated Object/Resource (pref. array())
    */
    protected function model_update_all($data, $where=NULL)
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
     * Method responsible for Multiple Model (Object/Resource) Deletion.
     * Mainly called from @method rest_delete.
     *  
     * Notes:
     * Basic implementation returns NULL. Needs to be overriden by Developer.
     * This method exists for the sake of Separation Of Concerns. Overriding it is optional for
     * the developer.
     * 
     * =============================================================================================
     * Important: This is Very Dangerous to be implemented. Implement with Care!
     * =============================================================================================
     * 
     * @param array $where Associative Array used as Where condition.
     * 
     * @example $this->model_delete_all(array('owner' => 5));
     * 
     * @return null Returns NULL
    */
    protected function model_delete_all($where)
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
     * Override to load any necessary CodeIgniter libraries before starting handling the request.
     * 
     * @example $this->load->library('session');
     * 
     * @return null
    */
    protected function lib_loader()
    {
        return NULL;
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
        $id = $this->get_object_id();

        if (!in_array($this->request->method, $this->allowed_methods))
        {
            $this->response->http_405($this->allowed_methods);
        }

        if ($id !== NULL || in_array($this->request->method, $this->allowed_array_methods))
        {
            if(in_array($this->request->format, $this->api_format) &&
                in_array($this->response->format, $this->api_format))
            {
                return;
            }
        }

        // Exit with Immediate "Method Not Allowed" response!
        $this->response->http_405($this->allowed_array_methods);
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

            // TODO: Add Validation Error message!
            $this->response->http_400("Bad Request");
        }
        return TRUE;
    }
}

/**
 * RestModelResource Class
 * 
 * This class extends @link RestResource with slight modifications (Overrides!). The purpose of this
 * class is to Expose your Model via REST API.
 * Supported HTTP Methods: GET, POST, PUT, DELETE
 * Supported Model Classes: DataMapper
 * 
 * Most of this class methods can be overriden to give the developer full control over the whole
 * process.
 * 
 * A REST API call Life cycle goes as follows:
 * - __construct method initializes the class with Request and Response objects
 * - handle_request method is overriden with a step of Model Wrraper RestModel Initialization. 
 *   It uses method get_object_instance() to retrieve the Model Wrapper Object to work with.
 * - rest_get(), rest_post(), rest_put and rest_delete() methods are responsible for preparing the
 *   next model operation. A call to @method get_object_id() is done to retrieve the ID of the 
 *   requested object *if exists!*
 * - @method get_object_id() can be overriden by Developer to return the ID of the object. Default
 *   behavior described with the method implementation below.
 *   model_create(), model_get(), model_get_all(), model_update() and model_delete() methods 
 *   are implemented to apply default Model operations.
 *  
 * @package Restapi
 */
class RestModelResource extends RestResource
{
    /**
     * Name of Model
     * This property is overriden with the name of the Model which will be exposed as REST API by 
     * RestModelResource.
     * 
     * @example protected $model_class = 'User';
     * @var string
     */
    protected $model_class = '';

    /**
     * The Field name of the Object ID.
     * 
     * The object will be searched/updated/deleted based on that field name.
     * Default is 'id'
     * 
     * Note: This property is added in case the developer doesn't want to expose the DB ID of
     * objects in the URI, instead, this property will expose another unique property for the model
     * (e.g. uuid, guid etc ...)
     * 
     * @example protected $model_id_field_name = 'guid';
     * The resource URI will look like:
     * /resource/343df1279c3c700fb3a2a996b6191e0d
     * instead of exposing the ID in DB
     * /resource/4
     * 
     * @var string
     */
    protected $model_id_field_name = 'id';

    /**
     * The index of the object ID in the URI.
     * Default is -1 (which means the last element in URI)
     * 
     * Important Note: This is the basic behavior. It might not suit every case. In case this 
     * is not the desired way of retrieving the ID from URI, please refer to @method get_object_id()
     * 
     * @example protected $object_id_uri_index = -1;
     * http://yourhost/users/               object ID = NULL, no object ID specified/detected
     * http://yourhost/users/1              object ID = 1
     * http://yourhost/users/200            object ID = 200
     * http://yourhost/users/2/blogs/10/    object ID = 10
     * 
     * @var int
     */
    protected $object_id_uri_index = -1;

    /**
     * The Loaded RestModel Object!
     * This objects acts as a Wrapper for all Model operations.
     * 
     * @var RestModel
     */
    protected $obj = NULL;

    /**
     * Flag for whether to add meta data or not.
     * This is usually set to false if it is a GET operation.
     * 
     * Meta Data added:
     * - count: count of objects returned in result
     * - total: total number of objects in DB
     * - limit: limit value applied to this GET operation
     * - offset: offset value applied to this GET operation
     * 
     * @var bool
     */
    protected $add_model_meta = FALSE;

    /**
     * Overrided property.
     * By default, caller cannot supply ID for creating new object (POST API call)
     * 
     * @var array
     */
    protected $protected_post_fields = array('id');

    /**
     * Construct method
     * 
     */
    public function __construct()
    {
        parent::__construct();        
    }

    /**
     * Overriden @method handle_request()
     * It adds RestModel object instantiation @link $obj
     * 
     */
    protected function handle_request()
    {
        // Instantiate our Object, doing our Model Specific thingie
        $this->obj = $this->get_object_instance();
        if(! $this->obj)
        {
            $this->response->http_500('Failed to Instantiate object!');
        }

        // Call Default Handler
        parent::handle_request();
    }

    /**
     * Get RestModel object
     * 
     * @return RestModel
     */
    private function get_object_instance()
    {
        if (! $this->model_class)
        {
            return NULL;
        }

        return new RestModel($this->model_class, $this->model_id_field_name);
    }

    /**
     * Get object ID
     * Default behavior depends on @link $object_id_uri_index
     * Can be overriden by Developer
     * 
     * Note: returning NULL means ID was not found in URI
     * 
     * @return int|string|null
     */
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

    /**
     * Returns an Array which will be used as Where selection 
     * 
     * Developers can override this method to specify certain selection criteria 
     * in @method model_get_all()
     * 
     * @example return array('owner' => 5, 'tag' => 'history');
     * 
     * @return array|null
     */
    protected function get_object_selection()
    {
        return NULL;
    }

    /**
     * Overriden method
     * Adds resource URI field to the object.
     * 
     * Default implementation:
     * - If no object ID, then append the value of the `id` field to the request URI.
     * - If object ID exists, then the value is the request URI.
     * 
     * @param array $obj Output object.
     * 
     * @return string Returns resource URI string
    */
    protected function get_resource_uri($obj)
    {
        $id = $this->get_object_id();

        if ($id === NULL)
        {
            if (!array_key_exists($this->model_id_field_name, $obj))
            {
                // Cannot build Resource URI -- ID field is required!!
                return NULL;
            }
            
            $id = $obj[$this->model_id_field_name];

            return $this->request->full_uri($id);
        }

        // We Have an object ID, The request URI is the Resource URI!
        return $this->request->full_uri();
    }

    /**
     * Overriden @method get_meta()
     * if @link $add_model_meta is TRUE, it adds the following to the meta data:
     * - count: count of objects returned in result
     * - total: total number of objects in DB
     * - limit: limit value applied to this GET operation
     * - offset: offset value applied to this GET operation
     * 
     * @return array
     */
    protected function get_meta()
    {
        $meta = parent::get_meta();

        $where = $this->get_object_selection();

        if ($this->add_model_meta)
        {
            $meta['total'] = $this->obj->count($where);
            $meta['count'] = $this->obj->result_count();
            $meta['limit'] = $this->limit;
            $meta['offset'] = $this->offset;
        }

        return $meta;
    }

    /**
     * Overriden @method rest_post()
     * Prepares data for @method model_create()
     * 
     * @return array Processed output data
     */
    protected function rest_post()
    {
        // Load Data
        $data = $this->process_input_data();

        if (is_array($data) && !is_assoc($data))
        {
            // Create new array of objects and save them!
            $res = $this->model_create_all($data);
        }
        else
        {
            // Create the new object and save it!
            $res = $this->model_create($data);            
        }

        // Well, it seems we made it ...
        return $this->process_output_data($res);
    }

    /**
     * Overriden @method model_create()
     * Loads @link $obj with data, Validates it and saves the new object in DB
     * 
     * If data is not valid it exits with @link HTTP_RESPONSE_BAD_REQUEST 400
     * If Save operation failed it exits with @link HTTP_RESPONSE_INTERNAL_ERROR 500
     * 
     * @return array Representation of the saved object
     */
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

    /**
     * Overriden @method model_create_all()
     * Loads @link $obj with objects in data, Validates it and saves the new object in DB
     * 
     * If one object in data array is not valid it exits with @link HTTP_RESPONSE_BAD_REQUEST 400
     * If Save operation failed it exits with @link HTTP_RESPONSE_INTERNAL_ERROR 500
     * 
     * @param array $data Array of Sufficient Input data for creating the new Objects/Resources
     * 
     * @return array Array of Representations of the saved objects
     */
    protected function model_create_all($data)
    {
        if (!is_array($data) || is_assoc($data))
        {
            $this->response->http_400("Inavlid Input Data. Expecting an Array of objects!");
        }

        $result = array();
        foreach ($data as $obj)
        {
            $result[] = $this->model_create($obj);
        }

        return $result;
    }

    /**
     * Overriden @method rest_get()
     * Prepares data for @method model_get() or model_get_all()
     * 
     * @return array Processed output data
     */
    protected function rest_get()
    {
        // Check if there is an `id`. i.e. retrieving specific object
        $id = $this->get_object_id();
        $where = $this->get_object_selection();
        if ($id !== NULL)
        {
            $res = $this->model_get($id, $where);
        }
        else
        {
            // Add Meta to result
            $this->add_model_meta = TRUE;
            $res = $this->model_get_all($where);
        }

        return $this->process_output_data($res);
    }

    /**
     * Overriden @method model_get()
     * Retrieves specific object with $id from DB
     * 
     * If object was not found` it exits with @link HTTP_RESPONSE_NOT_FOUND 404
     * 
     * @param array $where Array of Key/Value pairs used as Where condition.
     * 
     * @return array Representation of the retrieved object
     */
    protected function model_get($id, $where=NULL)
    {
        // Get the specified object
        if (! $this->obj->get($id, $where))
        {
            // 404 NOT FOUND
            $this->response->http_404('Error 404. Not Found');
        }

        return $this->obj->to_array();
    }

    /**
     * Overriden @method model_get_all()
     * Retrieves array of objects based on @link $offset and @link $limit
     * 
     * If object was not found` it exits with @link HTTP_RESPONSE_NOT_FOUND 404
     * 
     * @param array $where Array of Key/Value pairs used as Where condition.
     * 
     * @example $this->model_get_all(array('owner' => 5));
     * 
     * @return array Representation of the retrieved objects
     */
    protected function model_get_all($where=NULL)
    {
        // Get the specified object
        if (! $this->obj->get_all($this->limit, $this->offset, $where))
        {
            // 404 NOT FOUND
            $error = $this->obj->error();
            $this->response->http_404($error);
        }

        return $this->obj->to_array();
    }

    /**
     * Overriden @method rest_put()
     * Prepares data for @method model_update()
     * 
     * Note: Right now, it only supports updating Single object, this is why if no object id was
     * found it exits with HTTP_RESPONSE_NOT_FOUND
     * 
     * @return array Processed output data
     */
    protected function rest_put()
    {
        // Load Data
        $data = $this->process_input_data();

        // Check if there is an `id`. i.e. retrieving specific object
        $id = $this->get_object_id();
        if ($id === NULL)
        {
            $where = $this->get_object_selection();
            if (!$where || !is_array($where))
            {
                // Either ID or Where Selection is required for PUT operation - 404
                $this->response->http_404();
            }

            // Update All operation
            $res = $this->model_update_all($data, $where);
        }
        else
        {
            $res = $this->model_update($id, $data);
        }

        return $this->process_output_data($res);
    }

    /**
     * Overriden @method model_update()
     * Updates a certain object with $id
     * 
     * If object was not found it exits with @link HTTP_RESPONSE_NOT_FOUND 404
     * If data was not valid it exits with @link HTTP_RESPONSE_BAD_REQUEST
     * If update failed it exits with @link HTTP_RESPONSE_INTERNAL_ERROR
     * 
     * @return array Representation of the retrieved objects
     */
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

    /**
     * Overriden @method model_update_all()
     * Method responsible for Multiple Model (Objects/Resources) Update.
     * Mainly called from @method rest_put.
     *  
     * @param mixed $data Sufficient Input data for updating the Object/Resource
     * @param array $where Associative Array  used as Where condition.
     * 
     * @example $this->model_update_all($data, array('owner' => 5));
     * 
     * @return mixed Returns representation of updated Object/Resource (pref. array())
    */
    protected function model_update_all($data, $where)
    {
        if (!is_array($where))
        {
            $this->response->http_400('Invalid Objects Selection!');
        }

        if (!$this->obj->update_all($data, $where))
        {
            $error = $this->obj->error();
            $this->response->http_400($error);
        }

        return $this->obj->to_array();
    }

    /**
     * Overriden @method rest_delete()
     * Prepares data for @method model_delete() or @method model_delete_all
     * 
     * @return array Processed output data
     */
    protected function rest_delete()
    {
        // Check if there is an `id`. i.e. retrieving specific object
        $id = $this->get_object_id();
        $where = $this->get_object_selection();

        if ($id === NULL)
        {
            if (!is_array($where))
            {
                // Either ID or Where is required for DELETE operation - 404
                $this->response->http_404('Error 404. Not Found');
            }
            
            $this->model_delete_all($where);
        }
        else
        {
            $this->model_delete($id);
        }

        // DELETE returns No Content - 204
        return NULL;
    }

    /**
     * Overriden @method model_delete()
     * Deletes a certain object with $id
     * 
     * If object was not found it exits with @link HTTP_RESPONSE_NOT_FOUND 404
     * 
     * @return null
     */
    protected function model_delete($id)
    {
        // Get the specified object
        if ($id === NULL || ! $this->obj->get($id))
        {
            // 404 NOT FOUND
            $this->response->http_404('Error 404. Not Found');
        }

        $this->obj->delete();

        return NULL;
    }

    /**
     * Overriden @method model_delete_all()
     * 
     * Method responsible for Multiple Model (Object/Resource) Deletion.
     *  
     * =============================================================================================
     * Important: This is Very Dangerous to be implemented. Implement/Use with Care!
     * =============================================================================================
     * 
     * @param array $where Associative Array used as Where condition.
     * 
     * @example $this->model_delete_all(array('owner' => 5));
     * 
     * @return null Returns NULL
    */
    protected function model_delete_all($where)
    {
        if (!is_array($where))
        {
            $this->response->http_400('Invalid Objects Selection!');
        }

        $this->obj->delete_all($where);

        return NULL;
    }

    /**
     * Overriden @method rest_head()
     * Checks if object exists
     * 
     * @return mixed Returns Response data
    */
    protected function rest_head()
    {
        $id = $this->get_object_id();
        $where = $this->get_object_selection();

        if (!$id || !$this->obj->exists($id, $where))
        {
            $this->response->http_404();
        }

        return NULL;
    }


    /**
     * Overriden @method rest_patch()
     * Partial update of object.
     * 
     * Note: Current implementation, PATCH and PUT are quite identical, as both can accept partial
     * updates. This might change in the future.
     * 
     * @return mixed Returns Response data
    */
    protected function rest_patch()
    {
        return $this->rest_put();
    }
}

// Helper Method
function is_assoc($array)
{
    return (array_values($array) !== $array);
}