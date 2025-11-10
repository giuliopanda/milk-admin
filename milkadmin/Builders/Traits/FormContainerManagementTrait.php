<?php
namespace Builders\Traits;

/**
 * Trait FormContainerManagementTrait
 *
 * Handles container functionality for FormBuilder with Bootstrap grid layout
 * Allows organizing fields in responsive columns with custom layouts
 *
 * @package Builders\Traits
 */
trait FormContainerManagementTrait {

    /**
     * Add a container with fields organized in columns
     *
     * This method creates a Bootstrap grid container with fields distributed across columns.
     * It automatically reorganizes the fields array to insert HTML fields for the grid structure.
     *
     * @param array $fields Array of field names to include in the container
     * @param int|array $cols Number of columns or array of column sizes (e.g., [4,5,3] for col-md-4, col-md-5, col-md-3)
     * @param string $position_before Field name before which to insert the container (empty = append at the end)
     * @param string $title Optional title for the container
     * @param array $attributes Additional attributes for the container div (class, style, id, etc.)
     * @return self For method chaining
     *
     * @example
     * // Simple 3-column layout inserted before 'status' field
     * ->addContainer(['name', 'email', 'phone'], 3, 'status', 'Contact Information')
     *
     * @example
     * // Custom column sizes with Bootstrap grid, appended at the end
     * ->addContainer(['field1', 'field2', 'field3'], [4, 5, 3], '', 'Custom Layout', ['class' => 'border p-3'])
     *
     * @example
     * // Two fields in equal columns with title and custom styling
     * ->addContainer(['first_name', 'last_name'], 2, 'email', 'Name', ['style' => 'background-color: #f8f9fa;', 'class' => 'rounded'])
     */
    public function addContainer(string $id, array $fields, $cols, string $position_before = '', string $title = '', array $attributes = []): self {
        // Validate that all specified fields exist
        foreach ($fields as $field_name) {
            if (!isset($this->fields[$field_name])) {
                throw new \InvalidArgumentException("Field '{$field_name}' does not exist in the form");
            }
        }

        // Calculate column distribution
        $column_distribution = $this->calculateColumnDistribution($fields, $cols);

        // Generate container HTML with fields
        $this->insertContainerIntoFields($id, $fields, $column_distribution, $position_before, $title, $attributes);

        return $this;
    }

