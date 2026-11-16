<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            // REQ-2026-000145, given when the request is first submitted. A draft has none.
            $table->string('number', 20)->nullable()->unique();

            // users.id is `int unsigned` (not bigint), so user columns must match for the FK to attach.
            $table->unsignedInteger('requester_id');
            $table->foreign('requester_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            // The school the request is for: the department's own, or the one the requester
            // chose when the department serves every school. It decides the Head of School.
            $table->foreignId('school_id')->nullable()->constrained('schools')->restrictOnDelete();
            $table->foreignId('request_category_id')->constrained('request_categories')->restrictOnDelete();

            $table->string('title', 150);
            $table->text('description');
            $table->text('justification')->nullable();
            $table->date('required_date')->nullable();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->string('suggested_priority', 10)->nullable();

            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->unsignedInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedInteger('completed_by')->nullable();
            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        // One row per year; locked while the next request number is taken.
        Schema::create('request_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_sequences');
        Schema::dropIfExists('requests');
    }
};
