<?php
namespace Builders\Traits;

!defined('MILK_DIR') && die(); // Prevents direct access

/**
 * MethodFirstCompatibilityTrait - OLD Method-first style methods (DEPRECATED)
 *
 * These methods provide backward compatibility with the old Method-first API style.
 * New code should use the Field-first style instead.
 *
 * OLD style (deprecated):
 * ->setLabel('created_at', 'Publication Date')
 * ->asLink('title', '?page=posts&action=edit&id=%id%')
 *
 * NEW style (recommended):
 * ->field('created_at')->label('Publication Date')
 * ->field('title')->link('?page=posts&action=edit&id=%id%')
 *
 * @package Builders\Traits
 */
trait MethodFirstCompatibilityTrait
{
    /**
     * Set the label of a column (OLD Method-first style)
     *
     * @deprecated Use field()->label() instead
     * @param string $key Column key/name
     * @param string $label Display label for the column
     * @return static For method chaining
     *
     * @example ->setLabel('created_at', 'Publication Date')
     */
    public function setLabel($key, $label): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        $this->custom_columns[$key]['label'] = $label;
        return $this;
    }

    /**
     * Set the type of a column (OLD Method-first style)
     *
     * @deprecated Use field()->type() instead
     * @param string $key Column key/name
     * @param string $type Column type (text, select, date, html, etc.)
     * @return static For method chaining
     *
     * @example ->setType('status', 'select')
     */
    public function setType($key, $type): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        $this->custom_columns[$key]['type'] = $type;
        return $this;
    }

    /**
     * Set options for a column (OLD Method-first style)
     *
     * @deprecated Use field()->options() instead
     * @param string $key Column key/name
     * @param array $options Options for select type columns
     * @return static For method chaining
     *
     * @example ->setOptions('status', ['published' => 'Published', 'draft' => 'Draft'])
     */
    public function setOptions($key, $options): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        $this->custom_columns[$key]['options'] = $options;
        return $this;
    }

    /**
     * Set processing function for a column (OLD Method-first style)
     *
     * @deprecated Use field()->fn() instead
     * @param string $key Column key/name
     * @param callable $fn Function that processes column data ($row_array) => string
     * @return static For method chaining
     *
     * @example ->setFn('title', function($row) { return '<a href="?page=posts&id=' . $row['id'] . '">' . $row['title'] . '</a>'; })
     */
    public function setFn($key, callable $fn): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        $this->custom_columns[$key]['fn'] = $fn;
        return $this;
    }

    /**
     * Set truncate property for a column to limit text length (OLD Method-first style)
     *
     * @deprecated Use field()->truncate() instead
     * @param string $key Column key/name
     * @param int $length Maximum length of text
     * @param string $suffix Suffix to append when text is truncated (default: '...')
     * @return static For method chaining
     *
     * @example ->setTruncate('description', 100)
     */
    public function setTruncate($key, $length, $suffix = '...'): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        if (!isset($this->column_properties[$key])) {
            $this->column_properties[$key] = [];
        }
        $this->column_properties[$key]['truncate'] = [
            'length' => $length,
            'suffix' => $suffix
        ];
        return $this;
    }

    /**
     * Convert a column to a clickable link (OLD Method-first style)
     *
     * @deprecated Use field()->link() instead
     * @param string $key Column key/name
     * @param string $link Link URL pattern with placeholders like %id%, %field_name%
     * @param array $options Additional options for the link (target, class, etc.)
     * @return static For method chaining
     *
     * @example ->asLink('title', '?page=posts&action=edit&id=%id%')
     */
    public function asLink($key, $link, $options = []): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        // If fetch mode is active, automatically add data-fetch attribute
        if ($this->fetch_mode && !isset($options['data-fetch'])) {
            $options['data-fetch'] = 'post';
        }

        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        // Automatically set type to html for links
        $this->custom_columns[$key]['type'] = 'html';
        // Store the formatter function to be registered later in getData()
        // The handler receives the entire row array from getFormattedValue
        $this->custom_columns[$key]['fn'] = function($row_array) use ($key, $link, $options) {
            // Replace placeholders in the link
            $final_link = $link;

            // Convert array to flat string values for URL placeholders
            $row_properties = is_array($row_array) ? $row_array : get_object_vars($row_array);

            // Get rules from model to check for date formatting
            $rules = $this->model->getRules();
            $field_rule = $rules[$key] ?? null;

            // Convert objects (like DateTime) to strings for URL replacement
            $flat_properties = [];
            foreach ($row_properties as $prop_key => $prop_value) {
                if (is_object($prop_value)) {
                    if ($prop_value instanceof \DateTime) {
                        $flat_properties[$prop_key] = $prop_value->format('Y-m-d H:i:s');
                    } else {
                        // Skip non-DateTime objects
                    }
                } elseif (is_scalar($prop_value)) {
                    $flat_properties[$prop_key] = (string)$prop_value;
                }
            }

            $id_value = $flat_properties['id'] ?? null;
            $final_link = \App\Route::replaceUrlPlaceholders($final_link, ['id' => $id_value, ...$flat_properties]);

            // Build link attributes
            $attributes = [];
            if (isset($options['target'])) {
                $attributes[] = 'target="' . _r($options['target']) . '"';
            }
            if (isset($options['class'])) {
                $attributes[] = 'class="' . _r($options['class']) . '"';
            }
            foreach ($options as $option => $value) {
                $attributes[] = $option . '="' . _r($value) . '"';
            }

            $attr_string = !empty($attributes) ? ' ' . implode(' ', $attributes) : '';

            // Get the column value to display as link text
            $display_text = $this->extractDotNotationValue($row_array, $key);

            // Format dates according to rules
            if ($field_rule && in_array($field_rule['type'], ['datetime', 'date', 'time'])) {
                if ($display_text instanceof \DateTime) {
                    $formatted = \App\Get::formatDate($display_text, $field_rule['type']);
                    $display_text = $formatted !== '' ? $formatted : $display_text->format('Y-m-d H:i:s');
                } elseif (is_string($display_text) && $display_text !== '') {
                    $formatted = \App\Get::formatDate($display_text, $field_rule['type']);
                    $display_text = $formatted !== '' ? $formatted : $display_text;
                }
            }

            return '<a href="' . \App\Route::url($final_link) . '"' . $attr_string . '>' . $display_text . '</a>';
        };

        return $this;
    }

    /**
     * Convert a column to fetch link (OLD Method-first style)
     *
     * @deprecated Use field()->link() with fetch mode instead
     * @param string $key Column key/name
     * @param string $link Link URL pattern
     * @param array $options Additional options for the link
     * @return static For method chaining
     */
    public function fetchLink($key, $link, $options = []): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        $options['data-fetch'] = "post";
        return $this->asLink($key, $link, $options);
    }

    /**
     * Convert a column to file download links (OLD Method-first style)
     *
     * @deprecated Use field()->file() instead
     * @param string $key Column key/name containing file array data
     * @param array $options Additional options for the links
     * @return static For method chaining
     *
     * @example ->asFile('attachments')
     */
    public function asFile($key, $options = []): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        // Automatically set type to html for file links
        $this->custom_columns[$key]['type'] = 'html';

        // Store the formatter function to be registered later in getData()
        $this->custom_columns[$key]['fn'] = function($row_array) use ($key, $options) {
            $value = $this->extractDotNotationValue($row_array, $key);

            // Handle JSON string format
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                }
            }

            if (!is_array($value) || empty($value)) {
                return '';
            }

            // Build link attributes
            $default_class = $options['class'] ?? 'js-file-download';
            $target = $options['target'] ?? '_blank';

            $output = '';
            foreach ($value as $file) {
                $file_url = is_array($file) ? ($file['url'] ?? false) : (is_object($file) ? ($file->url ?? false) : false);
                $file_name = is_array($file) ? ($file['name'] ?? false) : (is_object($file) ? ($file->name ?? false) : false);

                if ($file_url && $file_name) {
                    $output .= '<a href="' . htmlspecialchars($file_url) . '" target="' . htmlspecialchars($target) . '" class="' . htmlspecialchars($default_class) . '">' . htmlspecialchars($file_name) . '</a><br>';
                }
            }

            return $output;
        };

        return $this;
    }

    /**
     * Convert a column to image thumbnails (OLD Method-first style)
     *
     * @deprecated Use field()->image() instead
     * @param string $key Column key/name containing image array data
     * @param array $options Additional options for the images
     * @return static For method chaining
     *
     * @example ->asImage('photos')
     */
    public function asImage($key, $options = []): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        // Automatically set type to html for images
        $this->custom_columns[$key]['type'] = 'html';

        // Store the formatter function
        $this->custom_columns[$key]['fn'] = function($row_array) use ($key, $options) {
            $value = $this->extractDotNotationValue($row_array, $key);

            // Handle JSON string format
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                }
            }

            if (!is_array($value) || empty($value)) {
                return is_string($value) ? '' : $value;
            }

            // Build image attributes
            $size = $options['size'] ?? 50;
            $class = $options['class'] ?? '';
            $lightbox = $options['lightbox'] ?? false;
            $max_images = $options['max_images'] ?? null;

            $output = '<div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">';
            $count = 0;
            foreach ($value as $file) {
                $file_url = is_array($file) ? ($file['url'] ?? false) : (is_object($file) ? ($file->url ?? false) : false);
                $file_name = is_array($file) ? ($file['name'] ?? '') : (is_object($file) ? ($file->name ?? '') : '');

                if ($file_url) {
                    if ($max_images !== null && $count >= $max_images) {
                        $remaining = count($value) - $count;
                        $output .= '<div style="width: ' . $size . 'px; height: ' . $size . 'px; display: flex; align-items: center; justify-content: center; background: #e9ecef; border-radius: 4px; font-size: 0.8rem;">+' . $remaining . '</div>';
                        break;
                    }

                    $img_html = '<img src="' . htmlspecialchars($file_url) . '" alt="' . htmlspecialchars($file_name) . '" style="width: ' . $size . 'px; height: ' . $size . 'px; object-fit: cover; border-radius: 4px;" class="' . htmlspecialchars($class) . '">';

                    if ($lightbox) {
                        $output .= '<a href="' . htmlspecialchars($file_url) . '" target="_blank" data-lightbox="' . htmlspecialchars($key) . '">' . $img_html . '</a>';
                    } else {
                        $output .= $img_html;
                    }
                    $count++;
                }
            }
            $output .= '</div>';

            return $output;
        };

        return $this;
    }

    /**
     * Hide a column from display (OLD Method-first style)
     *
     * @deprecated Use field()->hide() instead
     * @param string $key Column key/name to hide
     * @return static For method chaining
     *
     * @example ->hideColumn('updated_at')
     */
    public function hideColumn($key): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        $this->hidden_columns[] = $key;
        return $this;
    }

    /**
     * Hide multiple columns from display (OLD Method-first style)
     *
     * @param array $keys Array of column keys/names to hide
     * @return static For method chaining
     *
     * @example ->hideColumns(['updated_at', 'created_at'])
     */
    public function hideColumns(array $keys): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        foreach ($keys as $key) {
            $this->hidden_columns[] = $key;
        }
        return $this;
    }

    /**
     * Disable sorting for a specific column (OLD Method-first style)
     *
     * @deprecated Use field()->noSort() instead
     * @param string $key Column key/name to disable sorting for
     * @return static For method chaining
     *
     * @example ->disableSort('title_original')
     */
    public function disableSort($key): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        if (!isset($this->custom_columns[$key])) {
            $existing_column = $this->modellist_service->list_structure->getColumn($key);
            $this->custom_columns[$key] = [
                'action' => $existing_column ? 'modify' : 'add'
            ];
        }
        $this->custom_columns[$key]['disable_sort'] = true;
        return $this;
    }

    /**
     * Map sort field for a virtual column (OLD Method-first style)
     *
     * @deprecated Use field()->sortBy() instead
     * @param string $virtual_field Virtual field name
     * @param string $real_field Real database field name
     * @return static For method chaining
     *
     * @example ->mapSort('doctor_name', 'doctor.name')
     */
    public function mapSort($virtual_field, $real_field): static {
        // Reset current_field when using OLD methods
        $this->current_field = null;

        $this->sort_mappings[$virtual_field] = $real_field;
        // Apply mapping to the query object
        $this->query->setSortMapping($virtual_field, $real_field);
        return $this;
    }
}
