<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            // The Director signs when either rule matches: the category always needs it, or
            // the request's estimated cost reaches the threshold.
            $table->boolean('requires_director')->default(false);
            $table->decimal('director_cost_threshold', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $names = ['Maintenance', 'ICT Support', 'Academic Requirements', 'Office Supplies', 'Transport', 'Finance', 'Other'];

        foreach ($names as $name) {
            DB::table('request_categories')->insert([
                'name' => $name,
                'requires_director' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('request_categories');
    }
};
