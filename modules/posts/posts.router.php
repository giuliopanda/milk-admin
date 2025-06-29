<?php
namespace Modules\Posts;
use MilkCore\AbstractRouter;
use MilkCore\Theme;
use MilkCore\Get;
use MilkCore\Route;
use MilkCore\MessagesHandler;

!defined('MILK_DIR') && die(); // Prevents direct access

/**
 * The router manages the pages that need to be loaded.
 * If the link is &action=my_page, the function action_my_page() will be automatically searched
 */


class PostsRouter extends AbstractRouter
{

    protected function action_home() {
        $table_id = 'table_posts';

        $this->call_table_action($table_id, 'delete', 'table_action_delete');

        $modellist_data = $this->get_modellist_data($table_id);
        $modellist_data['info']->set_action( [$table_id.'-edit' => 'Edit', $table_id.'-delete' => 'Delete']);
        $modellist_data['info']->set_type('title', 'html');
        // Di default created_at è datetime ma dall'oggetto ho messo return get_created_at
        $modellist_data['info']->set_type('created_at', 'html');
       
        $this->output_table_response(__DIR__.'/views/list.page.php', $modellist_data);
    }

    protected function action_edit() {
        $id = _absint($_REQUEST['id'] ?? 0);
        $data = $this->model->get_by_id_for_edit($id,  Route::get_session_data()); 
        Get::theme_page('default', __DIR__ . '/views/edit.page.php',  ['id' => _absint($_REQUEST['id'] ?? 0), 'data' => $data, 'page' => $this->page, 'url_success'=>'?page='.$this->page, 'action_save'=>'save']);
    }

    protected function action_save() {
        $id = _absint($_REQUEST[$this->model->get_primary_key()] ?? 0);
        $obj = $this->model->get_empty($_REQUEST);
        $array_to_save = to_mysql_array($obj);
        
        if ($this->model->validate($array_to_save)) {
            if ($this->model->save($array_to_save, $id)) {
                Route::redirect_success( $_REQUEST['url_success'], _r('Save successful'));
            } else {
                $error = "An error occurred while saving the data. ".$this->model->get_last_error();
                $obj2 = $this->model->get_by_id_or_empty($id, $_REQUEST);
                Route::redirect_error($_REQUEST['url_error'], $error, to_mysql_array($obj2));
            }
        } 

        Route::redirect_handler_errors($_REQUEST['url_error'],  $array_to_save);
    }

    protected function table_action_delete($id, $request) {
        if ($this->model->delete($id)) {
            return true;
        } else {
            MessagesHandler::add_error($this->model->get_last_error());
            return false;
        }
    }
}