<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('consumables_users', function (Blueprint $table) {
            if (!Schema::hasColumn('consumables_users', 'asset_id')) {
                    $table->Integer('asset_id')->after('assigned_to')->nullable()->default(null);
                }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('consumables_users', function (Blueprint $table) {
            if (Schema::hasColumn('consumables_users', 'asset_id')) {
                $table->dropColumn('asset_id');
            }
        });
    }
};
