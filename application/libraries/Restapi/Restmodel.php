<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
* RestModel Class
* 
* Implements a wrapper class to handle all model related operation.
* 
* @package Restapi
* @subpackage RestModel
* @license MIT License https://github.com/mohabusama/ci-rest-api/blob/master/LICENSE
* @link https://github.com/mohabusama/ci-rest-api
* @author Mohab Usama
* @version 0.1.0
*/

define('REST_MODEL_TYPE_UNKNOWN', '');
define('REST_MODEL_TYPE_DATAMAPPER', 'datamapper');
// define('REST_MODEL_TYPE_DOCTRINE', 'doctrine');

/**
 * RestModel Class
 * 
 * This class provides all model related operations.
 * 
 * @package Restapi
 */

class RestModel
{
    /**
     * Private value for the wrapped model parent class.
     * Since currently only DataMapper is only supported, it holds @link REST_MODEL_TYPE_DATAMAPPER 
     * 
     * @var string
     */
    private $model_type = '';

    /**
     * The Model Class that will be wrapped.
     * Can only be set via @method __consruct()
     * 
     * @example new RestModel('User');
     * @var array
     */
    private $model_class = '';

    /**
     * The Model ID field name.
     * Can only be set via @method __consruct()
     * 
     * @var string
     */
    private $model_id_field_name = 'id';

    /**
     * This is the Private instance of the Model (Object) @link $model_class.
     * 
     * @var object
     */
    private $obj = NULL;

    /**
     * Private flag used by @method get_all() and @method to_array()
     * 
     * @var bool
     */
    private $is_array = FALSE;

    /**
     * Error message. Probably set by Validation.
     * 
     * @var string
     */
    private $err = '';

    /**
     * Construct method
     * 
     * Mainly, Instantiate new object of @link $model_class
     * 
     * @param string $model_class Value for @link $model_class
     * 
     * @return void
     */
    public function __construct($model_class, $id_field='id')
    {
        $this->model_class = $model_class;

        $this->model_id_field_name = $id_field;

        $this->obj = new $model_class;

        $this->model_type = $this->_get_model_type();
    }

    public function get_object_instance($id=NULL)
    {
        $obj = new $this->model_class;

        if ($id)
        {
            $obj->{"get_by_$this->model_id_field_name"}($id);
        }

        return $obj;
    }

