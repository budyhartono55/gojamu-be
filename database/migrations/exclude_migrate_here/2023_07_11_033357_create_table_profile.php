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
        Schema::create('profile', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text("about")->nullable();
            $table->text("visi")->nullable();
            $table->text("misi")->nullable();
            $table->text("caption_vm")->nullable();
            $table->text("maklumat_pelayanan")->nullable();
            $table->text("tugas_dan_fungsi")->nullable();
            $table->text("sop_ppidkab")->nullable();
            $table->text("profil_pimpinan")->nullable();
            $table->string("image_maklumat_pelayanan")->nullable();
            $table->string("image_tugas")->nullable();
            $table->string("image_struktur")->nullable();
            $table->string("image_about")->nullable();
            $table->string("image_profile_pimpinan")->nullable();
            $table->string('image_struktur_tpps')->nullable();
            $table->string('created_by')->nullable();
            $table->string('edited_by')->nullable();
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
        Schema::dropIfExists('profile');
    }
};
