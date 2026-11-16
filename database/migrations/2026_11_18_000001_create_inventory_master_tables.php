<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory module, set-up tables. Every inventory table starts with `inv_` so the module
 * stays apart from the rest of the school management system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_stores', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 20)->nullable()->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Storekeepers: the users who record stock movements for a store.
        Schema::create('inv_store_keepers', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained('inv_stores')->cascadeOnDelete();
            // users.id is `int unsigned` (not bigint), so this must match for the FK to attach.
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->primary(['store_id', 'user_id']);
        });

        Schema::create('inv_units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('abbreviation', 15)->unique();
            $table->timestamps();
        });

        Schema::create('inv_item_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('inv_stores')->restrictOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['store_id', 'name']);
        });

        Schema::create('inv_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('inv_stores')->restrictOnDelete();
            $table->foreignId('item_category_id')->nullable()->constrained('inv_item_categories')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('inv_units')->restrictOnDelete();
            $table->string('code', 30)->nullable()->unique();
            $table->string('name', 150);
            // Stock at or below this quantity shows on the low-stock list.
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'name']);
        });

        Schema::create('inv_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('tin', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('inv_stores')->insert([
            ['name' => 'Food Store', 'code' => 'FOOD', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Building Materials Store', 'code' => 'BUILD', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $units = [
            ['Kilogram', 'kg'], ['Gram', 'g'], ['Litre', 'L'], ['Piece', 'pc'], ['Bag', 'bag'], ['Sack', 'sack'],
            ['Box', 'box'], ['Carton', 'ctn'], ['Packet', 'pkt'], ['Tin', 'tin'], ['Bundle', 'bdl'], ['Roll', 'roll'],
            ['Metre', 'm'], ['Sheet', 'sheet'], ['Trip', 'trip'],
        ];

        foreach ($units as [$name, $abbreviation]) {
            DB::table('inv_units')->insert(['name' => $name, 'abbreviation' => $abbreviation, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_suppliers');
        Schema::dropIfExists('inv_items');
        Schema::dropIfExists('inv_item_categories');
        Schema::dropIfExists('inv_units');
        Schema::dropIfExists('inv_store_keepers');
        Schema::dropIfExists('inv_stores');
    }
};
