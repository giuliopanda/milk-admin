<?php
namespace App;
!defined('MILK_DIR') && die(); // Avoid direct access
/**
* Authentication Contract
* 
* This interface defines the contract for authentication services.
* Implementations should provide methods for user authentication and session management.
*/
interface AuthContractInterface {

    public static function getInstance();
   /**
    * Get the currently authenticated user
    * 
    * @return mixed The user object or null if no user is authenticated
    */
    public function getUser($id = 0);
   
   /**
    * Log out the currently authenticated user
    * 
    * @return bool True if the logout was successful
    */
   public function login($username_email = '', $password = '', $save_sessions = true);
   
   /**
    * Check if a user is currently authenticated
    * 
    * @return bool True if a user is authenticated, false otherwise
    */
   public function isAuthenticated();
   
   /**
    * Get the ID of the currently authenticated user
    * 
    * @return mixed The user ID or null if no user is authenticated
    */
   public function logout();
   
}