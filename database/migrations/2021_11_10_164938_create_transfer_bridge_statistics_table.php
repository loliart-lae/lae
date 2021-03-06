<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransferBridgeStatisticsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfer_bridge_statistics', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('send_count')->index()->default(0);

            // Power Bridge 集群权限
            $table->unsignedBigInteger('transfer_bridge_id')->index()->nullable();
            $table->foreign('transfer_bridge_id')->references('id')->on('transfer_bridges')->cascadeOnDelete();

            // Power Bridge 组权限配置
            $table->unsignedBigInteger('transfer_bridge_group_id')->index()->nullable();
            $table->foreign('transfer_bridge_group_id')->references('id')->on('transfer_bridge_groups')->cascadeOnDelete();

            // Power Bridge 客户机权限配置
            $table->unsignedBigInteger('transfer_bridge_guest_id')->index()->nullable();
            $table->foreign('transfer_bridge_guest_id')->references('id')->on('transfer_bridge_guests')->cascadeOnDelete();

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
        Schema::dropIfExists('transfer_bridge_statistics');
    }
}