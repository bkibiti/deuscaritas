<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            // A request returned for correction and sent again starts a new round.
            $table->unsignedSmallInteger('round')->default(1);
            $table->unsignedSmallInteger('position');
            $table->string('step_key', 30);
            $table->string('step_name', 100);

            // users.id is `int unsigned` (not bigint), so user columns must match for the FK to attach.
            $table->unsignedInteger('approver_id')->nullable();
            $table->foreign('approver_id')->references('id')->on('users')->nullOnDelete();

            // waiting (not reached yet), pending (its turn), approved, rejected, returned, skipped
            $table->string('status', 20)->default('waiting');
            $table->timestamp('activated_at')->nullable();
            $table->unsignedInteger('acted_by')->nullable();
            $table->foreign('acted_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->text('comment')->nullable();
            // SHA-256 of the request's content at the moment it was signed.
            $table->string('content_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['approver_id', 'status']);
            $table->index(['request_id', 'round', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_approvals');
    }
};
