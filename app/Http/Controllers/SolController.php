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
use GuzzleHttp\Client;
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
            $results = array();

            $balance_sol = $solanaService->getBalance($address);
            $results[] = ['name' => 'SOL', 'balance' => $balance_sol, 'decimals' => 9, 'pubkey' => $address];
            $response = $solanaService->getTokensByWallet($address);
            if (isset($response['result']['value'])) {
                foreach ($response['result']['value'] as $tokenAccount) {
                    $accountInfo = $tokenAccount['account']['data']['parsed']['info'];
                    if($accountInfo['mint'] == '8fanmtHCJMcCPWc95bQPS1ZwPN8jjjsHBDjL6LJ4Z1wJ'){
                        $results[] = ['name' => 'Unknown Token' ,'balance' => $accountInfo['tokenAmount']['uiAmount'], 'decimals' => $accountInfo['tokenAmount']['decimals'], 'pubkey' => $tokenAccount['pubkey']];
                    }else{
                        // $results[] = ['name' => $accountInfo['mint'] ,'balance' => $accountInfo['tokenAmount']['uiAmount'], 'decimals' => $accountInfo['tokenAmount']['decimals'], 'pubkey' => $tokenAccount['pubkey']];
                    }
                }
            }
            return response()->json($results);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function withdraw(Request $request){
        $current_user = session('current_user');
        $toAddress = $request->input('address');
        $amount = $request->input('amount');
        $token = $request->input('token');
        $decimals = $request->input('decimals');
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
            if($token == "SOL"){
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
            }else{
                $solanaService =  new SolanaService();
                if($token == "Unknown Token"){
                    $mintAddress = "8fanmtHCJMcCPWc95bQPS1ZwPN8jjjsHBDjL6LJ4Z1wJ";
                }
                list($txHash, $fee, $fromSPLAddress, $toSPLAddress) = $solanaService->transferSPL($fromSecretKey, $toAddress, $mintAddress, $amount);
                sleep(20);
                $getTransaction = $solanaService->getTransaction($txHash);
                if(!empty($getTransaction["result"]["blockTime"])){
                    $transaction->block_time = date('Y-m-d H:i:s', $getTransaction["result"]['blockTime']);
                }
                $transaction->status = 'success';
                $transaction->signatures = $txHash;
                $transaction->fee = $fee;
                $transaction->from_address = $fromSPLAddress;
                $transaction->to_address = $toSPLAddress;
                $transaction->save();
            }
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
        $solanaService =  new SolanaService();
        $address = $request->input('address');
        // $client = new Client();
        // $data = array();
        // $data['address'] = $address;
        // // Gửi dữ liệu đến Node.js API
        // $tokenSPL = $client->post('http://localhost:3000/getOrCreateSPLTokenAccount', [
        //     'json' => ['data' => $data]
        // ]);
        // // Nhận kết quả từ Node.js
        // $tokenSPL = json_decode($tokenSPL->getBody()->getContents(), true);
        $payerSecretKey = [242,68,92,173,86,8,228,143,78,61,44,176,201,89,177,232,250,214,218,224,89,114,138,96,127,118,102,86,216,55,3,72,109,216,172,177,145,52,205,136,164,110,27,207,135,70,168,213,236,84,153,189,251,5,156,217,204,7,142,253,122,89,231,165];

        // $tokenSPL =$solanaService->getOrCreateAssociatedTokenAccount( $address,$payerSecretKey, '8fanmtHCJMcCPWc95bQPS1ZwPN8jjjsHBDjL6LJ4Z1wJ');
        $tokenSPL = $solanaService->checkSPLTokenAddress($address, '8fanmtHCJMcCPWc95bQPS1ZwPN8jjjsHBDjL6LJ4Z1wJ');
        // $history = SolanaTransaction::where('address', $address)->get();
        $history = SolanaTransaction::where(function($query) use ($address, $tokenSPL) {
            $query->where('type', 'withdraw')
                  ->where('from_address', $address);
        })->orWhere(function($query) use ($address) {
            $query->where('type', 'deposit')
                  ->where('to_address', $address);
        });
        if($tokenSPL){
            $history = $history->orWhere(function($query) use ($tokenSPL) {
                $query->where('type', 'withdraw')
                      ->where('from_address', $tokenSPL);
            })->orWhere(function($query) use ($tokenSPL) {
                $query->where('type', 'deposit')
                      ->where('to_address', $tokenSPL);
            });
        }
        $history = $history->get();
        // Xử lý dữ liệu sau khi truy vấn
        foreach ($history as &$transaction) {
            // Nếu là địa chỉ gốc $address thì thêm "SOL" vào amount
            if ($transaction->from_address === $address || $transaction->to_address === $address) {
                $transaction->amount = floatval($transaction->amount) . ' SOL';
            }

            // Nếu là địa chỉ $tokenSPL thì thêm "UKN" vào amount
            if ($transaction->from_address === $tokenSPL || $transaction->to_address === $tokenSPL) {
                $transaction->amount = floatval($transaction->amount) . ' UKN';
            }
        }


        return response()->json([
            'history' => $history,
        ]);
    }
}
