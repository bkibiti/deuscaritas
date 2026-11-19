<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Stock ledger and Stock In. A store holds one pool of stock that every school draws from.
 * Documents (deliveries, opening stock) are drafted, then confirmed; confirming writes ledger
 * lines and moves the balance. Balances are never edited by hand.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $permissionNames = ['View Stock', 'Receive Stock'];

    public function up(): void
    {
        Schema::create('inv_document_sequences', function (Blueprint $table) {
            $table->string('prefix', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->primary(['prefix', 'year']);
        });

        Schema::create('inv_stock_documents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('type', 20); // receipt | opening
            $table->foreignId('store_id')->constrained('inv_stores')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('inv_suppliers')->restrictOnDelete();
            $table->string('reference', 100)->nullable(); // delivery note / invoice number
            $table->date('document_date');
            $table->string('notes', 500)->nullable();
            $table->string('status', 20)->default('draft'); // draft | confirmed
            // users.id is `int unsigned`, so these must match for the FKs to attach.
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('confirmed_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('inv_stock_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('inv_stock_documents')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inv_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->timestamps();
        });

        // Current quantity and value of each item in each store.
        Schema::create('inv_stock_balances', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained('inv_stores')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('inv_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('value', 16, 2)->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->primary(['store_id', 'item_id']);
        });

        // Every movement ever made. Quantity and value are signed: in is positive, out negative.
        Schema::create('inv_stock_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('inv_stores')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('inv_items')->restrictOnDelete();
            $table->foreignId('document_id')->constrained('inv_stock_documents')->restrictOnDelete();
            $table->date('movement_date');
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('value', 16, 2)->default(0);
            $table->decimal('balance_after', 14, 3);
            $table->timestamps();

            $table->index(['store_id', 'item_id', 'id']);
        });

        $now = now();

        foreach ($this->permissionNames as $name) {
            if (! DB::table('permissions')->where('name', $name)->exists()) {
                DB::table('permissions')->insert(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $superuserId = DB::table('roles')->where('name', 'Superuser')->value('id');

        if ($superuserId) {
            foreach (DB::table('permissions')->whereIn('name', $this->permissionNames)->pluck('id') as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $superuserId, 'permission_id' => $permissionId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_stock_ledger');
        Schema::dropIfExists('inv_stock_balances');
        Schema::dropIfExists('inv_stock_document_lines');
        Schema::dropIfExists('inv_stock_documents');
        Schema::dropIfExists('inv_document_sequences');

        $ids = DB::table('permissions')->whereIn('name', $this->permissionNames)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
