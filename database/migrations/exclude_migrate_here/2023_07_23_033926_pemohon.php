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
        Schema::create('pemohon', function (Blueprint $table) {
            $table->uuid('id')->primary();
            //sisi user ============
            $table->string("name"); //required
            $table->string("nik"); //required
            $table->text("address")->nullable();
            $table->string("email"); // required
            $table->string("contact"); // required
            $table->string("job")->nullable();
            $table->text("judul_informasi"); // required
            $table->text("rincian_informasi"); // required
            $table->text("tujuan_penggunaan"); // required
            // $table->enum("cara_memperoleh_informasi", ["Melihat", "Membaca", "Mendengarkan", "Mencatat"]); // required
            $table->string('cara_memperoleh_informasi')
                ->default('Melihat')
                ->nullable(false)
                ->checkIn(['Melihat', 'Membaca', 'Mendengarkan', 'Mencatat']);
            // $table->enum("mendapatkan_salinan_informasi", ["Softcopy", "Hardcopy"]); // required
            $table->string('mendapatkan_salinan_informasi')
                ->default('Softcopy')
                ->nullable(false)
                ->checkIn(['Softcopy', 'Hardcopy']);
            // $table->enum("cara_mendapatkan_salinan_informasi", ["Mengambil Langsung", "Faksimili", "Email", "WhatsApp"]); // required
            $table->string('cara_mendapatkan_salinan_informasi')
                ->default('WhatsApp')
                ->nullable(false)
                ->checkIn(['Mengambil Langsung', 'Faksimili', 'Email', 'WhatsApp']);

            //sisi admin ============
            // $table->enum("status", ['diajukan', 'disetujui']]); // auto generate
            $table->string('status')
                ->default('Diajukan')
                ->nullable(false)
                ->checkIn(['Diajukan', 'Diproses', 'Disetujui', 'Ditolak']);
            $table->string("file")->nullable();
            $table->string("ctg_information_id")->nullable();
            $table->string("ctg_pemohon_id")->nullable();
            $table->string("tujuan_opd")->nullable();
            $table->string("keterangan")->nullable();
            $table->string("url")->nullable();
            $table->string("kode_permohonan"); //auto generate
            $table->string("approved_by")->nullable(); //auto generate
            $table->timestamps();
            $table->softDeletes();


            // relations
            $table->foreign('ctg_pemohon_id')->references('id')->on('ctg_pemohon');
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
        Schema::dropIfExists('pemohon');
    }
};
