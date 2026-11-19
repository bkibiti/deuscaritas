<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departments briefly had a "responsibilities" text. It is not needed: remove the column from
 * any database that already got it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('departments', 'description')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }

    public function down(): void
    {
        // Nothing to restore.
    }
};
