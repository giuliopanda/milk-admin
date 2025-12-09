<?php
namespace App\Abstracts\Traits;

!defined('MILK_DIR') && die();

/**
 * Extension Management Trait
 *
 * Provides common functionality for managing extensions in both Models and Modules.
 * Handles normalization and merging of extension arrays.
 */
trait ExtensionManagementTrait
{
    /**
     * Normalize extensions array to associative format
     * Converts ['ExtName'] to ['ExtName' => []]
     * Also handles ['ExtName' => ['param' => 'value']] format
     *
     * @param array $extensions Extensions array
     * @return array Normalized extensions
     */
    protected function normalizeExtensions(array $extensions): array
    {
        $normalized = [];

        foreach ($extensions as $key => $value) {
            if (is_int($key)) {
                // Simple format: ['Audit']
                $normalized[$value] = [];
            } else {
                // Associative format: ['Audit' => ['show_menu' => true]]
                $normalized[$key] = is_array($value) ? $value : [];
            }
        }

        return $normalized;
    }

    /**
     * Merge two extension arrays, with newer parameters overwriting older ones
     *
     * @param array $original Original extensions
     * @param array $new New extensions to merge
     * @return array Merged extensions
     */
    protected function mergeExtensions(array $original, array $new): array
    {
        // Normalize both arrays first
        $original = $this->normalizeExtensions($original);
        $new = $this->normalizeExtensions($new);

        foreach ($new as $ext_name => $params) {
            if (isset($original[$ext_name])) {
                // Merge parameters, new values overwrite old ones
                $original[$ext_name] = array_merge($original[$ext_name], $params);
            } else {
                // Add new extension
                $original[$ext_name] = $params;
            }
        }

        return $original;
    }
}
