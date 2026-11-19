<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The official starting configuration from the "Departments & Stock Management" requirements
 * document: ten departments, the units it lists, ten stock categories and the items it names.
 * Anything already there under the same name is left as it is.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const DEPARTMENTS = [
        'Administration',
        'Construction, Maintenance and Facilities',
        'Environment',
        'Health',
        'Sports and Games',
        'Teaching and Learning',
        'Transport',
        'Food',
        'ICT and Technology',
        'Finance',
    ];

    /** @var array<int, array{0: string, 1: string}> units the document lists that were missing */
    private const UNITS = [
        ['Millilitre', 'ml'], ['Dozen', 'dz'], ['Set', 'set'], ['Pair', 'pair'], ['Bottle', 'btl'],
    ];

    /** @var array<string, array<int, array{0: string, 1: string}>> category => [item, unit abbreviation] */
    private const CATALOGUE = [
        'Food Stock' => [
            ['Maize', 'bag'], ['Rice', 'bag'], ['Beans', 'bag'], ['Maize flour', 'kg'], ['Wheat flour', 'kg'],
            ['Sugar', 'kg'], ['Cooking oil', 'L'], ['Salt', 'pkt'], ['Spices', 'pkt'],
        ],
        'Stationery Stock' => [
            ['A4 paper', 'box'], ['A3 paper', 'box'], ['Pens', 'dz'], ['Pencils', 'dz'], ['Exercise books', 'dz'],
            ['Files', 'pc'], ['Folders', 'pc'], ['Markers', 'pc'], ['Staplers', 'pc'], ['Staples', 'box'],
        ],
        'Cleaning Materials' => [
            ['Washing powder', 'kg'], ['Liquid soap', 'L'], ['Bleach', 'btl'], ['Disinfectant', 'btl'], ['Brooms', 'pc'],
            ['Mops', 'pc'], ['Buckets', 'pc'], ['Toilet cleaner', 'btl'], ['Cleaning gloves', 'pair'],
        ],
        'ICT and Electronics' => [
            ['Keyboards', 'pc'], ['Mice', 'pc'], ['Toners', 'pc'], ['Cartridges', 'pc'], ['Computer cables', 'pc'],
            ['Flash disks', 'pc'], ['Routers', 'pc'],
        ],
        'Sports and Games' => [
            ['Footballs', 'pc'], ['Volleyballs', 'pc'], ['Basketballs', 'pc'], ['Nets', 'pc'], ['Whistles', 'pc'],
            ['Sports uniforms', 'set'],
        ],
        'Medical and First Aid' => [
            ['First aid kits', 'set'], ['Bandages', 'roll'], ['Cotton wool', 'roll'], ['Medical gloves', 'pair'],
            ['Antiseptics', 'btl'], ['Thermometers', 'pc'],
        ],
        'Maintenance and Hardware' => [
            ['Nails', 'kg'], ['Screws', 'box'], ['Paints', 'L'], ['Paint brushes', 'pc'], ['Electrical cables', 'm'],
            ['Bulbs', 'pc'], ['Pipes', 'm'], ['Pipe fittings', 'pc'], ['Locks', 'pc'],
        ],
        'Kitchen and Cooking Equipment' => [
            ['Cooking pots', 'pc'], ['Plates', 'pc'], ['Cups', 'pc'], ['Spoons', 'pc'], ['Gas cylinders', 'pc'],
        ],
        'Uniforms and School Requirements' => [
            ['School uniforms', 'set'], ['Bedsheets', 'pc'], ['Blankets', 'pc'], ['Mattresses', 'pc'],
        ],
        'Furniture and General Assets' => [
            ['Tables', 'pc'], ['Chairs', 'pc'], ['Cabinets', 'pc'], ['Beds', 'pc'], ['Whiteboards', 'pc'],
            ['Projectors', 'pc'], ['Printers', 'pc'],
        ],
    ];

    /**
     * The trial categories added before the official list existed, and where their items go.
     *
     * @var array<string, string>
     */
    private const TRIAL_CATEGORIES = [
        'Cereals and grains' => 'Food Stock',
        'Cooking ingredients' => 'Food Stock',
        'Cement and aggregates' => 'Maintenance and Hardware',
        'Steel' => 'Maintenance and Hardware',
        'Roofing' => 'Maintenance and Hardware',
        'Hardware' => 'Maintenance and Hardware',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::DEPARTMENTS as $name) {
            // Official departments serve every school, so they have no school of their own.
            if (! DB::table('departments')->whereNull('school_id')->where('name', $name)->exists()) {
                DB::table('departments')->insert([
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (self::UNITS as [$name, $abbreviation]) {
            if (! DB::table('inv_units')->where('name', $name)->orWhere('abbreviation', $abbreviation)->exists()) {
                DB::table('inv_units')->insert(['name' => $name, 'abbreviation' => $abbreviation, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $units = DB::table('inv_units')->pluck('id', 'abbreviation');

        foreach (self::CATALOGUE as $categoryName => $items) {
            $categoryId = DB::table('inv_item_categories')->where('name', $categoryName)->value('id')
                ?? DB::table('inv_item_categories')->insertGetId(['name' => $categoryName, 'created_at' => $now, 'updated_at' => $now]);

            foreach ($items as [$itemName, $unitAbbreviation]) {
                $existing = DB::table('inv_items')->where('name', $itemName)->first();

                if ($existing) {
                    // A trial item of the same name joins the official category and keeps its unit.
                    DB::table('inv_items')->where('id', $existing->id)->update(['item_category_id' => $categoryId]);

                    continue;
                }

                if (! isset($units[$unitAbbreviation])) {
                    continue;
                }

                DB::table('inv_items')->insert([
                    'item_category_id' => $categoryId,
                    'unit_id' => $units[$unitAbbreviation],
                    'name' => $itemName,
                    'reorder_level' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (self::TRIAL_CATEGORIES as $trialName => $officialName) {
            $trialId = DB::table('inv_item_categories')->where('name', $trialName)->value('id');
            $officialId = DB::table('inv_item_categories')->where('name', $officialName)->value('id');

            if ($trialId && $officialId) {
                DB::table('inv_items')->where('item_category_id', $trialId)->update(['item_category_id' => $officialId]);
                DB::table('inv_item_categories')->where('id', $trialId)->delete();
            }
        }
    }

    public function down(): void
    {
        // The departments, categories and items stay: by now they may be in use.
    }
};
