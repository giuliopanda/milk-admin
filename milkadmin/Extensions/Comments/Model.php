<?php
namespace Extensions\Comments;

use App\Abstracts\{AbstractModelExtension, RuleBuilder};
use App\{Get, Hooks};


!defined('MILK_DIR') && die();

/**
 * Comments Model Extension
 *
 * Adds comment functionality to any model with:
 * - HasMany relationship to comments
 * - Automatic tracking of created_by/created_at
 * - Automatic tracking of updated_by/updated_at
 * - Dynamic table naming based on parent model
 *
 * @package Extensions\Comments
 */
class Model extends AbstractModelExtension
{
    /**
     * Foreign key field name in comments table
     * @var string
     */
    protected $foreign_key = 'entity_id';

    /**
     * Label for the entity being commented
     * @var string
     */
    protected $entity_label = 'Entity';

    /**
     * Field name for the comment text
     * @var string
     */
    protected $comment_field = 'comment';

    /**
     * Hook called during model configuration
     * Adds hasMany relationship to comments
     *
     * @param RuleBuilder $rule_builder The rule builder instance
     * @return void
     */
    public function configure(RuleBuilder $rule_builder): void
    {
        // Set up hook to configure CommentsModel dynamically FIRST
        // This must be done before creating the hasMany relationship
        Hooks::remove('CommentsModel.getTableParams');
        Hooks::set('CommentsModel.getTableParams', [$this, 'getTableParams']);

        // Add hasMany relationship to comments
        $rule_builder
            ->ChangeCurrentField($rule_builder->getPrimaryKey())
            ->hasMany('comments', CommentsModel::class, $this->foreign_key);
    }

    /**
     * Configure the CommentsModel dynamically based on parent model
     * This is called via hook when CommentsModel is instantiated
     *
     * @param RuleBuilder $rule The rule builder for CommentsModel
     * @param CommentsModel $commentsModel The comments model instance
     * @return void
     */
    public function getTableParams(): array
    {
        // Extract params from array (Hooks::run passes params as array)
        $parentModel = $this->model->get();
        $parentTable = $parentModel->getTable();
        return ['table_name' => $parentTable . '_comments',
                'foreign_key' => $this->foreign_key,
                'entity_label' => $this->entity_label,
                'db' => $parentModel->getDbType(),
                'comment_field' => $this->comment_field];

    }

   
}
