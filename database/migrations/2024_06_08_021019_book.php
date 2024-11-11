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
        Schema::create('book', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title");
            $table->text("file")->nullable();
            $table->string("file_size");
            $table->text("cover")->nullable();
            $table->date("posted_at");
            $table->string("ctg_book_id");
            // $table->string("topic_id");
            $table->string('created_by')->nullable();
            $table->string('edited_by')->nullable();
            $table->timestamps();

            $table->foreign('ctg_book_id')->references('id')->on('ctg_book');
            // $table->foreign('topic_id')->references('id')->on('topic');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('book');
    }
};
