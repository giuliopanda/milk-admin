<?php
namespace Extensions\Author;

use App\Abstracts\AbstractInstallExtension;
use App\{Cli};

!defined('MILK_DIR') && die();

/**
 * Author Install Extension
 *
 * Handles installation and updates for the Author extension.
 * Adds created_by column to the module's table.
 *
 * @package Extensions\Author
 */
class Install extends AbstractInstallExtension
{
    /**
     * Execute module installation
     * Adds the created_by column to the table
     *
     * @param array $data Installation data
     * @return array Modified data
     */
    public function installExecute(array $data = []): array
    {
        $this->addCreatedByColumn();
        return $data;
    }

    /**
     * Execute module update
     * Ensures the created_by column exists
     *
     * @param string $html Update HTML
     * @return string Modified HTML
     */
    public function installUpdate(string $html = ''): string
    {
        $this->addCreatedByColumn();

        $message = '<div class="alert alert-success">';
        $message .= 'Author extension: created_by column verified/added successfully.';
        $message .= '</div>';
        $html .= $message;

        return $html;
    }

    /**
     * Execute module uninstallation
     * Optionally remove the created_by column (commented out by default for safety)
     *
     * @return void
     */
    public function shellUninstallModule(): void
    {
        // Uncomment to remove the column on uninstall
        // WARNING: This will delete the created_by data permanently!
        // $this->removeCreatedByColumn();

        Cli::echo('Author extension: created_by column preserved (not removed on uninstall)');
    }

    /**
     * Add created_by column to the module's table
     *
     * @return bool Success status
     */
    private function addCreatedByColumn(): bool
    {
        $module = $this->module->get();
        $model = $module->getModel();

        if (!$model) {
            Cli::error('Author extension: Model not found');
            return false;
        }

        $schema = $model->getSchema();

        if (!$schema) {
            Cli::error('Author extension: Schema not available');
            return false;
        }

        // Check if column already exists
        if ($schema->columnExists('created_by')) {
            Cli::echo('Author extension: created_by column already exists');
            return true;
        }

        // Add the created_by column
        $schema->int('created_by', true, 0);

        // Modify the table (adds the new column)
        if ($schema->modify()) {
            Cli::success('Author extension: created_by column added successfully');
            return true;
        } else {
            Cli::error('Author extension: Failed to add created_by column');
            return false;
        }
    }

    /**
     * Remove created_by column from the module's table
     * Use with caution - this deletes data permanently!
     *
     * @return bool Success status
     */
    private function removeCreatedByColumn(): bool
    {
        $module = $this->module->get();
        $model = $module->getModel();

        if (!$model) {
            return false;
        }

        $schema = $model->getSchema();

        if (!$schema) {
            return false;
        }

        // Check if column exists
        if (!$schema->columnExists('created_by')) {
            Cli::echo('Author extension: created_by column does not exist');
            return true;
        }

        // Remove the column
        $schema->removeColumn('created_by');

        if ($schema->modify()) {
            Cli::success('Author extension: created_by column removed successfully');
            return true;
        } else {
            Cli::error('Author extension: Failed to remove created_by column');
            return false;
        }
    }
}
