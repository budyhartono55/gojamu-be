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
        Schema::create('galleries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title_gallery")->nullable();
            $table->text("description")->nullable();
            $table->string('image')->nullable();
            $table->string('ctg_gallery_id')->nullable();
            $table->string('created_by'); // auto generate
            $table->string('edited_by'); //auto generate
            $table->timestamps();

            $table->foreign('ctg_gallery_id')->references('id')->on('ctg_gallery');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('galleries');
    }
};
