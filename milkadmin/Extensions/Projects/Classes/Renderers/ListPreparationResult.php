<?php
namespace Extensions\Projects\Classes\Renderers;

!defined('MILK_DIR') && die();

/**
 * Result of the shared list preparation pipeline.
 *
 * A ready result exposes resolved params. A terminal result exposes the
 * response/redirect/error payload that should be returned by full build().
 */
final class ListPreparationResult
{
    private function __construct(
        public readonly ?ListContextParams $params,
        public readonly ?array $result
    ) {
    }

    public static function ready(ListContextParams $params): self
    {
        return new self($params, null);
    }

    /**
     * @param array<string,mixed> $result
     */
    public static function terminal(array $result): self
    {
        return new self(null, $result);
    }

    public function isReady(): bool
    {
        return $this->params instanceof ListContextParams;
    }
}
