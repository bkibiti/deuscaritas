<?php

namespace Database\Seeders;

use App\Models\Inventory\Item;
use App\Models\Inventory\ItemCategory;
use App\Models\Inventory\Store;
use App\Models\Inventory\Unit;
use Illuminate\Database\Seeder;

/**
 * Five starter items for each of the two stores, with their categories.
 *
 *   php artisan db:seed --class=InventorySampleItemsSeeder
 *
 * Safe to run more than once: an item or category that already exists is left as it is.
 */
class InventorySampleItemsSeeder extends Seeder
{
    /**
     * Store code => [item name, category, unit abbreviation, reorder level].
     *
     * @var array<string, array<int, array{0: string, 1: string, 2: string, 3: int}>>
     */
    private const ITEMS = [
        'FOOD' => [
            ['Maize flour', 'Cereals and grains', 'kg', 100],
            ['Rice', 'Cereals and grains', 'kg', 100],
            ['Beans', 'Cereals and grains', 'kg', 50],
            ['Cooking oil', 'Cooking ingredients', 'L', 20],
            ['Sugar', 'Cooking ingredients', 'kg', 25],
        ],
        'BUILD' => [
            ['Cement 42.5N', 'Cement and aggregates', 'bag', 20],
            ['Sand', 'Cement and aggregates', 'trip', 1],
            ['Iron bar 12 mm', 'Steel', 'pc', 30],
            ['Roofing sheet 28 gauge', 'Roofing', 'sheet', 20],
            ['Nails 4 inch', 'Hardware', 'kg', 10],
        ],
    ];

    public function run(): void
    {
        foreach (self::ITEMS as $storeCode => $items) {
            $store = Store::where('code', $storeCode)->first();

            if (! $store) {
                $this->command?->warn("Store {$storeCode} not found: its items were skipped.");

                continue;
            }

            foreach ($items as [$name, $categoryName, $unitAbbreviation, $reorderLevel]) {
                $unit = Unit::where('abbreviation', $unitAbbreviation)->first();

                if (! $unit) {
                    $this->command?->warn("Unit {$unitAbbreviation} not found: {$name} was skipped.");

                    continue;
                }

                $category = ItemCategory::firstOrCreate(['store_id' => $store->id, 'name' => $categoryName]);

                Item::firstOrCreate(
                    ['store_id' => $store->id, 'name' => $name],
                    ['item_category_id' => $category->id, 'unit_id' => $unit->id, 'reorder_level' => $reorderLevel, 'is_active' => true],
                );
            }
        }
    }
}
