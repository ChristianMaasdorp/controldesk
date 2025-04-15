<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            if(Schema::hasColumn('tickets', 'estimation_hours')){
                return;
            }else{
                $table->integer('estimation_hours')->default(0)->after('status');
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
