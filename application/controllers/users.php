<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH . 'libraries/Restapi/Restapi.php';

class Users extends RestModelResource
{
    protected $model_class = 'User';

    public function __construct()
    {
        parent::__construct();
    }
}
