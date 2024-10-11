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
        Schema::create('service', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title_service")->nullable();
            $table->string("facility")->nullable();
            $table->text("description")->nullable();
            $table->string("address")->nullable();
            $table->string("url_location")->nullable();
            $table->string("v_distance")->nullable();
            $table->string("v_duration")->nullable();
            $table->string("contact")->nullable();
            $table->string("email")->nullable();
            $table->string("facebook")->nullable();
            $table->string("instagram")->nullable();
            $table->string("twitter")->nullable();
            $table->string("youtube")->nullable();
            $table->string("tiktok")->nullable();
            $table->string("website")->nullable();
            $table->string("slug")->nullable(); //auto
            $table->string("photo")->nullable();
            $table->string("ctg_service_id")->nullable();
            $table->string("district_id")->nullable();
            $table->string("created_by"); //auto generate
            $table->string("edited_by"); //auto generate
            $table->timestamps();

            $table->foreign('ctg_service_id')->references('id')->on('ctg_service');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('service');
    }
};
