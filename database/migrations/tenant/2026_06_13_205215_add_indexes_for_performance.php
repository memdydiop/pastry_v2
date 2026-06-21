<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
            $table->index('client_id');
            $table->index('user_id');
            $table->index('delivery_due_at');
            $table->index(['client_id', 'status']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index('type');
            $table->index('order_id');
            $table->index('parent_transaction_id');
            $table->index('user_id');
            $table->index(['order_id', 'type']);
            $table->index(['type', 'paid_at']);
        });

        Schema::table('order_levels', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('order_status_logs', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('user_id');
        });

        Schema::table('order_images', function (Blueprint $table) {
            $table->index('order_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index('ingredient_id');
            $table->index('supplier_id');
            $table->index('user_id');
            $table->index('order_id');
            $table->index('type');
            $table->index(['ingredient_id', 'type']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->index('is_critical');
            $table->index('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['client_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['delivery_due_at']);
            $table->dropIndex(['client_id', 'status']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['order_id']);
            $table->dropIndex(['parent_transaction_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['order_id', 'type']);
            $table->dropIndex(['type', 'paid_at']);
        });

        Schema::table('order_levels', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('order_status_logs', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('order_images', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['ingredient_id']);
            $table->dropIndex(['supplier_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['order_id']);
            $table->dropIndex(['type']);
            $table->dropIndex(['ingredient_id', 'type']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex(['is_critical']);
            $table->dropIndex(['stock_quantity']);
        });
    }
};
