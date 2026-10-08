<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetNullOnRideRequestBidsDriver extends Migration
{
    /**
     * Bids are part of a ride's history, so permanently deleting a driver must
     * not wipe them (it used to cascade). Mirrors the other ride-related tables.
     */
    public function up()
    {
        DB::statement('ALTER TABLE `ride_request_bids` MODIFY `driver_id` BIGINT UNSIGNED NULL');

        Schema::table('ride_request_bids', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
        });
        Schema::table('ride_request_bids', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('ride_request_bids', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
        });

        DB::table('ride_request_bids')->whereNull('driver_id')->delete();
        DB::statement('ALTER TABLE `ride_request_bids` MODIFY `driver_id` BIGINT UNSIGNED NOT NULL');

        Schema::table('ride_request_bids', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
}
