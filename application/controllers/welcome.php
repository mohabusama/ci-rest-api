<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH . 'libraries/Restapi/Restapi.php';

class Welcome extends RestResource
{
    protected $meta_timestamp = TRUE;
    protected $allowed_methods = array(REQUEST_GET);

    protected $authentication = array('authenticate', 'authenticate_success');

    //protected $authorization = array('authorize_success', 'authorize_fail');
    protected $authorization = array('authorize_success');

    protected $validation = 'validation';

    public function index()
    {
        exit('CI');
    }

    public function __construct()
    {
        parent::__construct();
    }

    protected function rest_get()
    {
        $get = array(
            'uri' => $this->request->uri(),
            'args' => $this->request->args(),
            'data' => $this->request->data('class')
        );
        return $get;
    }

    public function authenticate()
    {
        return FALSE;
    }

    public function authenticate_success()
    {
        return TRUE;
    }

    public function authorize_fail()
    {
        return FALSE;
    }

    public function authorize_success()
    {
        return TRUE;
    }

    public function validation()
    {
        return TRUE;
    }
}
