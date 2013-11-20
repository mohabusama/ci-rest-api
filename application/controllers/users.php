<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH . 'libraries/Restapi/Restapi.php';

class Users extends RestModelResource
{
    protected $model_class = 'User';
    protected $excluded_fields = array('phone');
    protected $add_meta = TRUE;
    protected $meta_timestamp = TRUE;

    protected function get_object_selection()
    {
        //return NULL;
        return array('phone' => null);
    }

    protected function process_input_field_fullname($field, $value)
    {
      return strtolower($value);
    }

    protected function process_output_field_fullname($field, $value)
    {
        return ucwords($value);
    }
}
