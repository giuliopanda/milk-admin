<?php
namespace MilkCore;
!defined('MILK_DIR') && die(); // Avoid direct access

/**
* Dependency Container
* 
* A lightweight, powerful dependency injection container for managing service instances
* and dependencies throughout your application. This container supports automatic type
* detection for different service patterns (standard classes, static classes, singletons,
* factories, and direct values).
*
* Features:
* - Automatic type detection
* - Singleton initialization and caching
* - Lazy loading of dependencies
* - Support for closures and factory patterns
* - Simple, intuitive API
*
* Basic Usage:
* ```
* // Register a regular class
* DependencyContainer::bind('database', DatabaseConnection::class);
* $db = DependencyContainer::get('database', ['localhost', 'user', 'pass']);
* 
* // Register a singleton (always returns the same instance)
* DependencyContainer::bind('config', AppConfig::class, true);
* $config = DependencyContainer::get('config');
* 
* // Register a factory function
* DependencyContainer::bind('logger', function($file_name) {
*     return new FileLogger($file_name);
* });
* $logger = DependencyContainer::get('logger', ['app.log']);
* ```
* 
* @package     MilkCore
* @ignore
*/
class DependencyContainer {
   private static $services = [];
   private static $instances = [];
   
   // Service type constants
   const TYPE_CLASS = 'class';        // Regular class
   const TYPE_STATIC = 'static';      // Static class
   const TYPE_SINGLETON = 'singleton'; // Class with singleton pattern
   const TYPE_FACTORY = 'factory';    // Function/Closure
   const TYPE_VALUE = 'value';        // Direct value
   
   /**
    * Register a service with the dependency container
    * 
    * This method registers a service and automatically determines its type.
    * If the singleton parameter is set to true, the service will be instantiated
    * immediately and the same instance will be returned for all subsequent get() calls.
    * 
    * Example:
    * ```
    * // Register a regular class
    * DependencyContainer::bind('user_service', UserService::class);
    * 
    * // Register a service as a singleton
    * DependencyContainer::bind('mailer', MailerService::class, true);
    * 
    * // Register a factory with arguments for initialization
    * DependencyContainer::bind('session', SessionManager::class, true, ['session_id' => uniqid()]);
    * ```
    * 
    * @param string $service_name Identifier for the service
    * @param mixed $implementation The service implementation (class name, closure, or value)
    * @param bool $singleton If true, the service will be instantiated once and reused
    * @param array $arguments Arguments for initialization if instantiated immediately
    */
    public static function bind($service_name, $implementation, $singleton = false, $arguments = []) {
        // Automatically determine the implementation type
        $type = self::determine_type($implementation);
        
        // Save the implementation with its type
        self::$services[$service_name] = [
            'implementation' => $implementation,
            'type' => $type,
            'singleton' => $singleton
        ];
    }
   
   /**
    * Automatically determine the implementation type
    * 
    * This helper method analyzes the provided implementation and determines
    * what type of service it represents (class, static class, singleton, factory, or value).
    * 
    * @param mixed $implementation The service implementation to analyze
    * @return string The determined type constant
    */
   private static function determine_type($implementation) {
       if (is_string($implementation) && class_exists($implementation)) {
           $reflection = new \ReflectionClass($implementation);
           
           // Check if it's a static class
           $is_static = true;
           foreach ($reflection->getMethods() as $method) {
               if (!$method->isStatic() && !$method->isConstructor()) {
                   $is_static = false;
                   break;
               }
           }
           
           if ($is_static) {
               return self::TYPE_STATIC;
           } else if (method_exists($implementation, 'get_instance')) {
               return self::TYPE_SINGLETON;
           } else {
               return self::TYPE_CLASS;
           }
       } else if ($implementation instanceof \Closure) {
           return self::TYPE_FACTORY;
       } else {
           return self::TYPE_VALUE;
       }
   }
   
   /**
    * Alias method for bind() to maintain backward compatibility
    *
    * Example:
    * ```
    * DependencyContainer::register('cache', RedisCache::class);
    * ```
    * 
    * @param string $service_type Identifier for the service
    * @param mixed $implementation The service implementation
    */
   public static function register($service_type, $implementation) {
       self::bind($service_type, $implementation);
   }
   
