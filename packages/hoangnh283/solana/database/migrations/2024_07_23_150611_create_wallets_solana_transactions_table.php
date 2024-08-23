<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWalletsSolanaTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets_solana_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('from_address');
            $table->string('type');
            $table->string('to_address')->nullable();
            $table->string('signatures')->nullable();
            $table->decimal('amount', 20, 8);
            $table->decimal('fee', 20, 8);
            $table->string('status');
            $table->timestamp('block_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets_solana_transactions');
    }
};
