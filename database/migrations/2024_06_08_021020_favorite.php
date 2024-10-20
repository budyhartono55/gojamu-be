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
        Schema::create('favorite', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("content_id");
            $table->string("user_id");
            $table->string("book_id");
            $table->string('created_by')->nullable();
            $table->string('edited_by')->nullable();
            $table->timestamps();
            $table->foreign('content_id')->references('id')->on('content')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('user')->onDelete('cascade');
            $table->foreign('book_id')->references('id')->on('books')->onDelete('cascade');
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
        Schema::dropIfExists('favorite');
    }
};
