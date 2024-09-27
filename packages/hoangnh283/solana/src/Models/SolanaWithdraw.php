<?php

namespace Hoangnh283\Solana\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolanaWithdraw extends Model
{
    use HasFactory;

    protected $table = 'wallets_solana_withdraw';

    protected $fillable = ['address_id', 'amount', 'currency', 'transaction_id'];

    public function address()
    {
        return $this->belongsTo(SolanaAddress::class);
    }
    public function transaction()
    {
        return $this->belongsTo(SolanaTransaction::class, 'transaction_id');
    }
}