   /**
    * Instantiate a service based on its type
    * 
    * This internal method creates a new instance of the service according to
    * its registered type, applying any constructor arguments as needed.
    * 
    * @param string $service_type Identifier for the service
    * @param array $arguments Constructor arguments for instantiation
    * @return mixed The instantiated service
    */
   private static function instantiate($service_type, $arguments = []) {
       $service = self::$services[$service_type];
       $implementation = $service['implementation'];
       $type = $service['type'];
       switch ($type) {
           case self::TYPE_STATIC:
               return $implementation;
               
           case self::TYPE_SINGLETON:
               return $implementation::get_instance(...$arguments);
               
           case self::TYPE_CLASS:
               return new $implementation(...$arguments);
               
           case self::TYPE_FACTORY:
               return $implementation(...$arguments);
               
           case self::TYPE_VALUE:
           default:
               return $implementation;
       }
   }
   
   /**
    * Retrieve a service instance from the container
    * 
    * This method returns an instance of the requested service. If the service
    * was registered as a singleton, it will return the cached instance.
    * Otherwise, it creates a new instance each time.
    * 
    * Example:
    * ```
    * // Get a service without arguments
    * $auth_service = DependencyContainer::get('auth');
    * 
    * // Get a service with constructor arguments
    * $db_connection = DependencyContainer::get('database', ['hostname', 'username', 'password']);
    * ```
    * 
    * @param string $service_type Identifier for the service
    * @param array $arguments Constructor arguments if a new instance is created
    * @return mixed The service instance
    * @throws \Exception If the service is not registered
    */
   public static function get($service_type, $arguments = []) {
       if (!isset(self::$services[$service_type])) {
           throw new \Exception("Service not registered: $service_type");
       }
       
       $service = self::$services[$service_type];
       $is_singleton = $service['singleton'];
       
       // Return existing instance if it's a singleton and the instance exists
       if ($is_singleton && isset(self::$instances[$service_type])) {
         // Instantiate immediately if requested as singleton
         if (!isset(self::$instances[$service_type])) {
            self::$instances[$service_type] = self::instantiate($service_type, $arguments);
        }
           return self::$instances[$service_type];
       }        
       
       // Create a new instance
       $instance = self::instantiate($service_type, $arguments);
       
       // If it's a singleton, store the instance
       if ($is_singleton) {
           self::$instances[$service_type] = $instance;
       }
       
       return $instance;
   }
   
   /**
    * Check if a service is registered in the container
    * 
    * Example:
    * ```
    * if (DependencyContainer::has('logger')) {
    *     $logger = DependencyContainer::get('logger');
    * } else {
    *     // Use a default logger
    * }
    * ```
    * 
    * @param string $service_type Identifier for the service
    * @return bool True if the service is registered, false otherwise
    */
   public static function has($service_type) {
       return isset(self::$services[$service_type]);
   }
   
   /**
    * Remove a service from the container
    * 
    * This method removes both the service registration and any cached instances.
    * 
    * Example:
    * ```
    * DependencyContainer::unbind('cache'); // Remove the cache service
    * ```
    * 
    * @param string $service_type Identifier for the service
    */
   public static function unbind($service_type) {
       if (isset(self::$services[$service_type])) {
           unset(self::$services[$service_type]);
       }
       
       if (isset(self::$instances[$service_type])) {
           unset(self::$instances[$service_type]);
       }
   }
   
   /**
    * Remove all cached instances while keeping service registrations
    * 
    * This is useful for resetting the container's state without having to
    * re-register all services, such as during testing.
    * 
    * Example:
    * ```
    * // Reset all instances but keep registrations
    * DependencyContainer::flush();
    * ```
    */
   public static function flush() {
       self::$instances = [];
   }
}

/**
* Authentication Contract
* 
* This interface defines the contract for authentication services.
* Implementations should provide methods for user authentication and session management.
*/
interface AuthContract {

    public static function get_instance();
   /**
    * Get the currently authenticated user
    * 
    * @return mixed The user object or null if no user is authenticated
    */
    public function get_user($id = 0);
   
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
   public function is_authenticated();
   
   /**
    * Get the ID of the currently authenticated user
    * 
    * @return mixed The user ID or null if no user is authenticated
    */
   public function logout();
   
}