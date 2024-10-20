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
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title_media")->nullable();
            $table->text("ytb_url")->nullable();
            $table->date("posted_at")->nullable();
            $table->date("like_count")->nullable();
            $table->date("comment_count")->nullable();
            $table->date("rate_count")->nullable();
            $table->string("user_id")->nullable();
            $table->string("topic_id")->nullable();
            $table->string("ctg_media_id")->nullable();

            $table->string("created_by"); //auto generate
            $table->string("edited_by"); //auto generate
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('user');
            $table->foreign('topic_id')->references('id')->on('topic');
            $table->foreign('ctg_media_id')->references('id')->on('ctg_media');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('media');
    }
};
