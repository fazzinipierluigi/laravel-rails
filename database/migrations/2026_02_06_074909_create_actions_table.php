<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('actionable_type');
            $table->uuid('actionable_id');
            $table->index(['actionable_type', 'actionable_id']);
            $table->unsignedInteger('sort')->default(0);
            $table->string('phase')->nullable();
            $table->string('action');
            $table->json('configuration')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actions');
    }
};
