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
        Schema::create('ctg_service', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title_ctg")->nullable();
            $table->string("slug")->nullable(); //auto generate
            $table->string("icon")->nullable();
            $table->string("react_icon")->nullable();
            $table->string("color")->nullable();
            $table->string("created_by"); //auto generate
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
        Schema::dropIfExists('ctg_service');
    }
};
