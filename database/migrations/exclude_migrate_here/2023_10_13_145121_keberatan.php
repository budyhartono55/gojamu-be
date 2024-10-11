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
        Schema::create('keberatan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            //sisi user ============
            $table->string("nama"); //required
            $table->string("identitas"); //required
            $table->string("no_identitas"); // required
            $table->text("scan_identitas"); // required
            $table->text("informasi_diminta");
            $table->text("alasan"); // required
            $table->text("keterangan")->nullable();
            $table->text("catatan")->nullable();

            // $table->enum("cara_memperoleh_informasi", ["Melihat", "Membaca", "Mendengarkan", "Mencatat"]); // required
            $table->string('status')
                ->default('Menunggu')
                ->nullable(false)
                ->checkIn(['Menunggu', 'Diproses', 'Dipenuhi', 'Ditolak']);
            $table->string("created_by", 100)->nullable();
            $table->string("edited_by", 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('keberatan');
    }
};
