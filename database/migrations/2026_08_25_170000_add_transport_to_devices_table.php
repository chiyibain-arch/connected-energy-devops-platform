<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->enum('transport', ['http', 'mqtt'])
                ->default('http')
                ->after('name')
                ->index();
            $table->string('api_key')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('devices')->whereNull('api_key')->exists()) {
            throw new RuntimeException('Cannot restore a non-nullable api_key while devices without HTTP credentials exist.');
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex(['transport']);
            $table->dropColumn('transport');
            $table->string('api_key')->nullable(false)->change();
        });
    }
};
