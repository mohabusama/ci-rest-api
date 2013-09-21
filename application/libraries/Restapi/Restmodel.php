<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');


define('REST_MODEL_TYPE_UNKNOWN', '');
define('REST_MODEL_TYPE_DATAMAPPER', 'datamapper');
//define('REST_MODEL_TYPE_DOCTRINE', 'doctrine');

class RestModel
{

    private $model_type = '';

    private $model_class = '';

    private $obj = NULL;

    private $err = '';

    public function __construct($model_class)
    {
        $this->model_class = $model_class;

        $this->obj = new $model_class;

        $this->model_type = $this->_get_model_type();
    }

    public function get($id)
    {
        $this->obj->get_by_id($id);

        if (! $this->obj->id)
        {
            $this->_set_error();
            return FALSE;
        }

        return TRUE;
    }

    public function get_all($limit, $offset)
    {
        $this->obj->get_iterated($limit, $offset);

        return TRUE;
    }

    public function save()
    {
        $success = $this->obj->save();

        if (! $success)
        {
            if ($this->obj->valid)
            {
                $this->_set_error('Failed to save data!');
            }
            else
            {
                //Validation error
                $this->_set_error();
            }
        }

        return $success;
    }

    public function update()
    {
        $this->obj->validate();
        if(! $this->obj->valid)
        {
            $this->_set_error();
            return FALSE;
        }

        return $this->save();
    }

    public function delete()
    {
        if(! $this->obj->id)
        {
            return FALSE;
        }

        $this->obj->delete();
    }

    public function exists($id)
    {
        $obj = new $this->model_class;

        $obj->get_by_id($id);

        if(! $obj->id)
        {
            return FALSE;
        }

        return TRUE;
    }

    public function count()
    {
        $obj = new $this->model_class;

        return $obj->count();        
    }

    public function result_count()
    {
        return $this->obj->result_count();
    }

    public function load($data, $id=NULL)
    {
        if ($id)
        {
            $this->obj->get_by_id($id);
        }

        if (!is_array($data) || !$this->obj->from_array($data))
        {
            return FALSE;
        }

        return TRUE;
    }

    public function validate()
    {
        $this->obj->validate();

        if(! $this->obj->valid)
        {
            $this->_set_error();
            return FALSE;
        }
        return TRUE;
    }

    public function to_array()
    {
        if (! $this->obj->exists())
        {
            return array();
        }
        elseif ($this->obj->result_count() > 1)
        {
            // Returning Array of objects
            $res = array();
            foreach ($this->obj as $obj)
            {
                $res[] = $obj->to_array();
            }
            return $res;
        }
        else
        {
            return $this->obj->to_array();
        }
    }

    public function error()
    {
        return $this->err;
    }

    private function _set_error($err=NULL)
    {
        // Get the whole error string!
        $err_str = $err ? $err : $this->obj->error->string;

        $this->err = $err_str ? $err_str : 'An Error has occured!';
    }

    private function _get_model_type()
    {
        if ($this->obj instanceof DataMapper)
        {
            return REST_MODEL_TYPE_DATAMAPPER;
        }

        return REST_MODEL_TYPE_UNKNOWN;
    }
}