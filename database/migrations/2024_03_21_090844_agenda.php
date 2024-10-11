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
        Schema::create('agenda', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title_agenda")->nullable();
            $table->text("description")->nullable();
            $table->text("location")->nullable();
            $table->string("url_location")->nullable();
            $table->string("slug")->nullable(); //auto
            $table->string("hold_at")->nullable();
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
        Schema::dropIfExists('agenda');
    }
};
