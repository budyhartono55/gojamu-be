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
        Schema::create('book_topic', function (Blueprint $table) {
            $table->uuid('book_id');
            $table->uuid('topic_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('book_id')->references('id')->on('book')->onDelete('cascade');
            $table->foreign('topic_id')->references('id')->on('topic')->onDelete('cascade');

            // Composite primary key to ensure unique pairs of book and topic
            $table->primary(['book_id', 'topic_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('book_topic');
    }
};
