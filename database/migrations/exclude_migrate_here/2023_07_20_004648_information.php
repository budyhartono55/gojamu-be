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
        Schema::create('informations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("kode_informasi")->unique(); //required
            $table->string("title_informasi"); //required
            $table->text("description")->nullable();
            $table->string("opd_penanggung_jawab")->nullable();
            $table->string("slug"); //auto generate
            $table->string("sumber")->nullable();
            $table->string("file"); //required
            $table->string("file_type"); //auto generate
            $table->string("file_size"); //auto generate
            $table->string("url")->nullable(); //auto generate
            $table->boolean("isSync")
                ->default(true)
                ->nullable(false)
                ->checkIn([true, false]);; //auto generate
            $table->boolean("isNew")
                ->default(true)
                ->nullable(false)
                ->checkIn([false, true]);; //auto generate
            $table->string("ctg_information_id");
            $table->string("user_id"); //auto generate
            $table->string("edited_by"); //auto generate
            $table->date("posted_at"); //required
            $table->timestamps();
            $table->softDeletes();

            // relations
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('ctg_information_id')->references('id')->on('ctg_informations');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('informations');
    }
};
