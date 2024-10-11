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
        Schema::table('event_program', function ($table) {
            $table->string('venue_img')->nullable();
            $table->text('venue_desc')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */

    public function down()
    {
        Schema::table('event_program', function ($table) {
            $table->dropColumn('venue_img');
            $table->dropColumn('venue_desc');
        });
    }
};
