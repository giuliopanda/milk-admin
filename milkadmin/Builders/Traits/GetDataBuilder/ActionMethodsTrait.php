<?php

namespace Builders\Traits\GetDataBuilder;

use App\{MessagesHandler, Config};

!defined('MILK_DIR') && die();

/**
 * ActionMethodsTrait - Action management methods
 */
trait ActionMethodsTrait
{
    /**
     * Add a single row action
     */
    public function addAction(string $key, array $config): static
    {
        $this->actions->addRowAction($key, $config);
        return $this;
    }

    /**
     * Set all row actions (replaces existing)
     */
    public function setActions(array $actions): static
    {
        $this->actions->setRowActions($actions);
        return $this;
    }

    /**
     * Set default actions (Edit and Delete)
     */
    public function setDefaultActions(array $customActions = []): static
    {
        $this->actions->setDefaultActions($customActions, [$this, 'actionDeleteRow']);
        return $this;
    }

    /**
     * Add a single bulk action
     */
    public function addBulkAction(array $config): static
    {
        $this->actions->addBulkAction($config);
        return $this;
    }

    /**
     * Set bulk actions for selected rows
     */
    public function setBulkActions(array $actions): static
    {
        $this->actions->setBulkActions($actions);
        return $this;
    }

    /**
     * Default delete action handler
     */
    public function actionDeleteRow($records, $request): bool
    {
        if (!is_countable($records) || count($records) === 0) {
            MessagesHandler::addError('No items selected');
            return false;
        }

        $pk = $this->context->getModel()->getPrimaryKey();

        foreach ($records as $record) {
            $id = $record->{$pk};

            try {
                if (!$this->context->getModel()->delete($id)) {
                    MessagesHandler::addError($this->context->getModel()->getLastError());
                    return false;
                }

                MessagesHandler::addSuccess('Item deleted successfully');
            } catch (\Exception $e) {
                if (Config::get('debug', false)) {
                    throw $e;
                }

                MessagesHandler::addError($e->getMessage());
                return false;
            }
        }

        return true;
    }
}
