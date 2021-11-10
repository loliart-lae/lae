<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePowerBridgeStatisticsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('power_bridge_statistics', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('send_count')->index()->default(0);

            // Power Bridge 集群权限
            $table->unsignedBigInteger('power_bridge_id')->index()->nullable();
            $table->foreign('power_bridge_id')->references('id')->on('power_bridges');

            // Power Bridge 组权限配置
            $table->unsignedBigInteger('power_bridge_group_id')->index()->nullable();
            $table->foreign('power_bridge_group_id')->references('id')->on('power_bridge_groups');

            // Power Bridge 客户机权限配置
            $table->unsignedBigInteger('power_bridge_guest_id')->index()->nullable();
            $table->foreign('power_bridge_guest_id')->references('id')->on('power_bridge_guests');

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
        Schema::dropIfExists('power_bridge_statistics');
    }
}