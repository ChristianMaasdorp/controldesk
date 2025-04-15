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
                $table->integer('estimation_start_date')->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            //
        });
    }
};
