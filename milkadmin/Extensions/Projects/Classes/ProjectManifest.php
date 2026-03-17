<?php
namespace Extensions\Projects\Classes;

!defined('MILK_DIR') && die();

/**
 * Value object for Projects manifest data (tree-based).
 */
class ProjectManifest
{
    protected string $version;
    protected string $name;
    protected array $settings;
    /**
     * Tree of forms.
     *
     * Each node:
     * - ref: string (required)
     * - max_records: int|string (optional)
     * - showIf: string (optional)
     * - showIfMessage: string (optional)
     * - softDelete: bool (optional)
     * - allowDeleteRecord: bool (optional)
     * - allowEdit: bool (optional)
     * - childCountColumn: string (optional, hide|show)
     * - listDisplay: string (optional, page|offcanvas|modal)
     * - editDisplay: string (optional, page|offcanvas|modal)
     * - forms: array (optional children)
     *
     * @var array<int,array{
     *   ref:string,
     *   max_records?:int|string,
     *   showIf?:string,
     *   showIfMessage?:string,
     *   softDelete?:bool,
     *   allowDeleteRecord?:bool,
     *   allowEdit?:bool,
     *   childCountColumn?:string,
     *   listDisplay?:string,
     *   editDisplay?:string,
     *   forms?:array
     * }>
     */
    protected array $formsTree;

    /**
     * @param array $formsTree Normalized forms tree
     */
    public function __construct(
        string $version = '1.0',
        string $name = '',
        array $settings = [],
        array $formsTree = []
    ) {
        $this->version = $version;
        $this->name = $name;
        $this->settings = $settings;
        $this->formsTree = $formsTree;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * @return array<int,array{
     *   ref:string,
     *   max_records?:int|string,
     *   showIf?:string,
     *   showIfMessage?:string,
     *   softDelete?:bool,
     *   allowDeleteRecord?:bool,
     *   allowEdit?:bool,
     *   childCountColumn?:string,
     *   listDisplay?:string,
     *   editDisplay?:string,
     *   forms?:array
     * }>
     */
    public function getFormsTree(): array
    {
        return $this->formsTree;
    }
}
