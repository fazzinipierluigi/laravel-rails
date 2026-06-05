<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->json('view_permissions')->nullable()->after('is_end');
            $table->string('view_operator', 3)->default('OR')->after('view_permissions');
        });
    }

    public function down(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn(['view_permissions', 'view_operator']);
        });
    }
};
