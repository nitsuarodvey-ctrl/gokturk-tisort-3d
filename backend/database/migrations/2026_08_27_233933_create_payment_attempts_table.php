<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('merchant_order_id', 64)->unique();
            $table->unsignedInteger('amount');
            $table->char('currency_code', 4)->default('0949');
            $table->string('status', 32)->index();
            $table->string('gateway_order_id', 64)->nullable()->index();
            $table->string('provision_number', 64)->nullable();
            $table->string('rrn', 64)->nullable();
            $table->string('stan', 64)->nullable();
            $table->string('response_code', 10)->nullable();
            $table->string('response_message', 255)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->string('business_key', 100)->nullable();
            $table->timestamp('completed_at', 3)->nullable();
            $table->timestamps(3);
            $table->index(['order_id', 'created_at']);
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
