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
        Schema::create('liaison', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("name");
            $table->string("contact");
            $table->string("gender");
            $table->text("penanggung_jawab")->nullable();
            $table->text("image")->nullable();
            $table->string("kab_id")->nullable();
            $table->string("event_id")->nsullable();
            $table->string('created_by')->nullable();
            $table->string('edited_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('kab_id')->references('id')->on('kabupaten');
            $table->foreign('event_id')->references('id')->on('event_program');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('edited_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('liaison');
    }
};