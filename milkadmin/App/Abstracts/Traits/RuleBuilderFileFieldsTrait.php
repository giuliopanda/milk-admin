<?php
namespace App\Abstracts\Traits;

!defined('MILK_DIR') && die();

/**
 * RuleBuilderFileFieldsTrait - File and image upload field methods
 *
 * Provides methods for file/image fields: file, image, multiple,
 * maxFiles, accept, maxSize, uploadDir
 */
trait RuleBuilderFileFieldsTrait
{
    /**
     * Define a file upload field
     *
     * @param string $name Field name
     * @return self
     */
    public function file(string $name): self
    {
        $this->field($name, 'array');
        $this->formType('file');
        $this->label($this->createLabel($name));
        return $this;
    }

    /**
     * Define an image upload field
     *
     * @param string $name Field name
     * @return self
     */
    public function image(string $name): self
    {
        $this->field($name, 'array');
        $this->formType('image');
        $this->label($this->createLabel($name));

        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['accept'] = 'image/*';

        return $this;
    }

    /**
     * Allow multiple file uploads
     *
     * @param bool|int $multiple True for multiple, or max number
     * @return self
     */
    public function multiple(bool|int $multiple = true): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }

        if (is_bool($multiple)) {
            if ($multiple) {
                $this->rules[$this->current_field]['form-params']['multiple'] = 'multiple';
            } else {
                unset($this->rules[$this->current_field]['form-params']['multiple']);
            }
        } elseif (is_int($multiple)) {
            $this->maxFiles($multiple);
        }
        return $this;
    }

    /**
     * Set maximum number of files
     *
     * @param int $max Maximum files
     * @return self
     */
    public function maxFiles(int $max): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        if ($max > 1) {
            $this->rules[$this->current_field]['form-params']['multiple'] = 'multiple';
        }
        $this->rules[$this->current_field]['form-params']['max-files'] = $max;
        return $this;
    }

    /**
     * Set accepted file types
     *
     * @param string $accept e.g., 'image/*', '.pdf,.doc'
     * @return self
     */
    public function accept(string $accept): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['accept'] = $accept;
        return $this;
    }

    /**
     * Set maximum file size in bytes
     *
     * @param int $size Max size in bytes
     * @return self
     */
    public function maxSize(int $size): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['max-size'] = $size;
        return $this;
    }

    /**
     * Set upload directory
     *
     * @param string $dir Directory path
     * @return self
     */
    public function uploadDir(string $dir): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        $this->rules[$this->current_field]['form-params']['upload-dir'] = $dir;
        return $this;
    }

    /**
     * Enable/disable manual sorting in upload list (file/image fields).
     *
     * @param bool $enabled True to enable sorting, false to disable
     * @return self
     */
    public function sortable(bool $enabled = true): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        if ($enabled) {
            $this->rules[$this->current_field]['form-params']['sortable'] = true;
        } else {
            unset($this->rules[$this->current_field]['form-params']['sortable']);
        }
        return $this;
    }

    /**
     * Enable/disable download button for existing uploaded files (Projects extension).
     *
     * @param bool $enabled True to show download button, false to hide it
     * @return self
     */
    public function downloadLink(bool $enabled = true): self
    {
        if (!isset($this->rules[$this->current_field]['form-params'])) {
            $this->rules[$this->current_field]['form-params'] = [];
        }
        if ($enabled) {
            $this->rules[$this->current_field]['form-params']['download-link'] = true;
        } else {
            unset($this->rules[$this->current_field]['form-params']['download-link']);
        }
        return $this;
    }
}
