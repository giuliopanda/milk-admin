<?php
namespace Modules\Docs;

use App\Abstracts\AbstractModel;
use App\Get;
use Builders\ChartBuilder;
use Builders\TableBuilder;
use Builders\ScheduleGridBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access

class ArrayDbDocsService
{
    public const TABLE_NAME = 'documentation_products';
    public const SCHEDULE_TABLE_NAME = 'documentation_bookings';
    public const TABLE_ID = 'idTableDocumentationProductsExample';
    public const CHART_ID = 'idChartDocumentationProductsExample';
    public const SCHEDULE_GRID_ID = 'idScheduleGridDocumentationExample';
    public const TABLE_ACTION = 'arraydb-models-builders-table';
    public const CHART_ACTION = 'arraydb-models-builders-chart';
    public const SCHEDULE_GRID_ACTION = 'arraydb-models-builders-schedulegrid';

    public static function renderTable(string $tableId = self::TABLE_ID): string
    {
        return self::buildTable($tableId)->render();
    }

    public static function renderChart(string $chartId = self::CHART_ID): string
    {
        return self::buildChart($chartId)->render();
    }

    public static function tableResponse(string $tableId): array
    {
        return self::buildTable($tableId)->getResponse();
    }

    public static function chartResponse(string $chartId): array
    {
        return self::buildChart($chartId)->getResponse();
    }

    public static function renderScheduleGrid(string $gridId = self::SCHEDULE_GRID_ID): string
    {
        return self::buildScheduleGrid($gridId)->render();
    }

    public static function scheduleGridResponse(string $gridId): array
    {
        return self::buildScheduleGrid($gridId)->getResponse();
    }

    public static function buildTable(string $tableId): TableBuilder
    {
        $model = self::getModel();

        return TableBuilder::create($model, $tableId)
            ->setRequestAction(self::TABLE_ACTION)
            ->field('NAME')->label('Name')
            ->field('CATEGORY')->label('Category')
            ->field('PRICE')->label('Price')
            ->field('STATUS')->label('Status');
    }

    public static function buildChart(string $chartId): ChartBuilder
    {
        $model = self::getModel();

        return ChartBuilder::create($model, $chartId)
            ->setRequestAction(self::CHART_ACTION)
            ->select(['CATEGORY AS label', 'SUM(PRICE) AS value'])
            ->structure([
                'label' => ['label' => 'Category', 'axis' => 'x'],
                'value' => ['label' => 'Total', 'axis' => 'y'],
            ])
            ->groupBy('CATEGORY')
            ->orderBy('label', 'ASC');
    }

    public static function buildScheduleGrid(string $gridId): ScheduleGridBuilder
    {
        $model = self::getScheduleModel();

        return ScheduleGridBuilder::create($model, $gridId)
            ->setRequestAction(self::SCHEDULE_GRID_ACTION)
            ->setPeriod('week')
            ->detectPeriodFromRequest()
            ->mapFields([
                'row_id' => 'resource_name',
                'id' => 'id',
                'start_datetime' => 'start_datetime',
                'end_datetime' => 'end_datetime',
                'label' => 'activity_name',
                'color' => 'booking_color'
            ])
            ->setHeaderTitle('Resource Booking Schedule - Live Demo')
            ->setHeaderIcon('bi-calendar3')
            ->setHeaderColor('primary')
            ->setRowHeaderLabel('Rooms')
            ->setShowNavigation(true)
            ->gridColor('primary');
    }

    private static function getModel(): AbstractModel
    {
        self::seed();
        return new DocumentationProductsDocModel();
    }

    private static function getScheduleModel(): AbstractModel
    {
        self::seedSchedule();
        return new DocumentationBookingsDocModel();
    }

    private static function seed(): void
    {
        $db = Get::arrayDb();

        if (in_array(self::TABLE_NAME, $db->getTables(), true)) {
            return;
        }

        $db->addTable(self::TABLE_NAME, [
            ['ID_PRODUCT' => 1, 'NAME' => 'Notebook', 'CATEGORY' => 'Electronics', 'PRICE' => 999.90, 'STATUS' => 'ACTIVE'],
            ['ID_PRODUCT' => 2, 'NAME' => 'Mouse', 'CATEGORY' => 'Electronics', 'PRICE' => 24.50, 'STATUS' => 'ACTIVE'],
            ['ID_PRODUCT' => 3, 'NAME' => 'Desk', 'CATEGORY' => 'Office', 'PRICE' => 189.00, 'STATUS' => 'INACTIVE'],
        ], 'ID_PRODUCT');
    }

    private static function seedSchedule(): void
    {
        $db = Get::arrayDb();

        // Get requested week/year
        $gridId = self::SCHEDULE_GRID_ID;
        $week = $_REQUEST[$gridId]['week'] ?? date('W');
        $year = $_REQUEST[$gridId]['year'] ?? date('Y');

        // Always regenerate data for the requested week
        $resources = ['Conference Room A', 'Conference Room B', 'Training Room', 'Meeting Room 1', 'Meeting Room 2'];
        $activities = ['Team Meeting', 'Client Presentation', 'Training Session', 'Workshop', 'Interview', 'Project Review'];
        $colors = ['#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#00BCD4', '#F44336'];

        // Get Monday of requested week
        $monday = new \DateTime();
        $monday->setISODate($year, $week, 1);

        $data = [];
        $id = 1;

        // Create 20 sample bookings for the requested week
        for ($i = 0; $i < 20; $i++) {
            $resource = $resources[array_rand($resources)];
            $activity = $activities[array_rand($activities)];
            $color = $colors[array_rand($colors)];

            // Random day this week (0-6)
            $dayOffset = rand(0, 6);
            $startHour = rand(9, 17);
            $duration = rand(1, 3); // 1-3 hours

            $startDate = clone $monday;
            $startDate->modify("+{$dayOffset} days");
            $startDate->setTime($startHour, 0);

            $endDate = clone $startDate;
            $endDate->modify("+{$duration} hours");

            $data[] = [
                'id' => $id++,
                'resource_name' => $resource,
                'start_datetime' => $startDate->format('Y-m-d H:i:s'),
                'end_datetime' => $endDate->format('Y-m-d H:i:s'),
                'activity_name' => $activity,
                'booking_color' => $color,
            ];
        }

        // Remove old table if exists and add fresh data
        if (in_array(self::SCHEDULE_TABLE_NAME, $db->getTables(), true)) {
            $db->dropTable(self::SCHEDULE_TABLE_NAME);
        }
        $db->addTable(self::SCHEDULE_TABLE_NAME, $data, 'id');
    }
}

class DocumentationProductsDocModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table(ArrayDbDocsService::TABLE_NAME)
            ->id('ID_PRODUCT')
            ->db('array')
            ->title('NAME', 100)->label('Name')->required()
            ->string('CATEGORY', 50)->label('Category')
            ->decimal('PRICE', 10, 2)->label('Price')
            ->string('STATUS', 10)->label('Status');
    }
}

class DocumentationBookingsDocModel extends AbstractModel
{
    protected function configure($rule): void
    {
        $rule->table(ArrayDbDocsService::SCHEDULE_TABLE_NAME)
            ->id('id')
            ->db('array')
            ->string('resource_name', 100)->label('Resource')
            ->datetime('start_datetime')->label('Start')
            ->datetime('end_datetime')->label('End')
            ->string('activity_name', 100)->label('Activity')
            ->string('booking_color', 20)->label('Color');
    }
}
