<?php
namespace Modules\Posts;
use MilkCore\AbstractModel;

!defined('MILK_DIR') && die(); // Prevents direct access

class PostsModel extends AbstractModel
{
    public string $table = '#__posts';
    public string $object_class = 'PostsObject';

    public function after_create_table() {
        $sql =  "INSERT INTO `".$this->table."` (`id`, `title`, `content`, `created_at`, `updated_at`) VALUES
    (1, 'Post Title 1', 'Content of post 1', '2024-11-01 10:00:00',  '2024-11-01 10:00:00'),
    (2, 'Post Title 2', 'Content of post 2', '2024-11-02 11:30:00', '2024-11-02 11:30:00'),
    (3, 'Post Title 3', 'Content of post 3', '2024-12-03 15:45:00', '2024-12-03 15:45:00');";
            $this->db->query($sql);
    }
}