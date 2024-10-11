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
        Schema::create('base', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title_base")->nullable();
            $table->string("location")->nullable();
            $table->string("url_location")->nullable();
            $table->integer("mem_quantity")->nullable();
            $table->string("slug")->nullable(); //auto
            $table->string("photo")->nullable();
            $table->string("asal_kab_id")->nullable();
            $table->string("event_id")->nullable();
            $table->string("created_by"); //auto generate
            $table->string("edited_by"); //auto generate
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('event_program');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('base');
    }
};
