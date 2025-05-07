<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ticket_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('old_responsible_id')->nullable()->after('new_status_id');
            $table->unsignedBigInteger('new_responsible_id')->nullable()->after('old_responsible_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ticket_activities', function (Blueprint $table) {
            $table->dropForeign(['old_responsible_id']);
            $table->dropForeign(['new_responsible_id']);
        });
    }
};
