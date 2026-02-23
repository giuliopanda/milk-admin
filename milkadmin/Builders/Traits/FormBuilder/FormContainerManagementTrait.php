<?php
namespace Builders\Traits\FormBuilder;

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
     * @param array $fields Array of field names to include in the container.
     *                      You can also pass inline HTML snippets (strings containing '<' and '>'),
     *                      which will be rendered as HTML inside the grid.
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
     *
     * @example
     * // Mix fields and inline HTML inside the container
     * ->addContainer(['name', '<div class="small text-muted">Note</div>', 'email'], 3)
     *
     * @example
     * // Stack multiple fields in the same column using nested arrays
     * ->addContainer('contact', [['phone', 'email'], 'notes'], 2)
     */
    public function addContainer(string $id, array $fields, $cols, string $position_before = '', string $title = '', array $attributes = []): self {
        $normalized_fields = [];
        $normalized_column_groups = [];
        $inline_html_fields = [];
        $existing_names = array_fill_keys(array_keys($this->fields), true);

        foreach ($fields as $field_entry) {
            if (is_array($field_entry)) {
                $column_group = [];
                foreach ($field_entry as $group_entry) {
                    if (is_array($group_entry)) {
                        throw new \InvalidArgumentException('Nested container field arrays are supported only one level deep');
                    }
                    $normalized_name = $this->normalizeContainerFieldEntry($group_entry, $existing_names, $inline_html_fields);
                    $column_group[] = $normalized_name;
                    $normalized_fields[] = $normalized_name;
                }
                if (!empty($column_group)) {
                    $normalized_column_groups[] = $column_group;
                }
                continue;
            }

            $normalized_name = $this->normalizeContainerFieldEntry($field_entry, $existing_names, $inline_html_fields);
            $normalized_fields[] = $normalized_name;
            $normalized_column_groups[] = [$normalized_name];
        }

        foreach ($inline_html_fields as $inline_name => $html) {
            $this->fields[$inline_name] = [
                'type' => 'html',
                'value' => '',
                'html' => $html,
                'name' => $inline_name
            ];
        }

        // Calculate column distribution
        $column_distribution = $this->calculateColumnDistribution($normalized_column_groups, $cols);

        // Generate container HTML with fields
        $this->insertContainerIntoFields($id, $normalized_fields, $column_distribution, $position_before, $title, $attributes);

        return $this;
    }

    private function normalizeContainerFieldEntry($field_entry, array &$existing_names, array &$inline_html_fields): string {
        if ($this->isInlineHtmlSnippet($field_entry)) {
            $inline_name = $this->generateInlineHtmlFieldName($existing_names);
            $existing_names[$inline_name] = true;
            $inline_html_fields[$inline_name] = $field_entry;
            return $inline_name;
        }

        if (!is_string($field_entry) && !is_numeric($field_entry)) {
            throw new \InvalidArgumentException('Container field entries must be field names, inline HTML, or one-level arrays');
        }

        $field_name = (string) $field_entry;
        if ($field_name === '') {
            throw new \InvalidArgumentException('Container field name cannot be empty');
        }

        if (!isset($this->fields[$field_name]) && isset($this->fields_copy[$field_name])) {
            if (isset($this->removed_fields[$field_name])) {
                unset($this->removed_fields[$field_name]);
            }
            $this->fields[$field_name] = $this->fields_copy[$field_name];
        } else if (!isset($this->fields[$field_name]) && !isset($this->fields_copy[$field_name])) {
            throw new \InvalidArgumentException("Field '{$field_name}' does not exist in the form");
        }

        return $field_name;
    }

    /**
     * Calculate column distribution for fields
     *
     * @param array $column_groups Array of column groups, each containing one or more field names
     * @param int|array $cols Number of columns or array of column sizes
     * @return array Array of rows, each containing columns with field names and sizes
     */
    private function calculateColumnDistribution(array $column_groups, $cols): array {
        $rows = [];
        $field_index = 0;
        $total_fields = count($column_groups);

        // Determine if $cols is an integer or array
        if (is_int($cols)) {
            // Equal column distribution
            $col_size = 12 / $cols;
            $cols_per_row = $cols;

            while ($field_index < $total_fields) {
                $row = [];
                for ($i = 0; $i < $cols_per_row && $field_index < $total_fields; $i++) {
                    $row[] = [
                        'fields' => $column_groups[$field_index],
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
                        'fields' => $column_groups[$field_index],
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

        // Add mb-3 class if no other classes are specified
        if (!isset($attributes['class']) || empty($attributes['class'])) {
            $attributes['class'] = 'mb-3';
        }

        $html_fields[$id] = [
            'type' => 'openTag',
            'tag' => 'div',
            'attributes' => $attributes,
            'id' => $id,
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
                $column_fields = [];
                if (isset($column['fields']) && is_array($column['fields'])) {
                    $column_fields = $column['fields'];
                } else if (isset($column['field'])) {
                    $column_fields = [$column['field']];
                }
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

                // Add the actual field(s)
                $total_column_fields = count($column_fields);
                $use_vertical_wrappers = $total_column_fields >= 2;
                foreach ($column_fields as $field_index => $field_name) {
                    if ($use_vertical_wrappers) {
                        $wrapper_class = 'milk-col-stack-item';
                        if ($field_index < $total_column_fields - 1) {
                            $wrapper_class .= ' mb-3';
                        }

                        $html_fields[$this->generateHtmlFieldName($html_counter)] = [
                            'type' => 'html',
                            'value' => '',
                            'html' => "<div class=\"{$wrapper_class}\">",
                            'name' => $this->generateHtmlFieldName($html_counter),
                            'form-params' => ['in-container' => true]
                        ];
                        $html_counter++;
                    }

                    if (isset($original_fields[$field_name])) {
                        // Mark field as being in a container to prevent extra wrappers
                        $field_data = $original_fields[$field_name];
                        if (!isset($field_data['form-params'])) {
                            $field_data['form-params'] = [];
                        }
                        $field_data['form-params']['in-container'] = true;
                        $html_fields[$field_name] = $field_data;
                    }

                    if ($use_vertical_wrappers) {
                        $html_fields[$this->generateHtmlFieldName($html_counter)] = [
                            'type' => 'html',
                            'value' => '',
                            'html' => '</div>',
                            'name' => $this->generateHtmlFieldName($html_counter),
                            'form-params' => ['in-container' => true]
                        ];
                        $html_counter++;
                    }
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

    private function isInlineHtmlSnippet($value): bool {
        return is_string($value) && strpos($value, '<') !== false && strpos($value, '>') !== false;
    }

    private function generateInlineHtmlFieldName(array $existing_names): string {
        $counter = 1;
        do {
            $field_name = 'HINLINE' . str_pad((string)$counter, 4, '0', STR_PAD_LEFT);
            $counter++;
        } while (isset($existing_names[$field_name]) || isset($this->fields[$field_name]));

        return $field_name;
    }
}
