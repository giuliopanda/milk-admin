<?php
namespace Modules\Posts;
use MilkCore\AbstractObject;
use MilkCore\Route;
use MilkCore\Get;

!defined('MILK_DIR') && die(); // Prevents direct access

class PostsObject extends AbstractObject
{
    public function init_rules() {
        $this->rule('id', [
            'type' => 'id',  'form-type' => 'hidden'
        ]);
        $this->rule('title', [
            'type' => 'string', 'length' => 100, 'label' => 'Title',
            'form-params' => [
                'invalid-feedback'=>'The title is required', 'required' => true
            ]
        ]);
        
        $this->rule('content', [
            'type' => 'text', 'label' => 'Content', 
            'form-type' => 'editor'
        ]);

        $this->rule('created_at', [
            'type' => 'datetime', 'label' => 'Creation date', 'form-type' => 'hidden'
           
        ]);
        $this->rule('updated_at', [
            'type' => 'datetime', 'list' => false, 'edit' => false,
        ]);
    }

    /**
     * Make the title clickable
     */
    public function get_title($value) {
        $link = Route::url('?page=posts&action=edit&id='.$this->attributes['id']);
        return '<a href="'.$link.'">'.$value.'</a>';
    }

    /**
     * Print in the creation date column also the date of the last update
     */
    public function get_created_at($value) {
        return 'Created at: '.Get::format_date($value, 'datetime'). "<br />Updated at: ".Get::format_date($this->attributes['updated_at'], 'datetime');
    }

    /**
     * Set the creation date if it has not been entered
     */
    public function set_created_at($value) {
        if ($value == '' || $value == '0000-00-00 00:00:00') {
            return date('Y-m-d H:i:s');
        } else {
            return $value;
        }
    }

    /**
     * Set the update date
     */
    public function set_updated_at($value) {
        return date('Y-m-d H:i:s');
    }

}