    /**
     * Calculate column distribution for fields
     *
     * @param array $fields Array of field names
     * @param int|array $cols Number of columns or array of column sizes
     * @return array Array of rows, each containing columns with field names and sizes
     */
    private function calculateColumnDistribution(array $fields, $cols): array {
        $rows = [];
        $field_index = 0;
        $total_fields = count($fields);

        // Determine if $cols is an integer or array
        if (is_int($cols)) {
            // Equal column distribution
            $col_size = 12 / $cols;
            $cols_per_row = $cols;

            while ($field_index < $total_fields) {
                $row = [];
                for ($i = 0; $i < $cols_per_row && $field_index < $total_fields; $i++) {
                    $row[] = [
                        'field' => $fields[$field_index],
                        'size' => $col_size
                    ];
                    $field_index++;
                }
                $rows[] = $row;
            }
        } else if (is_array($cols)) {
            // Custom column sizes distribution
            while ($field_index < $total_fields) {
                $row = [];
                foreach ($cols as $col_size) {
                    if ($field_index >= $total_fields) {
                        break;
                    }
                    $row[] = [
                        'field' => $fields[$field_index],
                        'size' => $col_size
                    ];
                    $field_index++;
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Insert container structure into fields array
     *
     * @param array $fields Array of field names to containerize
     * @param array $column_distribution Array of rows with column distribution
     * @param string $position_before Field name before which to insert the container
     * @param string $title Container title
     * @param array $attributes Container attributes
     */
    private function insertContainerIntoFields(string $id, array $fields, array $column_distribution, string $position_before, string $title, array $attributes): void {
        // Store original field data
        $original_fields = [];
        foreach ($fields as $field_name) {
            $original_fields[$field_name] = $this->fields[$field_name];
        }

        // Build container HTML
        $container_html = $this->buildContainerHtml($id, $original_fields, $column_distribution, $title, $attributes);

        // Rebuild fields array with container inserted at the right position
        $new_fields = [];
        $container_inserted = false;
        $fields_set = array_flip($fields); // For fast lookup

        foreach ($this->fields as $field_name => $field_data) {
            // Skip fields that should be in the container
            if (isset($fields_set[$field_name])) {
                continue;
            }

            // Insert container before the specified field
            if (!$container_inserted && !empty($position_before) && $field_name === $position_before) {
                foreach ($container_html as $html_field_name => $html_field) {
                    $new_fields[$html_field_name] = $html_field;
                }
                $container_inserted = true;
            }

            // Add the current field
            $new_fields[$field_name] = $field_data;
        }

        // If container not inserted yet (position_before not found or empty), append at the end
        if (!$container_inserted) {
            foreach ($container_html as $html_field_name => $html_field) {
                $new_fields[$html_field_name] = $html_field;
            }
        }

        $this->fields = $new_fields;
    }

    /**
     * Build container HTML with Bootstrap grid structure
     *
     * @param array $original_fields Original field definitions
     * @param array $column_distribution Array of rows with column distribution
     * @param string $title Container title
     * @param array $attributes Container attributes
     * @return array Array of HTML field definitions to insert
     */
    private function buildContainerHtml(string $id, array $original_fields, array $column_distribution, string $title, array $attributes): array {
        $html_fields = [];

        // Start counter from a number that won't conflict with existing HCNT fields
        $html_counter = 1;
        $existing_hcnt_fields = array_filter(array_keys($this->fields), function($key) {
            return strpos($key, 'HCNT') === 0;
        });
        if (!empty($existing_hcnt_fields)) {
            // Extract highest number and start from there + 1
            $max_num = 0;
            foreach ($existing_hcnt_fields as $field_name) {
                $num = intval(substr($field_name, 4));
                if ($num > $max_num) {
                    $max_num = $num;
                }
            }
            $html_counter = $max_num + 1;
        }

     
        $html_fields[$id] = [
            'type' => 'openTag',
            'tag' => 'div',
            'attributes' => $attributes,
            'id' => $id,
            'name' => $id,
            'form-params' => ['in-container' => true]
        ];

        // Add title if provided
        if (!empty($title)) {
            $html_fields[$this->generateHtmlFieldName($html_counter)] = [
                'type' => 'html',
                'value' => '',
                'html' => "<h4 class=\"mb-3\">" . _rh($title) . "</h4>",
                'name' => $this->generateHtmlFieldName($html_counter),
                'form-params' => ['in-container' => true]
            ];
            $html_counter++;
        }

        // Process each row
        $total_rows = count($column_distribution);
        foreach ($column_distribution as $row_index => $row) {
            // Build row classes: milk-row-X and mb-3 (except for last row)
            $row_num = $row_index + 1;
            $row_classes = "row g-3 milk-row-{$row_num}";
            if ($row_index < $total_rows - 1) {
                $row_classes .= " mb-3";
            }

            // Open row
            $html_fields[$this->generateHtmlFieldName($html_counter)] = [
                'type' => 'html',
                'value' => '',
                'html' => "<div class=\"{$row_classes}\">",
                'name' => $this->generateHtmlFieldName($html_counter),
                'form-params' => ['in-container' => true]
            ];
            $html_counter++;

            // Process each column in the row
            foreach ($row as $column) {
                $field_name = $column['field'];
                $col_size = $column['size'];

                // Open column
                $html_fields[$this->generateHtmlFieldName($html_counter)] = [
                    'type' => 'html',
                    'value' => '',
                    'html' => "<div class=\"col-md-{$col_size}\">",
                    'name' => $this->generateHtmlFieldName($html_counter),
                    'form-params' => ['in-container' => true]
                ];
                $html_counter++;

                // Add the actual field
                if (isset($original_fields[$field_name])) {
                    // Mark field as being in a container to prevent extra wrappers
                    $field_data = $original_fields[$field_name];
                    if (!isset($field_data['form-params'])) {
                        $field_data['form-params'] = [];
                    }
                    $field_data['form-params']['in-container'] = true;
                    $html_fields[$field_name] = $field_data;
                }

                // Close column
                $html_fields[$this->generateHtmlFieldName($html_counter)] = [
                    'type' => 'html',
                    'value' => '',
                    'html' => '</div>',
                    'name' => $this->generateHtmlFieldName($html_counter),
                    'form-params' => ['in-container' => true]
                ];
                $html_counter++;
            }

            // Close row
            $html_fields[$this->generateHtmlFieldName($html_counter)] = [
                'type' => 'html',
                'value' => '',
                'html' => '</div>',
                'name' => $this->generateHtmlFieldName($html_counter),
                'form-params' => ['in-container' => true]
            ];
            $html_counter++;
        }

        // Close container
        $html_fields[$this->generateHtmlFieldName($html_counter)] = [
            'type' => 'html',
            'value' => '',
            'html' => '</div>',
            'name' => $this->generateHtmlFieldName($html_counter),
            'form-params' => ['in-container' => true]
        ];

        return $html_fields;
    }

    /**
     * Generate a unique HTML field name
     *
     * @param int $counter Counter for unique naming
     * @return string Generated field name
     */
    private function generateHtmlFieldName(int $counter): string {
        $field_name = "HCNT" . str_pad($counter, 4, '0', STR_PAD_LEFT);

        // Ensure uniqueness
        while (isset($this->fields[$field_name])) {
            $counter++;
            $field_name = "HCNT" . str_pad($counter, 4, '0', STR_PAD_LEFT);
        }

        return $field_name;
    }

}
