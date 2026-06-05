<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transitions', function (Blueprint $table) {
            $table->json('view_permissions')->nullable()->after('permission');
            $table->string('view_operator', 3)->default('OR')->after('view_permissions');
            $table->json('advance_permissions')->nullable()->after('view_operator');
            $table->string('advance_operator', 3)->default('OR')->after('advance_permissions');
        });
    }

    public function down(): void
    {
        Schema::table('transitions', function (Blueprint $table) {
            $table->dropColumn(['view_permissions', 'view_operator', 'advance_permissions', 'advance_operator']);
        });
    }
};
