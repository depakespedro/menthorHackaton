<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableIntersetcionsRespondetsSegments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('intersections_respondents_segments', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('project_id')->unsigned();
            $table->foreign('project_id')->references('id')->on('projects');

            $table->integer('respondent_one_id')->unsigned();
            $table->foreign('respondent_one_id')->references('id')->on('respondents');

            $table->integer('respondent_two_id')->unsigned();
            $table->foreign('respondent_two_id')->references('id')->on('respondents');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('intersections_respondents_segments');
    }
}
