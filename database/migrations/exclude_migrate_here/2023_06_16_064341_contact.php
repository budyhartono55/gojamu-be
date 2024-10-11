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
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text("address")->nullable();
            $table->string("url_address")->nullable();
            $table->string("contact")->nullable();
            $table->string("email")->nullable();
            $table->string("facebook")->nullable();;
            $table->string("instagram")->nullable();
            $table->string("twitter")->nullable();;
            $table->string("youtube")->nullable();;
            $table->string("tiktok")->nullable();
            $table->string("website")->nullable();
            $table->text('url_address')->nullable()->change();
            $table->string('created_by'); // auto generate
            $table->string('edited_by'); //auto generate
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
        Schema::dropIfExists('contacts');
    }
};
