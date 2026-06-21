<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS cmd_seq START 1 INCREMENT 1 NO CYCLE');
        }

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->string('type')->default('payment');

            $table->foreignId('order_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('parent_transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->onDelete('set null');

            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->nullable();
            $table->dateTime('paid_at')->index();
            $table->string('reference')->nullable();
            $table->string('external_ref')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('edit_old_values')->nullable();

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('restrict');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS cmd_seq');
        }
    }
};
