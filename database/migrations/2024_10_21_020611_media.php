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
            $table->text("description")->nullable();
            $table->text("ytb_url")->nullable();
            $table->date("posted_at")->nullable();
            $table->integer("like_count")->default(0);
            $table->integer("comment_count")->default(0);
            $table->integer("rate_count")->default(0);
            $table->string("report_stat")->nullable();
            $table->string("user_id")->nullable();
            $table->string("ctg_media_id")->nullable();
            $table->string("created_by"); //auto generate
            $table->string("edited_by"); //auto generate
            $table->timestamps();

            $table->foreign('ctg_media_id')->references('id')->on('ctg_media');
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
        Schema::dropIfExists('media');
    }
};
