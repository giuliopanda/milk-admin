<?php
namespace Extensions\Comments;

use App\Abstracts\AbstractGetDataBuilderExtension;
use App\{Get, Permissions};

!defined('MILK_DIR') && die();

/**
 * Comments GetDataBuilder Extension
 *
 * Adds a "comments" column to list views that shows:
 * - Number of comments for the entity
 * - Clickable link to open comments offcanvas
 * - Chat icon for visual clarity
 *
 * @package Extensions\Comments
 */
class GetDataBuilder extends AbstractGetDataBuilderExtension
{
    /**
     * Hook called during builder configuration
     * Adds the comments column to the list view
     *
     * @param object $builder The builder instance
     * @return void
     */
    public function configure(object $builder): void
    {
        $page = $builder->getPage();

        $builder->field('comments')
            ->label('Comments')
            ->fn(function ($row) use ($page) {
                $commentsCount = count($row->comments);
                return '<a href="?page=' . $page . '&action=comments&entity_id=' . $row->id . '" data-fetch="post">'
                    . $commentsCount . ' <i class="bi bi-chat-dots"></i></a>';
            });
    }
}
