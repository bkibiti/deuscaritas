<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock Out: an approved request is issued from the stores. An issue document records which
 * request it served and who received the items; each request line remembers how much was issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv_stock_documents', function (Blueprint $table) {
            $table->foreignId('request_id')->nullable()->after('supplier_id')->constrained('requests')->restrictOnDelete();
            // users.id is `int unsigned`, so this must match for the FK to attach.
            $table->unsignedInteger('received_by')->nullable()->after('confirmed_at');
            $table->foreign('received_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('request_items', function (Blueprint $table) {
            $table->decimal('issued_quantity', 14, 3)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->dropColumn('issued_quantity');
        });

        Schema::table('inv_stock_documents', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
            $table->dropForeign(['received_by']);
            $table->dropColumn(['request_id', 'received_by']);
        });
    }
};
