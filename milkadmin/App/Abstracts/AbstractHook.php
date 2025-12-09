<?php
namespace App\Abstracts;

use App\Abstracts\Traits\AttributeHookTrait;
use App\ExtensionLoader;

!defined('MILK_DIR') && die(); // Prevent direct access

/**
 * Abstract Hook Class
 *
 * This class serves as the base for hook management in modules. It provides
 * a standardized structure for organizing hook callbacks using the HookCallback attribute.
 * Classes that extend this class can define methods with the #[HookCallback] attribute
 * that will be automatically registered in the hook system.
 *
 * @example
 * ```php
 * class PostsHook extends \App\Abstracts\AbstractHook {
 *
 *     #[HookCallback('post_save', 10)]
 *     public function onPostSave($post_data) {
 *         // This method will be called when Hooks::run('post_save', $data) is executed
 *         // Process and return modified data
 *         return $post_data;
 *     }
 *
 *     #[HookCallback('post_delete', 20)]
 *     public function onPostDelete($post_id) {
 *         // This method will be called when Hooks::run('post_delete', $id) is executed
 *         // Perform cleanup tasks
 *         return $post_id;
 *     }
 * }
 * ```
 *
 * @package     App
 * @subpackage  Abstracts
 */

#[\AllowDynamicProperties]
abstract class AbstractHook {

    use AttributeHookTrait;


    /**
     * Constructor
     *
     * Automatically registers all methods decorated with the HookCallback attribute
     * when the class is instantiated.

     */
    public function __construct() {

        $this->registerHooks();
    }

}