<?php
namespace Modules\Docs\Pages;
/**
* @title Introduction
* @order 10
* @tags forms, ObjectToForm, automatic generation, rules, validation
*/
!defined('MILK_DIR') && die(); // Avoid direct access
?>

<div class="bg-white p-4">
   <h1>Introduction to form creation</h1>

   <div class="alert alert-primary">
      <h5 class="alert-heading">Quick Form Creation with FormBuilder</h5>
      <p class="mb-0">
         This is a manual, artisanal system for creating forms. If you need to create forms quickly from your Models,
         we recommend using the <strong>FormBuilder</strong> which can generate complete forms in minutes:
         <br><br>
         <a href="?page=docs&action=Developer/Form/builders-form" class="alert-link">
            <strong>→ Getting Started - Forms with FormBuilder</strong>
         </a>
      </p>
   </div>

   <p class="alert alert-info">The following documents show how to print various form elements through two systems: the FORM class and template plugins. <br>However, there is a faster system to print a form starting from the structure of the class that extends AbstractObjects. <br>

   <h4 class="mt-2">Form creation</h4>
   <pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">&lt;div class="card-body"&gt;
   &lt;?php 
       echo ObjectToForm::start($page, $action_save);
       ?&gt;
       &lt;div class="form-group col-xl-6"&gt;
       &lt;?php
           // extract all form fields with edit = true
           foreach ($data->getRules('edit', true) as $key => $rule) {
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

<p><code>$data->getRules('edit', true)</code> extracts all class fields that have the <code>edit</code> property set to <code>true</code>. Considering that custom properties can be added within the rules, this allows creating automatic field groups to extract various fields. <code>ObjectToForm::row($rule, $data->$key)</code> converts the various fields into form elements.</p> <p>This way it's possible to create form parts or entire forms more quickly</p>

<h4 class="mt-2">Data saving</h4>
<p>Always if an Object class extending AbstractObject and a model class extending AbstractModel have been set up, during the saving phase you can take advantage of a series of facilitations.</p>

<pre class="pre-scrollable border p-2" class="text-bg-gray"><code class="language-php">$obj = $this->model->getEmpty($_REQUEST);

if ($obj->validate()) {
   if ($obj->save()) {
       ...
   }
}</code></pre>

<p><code>$this->model->getEmpty($_REQUEST)</code> returns a Model instance with data compiled from $_REQUEST.</p>
<p><code>$obj->validate()</code> validates the internal data based on the Object class rules.</p>
<p><code>$obj->save()</code> saves the internal data to the database.</p>

<p>Obviously all this is optional and should be used only if it facilitates the work.</p>

</div>