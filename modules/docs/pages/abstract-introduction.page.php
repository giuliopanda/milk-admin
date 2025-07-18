<?php
namespace Modules\docs;
/**
* @title Introduction 
* @category Abstracts Class
* @order 10
* @tags abstract, classes, AbstractController, AbstractRouter, AbstractModel, AbstractObject, modules, permissions, installation, hooks, module-development, bootstrap, init, init_rules, data-structure, routing, actions, MVC, framework, getting-started, extending, inheritance, base-classes
*/
use MilkCore\Route;

!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

   <h1>Writing modules through abstract classes</h1>

   <p>Abstract classes are classes that cannot be instantiated directly, but can be extended by other classes. These are used to create advanced modules in a simpler way.</p>


   <p><a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/abstract-controller.page'); ?>">AbstractController</a> is used to initialize the module and manage basic permissions (for more specific contexts they are managed in individual methods), page name, model definition and default router, installation and system hooks.</p>
   
   <p><a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/abstract-router.page'); ?>">AbstractRouter</a> is used to handle various calls, typically html and json. The router, depending on the action set in the url, looks for a method with the same name and calls it if it exists. <br>
   For example: <code>?page=my_module&action=my-action</code> will look for the action_my_action function inside the class that extends RouterAbstract</p>

   <p><a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/abstract-model.page'); ?>">AbstractModel</a> obviously serves for data connection and integrates with AbstractObject which defines the data structure</p>

   <p><a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/abstract-object.page'); ?>">AbstractObject</a> defines a data structure. If set, it is able to create and modify the table automatically</p>

   <p>You can follow the <a href="<?php echo Route::url('?page=docs&action=/modules/docs/pages/getting-started-post.page'); ?>">Create complete Module</a> guide for an initial overview.</p>

</div>