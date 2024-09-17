<?php

namespace Hoangnh283\Solana\Services;
// require 'vendor/autoload.php';

// use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Log;

use Attestto\SolanaPhpSdk\Connection;
use Attestto\SolanaPhpSdk\SolanaRpcClient;
use Attestto\SolanaPhpSdk\KeyPair;
use Attestto\SolanaPhpSdk\PublicKey;
use Attestto\SolanaPhpSdk\Programs\SystemProgram;
use Attestto\SolanaPhpSdk\Transaction;
use Attestto\SolanaPhpSdk\Programs\SplTokenProgram;
use Attestto\SolanaPhpSdk\TransactionInstruction;
use Attestto\SolanaPhpSdk\Util\Buffer;
use Attestto\SolanaPhpSdk\Util\AccountMeta;
class SolanaService
{
    protected $lamports = 1000000000;
    protected $url = 'https://api.devnet.solana.com';
    protected $randomKey;

    public function __construct()
    {
        $this->randomKey = random_int(0, 99999999);
    }

    function base58_encode($string) {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $base_count = strlen($alphabet);
        $encoded = '';
        $num = gmp_init(bin2hex($string), 16);

        while (gmp_cmp($num, $base_count) >= 0) {
            list($num, $rem) = gmp_div_qr($num, $base_count);
            $encoded = $alphabet[gmp_intval($rem)] . $encoded;
        }
        $encoded = $alphabet[gmp_intval($num)] . $encoded;

        return $encoded;
    }

    function base58_decode($input) {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $base_count = strlen($alphabet);
        $decoded = gmp_init('0');

        for ($i = 0; $i < strlen($input); $i++) {
            $decoded = gmp_add(gmp_mul($decoded, $base_count), strpos($alphabet, $input[$i]));
        }

        return hex2bin(gmp_strval($decoded, 16));
    }

    public function generateSolanaWallet() {
        // Tạo cặp khóa mới (Public Key và Secret Key)
        $keypair = sodium_crypto_sign_keypair();
        
        // Lấy Secret Key từ cặp khóa (privateKey + publicKey)
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $secretKeyArray = unpack('C*', $secretKey); // Chuyển đổi secretKey sang mảng byte (array of integers)

        // Lấy Public Key từ cặp khóa
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $privateKeyBase58 = $this->base58_encode($secretKey);
        $publicKeyBase58 = $this->base58_encode($publicKey);
        // Trả về khóa công khai và khóa bí mật
        return [
            'publicKey' => $publicKeyBase58,
            'secretKey' => json_encode(array_values($secretKeyArray)),
            'privateKeyBase58' => $privateKeyBase58,
        ];
    }

    public function createAddress(){
        // Tạo Keypair mới
        $keypair = Keypair::generate();

        // Lấy địa chỉ công khai và khóa riêng
        $publicKey = $keypair->getPublicKey()->toBase58();
        $secretKey = $keypair->getSecretKey();
        return [
            'publicKey'=> $publicKey,
            'secretKey'=> $secretKey->toArray(),
        ];
    }

    public function transfer($fromSecretKey, $toAddress, $amount){
        $client = new SolanaRpcClient(SolanaRpcClient::DEVNET_ENDPOINT);
        $connection = new Connection($client);
        $fromKeyPair = KeyPair::fromSecretKey($fromSecretKey);
        $toPublicKey = new PublicKey($toAddress);

        // Lấy recentBlockhash
        // $recentBlockhashResponse = $connection->getRecentBlockhash(); // 21/08/2024 không dùng được nữa
        $recentBlockhashResponse = $this->getLatestBlockhash();
        if (!isset($recentBlockhashResponse)) {
            throw new Exception('Failed to retrieve recent blockhash');
        }
        $recentBlockhash = $recentBlockhashResponse;

        $instruction = SystemProgram::transfer(
            $fromKeyPair->getPublicKey(),
            $toPublicKey,
            $amount * $this->lamports
        );
        
        // $transaction = new Transaction(null, null, $fromKeyPair->getPublicKey()); 
        $transaction = new Transaction();
        $transaction->recentBlockhash = $recentBlockhash;
        $transaction->feePayer = $fromKeyPair->getPublicKey();
        $transaction->add($instruction);
        // $transaction->sign($fromKeyPair);
        $serializedMessage = $transaction->serializeMessage();
        $fee = $this->getFeeForMessage(base64_encode($serializedMessage));
        $txHash = $connection->sendTransaction($transaction, [$fromKeyPair]);
        return [$txHash, $fee];
    }

