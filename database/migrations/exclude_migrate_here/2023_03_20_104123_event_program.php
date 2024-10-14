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
        Schema::create('event_program', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title_event")->nullable();
            $table->text("description")->nullable();
            $table->string("location")->nullable();
            $table->string("url_location")->nullable();
            $table->date("start_date")->nullable();
            $table->date("end_date")->nullable(); //auto
            $table->string("banner")->nullable();
            $table->string("slug"); //auto generate
            $table->text("agenda")->nullable(); //auto generate
            $table->text("guide_book")->nullable(); //auto generate
            $table->string("created_by"); //auto generate
            $table->string("edited_by"); //auto generate
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
        Schema::dropIfExists('event_program');
    }
};
