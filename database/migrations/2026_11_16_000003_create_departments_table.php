<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            // No school: a department that serves every school (e.g. ICT, Finance).
            $table->foreignId('school_id')->nullable()->constrained('schools')->restrictOnDelete();
            $table->string('name', 100);
            // users.id is `int unsigned` (not bigint), so this must match for the FK to attach.
            $table->unsignedInteger('head_user_id')->nullable();
            $table->foreign('head_user_id')->references('id')->on('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