    public function transferSPL($fromSecretKey, $toAddress, $mintAddress, $amount){
        try {
            $client = new SolanaRpcClient(SolanaRpcClient::DEVNET_ENDPOINT);
            $connection = new Connection($client);
            $tokenProgramId = new PublicKey('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA');

            $fromKeyPair = Keypair::fromSecretKey($fromSecretKey);
            $fromPublicKey = $fromKeyPair->getPublicKey();
            $toPublicKey = new PublicKey($toAddress);
            $mintPublicKey = new PublicKey($mintAddress);

            // Lấy tài khoản token của người gửi (payer)
            $payerTokenAccount = $this->findAssociatedTokenAddress($fromPublicKey, $mintPublicKey);

            // Lấy tài khoản token của người nhận
            // $recipientTokenAccount = $this->findAssociatedTokenAddress($toPublicKey, $mintPublicKey);
            $recipientTokenAccount =  new PublicKey($this->getOrCreateAssociatedTokenAccount($toAddress, $fromSecretKey, $mintAddress));
            
            // Tạo Transfer Instruction
            $transferInstruction = new TransactionInstruction(
                $tokenProgramId,
                [
                    new AccountMeta($payerTokenAccount, false, true),
                    new AccountMeta($recipientTokenAccount, false, true),
                    new AccountMeta($fromPublicKey, true, false)
                ],
                Buffer::from(array_merge([3], unpack('C*', pack('P', $amount * $this->lamports))))
            );

            $recentBlockhashResponse = $this->getLatestBlockhash();
            $recentBlockhash = $recentBlockhashResponse;
            $transaction = new Transaction();
            $transaction->recentBlockhash = $recentBlockhash;
            $transaction->feePayer = $fromPublicKey;
            $transaction->add($transferInstruction);
            $serializedMessage = $transaction->serializeMessage();
            $fee = $this->getFeeForMessage(base64_encode($serializedMessage));
            $txHash = $connection->sendTransaction($transaction, [$fromKeyPair]);
            return [$txHash, $fee, $payerTokenAccount->toBase58(), $recipientTokenAccount->toBase58()];
            return $txHash;
        } catch (Exception $e) {
            return false;
            return "Lỗi: " . $e->getMessage();
        }
    }

    private function findAssociatedTokenAddress(PublicKey $walletAddress, PublicKey $tokenMintAddress) {
        $associatedTokenProgramId = new PublicKey('ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL');
        $tokenProgramId = new PublicKey('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA');
        
        list($address, ) = PublicKey::findProgramAddress(
            [
                $walletAddress->toBytes(),
                $tokenProgramId->toBytes(),
                $tokenMintAddress->toBytes()
            ],
            $associatedTokenProgramId
        );
        
        return $address;
    }

    public function getOrCreateAssociatedTokenAccount($ownerAddress, $payerSecretKey = [242,68,92,173,86,8,228,143,78,61,44,176,201,89,177,232,250,214,218,224,89,114,138,96,127,118,102,86,216,55,3,72,109,216,172,177,145,52,205,136,164,110,27,207,135,70,168,213,236,84,153,189,251,5,156,217,204,7,142,253,122,89,231,165],  $mintAddress = '8fanmtHCJMcCPWc95bQPS1ZwPN8jjjsHBDjL6LJ4Z1wJ') {
        $client = new SolanaRpcClient(SolanaRpcClient::DEVNET_ENDPOINT);
        $connection = new Connection($client);
        // $systemProgramId = new PublicKey('11111111111111111111111111111111');
        // $tokenProgramId = new PublicKey("TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA");
        $payerKeyPair = Keypair::fromSecretKey($payerSecretKey);
        $payer = $payerKeyPair->getPublicKey();
        $mint = new PublicKey($mintAddress);
        $owner = new PublicKey($ownerAddress);
        $tokenAccount = $this->checkSPLTokenAddress($ownerAddress, $mintAddress);
        if($tokenAccount){
            return $tokenAccount;
        }
        $associatedTokenProgram = new SplTokenProgram($client);
        $associatedTokenProgram->getOrCreateAssociatedTokenAccount($connection, $payer ,$mint, $owner);
        return $this->checkSPLTokenAddress($ownerAddress, $mintAddress);
    }

