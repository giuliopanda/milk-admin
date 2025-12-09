<?php

namespace Builders\Traits\GetDataBuilder;

!defined('MILK_DIR') && die();

/**
 * MethodFirstCompatibilityTrait - Backward compatibility for old API style
 *
 * Use field-first style instead: ->field('name')->label('...')
 */
trait MethodFirstCompatibilityTrait
{
    /**
     * @deprecated Use ->field($key)->label($label) instead
     */
    public function setLabel(string $key, string $label): static
    {
        $this->columns->setLabel($key, $label);
        return $this;
    }

    /**
     * @deprecated Use ->field($key)->type($type) instead
     */
    public function setType(string $key, string $type): static
    {
        $this->columns->setType($key, $type);
        return $this;
    }

    /**
     * @deprecated Use ->field($key)->options($options) instead
     */
    public function setOptions(string $key, array $options): static
    {
        $this->columns->setOptions($key, $options);
        return $this;
    }

    /**
     * @deprecated Use ->field($key)->link($link) instead
     */
    public function asLink(string $key, string $link, array $options = []): static
    {
        return $this->field($key)->link($link, $options);
    }

    /**
     * @deprecated Use ->field($key)->file() instead
     */
    public function asFile(string $key, array $options = []): static
    {
        return $this->field($key)->file($options);
    }

    /**
     * @deprecated Use ->field($key)->image() instead
     */
    public function asImage(string $key, array $options = []): static
    {
        return $this->field($key)->image($options);
    }

    /**
     * @deprecated Use ->field($key)->fn($fn) instead
     */
    public function setFunction(string $key, callable $fn): static
    {
        $this->columns->setFunction($key, $fn);
        return $this;
    }

    /**
     * @deprecated Use ->field($key)->noSort() instead
     */
    public function disableSort(string $key): static
    {
        $this->columns->setDisableSort($key, true);
        return $this;
    }

    /**
     * @deprecated Use ->field($key)->sortBy($realField) instead
     */
    public function mapSortField(string $virtualField, string $realField): static
    {
        $this->context->addSortMapping($virtualField, $realField);
        return $this;
    }
}
