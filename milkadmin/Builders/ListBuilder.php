<?php
namespace Builders;

use App\Modellist\{ListStructure, ModelList};
use App\{Get, MessagesHandler, Route, Config};
use App\Database\{Query};
use App\Exceptions\DatabaseException;

!defined('MILK_DIR') && die(); // Prevents direct access

require_once 'TableBuilder.php';

/**
 * ViewTableBuilder - Gestione visualizzazione tabella
 *
 * Estende TableBuilder e fornisce i metodi di output (HTML/response)
 * senza introdurre logica di costruzione query o preparazione dati.
 */
class ListBuilder extends GetDataBuilder
{

    protected $box_template = '';
    protected $box_attrs = [];
    /**
     * Get HTML list string
     *
     * @return string Complete HTML list ready for display
     */
    public function render(): string {
        $data = $this->getData();
        return  Get::themePlugin('List', [
            'info' => $data['info'],
            'rows' => $data['rows'],
            'page_info' => $data['page_info'],
            'box_attrs' => $this->box_attrs  // Lista usa box_attrs invece di table_attrs
        ]);
    }

    // Method chaining per configurazione lista (snake_case)
    public function setBoxAttrs(array $attrs): static {
        $this->box_attrs = $attrs;
        return $this;
    }

    public function addBoxAttr($element, $key, $value): static {
        $this->box_attrs[$element][$key] = $value;
        return $this;
    }

    /**
     * Set attributes for form element
     * @param string $key Attribute name
     * @param string $value Attribute value
     * @return static For method chaining
     */
    public function setFormAttr($key, $value): static {
        $this->box_attrs['form'][$key] = $value;
        return $this;
    }

    // Enhanced class management methods for list boxes

