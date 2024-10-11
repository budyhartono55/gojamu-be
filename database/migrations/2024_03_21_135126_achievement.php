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
        Schema::create('achievement', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title_achievement");
            $table->string("slug");
            $table->string("title_evidence");
            $table->integer("quantity_evidence")->nullable()->default(0);
            $table->string("document")->nullable();
            $table->string("event_id")->nullable();
            $table->string("contest_id")->nullable();
            $table->string("entrant_id")->nullable();
            $table->string("kab_id")->nullable();
            $table->string('created_by')->nullable();
            $table->string('edited_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('kab_id')->references('id')->on('kabupaten');
            $table->foreign('event_id')->references('id')->on('event_program');
            $table->foreign('contest_id')->references('id')->on('contest');
            $table->foreign('entrant_id')->references('id')->on('entrant');
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
        Schema::dropIfExists('achievement');
    }
};
