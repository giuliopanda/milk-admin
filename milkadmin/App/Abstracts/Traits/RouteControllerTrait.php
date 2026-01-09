<?php
namespace App\Abstracts\Traits;

use App\Attributes\RequestAction;
use App\Attributes\AccessLevel;
use App\Route;
use App\Theme;
use App\Permissions;
use App\ExtensionLoader;
use ReflectionClass;
use ReflectionMethod;

/**
 * Need Access method to check if the user has permission to access the page
 */

!defined('MILK_DIR') && die();

trait RouteControllerTrait {

    private array $routeMap = [];
    private array $accessLevelMap = [];

    public function handleRoutes() {

        // Call extension hook: before handling routes
        if (isset($this->loaded_extensions)) {
            ExtensionLoader::callHook($this->loaded_extensions, 'onHandleRoutes', []);
        }

        Theme::set('header.title', Theme::get('site.title')." - ". $this->title);

        $action = $_REQUEST['action'] ?? null;

        if (!isset($action) || empty($action)) {
            $action = 'home';
        }

        // First try attribute-based routes
        $attributeMethod = $this->findRouteMethod($action);
        if ($attributeMethod) {
            // Check method-specific access level OR fallback to module access
            if (!$this->checkMethodAccess($attributeMethod)) {
                Route::redirect('?page=deny');
                return;
            }

            // Support both string (method name) and callable (array with object and method)
            if (is_array($attributeMethod)) {
                [$obj, $method] = $attributeMethod;
                $obj->$method();
            } else {
                $this->$attributeMethod();
            }
            return;
        }

        // Fall back to traditional actionXxx methods
        // OLD SYSTEM actionFunctionName
        $function = $this->actionName($action);

        if (method_exists($this, $function)) {
            // Check method-specific access level OR fallback to module access
            if (!$this->checkMethodAccess($function)) {
                Route::redirect('?page=deny');
                return;
            }
            $this->$function();
        } else if ($function == 'actionRelatedSearchField') {
            $this->relatedSearchField();
        } else {
            Route::redirect('?page=404');
            return;
        }

    }

    /**
     * Generic search for autocomplete based on relationship configuration
     * Used only in MilkSelect single on BelongsTo relationship
     * Please note that it queries another model to get the results.
     *
     * @param string $search Search term
     * @param string $field_name Field name that has the apiUrl (e.g., 'doctor_id')
     * @return array Array of options matching the search
     */
    public function relatedSearchField() {
        if (!isset ($this->model) || !isset($_REQUEST['f']) || !isset($_REQUEST['q'])) {
            \App\Response::json([
                'success' => 'error',
                'message' => 'Missing parameters',
                'options' => []
            ]);
        }
        if (strlen($_REQUEST['q']) < 2 ) {
            \App\Response::json([
                'success' => 'ok',
                'options' => []
            ]);
        }

        $search = trim($_REQUEST['q']);
        $field = trim($_REQUEST['f']);
      
		$options = $this->model->searchRelated($search,  $field);
		\App\Response::json([
			'success' => 'ok',
			'options' => $options
		]);
    }

    private function findRouteMethod(string $action): string|array|null {
        if (empty($this->routeMap)) {
            $this->buildRouteMap();
        }

        return $this->routeMap[$action] ?? null;
    }

    private function buildRouteMap(): void {
        // Scan the controller itself
        $this->scanAttributesFromClass($this);

        // Scan loaded extensions
        if (isset($this->loaded_extensions) && !empty($this->loaded_extensions)) {
            foreach ($this->loaded_extensions as $extension) {
                $this->scanAttributesFromClass($extension);
            }
        }

        // Scan Controller extensions from the Module (Controller extensions are Module-managed)
        if (isset($this->module) && method_exists($this->module, 'getLoadedControllerExtensions')) {
            $controller_extensions = $this->module->getLoadedControllerExtensions();
            if (!empty($controller_extensions)) {
                foreach ($controller_extensions as $extension) {
                    $this->scanAttributesFromClass($extension);
                }
            }
        }
    }

    /**
     * Scan a class for RequestAction and AccessLevel attributes
     *
     * @param object $target The object to scan (controller or extension)
     * @return void
     */
    private function scanAttributesFromClass(object $target): void {
        $reflection = new ReflectionClass($target);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);

        foreach ($methods as $method) {
            $methodName = $method->getName();

            // Build route map from RequestAction attributes
            $attributes = $method->getAttributes(RequestAction::class);

            foreach ($attributes as $attribute) {
                $route = $attribute->newInstance();

                // If scanning an extension, store as callable [extension, method]
                if ($target !== $this) {
                    $this->routeMap[$route->action] = [$target, $methodName];
                } else {
                    $this->routeMap[$route->action] = $methodName;
                }
            }

            // Build access level map from AccessLevel attributes
            $accessAttributes = $method->getAttributes(AccessLevel::class);
            if (!empty($accessAttributes)) {
                $accessLevel = $accessAttributes[0]->newInstance();

                // Create unique key for extensions
                if ($target !== $this) {
                    $key = spl_object_id($target) . '::' . $methodName;
                    $this->accessLevelMap[$key] = $accessLevel;
                } else {
                    $this->accessLevelMap[$methodName] = $accessLevel;
                }
            }
        }
    }

    private function actionName($action) {
        $action = strtolower(_raz(str_replace("-","_", $action)));
        $action_array = explode("_", $action);
        return 'action' . implode("", array_map('ucfirst', $action_array));
    }

    /**
     * Check if the user has access to a specific method based on AccessLevel attribute
     * If no AccessLevel attribute is set, falls back to module-level access check
     *
     * @param string|array $methodName The name of the method or callable [object, method] to check
     * @return bool True if access is granted, false otherwise
     */
    private function checkMethodAccess(string|array $methodName): bool {
        // Build maps if not already built
        if (empty($this->routeMap) && empty($this->accessLevelMap)) {
            $this->buildRouteMap();
        }

        // Determine the access level map key
        $accessKey = $methodName;
        if (is_array($methodName)) {
            // Extension method: use object ID + method name
            [$obj, $method] = $methodName;
            $accessKey = spl_object_id($obj) . '::' . $method;
        }

        // If no AccessLevel attribute is set, fallback to module-level access
        if (!isset($this->accessLevelMap[$accessKey])) {
            $moduleAccess = $this->access();
            return $moduleAccess;
        }

        // Method has specific AccessLevel - use it instead of module access
        $accessLevel = $this->accessLevelMap[$accessKey];
        $level = $accessLevel->getBaseLevel();
        $hook = $this->page ?? null;


        switch ($level) {
            case 'public':
                return true;

            case 'registered':
                $result = (Permissions::check('_user.is_guest', $hook) == false);
                return $result;

            case 'authorized':
                $permission_name = $this->module->getPermissionName();
              
                $result = Permissions::check($this->page.".".$permission_name, $hook);
                if ($result) {
                    $permission2 = $accessLevel->getPermission();
                    if ($permission2) {
                        return  Permissions::check($this->page.".".$permission2, $hook);
                    }
                }
               return $result;

            case 'admin':
                $result = Permissions::check('_user.is_admin', $hook);
                return $result;

            default:
                return false;
        }
    }
}
