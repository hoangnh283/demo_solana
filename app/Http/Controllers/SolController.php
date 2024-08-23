<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Exception;
use Hoangnh283\Solana\Services\SolanaService;
use Hoangnh283\Solana\Models\SolanaAddress;
use Hoangnh283\Solana\Models\SolanaTransaction;
use Hoangnh283\Solana\Models\SolanaWithdraw;
class SolController extends Controller
{
    public function store(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        try {
            $existingAddress = SolanaAddress::where('user_id', $user->id)->first();
            if (!$existingAddress) {
                $solanaService =  new SolanaService();
                $result = $solanaService->createAddress();
                $address = SolanaAddress::create([
                    'address' => $result['publicKey'],
                    'secret_key' => json_encode($result['secretKey']),
                    'user_id' => $user->id,
                ]);
            }
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
        return response()->json(['success' => 'User created successfully!', 'user' => $user]);
    }

    // Fetch all users
    public function index()
    {
        $users = User::with('walletAddress')->get();
        return response()->json(['users' => $users]);
    }

    // Set current user
    public function setCurrentUser(Request $request)
    {
        $user = User::find($request->input('user_id'));
        if ($user) {
            // Logic to set the current user (this could be session management or other logic)
            // For example, using session:
            session(['current_user' => $user]);

            return response()->json(['user_name' => $user->name]);
        }

        return response()->json(['error' => 'User not found'], 404);
    }

    public function getBalance(Request $request){
        try{
            $address = $request->input('address');
            $solanaService =  new SolanaService();
            $user = User::find($request->input('user_id'));
            if ($user) {
                session(['current_user' => $user]);
            }
            $balance = $solanaService->getBalance($address);
            return response()->json(['balance' => $balance]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function withdraw(Request $request){
        $current_user = session('current_user');
        $toAddress = $request->input('address');
        $amount = $request->input('amount');
        $userSolanaInfo = SolanaAddress::where('user_id', $current_user->id);
        $fromSecretKey = json_decode($userSolanaInfo->value('secret_key'));

        // Khởi tạo giao dịch và ghi lại trạng thái ban đầu là "pending"
        $transaction = SolanaTransaction::create([
                'from_address' => $userSolanaInfo->value('address'),
                'to_address' => $toAddress,
                'amount' => $amount,
                'fee' => 0,
                'status' => "pending",
                'type' => "withdraw"
            ]);
        try {
            $solanaService =  new SolanaService();
            list($txHash, $fee) = $solanaService->transfer($fromSecretKey, $toAddress, $amount);
            sleep(20);
            $getTransaction = $solanaService->getTransaction($txHash);
            if(!empty($getTransaction["result"]["blockTime"])){
                $transaction->block_time = date('Y-m-d H:i:s', $getTransaction["result"]['blockTime']);
            }
            $transaction->status = 'success';
            $transaction->signatures = $txHash;
            $transaction->fee = $fee;
            $transaction->save();
            SolanaWithdraw::create([
                'address_id' => $userSolanaInfo->value('id'),
                'transaction_id' => $transaction->id,
                'amount' => $amount,
            ]);
            return response()->json(['success' => true,'transaction' => $transaction, 'user_id'=> $current_user->id]);
        } catch (Exception $e) {    
            $transaction->status = 'failed';
            $transaction->save();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getHistory(Request $request){
        $address = $request->input('address');
        // $history = SolanaTransaction::where('address', $address)->get();
        $history = SolanaTransaction::where(function($query) use ($address) {
            $query->where('type', 'withdraw')
                  ->where('from_address', $address);
        })->orWhere(function($query) use ($address) {
            $query->where('type', 'deposit')
                  ->where('to_address', $address);
        })->get();
        return response()->json([
            'history' => $history,
        ]);
    }
}
