<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChangeUserCascadeDeletesToSetNull extends Migration
{
    /**
     * Columns that already have an ON DELETE CASCADE foreign key to users.id
     * and must be flipped to SET NULL. Ride, payment, complaint, review,
     * wallet and points history are records that must survive a permanently
     * deleted account for legal, tax and safety purposes - they just lose the
     * link to who they belonged to.
     *
     * @var array<string, string>
     */
    protected $cascadeColumns = [
        'ride_requests' => 'rider_id',
        'payments' => 'rider_id',
        'complaints' => 'rider_id',
        'reviews' => 'rider_id',
        'wallets' => 'user_id',
        'wallet_histories' => 'user_id',
        'withdraw_requests' => 'user_id',
        'points' => 'user_id',
        'points_histories' => 'user_id',
    ];

    /**
     * driver_id columns on these ride-related tables have no foreign key at
     * all today. Permanently deleting a driver (via a Driver Reactivation
     * Request) is a newly introduced flow, so these get a SET NULL foreign
     * key added from the start rather than being left to dangle.
     *
     * @var array<string, string>
     */
    protected $newSetNullColumns = [
        'ride_requests' => 'driver_id',
        'complaints' => 'driver_id',
        'reviews' => 'driver_id',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // payments.rider_id is NOT NULL today; it must be nullable before the
        // foreign key can SET NULL on delete. Done via raw SQL since
        // doctrine/dbal (required by Blueprint::change()) isn't installed.
        DB::statement('ALTER TABLE `payments` MODIFY `rider_id` BIGINT UNSIGNED NULL');

        foreach ($this->cascadeColumns as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->foreign($column)->references('id')->on('users')->onDelete('set null');
            });
        }

        foreach ($this->newSetNullColumns as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->foreign($column)->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach ($this->newSetNullColumns as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        }

        foreach ($this->cascadeColumns as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->foreign($column)->references('id')->on('users')->onDelete('cascade');
            });
        }

        DB::statement('ALTER TABLE `payments` MODIFY `rider_id` BIGINT UNSIGNED NOT NULL');
    }
}
