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
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title_layanan"); //required
            $table->text("description")->nullable();
            $table->string("url")->nullable();
            $table->text("icon")->nullable();
            $table->string("slug"); // auto generate
            $table->string("created_by"); // auto generate
            $table->string("edited_by"); //auto generate
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
        Schema::dropIfExists('services');
    }
};
