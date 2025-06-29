<?php
namespace Modules\Posts;
use MilkCore\Get;
use MilkCore\MessagesHandler;

!defined('MILK_DIR') && die(); // Avoid direct access

echo Get::theme_plugin('title', ['title_txt' => "Posts", 'description' => 'This is a sample module to show how to create a basic module on Milk Admin. Go to the modules/posts folder to see the code.',  'btns' => [ ['title'=>'Add New', 'color'=>'primary', 'link'=>'?page='.$page.'&action=edit']]]);

MessagesHandler::display_messages();

?>
<div class="my-4 row">
    <div class="col">
        <div class="input-group d-inline-flex ms-2" style="width: auto; vertical-align: middle;">
            <input class="form-control" type="search" placeholder="Search" aria-label="Search" spellcheck="false" data-ms-editor="true" id="table_posts_search" >
        </div>
    </div>
</div>
<br>
<?php echo $table_html;