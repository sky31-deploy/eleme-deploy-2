<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBuildsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('builds', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('site_id');
            $table->integer('job_id')->default(0);
            $table->string('commit')->default('');
            // 可以认为是branch,但是因为本身支持例如commit,或者tag的checkout,所以改用checkout
            $table->string('checkout');
            $table->string('status')->default('');
            $table->string('status_info')->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::drop('builds');
    }

}
