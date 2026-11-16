<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The request timeline and audit trail: one row per thing that happened.
        Schema::create('request_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            // users.id is `int unsigned` (not bigint), so this must match for the FK to attach.
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('action', 30);
            $table->string('step_name', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_actions');
    }
};
