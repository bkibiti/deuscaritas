<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The lines of a request, as on the paper form: particular, quantity, rate, amount.
        // The particular is always an inventory item, never free text.
        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inv_items')->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->decimal('quantity', 14, 3);
            // The rate may be unknown when the request is raised.
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['request_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};
