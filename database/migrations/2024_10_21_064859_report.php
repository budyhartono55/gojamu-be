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
        Schema::create('report', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date("posted_at")->nullable();
            $table->string("media_id")->nullable();
            $table->string("comment_id")->nullable();
            $table->text("reason")->nullable();
            $table->string("user_id")->nullable();
            $table->string("created_by"); //auto generate
            $table->string("edited_by"); //auto generate
            $table->timestamps();

            $table->foreign('comment_id')->references('id')->on('comment');
            $table->foreign('media_id')->references('id')->on('media');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('report');
    }
};