    public function checkSPLTokenAddress($address, $mintAddress){
        $client = new SolanaRpcClient(SolanaRpcClient::DEVNET_ENDPOINT);
        $response = $client->call('getTokenAccountsByOwner', [
            $address,
            ['mint' => $mintAddress],
            ['encoding' => 'jsonParsed']
        ]);
        if (!empty($response['value'])) {   
            return $response['value'][0]['pubkey'];
        }
        return false;
    }

    public function getBalanceSPL($mintAddress, $address){
        $client = new SolanaRpcClient(SolanaRpcClient::DEVNET_ENDPOINT);
        $publicKey = new PublicKey($address);
        // Tạo chương trình token SPL
        $splTokenProgram = new SplTokenProgram($client);
        $response = $splTokenProgram->getTokenAccountsByOwner($publicKey);
        return $response;
        $accounts = $response['result']['value'] ?? [];
        
        // Duyệt qua các tài khoản để tìm tài khoản Raydium
        $balance = 0;
        foreach ($accounts as $account) {
            $accountData = $account['account']['data']['parsed']['info'];
            
            if ($accountData['mint'] === $mintAddress) {
                $balance = $accountData['tokenAmount']['uiAmountString'];
                break;
            }
        }
        return $balance;
    }

    public function transactionHistory(){
        // Endpoint RPC của Solana
        $rpcUrl = SolanaRpcClient::DEVNET_ENDPOINT;

        // Địa chỉ ví mà bạn muốn lấy lịch sử giao dịch
        $address = '8Po4tv7Yvwof4MdPP4n4ogMrQqVikqLP8EJKjmtkE71v'; // Thay thế bằng địa chỉ ví của bạn

        // Dữ liệu yêu cầu để lấy các chữ ký giao dịch đã xác nhận
        $requestData = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getConfirmedSignaturesForAddress2',
            'params' => [$address]
        ];

        // Chuyển đổi dữ liệu yêu cầu thành JSON
        $jsonData = json_encode($requestData);

        // Tạo cURL session
        $ch = curl_init($rpcUrl);

        // Cấu hình cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        // Gửi yêu cầu và nhận phản hồi
        $response = curl_exec($ch);

        // Kiểm tra lỗi cURL
        if ($response === false) {
            echo 'cURL Error: ' . curl_error($ch);
            exit;
        }

        // Giải mã phản hồi JSON
        $responseData = json_decode($response, true);

        // Đóng cURL session
        curl_close($ch);

