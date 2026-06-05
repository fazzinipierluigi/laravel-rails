<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transitions', function (Blueprint $table) {
            $table->string('form_type', 10)->nullable()->after('redirect');
            $table->longText('form_data')->nullable()->after('form_type');
        });
    }

    public function down(): void
    {
        Schema::table('transitions', function (Blueprint $table) {
            $table->dropColumn(['form_type', 'form_data']);
        });
    }
};
