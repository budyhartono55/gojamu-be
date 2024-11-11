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
        Schema::create('pivot_media_topic', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("media_id");
            $table->string("topic_id");
            $table->string("created_by"); //auto generate
            $table->string("edited_by"); //auto generate
            $table->timestamps();

            $table->primary(['media_id', 'topic_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pivot_media_topic');
    }
};