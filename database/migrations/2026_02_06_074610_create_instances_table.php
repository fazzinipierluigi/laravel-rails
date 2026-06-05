<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('instanceable_type');
            $table->string('instanceable_id');
            $table->index(['instanceable_type', 'instanceable_id']);
            $table->foreignUuid('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            $table->foreignUuid('state_id')->references('id')->on('states')->onDelete('cascade');
            $table->json('variables')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instances');
    }
};