    /**
     * Set CSS classes for the container element
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     */
    public function containerClass($classes): static {
        $this->box_attrs['container']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for the column wrapper
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     */
    public function colClass($classes): static {
        $this->box_attrs['col']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for all box items
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     */
    public function boxClass($classes): static {
        // Preserva sempre le classi essenziali per funzionalità
        $essential_classes = ['js-box-item'];

        // Aggiungi 'card' se non presente nelle classi fornite
        if (strpos($classes, 'card') === false) {
            $essential_classes[] = 'card';
        }

        // Combina classi essenziali con quelle fornite
        $full_classes = implode(' ', $essential_classes) . ' ' . $classes;
        $this->box_attrs['box']['class'] = trim($full_classes);
        return $this;
    }

    /**
     * Set CSS classes for box header
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     */
    public function boxHeaderClass($classes): static {
        $this->box_attrs['box.header']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for box body
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     */
    public function boxBodyClass($classes): static {
        $this->box_attrs['box.body']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for box footer
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     */
    public function boxFooterClass($classes): static {
        $this->box_attrs['box.footer']['class'] = $classes;
        return $this;
    }

    /**
     * Alternate box colors between odd and even items
     * @param string $odd_classes CSS classes for odd boxes
     * @param string $even_classes CSS classes for even boxes
     * @return static For method chaining
     */
    public function boxClassAlternate($odd_classes, $even_classes = null): static {
        $this->box_conditions[] = [
            'type' => 'alternate',
            'odd_classes' => $odd_classes,
            'even_classes' => $even_classes ?? ''
        ];
        return $this;
    }

    /**
     * Apply CSS class to boxes based on field value
     * @param string $field Field name to check
     * @param mixed $value Value to compare
     * @param string $classes CSS classes to apply when condition matches
     * @param string $comparison Comparison operator (==, !=, >, <, >=, <=, contains)
     * @return static For method chaining
     */
    public function boxClassByValue($field, $value, $classes, $comparison = '=='): static {
        $this->box_conditions[] = [
            'type' => 'value',
            'field' => $field,
            'value' => $value,
            'classes' => $classes,
            'comparison' => $comparison
        ];
        return $this;
    }

    /**
     * Set CSS classes for field row container
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     */
    public function fieldRowClass($classes): static {
        $this->box_attrs['field.row']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for field labels
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     */
    public function fieldLabelClass($classes): static {
        $this->box_attrs['field.label']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for field values
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     */
    public function fieldValueClass($classes): static {
        $this->box_attrs['field.value']['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS classes for a specific field by name
     * @param string $field_name Name of the field
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     */
    public function fieldClass($field_name, $classes): static {
        $key = 'field.' . str_replace(' ', '_', $field_name);
        $this->box_attrs[$key]['class'] = $classes;
        return $this;
    }

    /**
     * Apply CSS class to a specific field based on its own value
     * @param string $field_name Field to style and check
     * @param mixed $value Value to compare
     * @param string $classes CSS classes to apply when condition matches
     * @param string $comparison Comparison operator (==, !=, >, <, >=, <=, contains)
     * @return static For method chaining
     *
     * @example ->classByValue('status', 'active', 'text-success')
     * @example ->classByValue('stock', 100, 'text-danger', '<')
     */
    public function classByValue($value, $classes, $comparison = '=='): static {
        if ($this->current_field === null) {
            throw \Builders\Exceptions\BuilderException::noCurrentField('class');
        }
        $this->field_conditions[] = [
            'type' => 'value',
            'field' => $this->current_field,
            'check_field' => $this->current_field, // Controlla lo stesso campo
            'value' => $value,
            'classes' => $classes,
            'comparison' => $comparison
        ];
        return $this;
    }


    /**
     * Apply quick color theme to boxes
     * @param string $color Color name (primary, success, danger, warning, info, light, dark)
     * @return static For method chaining
     */
    public function boxColor($color): static {
        // Mappa i colori semplici alle classi Bootstrap
        $color_map = [
            // Colori base
            'primary' => 'border-primary',
            'secondary' => 'border-secondary',
            'success' => 'border-success',
            'danger' => 'border-danger',
            'warning' => 'border-warning',
            'info' => 'border-info',
            'light' => 'border-light',
            'dark' => 'border-dark',

            // Aliases più semplici
            'blue' => 'border-primary',
            'gray' => 'border-secondary',
            'grey' => 'border-secondary',
            'green' => 'border-success',
            'red' => 'border-danger',
            'yellow' => 'border-warning',
            'cyan' => 'border-info',
            'white' => 'border-light',
            'black' => 'border-dark',
        ];

        // Colori per l'header
        $header_color_map = [
            'primary' => 'bg-primary text-white',
            'secondary' => 'bg-secondary text-white',
            'success' => 'bg-success text-white',
            'danger' => 'bg-danger text-white',
            'warning' => 'bg-warning text-dark',
            'info' => 'bg-info text-white',
            'light' => 'bg-light text-dark',
            'dark' => 'bg-dark text-white',

            // Aliases
            'blue' => 'bg-primary text-white',
            'gray' => 'bg-secondary text-white',
            'grey' => 'bg-secondary text-white',
            'green' => 'bg-success text-white',
            'red' => 'bg-danger text-white',
            'yellow' => 'bg-warning text-dark',
            'cyan' => 'bg-info text-white',
            'white' => 'bg-light text-dark',
            'black' => 'bg-dark text-white',
        ];

        // Applica il colore al bordo del box
        if (isset($color_map[$color])) {
            $border_class = $color_map[$color];
            $current_classes = $this->box_attrs['box']['class'] ?? 'card js-box-item';
            $this->box_attrs['box']['class'] = trim($current_classes . ' ' . $border_class);
        }

        // Applica il colore all'header
        if (isset($header_color_map[$color])) {
            $this->boxHeaderClass($header_color_map[$color]);
        }

        return $this;
    }

    /**
     * Set the number of columns for responsive grid layout
     * @param int $cols Columns on small screens (≥768px)
     * @return static For method chaining
     */
    public function gridColumns($cols): static {
        $col_classes = 'col-12';
        $col_md = 6;
        if ($cols == 5) {
            $cols = 4;
        } 
        if ($cols > 6) {
            $cols = 6;
        }
        if ($cols < 1) {
            $cols = 1;
        }
        if ($cols < 3) {
            $col_md = 12;
        }
        if ($cols) $col_classes .= ' col-md-12 col-lg-' . $col_md . ' col-xl-' . (12 / $cols);
        

        $this->colClass($col_classes);
        return $this;
    }

    /**
     * Set CSS classes for checkbox wrapper
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     */
    public function checkboxWrapperClass($classes): static {
        $this->box_attrs['checkbox.wrapper']['class'] = $classes;
        return $this;
    }

    /**
     * Set custom box template file
     * @param string $template_path Absolute path to the custom template file
     * @return static For method chaining
     *
     * @example ->setBoxTemplate(__DIR__ . '/Views/custom-box.php')
     */
    public function setBoxTemplate($template_path): static {
        if (!file_exists($template_path)) {
            throw new \InvalidArgumentException("Template file not found: {$template_path}");
        }
        $this->box_template = $template_path;
        return $this;
    }

    // ========================================================================
    // FIELD-FIRST STYLE - LIST GRAPHIC METHODS
    // ========================================================================

    /**
     * Set CSS class for current field (Field-first style)
     * Requires field() to be called first
     *
     * @param string $classes CSS classes to apply
     * @return static For method chaining
     *
     * @throws BuilderException if field() was not called first
     * @example ->field('status')->class('fw-bold text-uppercase')
     */
    public function class(string $classes): static {
        if ($this->current_field === null) {
            throw \Builders\Exceptions\BuilderException::noCurrentField('class');
        }

        $field_name = $this->current_field;
        $key = 'field.' . str_replace(' ', '_', $field_name);
        $this->box_attrs[$key]['class'] = $classes;
        return $this;
    }

    /**
     * Set CSS class based on current field value (Field-first style)
     * Requires field() to be called first
     *
     * @param mixed $value Value to compare
     * @param string $classes CSS classes to apply when condition matches
     * @param string $comparison Comparison operator (==, !=, >, <, >=, <=, contains)
     * @return static For method chaining
     *
     * @throws BuilderException if field() was not called first
     * @example ->field('status')->classValue('active', 'text-success fw-bold')
     * @example ->field('stock')->classValue(10, 'text-danger', '<')
     */
    public function classValue($value, string $classes, string $comparison = '=='): static {
        if ($this->current_field === null) {
            throw \Builders\Exceptions\BuilderException::noCurrentField('classValue');
        }

        $field_name = $this->current_field;
        $this->field_conditions[] = [
            'type' => 'value',
            'field' => $field_name,
            'check_field' => $field_name, // Controlla lo stesso campo
            'value' => $value,
            'classes' => $classes,
            'comparison' => $comparison
        ];
        return $this;
    }

    /**
     * Set CSS class based on another field's value (Field-first style)
     * Requires field() to be called first
     *
     * @param string $check_field Field to check for value
     * @param mixed $value Value to compare
     * @param string $classes CSS classes to apply when condition matches
     * @param string $comparison Comparison operator (==, !=, >, <, >=, <=, contains)
     * @return static For method chaining
     *
     * @throws BuilderException if field() was not called first
     * @example ->field('price')->classOtherValue('status', 'discount', 'text-success fw-bold')
     */
    public function classOtherValue(string $check_field, $value, string $classes, string $comparison = '=='): static {
        if ($this->current_field === null) {
            throw \Builders\Exceptions\BuilderException::noCurrentField('classOtherValue');
        }

        $field_name = $this->current_field;
        $this->field_conditions[] = [
            'type' => 'value',
            'field' => $field_name,
            'check_field' => $check_field,
            'value' => $value,
            'classes' => $classes,
            'comparison' => $comparison
        ];
        return $this;
    }

    // ========================================================================
    // END FIELD-FIRST STYLE - LIST GRAPHIC METHODS
    // ========================================================================
}
