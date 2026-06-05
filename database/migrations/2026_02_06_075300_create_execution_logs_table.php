<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('instance_id')->references('id')->on('instances')->onDelete('cascade');
            $table->string('event', 60);
            $table->string('subject_type', 30)->nullable(); // state | transition | action
            $table->uuid('subject_id')->nullable();         // FK to states.id or transitions.id
            $table->json('data');
            $table->string('triggered_by', 120)->nullable();
            $table->timestamp('occurred_at');

            $table->index(['instance_id', 'occurred_at']);
            $table->index(['instance_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_logs');
    }
};
