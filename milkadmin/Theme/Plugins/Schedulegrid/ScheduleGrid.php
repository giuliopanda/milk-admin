<?php

/**
 * Class ScheduleGrid - Facade for schedule grid management
 *
 * This class serves as the main interface for using the schedule grid.
 * It coordinates between ScheduleGridData (logic) and GridRenderer (visualization).
 *
 * Pattern: Same as Calendar facade
 */
class ScheduleGrid {
    private $data;
    private $renderer;

    /**
     * Constructor
     *
     * @param array $config Configuration array from Builder
     */
    public function __construct(array $config) {
        // Initialize data
        $this->data = new ScheduleGridData($config);

        // Initialize renderer (for now only GridRenderer, could be extended)
        $this->renderer = new GridRenderer($this->data);
    }

    /**
     * Render the complete schedule grid
     *
     * @return string Grid HTML
     */
    public function render() {
        return $this->renderer->render();
    }

    /**
     * Get the grid data object
     * For advanced usage or custom renderers
     *
     * @return ScheduleGridData
     */
    public function getData() {
        return $this->data;
    }

    /**
     * Get the grid renderer object
     * For advanced usage or customization
     *
     * @return GridRenderer
     */
    public function getRenderer() {
        return $this->renderer;
    }
}
