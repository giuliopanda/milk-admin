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
    private array $programmaticRouteMap = [];
    private array $programmaticAccessLevelMap = [];
    private array $programmaticAccessByAction = [];
    private bool $routeMapBuilt = false;
    private array $attributeActionSet = [];
    private bool $attributeActionSetBuilt = false;

    /**
     * Register a request action programmatically (without #[RequestAction] attribute).
     *
     * This works both in Module and Controller because both use RouteControllerTrait.
     *
     * Examples:
     * - $this->registerRequestAction('home', 'myHomeMethod');
     * - $this->registerRequestAction('sync', 'runSync', 'admin');
     * - $this->registerRequestAction('custom', [$someObject, 'methodName']);
     *
     * @param string $action Route action (e.g. "home", "my-action")
     * @param string|array $handler Method name or callable pair [object, method]
     * @param string|null $accessLevel Optional access level ('public', 'registered', 'admin', 'authorized[:permission]')
     * @return bool True when registered, false when already registered
     */
    public function registerRequestAction(string $action, string|array $handler, ?string $accessLevel = null): bool
    {
        $action = trim($action);
        if ($action === '') {
            throw new \InvalidArgumentException('Action cannot be empty');
        }

        // If called on a Module that has an active Controller, register the route on the Controller
        // so it is effective even when the controller is the active route handler.
        if (
            property_exists($this, 'controller')
            && is_object($this->controller)
            && $this->controller !== $this
            && method_exists($this->controller, 'registerRequestAction')
        ) {
            $forwardHandler = $handler;

            // If handler string belongs to Module but not to Controller, preserve Module method call.
            if (
                is_string($handler)
                && method_exists($this, $handler)
                && !method_exists($this->controller, $handler)
            ) {
                $forwardHandler = [$this, $handler];
            }

            return (bool) $this->controller->registerRequestAction($action, $forwardHandler, $accessLevel);
        }

        // Avoid accidental overrides: if already registered, do not re-register.
        if (isset($this->programmaticRouteMap[$action])) {
            return false;
        }

        // If routes were already built, also avoid overriding attribute-based routes.
        if ($this->routeMapBuilt && isset($this->routeMap[$action])) {
            return false;
        }

        // If routes are not built yet, prevent overriding request actions declared on this class via attribute.
        // Note: we intentionally do NOT build the full route map here, to avoid freezing extension scanning too early.
        if (!$this->routeMapBuilt && $this->hasRequestActionAttribute($action)) {
            return false;
        }

        $normalizedHandler = $this->normalizeRequestActionHandler($handler);

        $this->programmaticRouteMap[$action] = $normalizedHandler;
        $this->routeMap[$action] = $normalizedHandler;

        if ($accessLevel !== null) {
            $access = new AccessLevel($accessLevel);
            $accessKey = $this->getAccessMapKeyForHandler($normalizedHandler);
            $this->programmaticAccessLevelMap[$accessKey] = $access;
            $this->accessLevelMap[$accessKey] = $access;
            $this->programmaticAccessByAction[$action] = $accessLevel;
        }

        return true;
    }

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

        // Deprecated fallback: legacy actionXxx methods (kept for backward compatibility)
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
        if (!$this->routeMapBuilt) {
            $this->buildRouteMap();
        }

        return $this->routeMap[$action] ?? null;
    }

    private function buildRouteMap(): void {
        $this->routeMapBuilt = true;

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

        // Programmatic routes are applied at the end so they can override scanned routes.
        $this->applyProgrammaticRequestActions();
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

    /**
     * Convert action name to legacy actionXxx method name.
     *
     * @deprecated Legacy routing fallback. Prefer #[RequestAction] or registerRequestAction().
     */
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
        if (!$this->routeMapBuilt) {
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

    /**
     * Normalize handler to a supported internal format.
     *
     * @param string|array $handler
     * @return string|array
     */
    private function normalizeRequestActionHandler(string|array $handler): string|array
    {
        if (is_string($handler)) {
            return $handler;
        }

        if (is_array($handler) && count($handler) === 2 && is_object($handler[0]) && is_string($handler[1])) {
            return [$handler[0], $handler[1]];
        }

        throw new \InvalidArgumentException(
            'Handler must be a method name string or [object, method] pair'
        );
    }

    /**
     * Return access-level map key for a handler.
     *
     * @param string|array $handler
     * @return string
     */
    private function getAccessMapKeyForHandler(string|array $handler): string
    {
        if (is_array($handler)) {
            [$obj, $method] = $handler;
            return spl_object_id($obj) . '::' . $method;
        }

        return $handler;
    }

    /**
     * Merge request actions registered with registerRequestAction().
     *
     * @return void
     */
    protected function applyProgrammaticRequestActions(): void
    {
        foreach ($this->programmaticRouteMap as $action => $handler) {
            $this->routeMap[$action] = $handler;
        }

        foreach ($this->programmaticAccessLevelMap as $key => $accessLevel) {
            $this->accessLevelMap[$key] = $accessLevel;
        }
    }

    private function hasRequestActionAttribute(string $action): bool
    {
        if (!$this->attributeActionSetBuilt) {
            $this->attributeActionSetBuilt = true;
            $this->attributeActionSet = [];

            try {
                $reflection = new ReflectionClass($this);
                foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PRIVATE) as $method) {
                    foreach ($method->getAttributes(RequestAction::class) as $attr) {
                        $instance = $attr->newInstance();
                        $attrAction = trim((string) ($instance->action ?? ''));
                        if ($attrAction !== '') {
                            $this->attributeActionSet[$attrAction] = true;
                        }
                    }
                }
            } catch (\Throwable) {
                // If reflection fails for any reason, do not block registrations here.
                $this->attributeActionSet = [];
            }
        }

        return isset($this->attributeActionSet[$action]);
    }
}
