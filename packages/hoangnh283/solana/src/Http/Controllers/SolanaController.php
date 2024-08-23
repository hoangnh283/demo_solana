<?php

namespace Hoangnh283\Solana\Http\Controllers;
require 'vendor/autoload.php';
use Hoangnh283\Solana\Services\SolanaService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;
use Hoangnh283\Solana\Models\SolanaAddress;
use Hoangnh283\Solana\Models\SolanaTransaction;
use Hoangnh283\Solana\Models\SolanaWithdraw;

class SolanaController extends Controller
{
    protected $solanaService;
    protected $current_user_id = 1;

    public function __construct(SolanaService $solanaService)
    {
        $this->solanaService = $solanaService;
    }
    public function test(Request $request) {
        // $getSolPrice = $this->solanaService->getFees('https://api.devnet.solana.com');   
        // $getSolPrice = $this->solanaService->getConfirmedSignaturesForAddress2('8Po4tv7Yvwof4MdPP4n4ogMrQqVikqLP8EJKjmtkE71v', 10);
        // return response()->json($getSolPrice);
    }
    public function createAddress(Request $request)
    {
        $userId = $request->input('user_id');
        try {
            $result = $this->solanaService->createAddress();
            $address = SolanaAddress::create([
                'address' => $result['publicKey'],
                'secret_key' => json_encode($result['secretKey']),
                'user_id' => $userId,
            ]);
            return response()->json($address);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
        
    }

    public function deposit(Request $request)
    {
        $addressId = $request->input('address_id');
        $amount = $request->input('amount');

        try {
            $deposit = $this->solanaService->deposit($addressId, $amount);
            return response()->json($deposit);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function withdraw(Request $request)
    {
        $addressId = $request->input('address_id');
        $amount = $request->input('amount');

        try {
            $withdraw = $this->solanaService->withdraw($addressId, $amount);
            return response()->json($withdraw);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getSignaturesForAddress(Request $request)
    {
        $address = $request->input('address');
        $limit = $request->input('limit');
        try {
            $result = $this->solanaService->getSignaturesForAddress($address, $limit);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function requestAirdrop(Request $request)
    {
        $address = $request->input('address');
        $amount = $request->input('amount');
        try {
            $result = $this->solanaService->requestAirdrop($address, $amount);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function transfer(Request $request) {
        $userSolanaInfo = SolanaAddress::where('user_id', $request->input('user_id'))->first();
        if($userSolanaInfo){
            $fromSecretKey = json_decode($userSolanaInfo->secret_key);
            $toAddress = $request->input('to_address');
            $amount = $request->input('amount');
            // Khởi tạo giao dịch và ghi lại trạng thái ban đầu là "pending"
            $transaction = SolanaTransaction::create([
                'from_address' => $userSolanaInfo->address,
                'to_address' => $toAddress,
                'amount' => $amount,
                'fee' => 0,
                'status' => "pending",
                'type' => "withdraw"
            ]); 
            try {
                list($txHash, $fee) = $this->solanaService->transfer($fromSecretKey, $toAddress, $amount);
                sleep(20);
                $getTransaction = $this->solanaService->getTransaction($txHash);
                if(!empty($getTransaction["result"]["blockTime"])){
                    $transaction->block_time = date('Y-m-d H:i:s', $getTransaction["result"]['blockTime']);
                }
                $transaction->status = 'success';
                $transaction->fee = $fee;
                $transaction->signatures = $txHash;
                $transaction->save();
                SolanaWithdraw::create([
                    'address_id' => $userSolanaInfo->id,
                    'transaction_id' => $transaction->id,
                    'amount' => $amount,
                ]);
                return response()->json($transaction);
            } catch (Exception $e) {   
                $transaction->status = 'failed';
                $transaction->save();
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }else{
            return response()->json(['error' => 'User not found'], 500);
        }
    }
    public function recordTransaction($addressId, $amount, $status, $type, $transactionId = null)
    {
        return SolanaTransaction::create([
            'from_address' => $addressId,
            'signatures' => $transactionId,
            'amount' => $amount,
            'status' => $status,
            'type' => $type
        ]);
    }

    
}
