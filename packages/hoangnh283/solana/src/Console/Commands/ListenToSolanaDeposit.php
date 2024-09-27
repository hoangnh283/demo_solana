<?php 
namespace Hoangnh283\Solana\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use React\EventLoop\Factory;
use React\Socket\Connector as ReactConnector;
use Exception;
use Hoangnh283\Solana\Services\SolanaService;
use Hoangnh283\Solana\Models\SolanaTransaction;
use Hoangnh283\Solana\Models\SolanaDeposit;
use Hoangnh283\Solana\Models\SolanaAddress;
class ListenToSolanaDeposit extends Command
{
    protected $signature = 'solana:listen-deposit';
    protected $description = 'Description of your command';
    protected $current_user_id = 1;
    protected $addressModel;
    protected $seenSignatures = [];
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(){
        $this->connect();
    }

    protected function connect(){
        // $this->addressModel = $this->getAddressByUserId($this->current_user_id);
        $this->addressModel = $this->getAllAddressUsers();
        // var_dump($this->getAllAddressUsers());die;
        if($this->addressModel){
            $loop = Factory::create();
            $connector = new Connector($loop, new ReactConnector($loop));
            $connector('wss://api.devnet.solana.com')->then(function(WebSocket $conn) use ($loop) {
                $this->info("Connected to Solana WebSocket.");
                foreach ($this->addressModel as $address) {
                    $conn->send(json_encode([
                        'jsonrpc' => '2.0',
                        'id' => uniqid(),
                        'method' => 'logsSubscribe',
                        'params' => [
                            [
                                'mentions' => [$address],
                            ]
                            // ["all"]
                        ]
                    ]));
                }
                $loop->addPeriodicTimer(10, function() use ($conn) {
                    if(count($this->getAllAddressUsers()) != count($this->addressModel)){
                        $this->info("this->getAllAddressUsers ". count($this->addressModel));
                        $this->info("this->addressModel ". count($this->addressModel));
                        $conn->close(200, 'restart');
                    }else{
                        // Gửi tin nhắn ping nếu cần
                        try {
                            $conn->send(json_encode([
                                'type' => 'ping'
                            ]));
                        } catch (Exception $e) {
                            $this->error("Failed to send ping: " . $e->getMessage());
                        }
                    }

                });

                $conn->on('message', function($msg) {
                    $data = json_decode($msg, true);
                    if (isset($data["params"]["result"]['value']['signature']) && !empty($data["params"]["result"]['value']['signature'])) {
                        $signature = $data["params"]["result"]['value']['signature'];
                        if ($signature && !in_array($signature, $this->seenSignatures)) {
                            $this->info('signature: ' . $data["params"]["result"]['value']['signature']);
                            $this->seenSignatures[] = $signature;
                            sleep(5);
                            $SolanaService = new SolanaService;
                            $getTransaction = $SolanaService->getTransaction($data["params"]["result"]['value']['signature']);
                            $currency = '';
                            if(!empty($getTransaction["result"])){
                                $lamports = 1000000000;
                                if(count($getTransaction["result"]['meta']['postTokenBalances']) > 0){
                                    $this->info('address: ' . $getTransaction["result"]['meta']['postTokenBalances'][0]['owner']);
                                    if($getTransaction["result"]['meta']['postTokenBalances'][0]['mint'] == '8fanmtHCJMcCPWc95bQPS1ZwPN8jjjsHBDjL6LJ4Z1wJ'){
                                        $currency = 'Unknown Token';
                                    }
                                    $userAddressInfo = SolanaAddress::where('address', $getTransaction["result"]['meta']['postTokenBalances'][0]['owner'])->first();
                                    $postBalances = $getTransaction["result"]['meta']['postTokenBalances'][0]['uiTokenAmount']['amount'] / $lamports;
                                    $confirmed = false;
                                    while (!$confirmed) {
                                        if (count($getTransaction["result"]['meta']['preTokenBalances'])>0) {
                                            $confirmed = true;
                                        } else {
                                            sleep(2); // Chờ 2 giây trước khi thử lại
                                            $getTransaction = $SolanaService->getTransaction($data["params"]["result"]['value']['signature']);
                                        }
                                    }
                                    $preBalances = $getTransaction["result"]['meta']['preTokenBalances'][0]['uiTokenAmount']['amount'] / $lamports;
                                    $meta = $getTransaction["result"]['meta'];
                                    foreach ($meta['postTokenBalances'] as $index => $postBalance) {
                                        $preBalance = $meta['preTokenBalances'][$index];
                                        if ($preBalance['uiTokenAmount']['amount'] > $postBalance['uiTokenAmount']['amount']) {
                                            $fromAddress = $getTransaction["result"]["transaction"]["message"]["accountKeys"][$postBalance['accountIndex']];
                                        } elseif ($preBalance['uiTokenAmount']['amount'] < $postBalance['uiTokenAmount']['amount']) {
                                            $toAddress = $getTransaction["result"]["transaction"]["message"]["accountKeys"][$postBalance['accountIndex']];
                                        }
                                    }
                                }else{
                                    $userAddressInfo = SolanaAddress::where('address', $getTransaction["result"]["transaction"]["message"]["accountKeys"][1])->first();
                                    $postBalances = $getTransaction["result"]["meta"]["postBalances"][1] / $lamports;
                                    $preBalances = $getTransaction["result"]["meta"]["preBalances"][1] / $lamports;
                                    $fromAddress = $getTransaction["result"]["transaction"]["message"]["accountKeys"][0];
                                    $toAddress = $getTransaction["result"]["transaction"]["message"]["accountKeys"][1];
                                    $currency = 'SOL';
                                }
                                $this->info('from address: ' . $fromAddress);
                                $this->info('to address: ' . $toAddress);
                                $amount = abs($postBalances - $preBalances);
                                $fee = $getTransaction["result"]["meta"]["fee"] / $lamports;
                                
                                $transaction = SolanaTransaction::create([
                                    'from_address' => $fromAddress,
                                    'to_address' => $toAddress,
                                    'amount' => $amount,
                                    'fee' => 0,
                                    'signatures'=> $data["params"]["result"]['value']['signature'],
                                    'status' => "success",
                                    'type' => "deposit",
                                    'block_time' => date('Y-m-d H:i:s', $getTransaction["result"]['blockTime']),
                                    
                                ]);
                                SolanaDeposit::create([
                                    'address_id' => $userAddressInfo->id,
                                    'transaction_id' => $transaction->id,
                                    'currency' => $currency,
                                    'amount' => $amount,
                                ]);
                            }
                        }
                    }
                });
                $conn->on('close', function ($code, $reason) use ($loop) {
                    $this->info("Connection closed: Code: $code, Reason: $reason");
                    if($code == 200 && $reason == 'restart'){
                        $this->info("Reconnect");
                        $this->connect();
                    }else{
                        $this->info("Reconnecting in 5 seconds...");
                        $loop->addTimer(5, function () use ($loop) {
                            $this->connect(); // Gọi lại phương thức connect sau 5 giây
                        });
                    }

                });
                }, function($e) {
                    $this->error("Could not connect: {$e->getMessage()}");
                });

            $loop->run();
        }
    }

    public function getAddressByUserId($userid){
        $userSolanaInfo = SolanaAddress::where('user_id', $userid)->first();
        return $userSolanaInfo ? $userSolanaInfo : null;
    }
    public function getAllAddressUsers(){
        return SolanaAddress::pluck('address')->toArray();
    }
}
