<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');


class User extends DataMapper
{    
    public $fullname;
    public $email;
    public $phone;
    public $status;

    public $table = 'users';

    public $validation = array(
        'fullname' => array(
			'rules' => array('required', 'max_length' => 50)
        ),
        'email' => array(
			'rules' => array('required', 'trim', 'valid_email', 'unique')
        )
    );
    
    public function __construct()
    {
        parent::__construct();
    }
}
