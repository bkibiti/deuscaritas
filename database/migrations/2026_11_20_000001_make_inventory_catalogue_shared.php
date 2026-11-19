<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One catalogue for the whole organisation. Categories and items no longer belong to a
 * store: an item is registered once, and (from the stock ledger onwards) each store holds
 * its own balance of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->mergeDuplicateCategories();

        if (Schema::hasColumn('inv_items', 'store_id')) {
            $this->renameDuplicateItems();
        }

        // SQLite changes a table by rebuilding it, which would clear each item's category
        // through the foreign key. Remember the links and put them back afterwards.
        $categoryOfItem = DB::table('inv_items')->whereNotNull('item_category_id')->pluck('item_category_id', 'id');

        foreach (['inv_items', 'inv_item_categories'] as $tableName) {
            $this->detachFromStore($tableName);
        }

        foreach ($categoryOfItem as $itemId => $categoryId) {
            DB::table('inv_items')->where('id', $itemId)->whereNull('item_category_id')->update(['item_category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        $storeId = DB::table('inv_stores')->orderBy('id')->value('id');

        foreach (['inv_item_categories', 'inv_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['name']);
                $table->foreignId('store_id')->nullable()->constrained('inv_stores')->restrictOnDelete();
            });

            DB::table($tableName)->update(['store_id' => $storeId]);

            Schema::table($tableName, function (Blueprint $table) {
                $table->unique(['store_id', 'name']);
            });
        }
    }

    /**
     * Remove store_id from a table and make names unique on their own. Each step checks what
     * is there first, so a run that stopped halfway (MySQL does not undo schema changes) can
     * simply be run again.
     */
    private function detachFromStore(string $tableName): void
    {
        if (Schema::hasColumn($tableName, 'store_id')) {
            // The foreign key goes first: MySQL uses the (store_id, name) index for it and
            // will not drop an index a foreign key still needs.
            $hasForeignKey = collect(Schema::getForeignKeys($tableName))->contains(fn (array $key) => $key['columns'] === ['store_id']);

            if ($hasForeignKey) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign(['store_id']));
            }

            if ($this->hasIndex($tableName, ['store_id', 'name'])) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropUnique(['store_id', 'name']));
            }

            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('store_id'));
        }

        if (! $this->hasIndex($tableName, ['name'])) {
            Schema::table($tableName, fn (Blueprint $table) => $table->unique('name'));
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasIndex(string $tableName, array $columns): bool
    {
        return collect(Schema::getIndexes($tableName))->contains(fn (array $index) => $index['columns'] === $columns);
    }

    /**
     * Two stores may each have had a category of the same name. Keep the first and move the
     * other's items into it.
     */
    private function mergeDuplicateCategories(): void
    {
        $kept = [];

        foreach (DB::table('inv_item_categories')->orderBy('id')->get() as $category) {
            $key = mb_strtolower($category->name);

            if (! isset($kept[$key])) {
                $kept[$key] = $category->id;

                continue;
            }

            DB::table('inv_items')->where('item_category_id', $category->id)->update(['item_category_id' => $kept[$key]]);
            DB::table('inv_item_categories')->where('id', $category->id)->delete();
        }
    }

    /**
     * Two stores may each have had an item of the same name. They may be on requests, so
     * neither is removed: the later one gets its store's name added.
     */
    private function renameDuplicateItems(): void
    {
        $seen = [];

        foreach (DB::table('inv_items')->orderBy('id')->get() as $item) {
            $key = mb_strtolower($item->name);

            if (! isset($seen[$key])) {
                $seen[$key] = true;

                continue;
            }

            $storeName = DB::table('inv_stores')->where('id', $item->store_id)->value('name');
            $name = mb_substr($item->name.' ('.$storeName.')', 0, 150);

            DB::table('inv_items')->where('id', $item->id)->update(['name' => $name]);
            $seen[mb_strtolower($name)] = true;
        }
    }
};
