<?php
namespace Modules\Docs;

use App\Abstracts\AbstractModel;
use App\Get;
use Builders\ChartBuilder;
use Builders\TableBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access

class ArrayDbDocsService
{
    public const TABLE_NAME = 'documentation_products';
    public const TABLE_ID = 'idTableDocumentationProductsExample';
    public const CHART_ID = 'idChartDocumentationProductsExample';
    public const TABLE_ACTION = 'arraydb-models-builders-table';
    public const CHART_ACTION = 'arraydb-models-builders-chart';

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

    private static function getModel(): AbstractModel
    {
        self::seed();
        return new DocumentationProductsDocModel();
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
