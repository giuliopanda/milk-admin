<?php
namespace Modules\Posts;
use MilkCore\Get;
use MilkCore\ObjectToForm;
use MilkCore\MessagesHandler;

!defined('MILK_DIR') && die(); // Avoid direct access

$title = _absint($_REQUEST['id'] ?? 0) > 0 ? 'Edit Post' : 'Add Post';
echo Get::theme_plugin('title', ['title_txt' => $title, 'description' => 'A basic module to show an example table.']);
MessagesHandler::display_messages();

?>
<div class="card">
    <div class="card-body">
        <?php 
        echo ObjectToForm::start($page, $url_success, '', $action_save);
        ?>
        <div class="form-group col-xl-6">
            <?php
            // extract all form fields with edit = true
            foreach ($data->get_rules('edit', true) as $key => $rule) {
                echo ObjectToForm::row($rule, $data->$key);
            } 
            ?>
        </div>
        <?php
        echo ObjectToForm::submit();
        echo ObjectToForm::end();
        ?>
    </div>
</div>