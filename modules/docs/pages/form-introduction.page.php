<?php
namespace Modules\docs;
/**
* @title Introduction
* @category Forms
* @order 10
* @tags 
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
   <h1>Introduction to form creation</h1>
 
   <p class="alert alert-info">The following documents show how to print various form elements through two systems: the FORM class and template plugins. <br>However, there is a faster system to print a form starting from the structure of the class that extends AbstractObjects. <br>


   <h4 class="mt-2">Form creation</h4>
   <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;div class="card-body"&gt;
   &lt;?php 
       echo ObjectToForm::start($page, $url_success, '', $action_save);
       ?&gt;
       &lt;div class="form-group col-xl-6"&gt;
       &lt;?php
           // extract all form fields with edit = true
           foreach ($data->get_rules('edit', true) as $key => $rule) {
               // Automatically generates the form field based on the rule
               echo ObjectToForm::row($rule, $data->$key);
           } 
       ?&gt;
       &lt;/div&gt;
       &lt;?php
       echo ObjectToForm::submit();
       echo ObjectToForm::end();
       ?&gt;
   &lt;/div&gt;</code></pre>



<p><code>$data->get_rules('edit', true)</code> extracts all class fields that have the <code>edit</code> property set to <code>true</code>. Considering that custom properties can be added within the rules, this allows creating automatic field groups to extract various fields. <code>ObjectToForm::row($rule, $data->$key)</code> converts the various fields into form elements.</p> <p>This way it's possible to create form parts or entire forms more quickly</p>

<h4 class="mt-2">Data saving</h4>
<p>Always if an Object class extending AbstractObject and a model class extending AbstractModel have been set up, during the saving phase you can take advantage of a series of facilitations.</p>

<pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">$obj = $this->model->get_empty($_REQUEST);
$array_to_save = to_mysql_array($obj);

if ($this->model->validate($array_to_save)) {
   if ($this->model->save($array_to_save, $id)) {
       ...
   }
}</code></pre>

<p><code>$this->model->get_empty($_REQUEST)</code> returns an object with data compiled from $_REQUEST. <code>to_mysql_array($obj)</code> converts this object into an array that can be saved based on the model.</p>
<p><code>$this->model->validate($array_to_save)</code> validates the data based on the Object class rules.</p>
<p><code>$this->model->save($array_to_save, $id)</code> saves the data based on the model.</p>

<p>Obviously all this is optional and should be used only if it facilitates the work.</p>

</div>