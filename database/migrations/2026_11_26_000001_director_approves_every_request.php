<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Director now signs every request, not only those a category or cost sends up.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('workflow_steps')->where('key', 'director')->update(['condition' => 'always']);
    }

    public function down(): void
    {
        DB::table('workflow_steps')->where('key', 'director')->update(['condition' => 'director_rule']);
    }
};
