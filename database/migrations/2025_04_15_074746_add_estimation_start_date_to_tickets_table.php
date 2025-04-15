<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            if(Schema::hasColumn('tickets', 'estimation_start_date')){
                return;
            }else{
                $table->dateTime('estimation_start_date')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            if(Schema::hasColumn('tickets', 'estimation_start_date')){
                $table->dropColumn('estimation_start_date');
            }
        });
    }
};
