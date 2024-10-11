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
        Schema::create('announcement', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title_announcement");
            $table->string("slug");
            $table->text("description")->nullable();
            $table->date("posted_at");
            $table->string("evidence")->nullable();
            $table->text("url_location")->nullable();
            $table->text("document")->nullable();
            $table->string("event_id")->nullable();
            $table->string('created_by')->nullable();
            $table->string('edited_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
        Schema::dropIfExists('announcement');
    }
};
