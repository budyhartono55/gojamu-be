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
        Schema::create('berkas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("kode_berkas")->unique(); //required
            $table->string("title_berkas"); //required
            $table->text("description")->nullable();
            $table->string("opd_penanggung_jawab")->nullable();
            $table->string("slug"); //auto generate
            $table->string("sumber")->nullable();
            $table->string("file"); //required
            $table->string("file_type"); //auto generate
            $table->string("file_size"); //auto generate
            $table->string("url")->nullable(); //auto generate
            $table->string('ctg_berkas_id')->nullable();
            $table->string("created_by"); //auto generate
            $table->string("edited_by"); //auto generate
            $table->date("posted_at"); //required
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('ctg_berkas_id')->references('id')->on('ctg_berkas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('berkas');
    }
};
