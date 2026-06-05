<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignUuid('from')->references('id')->on('states')->onDelete('cascade');
            $table->foreignUuid('to')->references('id')->on('states')->onDelete('cascade');
            $table->json('show_condition')->nullable();
            $table->json('execute_condition')->nullable();
            $table->json('exit_condition')->nullable();
            $table->string('permission')->nullable();
            $table->string('ui')->nullable();
            $table->string('redirect')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transitions');
    }
};
