<?php
namespace Modules\Docs\Pages;
/**
 * @title Getting Started - Forms
 * @guide developer
 * @order 30
 * @tags forms, FormBuilder, tutorial, getting-started, beginner, create-forms, automatic-forms, save-data
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">
     <h1>Getting Started with Forms</h1>
    <p class="text-muted">Revision: 2025/10/18</p>
    <p class="lead">This tutorial will guide you through creating forms in MilkAdmin. You can create forms automatically starting from the Model with the Builder or manage them manually with the framework classes.</p>
   
    <h2 class="mt-4">FormBuilder</h2>
    <p>The fastest way to create forms in MilkAdmin is to use the <strong>FormBuilder</strong>, which automatically generates form fields from the Model structure.</p>
    <h3>Basic Example</h3>
    <p>Starting from an already configured model, you can create a complete form simply by writing:</p>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">use Builders\FormBuilder;
use App\{Response, Abstract\AbstractModule};
class ProductModule extends AbstractModule {
    #[RequestAction('edit')]
    public function actionEdit() {
        $form = FormBuilder::create($this->model)->getForm();
        Response::render($form);
    }
}</code></pre>
    <div class="alert alert-success mt-3">
        <strong>✅ Done!</strong> With these few lines you have a complete form with:
        <ul class="mb-0">
            <li>All Model fields</li>
            <li>Automatic validation</li>
            <li>Save button</li>
            <li>CSRF token handling</li>
            <li>Saving for new or modified records</li>
        </ul>
    </div>
    <p>To learn more about creating forms with the FormBuilder, consult the complete documentation at <a href="?page=docs&action=Developer/Form/builders-form">FormBuilder Class Documentation</a></p>
    <h2 class="mt-4">Under the Hood</h2>
    <p>Behind the Form Builder there is a complete and accessible system for creating forms and validating data...</p>
    <p>All of this part is also accessible separately and is documented in detail in the <strong>Forms</strong> category:
        <br><br>
        <a href="?page=docs&action=Framework/Forms/form-introduction" class="alert-link">
            <strong>→ Introduction to Form Creation</strong>
        </a>
        <br>
    </p>
  
    <h2 class="mt-4">Next Steps</h2>
    <div class="alert alert-success">
        <strong>🎉 Congratulations!</strong> Now you know how to create forms in MilkAdmin using the FormBuilder.
        <p class="mt-3 mb-0"><strong>To learn more:</strong></p>
        <ul class="mb-0">
            <li><a href="?page=docs&action=Developer/Form/builders-form"><strong>FormBuilder Class Documentation</strong></a> - Complete FormBuilder documentation</li>
            <li><a href="?page=docs&action=Developer/Form/builders-form-fields"><strong>Field Management</strong></a> - Advanced field management</li>
            <li><a href="?page=docs&action=Developer/Form/builders-form-validation"><strong>Form Validation</strong></a> - Custom validation</li>
            <li><a href="?page=docs&action=Developer/Form/builders-form-conditional-visibility"><strong>Conditional Visibility</strong></a> - Show/hide fields dynamically</li>
            <li><a href="?page=docs&action=Framework/Forms/form-introduction"><strong>Manual Form System</strong></a> - Manual system for complex forms</li>
        </ul>
    </div>
</div>