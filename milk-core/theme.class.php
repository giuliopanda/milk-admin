<?php
namespace MilkCore;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
* GP Theme
* Template-specific data storage class.
* Data has the characteristic of always being stored in arrays
* so set('a',1) and set('a',2)
* will return get('a') = [1,2]
* To delete data, just pass NULL as the value.
 *
 * @package     MilkCore
 */

class Theme
{

	/*
	 * @var 		array  			Tutti i dati del Registro
	*/
	static $registry 				= array();


	/**
	 * Stores a new variable inside an array to be used in the theme
	 * To remove a variable, just pass NULL as the path value.	
	 * @param string  path	Where to save the data inside the registry array es: main.mieidati.
	 * @param mixed  data	The data to save
	 * @return  	void
	**/
	public static function set($path, $data) 
	{
		if ($path == '') return;
        if ($data === null) {
			if (isset(self::$registry[$path])) {
				unset(self::$registry[$path]);
			} 
			return;
		}
	
		if (!isset(self::$registry[$path]) || !is_array(self::$registry[$path])) {
			self::$registry[$path] = [];
		}
		$data = Hooks::run('theme_set_'.$path, $data);
		self::$registry[$path][] = $data;
	}

	/**
	 * Deletes a variable
	 * @param  string path		The path where the data was saved inside the registry array es: main.miavar
	 * @return  	void
	 */
	public static function delete($path)  
	{
		if (isset(self::$registry[$path])) {
			unset(self::$registry[$path]);
		}
	}

	/**
	 * Returns the variable if it is an array the last variable
	 * @param  string path		The path where the data was saved inside the registry array es: main.miavar
	 * @param  mixed  default	If there is no data in the path returns the default value
	 * @param  mixed  return  	Returns the stored value or the default value
	 * @return mixed
	**/
	public static function get($path, $default = null) 
	{
		if (isset(self::$registry[$path])) {
			if (is_array(self::$registry[$path]) || is_object(self::$registry[$path])) {
				$return = (array)self::$registry[$path];
				$return = end($return);
			} else {
				$return = self::$registry[$path];
			}
		} else {
			$return = $default;
		}
		return Hooks::run('theme_get_'.$path, $return, 'string');
	}

	/**
	 * Orders an array of data based on a key
	 * @param  \string path			The name of the variable
	 * @param  \string order_field	The key to order by
	 * @param  \string dir			The direction of the sorting asc or desc
	 */
	static function multiarray_order($path, $order_field, $dir='asc') {
		$data = self::get_all($path);
		if (is_array($data)) {
			$sort = [];
			foreach ($data as $key => $value) {
				if (isset($value[$order_field])) {
					$sort[$key] = $value[$order_field]; 
				} else {
					$sort[$key] = 10;
				}
			}
			array_multisort($sort, $dir == 'asc' ? SORT_ASC : SORT_DESC, $data);
			self::$registry[$path] = $data;
		}
	}

	/**
	 * Returns the variable if it is an array the last variable
	 * @param  string path		The name of the variable
	 * @param  mixed  default	If there is no data in the path returns the default value
	 * @param  mixed  return  	mixed
	**/
	static function get_all($path, $default = null) 
	{
		if (isset(self::$registry[$path])) {
			$return = self::$registry[$path];
		} else {
			$return = $default;
		}
		return Hooks::run('theme_get_'.$path, $return, 'array');
	}
	/**
	 * Verifies if a variable is set
	 * @param  \string path		The path where the data was saved inside the registry array es: main.miavar
	 * @param  \bool  return 
	**/
	static function has($path) {
		return isset(self::$registry[$path]);
	}

	/**
	 * Iterates over a variable. 
	 *@param   path		string	The path where the data was saved inside the registry array es: main.miavar
	 *@param   return  	yield
	**/
	static function for($path) 
	{
        $data = self::get_all($path);
        // yield
        if (is_array($data)|| is_object($data)) {
            foreach ($data as $key=>$value) {
                yield $key => $value;
            }
        }
		return;
    }

	/**
	 * Verifies if a variable is of a certain type
	 * @param   var		mixed		The variable to verify
	 * @param   type	string		The type to verify
	 * If type is an array or an object, it verifies that the keys are present
	 * If it is an object, it also verifies that the object is of the same type
	 * @return  	Boolean
	 */
	static function check($var, $type) {
		
		if ($type == 'string' && is_string($var)) {
			return true;
		}
		if ($type == 'int' && is_int($var)) {
			return true;
		}
		if ($type == 'float' && is_float($var)) {
			return true;
		}
		if ($type == 'array' && is_array($var)) {
			return true;
		}
		if ($type == 'object' && is_object($var)) {
			return true;
		}
		
		if (is_array($type) && is_array($var)) {
			// type contains the names of the keys and verifies that they are met
			foreach ($type as $key) {
				if (!isset($var[$key])) {
					return false;
				}
			}
			return true;
		} 
		if (is_object($type) && is_object($var)) {
			// verifies the object name
			if (get_class($var) != get_class($type)) {
				return false;
			}
			// type contains the names of the keys and verifies that they are met
			foreach ($type as $key) {
				if (!property_exists($var, $key)) {
					return false;
				}
			}
			return true;
		}
		return false;

	}

}