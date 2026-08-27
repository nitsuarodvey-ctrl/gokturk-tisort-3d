<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('phone', 32);
            $table->string('size', 2);
            $table->unsignedInteger('quantity');
            $table->string('delivery_type', 40);
            $table->string('city', 80)->nullable();
            $table->string('district', 80)->nullable();
            $table->string('address', 500)->nullable();
            $table->unsignedInteger('unit_price')->default(499);
            $table->unsignedInteger('total');
            $table->string('payment_status', 20)->default('waiting');
            $table->string('order_status', 20)->default('preorder');
            $table->string('production_status', 30)->default('waiting');
            $table->string('delivery_status', 30)->default('waiting');
            $table->string('notes', 2000)->nullable();
            $table->timestamps(3);
            $table->index('created_at');
            $table->index(['order_status', 'payment_status']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders ADD CONSTRAINT qty_chk CHECK (quantity BETWEEN 1 AND 20)');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT price_chk CHECK (unit_price = 499)');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT total_chk CHECK (total = quantity * unit_price)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
