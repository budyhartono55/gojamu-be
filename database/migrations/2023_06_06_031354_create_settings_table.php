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
        Schema::create('setting', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('title_jumbotron')->nullable();
            $table->text('moto_jumbotron')->nullable();
            $table->text('image_jumbotron')->nullable();
            $table->text('title_app')->nullable();
            $table->text('about_app')->nullable();
            $table->text('address_app')->nullable();
            $table->text('contact_app')->nullable();
            $table->text('facebook_app')->nullable();
            $table->text('instagram_app')->nullable();
            $table->text('image1_app')->nullable();
            $table->text('image2_app')->nullable();
            $table->text('image3_app')->nullable();
            $table->text('title_promote')->nullable();
            $table->text('link_promote')->nullable();
            $table->string('created_by');
            $table->string('edited_by')->nullable();
            $table->timestamps();
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
        Schema::dropIfExists('setting');
    }
};
