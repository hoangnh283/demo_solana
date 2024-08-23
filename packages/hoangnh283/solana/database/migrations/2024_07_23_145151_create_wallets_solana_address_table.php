<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWalletsSolanaAddressTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets_solana_address', function (Blueprint $table) {
            $table->id();
            $table->string('address')->unique();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('secret_key');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets_solana_address');
    }
};