    /**
     * Get object by ID
     * 
     * Loads @link $obj after retrieving by $id
     * 
     * @todo Is the $where needed. The behavior is now inconsistent between diff methods GET,
     * POST, PUT and DELETE. Not all of them accept $where condition.
     * 
     * @param int|string $id ID of the object
     * @param array $where Array of Key/Value pairs used as Where condition.
     * 
     * @return bool TRUE if found, FALSE if not found.
     */
    public function get($id, $where=NULL)
    {
        if (is_array($where) && count($where))
        {
            $this->obj->where($where);
        }

        $this->obj->{"get_by_$this->model_id_field_name"}($id);

        if (! $this->obj->id)
        {
            $this->_set_error();
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Get All objects in DB
     * 
     * This operation is constrained by the $limit and $offset values
     * Loads @link $obj after retrieving iterated result. Iterated result is used for optimization.
     * 
     * @param int $limit Maximum limit of result to be retrieved
     * @param int $offset Starting offset in DB of the results
     * @param array $where Array of Key/Value pairs used as Where condition.
     * 
     * @return bool
     */
    public function get_all($limit=50, $offset=0, $where=NULL)
    {
        if (is_array($where) && count($where))
        {
            $this->obj->where($where);
        }

        $this->obj->get_iterated($limit, $offset);

        $this->is_array = TRUE;

        return TRUE;
    }

    /**
     * Save object in DB
     * 
     * Expects @link $obj to be already loaded with data. Check @method load()
     * 
     * @return bool TRUE if success, FALSE if failure.
     */
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

    /**
     * Update existing object
     * 
     * Expects @link $obj to be already loaded with data. Check @method load()
     * 
     * @return bool TRUE if success, FALSE if failure.
     */
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

    /**
     * Update All objects based on a Where selection
     * 
     * Expects @link $obj to be already loaded with data. Check @method load()
     * @param array $where Associative array represents Objects Selection criteria
     * @param array $data Associative Array of fields to be updated in all objects
     * 
     * @return bool TRUE if success, FALSE if failure.
     */
    public function update_all($data, $where)
    {
        if (!is_array($where) || !count($where))
        {
            $this->_set_error("Invalid Object selection!");
            return FALSE;
        }

        // Update All
        $success = $this->obj->where($where)->update($data);

        if (!$success)
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

        $this->obj->where($where)->get();

        return $success;
    }

    /**
     * Delete existing object
     * 
     * Expects @link $obj to be already loaded with data. Check @method load()
     * 
     * @return bool TRUE if success, FALSE if failure.
     */
    public function delete()
    {
        if(! $this->obj->id)
        {
            return FALSE;
        }

        $this->obj->delete();
        return TRUE;
    }

    /**
     * Delete All existing object
     * 
     * @param array $where Object Selection to be deleted
     * 
     * @return bool TRUE if success, FALSE if failure.
     */
    public function delete_all($where)
    {
        if (!is_array($where) || !count($where))
        {
            $this->_set_error("Invalid Objects selection!");
            return FALSE;
        }

        $this->obj->where($where)->get();

        foreach ($this->obj->all as $obj)
        {
            $obj->delete();
        }

        return TRUE;
    }

    /**
     * Checks if object with $id exists
     * 
     * @param int|string $id ID of the object to be checked.
     * 
     * @return bool TRUE if exists, FALSE otherwise.
     */
    public function exists($id, $where=NULL)
    {
        $obj = new $this->model_class;

        if (is_array($where) && count($where))
        {
            $obj->where($where);
        }

        $obj->{"get_by_$this->model_id_field_name"}($id);

        if(! $obj->id)
        {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Count of objects in DB
     * 
     * @param array $where Array of Where conditions applied to count
     * 
     * @return int Count of objects in DB
     */
    public function count($where=NULL)
    {
        $obj = new $this->model_class;

        if ($where)
        {
            // Conditional count
            return $obj->where($where)->count();
        }

        // All Objects!
        return $obj->count();
    }

    /**
     * Result count in current object @link $obj
     * 
     * @return int Result Count in object
     */
    public function result_count()
    {
        return $this->obj->result_count();
    }

    /**
     * Loads object @link $obj with Data.
     * 
     * @param array $data Valid Data to be loaded in object
     * @param int|string $id ID of object. Used in Update operation.
     * 
     * @return bool TRUE if success, FALSE if failure.
     */
    public function load($data, $id=NULL)
    {
        if ($id)
        {
            $this->obj->{"get_by_$this->model_id_field_name"}($id);
        }
        else
        {
            $this->obj = new $this->model_class;
        }

        if (!is_array($data) || !$this->obj->from_array($data))
        {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Validates the object with loaded data
     * 
     * Expects @link $obj to be already loaded with data. Check @method load()
     * 
     * @return bool TRUE if valid, FALSE otherwise.
     */
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

    /**
     * Returns Array representation of current object @link $obj
     * 
     * @return array
     */
    public function to_array()
    {
        if (! $this->obj->exists())
        {
            return array();
        }
        elseif ($this->obj->result_count() > 1 || $this->is_array)
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

    /**
     * Returns the current error @link $err
     * 
     * @return string Error message
     */
    public function error()
    {
        return $this->err;
    }

    /**
     * Set the error.
     * 
     * This method checks if there is an error set in the object @link $obj.
     * 
     * @return string Error message
     */
    private function _set_error($err=NULL)
    {
        // Get the whole error string!
        $err_str = '';
        if ($err)
        {
            $err_str = $err;
        }
        else
        {
            foreach ($this->obj->error->all as $err)
            {
                $err_str .= $err . '<br>';
            }
        }

        $this->err = $err_str ? $err_str : 'An Error has occured!';
    }

    /**
     * Detects and returns the model type of current object @link $obj
     * 
     * Current, only DataMapper is supported @link REST_MODEL_TYPE_DATAMAPPER
     * 
     * @return string Model type of current object.
     */
    private function _get_model_type()
    {
        if ($this->obj instanceof DataMapper)
        {
            return REST_MODEL_TYPE_DATAMAPPER;
        }

        return REST_MODEL_TYPE_UNKNOWN;
    }
}