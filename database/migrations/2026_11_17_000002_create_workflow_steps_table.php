<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('position');
            $table->string('key', 30)->unique();
            $table->string('name', 100);
            // Who signs: the head of the request's department, the head of its school, or
            // one named user (user_id).
            $table->string('approver_type', 20);
            // users.id is `int unsigned` (not bigint), so this must match for the FK to attach.
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            // "always", or "director_rule": only when the request's category or cost needs it.
            $table->string('condition', 20)->default('always');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $steps = [
            [1, 'department_head', 'Head of Department', 'department_head', 'always'],
            [2, 'school_head', 'Head of School', 'school_head', 'always'],
            [3, 'coordinator', 'School Coordinator', 'user', 'always'],
            [4, 'manager', 'Manager', 'user', 'always'],
            [5, 'director', 'Director', 'user', 'director_rule'],
        ];

        foreach ($steps as [$position, $key, $name, $approverType, $condition]) {
            DB::table('workflow_steps')->insert([
                'position' => $position,
                'key' => $key,
                'name' => $name,
                'approver_type' => $approverType,
                'condition' => $condition,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