        // Kiểm tra phản hồi và lấy chữ ký giao dịch
        if (isset($responseData['result'])) {
            echo "Transaction Signatures:\n";
            print_r($responseData['result']);
            $results = [];
            // Lặp qua các chữ ký để lấy chi tiết giao dịch
            foreach ($responseData['result'] as $signature) {
                // Lấy thông tin chi tiết về từng giao dịch
                $transactionRequestData = [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'getConfirmedTransaction',
                    'params' => [$signature['signature']]
                ];
                
                // Chuyển đổi dữ liệu yêu cầu thành JSON
                $transactionJsonData = json_encode($transactionRequestData);
                
                // Tạo cURL session cho yêu cầu chi tiết giao dịch
                $ch = curl_init($rpcUrl);
                
                // Cấu hình cURL
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $transactionJsonData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                
                // Gửi yêu cầu và nhận phản hồi
                $transactionResponse = curl_exec($ch);
                
                // Kiểm tra lỗi cURL
                if ($transactionResponse === false) {
                    echo 'cURL Error: ' . curl_error($ch);
                } else {
                    // Giải mã phản hồi JSON
                    $transactionData = json_decode($transactionResponse, true);
                    $results[] = $transactionData;
                    echo "Transaction Details:\n";
                    print_r($transactionData);
                }
                curl_close($ch);
            }
            return $results;
        } else {
            echo "No transactions found or an error occurred.\n";
        }
    }

    public function getBalance($address){
        $postData = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getBalance',
            'params' => [
                $address
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            Log::error('cURL error: ' . curl_error($ch));
            throw new Exception('Failed to retrieve balance: ' . curl_error($ch));
        }

        curl_close($ch);

        $responseData = json_decode($response, true);

        if (isset($responseData['result']['value'])) {
            $balance = $responseData['result']['value'] / 1000000000; // Số dư được trả về trong đơn vị lamports, chia cho 1 tỷ để chuyển sang SOL
            Log::info('Balance retrieved successfully', ['balance' => $balance]);
            return $balance;
        } else {
            Log::error('Failed to retrieve balance', ['response' => $response]);
            throw new Exception('Failed to retrieve balance');
        }
    }

    public function getSignaturesForAddress($address, $limit){
        $postData = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getSignaturesForAddress',
            'params' => [
                $address,
                ['limit' => $limit]
            ]
        ];  

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            Log::error('cURL error: ' . curl_error($ch));
            throw new Exception('Failed to retrieve signature: ' . curl_error($ch));
        }
        curl_close($ch);
        $responseData = json_decode($response, true);
        if (isset($responseData['result'][0]['signature'])) {
            return $responseData;
        } else {
            Log::error('Failed to retrieve signature', ['response' => $response]);
            throw new Exception('Failed to retrieve signature');
        }
    }

    public function requestAirdrop($address, $amount){
        $postData = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'requestAirdrop',
            'params' => [
                $address,
                $amount * $this->lamports
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            Log::error('cURL error: ' . curl_error($ch));
            throw new Exception('Failed to retrieve airdrop: ' . curl_error($ch));
        }
        curl_close($ch);
        $responseData = json_decode($response, true);
        if (isset($responseData['result'])) {
            return $responseData;
        } else {
            Log::error('Failed to retrieve airdrop', ['response' => $response]);
            throw new Exception($responseData['error']['message']);
        }
    }

    public function generateRandomHex($length = 42) {
        // Đảm bảo rằng chiều dài của chuỗi là số chẵn
        $length = ($length % 2 == 0) ? $length : $length + 1;

        $randomBytes = random_bytes($length / 2);
        $hex = bin2hex($randomBytes);

        return substr($hex, 0, $length);
    }

    public function getTransaction($signature){
        $postData = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getTransaction',
            'params' => [
                $signature
                , 'json'
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            Log::error('cURL error: ' . curl_error($ch));
            throw new Exception('Failed to retrieve balance: ' . curl_error($ch));
        }

        curl_close($ch);

        $responseData = json_decode($response, true);
        if (isset($responseData)) {
            return $responseData;
        } else {
            Log::error('Failed to retrieve balance', ['response' => $response]);
            throw new Exception('Failed to retrieve balance');
        }
    }

    public function getAccountInfo($address){

        $client = new SolanaRpcClient(SolanaRpcClient::DEVNET_ENDPOINT);
        $connection = new Connection($client);// URL của RPC endpoint

        $publicKey = new PublicKey($address);

        try {
            $accountInfo = $connection->getAccountInfo($publicKey);
            if ($accountInfo) {
                return $accountInfo;
            } else {
                echo "Không tìm thấy thông tin tài khoản.\n";
            }
        } catch (Exception $e) {
            echo "Lỗi khi lấy thông tin tài khoản: " . $e->getMessage() . "\n";
        }
    }

    public function getFees(){ // 21/08/2024 không dùng được nữa
        $data = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getFees'
        ];
    
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);
    
        $result = json_decode($response, true);
        return $result['result']['value']['feeCalculator']['lamportsPerSignature'] / $this->lamports;
    }

    public function getFeeForMessage($message){
        $data = [
            "jsonrpc" => "2.0",
            "id" => 1,
            "method" => "getFeeForMessage",
            "params" => [
                $message,
                [
                    "commitment" => "processed"
                ]
            ]
        ];
    
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);
    
        $result = json_decode($response, true);
        return $result['result']['value'] / $this->lamports;
    }

    protected function getRecentBlockhash($url) {
        $data = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getRecentBlockhash',
            'params' => []
        ];
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);
    
        $result = json_decode($response, true);
        return $result['result']['value']['blockhash'];
    }
    protected function getLatestBlockhash() {
        $data = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getLatestBlockhash',
            'params' => [
                ["commitment"=>"processed"]
            ]
        ];
    
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);
    
        $result = json_decode($response, true);
        return $result['result']['value']['blockhash'];
    }

    protected function sendTransaction($url, $signedTransaction) {
        $data = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'sendTransaction',
            'params' => [$signedTransaction]
        ];
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);
    
        return json_decode($response, true);
    }
    
    function getTokensByWallet($walletAddress) {
        // Payload cho yêu cầu RPC
        $data = [
            "jsonrpc" => "2.0",
            "id" => 1,
            "method" => "getTokenAccountsByOwner",
            "params" => [
                $walletAddress,
                [
                    "programId" => "TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA"
                ],
                [
                    "encoding" => "jsonParsed"
                ]
            ]
        ];
    
        // Sử dụng cURL để gửi yêu cầu
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Lỗi cURL: ' . curl_error($ch);
        }
        curl_close($ch);
        return json_decode($response, true);
    }
}
