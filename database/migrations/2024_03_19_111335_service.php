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
            $table->string("icon")->nullable();
            $table->text("url")->nullable();
            $table->string("ctg_service_id")->nullable();
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
