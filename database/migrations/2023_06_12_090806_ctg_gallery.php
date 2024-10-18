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
        Schema::create('ctg_gallery', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title_ctg");
            $table->string("slug"); //auto generate
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
        Schema::dropIfExists('ctg_gallery');
    }
};