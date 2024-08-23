<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWalletsSolanaWithdrawTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets_solana_withdraw', function (Blueprint $table) {
            $table->id();
            $table->foreignId('address_id')->constrained('wallets_solana_address')->onDelete('cascade');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->foreign('transaction_id')->references('id')->on('wallets_solana_transactions');
            $table->decimal('amount', 20, 8);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets_solana_withdraw');
    }
};
