<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableIntersectionsSegmetns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('intersections_segments', function (Blueprint $table) {
            $table->increments('id');

            $table->string('title');

            $table->integer('project_id')->unsigned();
            $table->foreign('project_id')->references('id')->on('projects');

            $table->integer('segment_one_id')->unsigned();
            $table->foreign('segment_one_id')->references('id')->on('segments');

            $table->integer('segment_two_id')->unsigned();
            $table->foreign('segment_two_id')->references('id')->on('segments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('intersections_segments');
    }
}
