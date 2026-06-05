<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            $table->string('slug')->nullable();
            $table->string('code')->nullable();
            $table->string('name');
            $table->boolean('is_start')->default(false);
            $table->boolean('is_end')->default(false);
            $table->float('x')->default(0);
            $table->float('y')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
