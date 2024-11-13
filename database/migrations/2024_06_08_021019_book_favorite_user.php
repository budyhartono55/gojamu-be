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
        Schema::create('book_favorite_user', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->constrained()->onDelete('cascade');
            $table->uuid('book_id')->constrained()->onDelete('cascade');
            $table->timestamp('marked_at')->nullable(); // Optional: Track when the book was marked.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('book_favorite_user');
    }
};
