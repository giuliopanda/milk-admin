<?php
namespace Modules\Posts;
use App\Attributes\{Validate};
use App\Abstracts\AbstractModel;

class PostsModel extends AbstractModel
{
   
    protected function configure($rule): void
    {
        $rule->table('#__posts')
            ->id()
            ->hasMany('comments', CommentsModel::class, 'post_id', 'CASCADE', false)
            ->title()->index()
            ->text('content')->formType('editor');

    }

    #[Validate('title')]
    public function validateTitle($current_record_obj): string {
        $value = $current_record_obj->title;
        if (strlen($value) < 5) {
            return 'Title must be at least 5 characters long';
        }
        return '';
    }

    protected function afterCreateTable(): void {
        $sql =  "INSERT INTO `".$this->table."` (`id`, `title`, `content`) VALUES
    (1, 'Post Title 1', 'Content of post 1'),
    (2, 'Post Title 2', 'Content of post 2'),
    (3, 'Post Title 3', 'Content of post 3');";
            $this->db->query($sql);
    }
}