<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            // users.id is `int unsigned` (not bigint), so this must match for the FK to attach.
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            // supporting (sent with the request) or completion (evidence the work was done)
            $table->string('kind', 20)->default('supporting');
            $table->string('original_name', 255);
            $table->string('path', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_attachments');
    }
};
