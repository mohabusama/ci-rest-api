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
     * This is the Private instance of the Model (Object) @link $model_class.
     * 
     * @var object
     */
    private $obj = NULL;

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
    public function __construct($model_class)
    {
        $this->model_class = $model_class;

        $this->obj = new $model_class;

        $this->model_type = $this->_get_model_type();
    }

    /**
     * Get object by ID
     * 
     * Loads @link $obj after retrieving by $id
     * 
     * @param int|string $id ID of the object
     * 
     * @return bool TRUE if found, FALSE if not found.
     */
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

    /**
     * Get All objects in DB
     * 
     * This operation is constrained by the $limit and $offset values
     * Loads @link $obj after retrieving iterated result. Iterated result is used for optimization.
     * 
     * @param int $limit Maximum limit of result to be retrieved
     * @param int $offset Starting offset in DB of the results
     * 
     * @return bool
     */
    public function get_all($limit=50, $offset=0)
    {
        $this->obj->get_iterated($limit, $offset);

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
     * Checks if object with $id exists
     * 
     * @param int|string $id ID of the object to be checked.
     * 
     * @return bool TRUE if exists, FALSE otherwise.
     */
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

    /**
     * Count of objects in DB
     * 
     * @return int Count of objects in DB
     */
    public function count()
    {
        $obj = new $this->model_class;

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
            $this->obj->get_by_id($id);
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
        $err_str = $err ? $err : $this->obj->error->string;

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