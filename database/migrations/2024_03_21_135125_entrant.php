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
        Schema::create('entrant', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("name")->nullable();
            $table->string("mem_evidence")->nullable();
            $table->string("contact")->nullable();
            $table->string("gender")
                ->checkIn(["Laki-laki", "Perempuan"])
                ->nullable();
            $table->string("photo")->nullable();
            $table->string("asal_kab_id")->nullable();
            $table->string("event_id")->nullable();
            $table->string("base_id")->nullable();
            $table->string("contest_id")->nullable();
            $table->string("created_by"); //auto generate
            $table->string("edited_by"); //auto generate
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('event_program');
            $table->foreign('base_id')->references('id')->on('base');
            $table->foreign('contest_id')->references('id')->on('contest');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('entrant');
    }
};
