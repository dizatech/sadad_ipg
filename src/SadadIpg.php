<?php

namespace Dizatech\SadadIpg;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use stdClass;

class SadadIpg
{
    protected $merchant_id;
    protected $terminal_id;
    protected $key;
    protected $client;

    public function __construct(array $args = [])
    {
        $this->merchant_id = $args['merchant_id'];
        $this->terminal_id = $args['terminal_id'];
        $this->key = $args['key'];
        $this->client = new Client();
    }

    public function requestPayment(int $order_id, int $amount, string $redirect_url): object
    {
        $result = new stdClass();
        $result->status = 'error';

        try {
            $response = $this->client->post(
                'https://sadad.shaparak.ir/VPG/api/v0/Request/PaymentRequest',
                [
                    'json'  => [
                        'MerchantId'    => $this->merchant_id,
                        'TerminalId'    => $this->terminal_id,
                        'Amount'        => $amount,
                        'OrderId'       => $order_id,
                        'LocalDateTime' => Carbon::now()->format('Y-m-d H:i:s'),
                        'ReturnUrl'     => $redirect_url,
                        'SignData'      => $this->sign("{$this->terminal_id};{$order_id};{$amount}"),
                    ],
                ]
            );
            $response = json_decode($response->getBody()->getContents());
            if ($response->ResCode == 0 && $response->Token) {
                $result->status = 'success';
                $result->token = $response->Token;
            } else {
                $result->message = $response->Description;
            }
        } catch (Exception $e) {
            $result->message = $e->getMessage();
        }

        return $result;
    }

    public function verify(string $token): object
    {
        $result = new stdClass();
        $result->status = 'error';

        try {
            $response = $this->client->post(
                'https://sadad.shaparak.ir/VPG/api/v0/Advice/Verify',
                [
                    'json'  => [
                        'token'         => $token,
                        'SignData'      => $this->sign($token),
                    ],
                ]
            );
            $response = json_decode($response->getBody()->getContents());
            if ($response->ResCode == 0 || $response->ResCode == 100) {
                $result->status = 'success';
                $result->ref_no = $response->RetrivalRefNo;
            } else {
                $result->message = $response->Description;
            }
        } catch (Exception $e) {
            $result->message = $e->getMessage();
        } 
        
        return $result;
    }

    protected function sign(string $signable): string
    {
        $key = base64_decode($this->key);
        $ciphertext = OpenSSL_encrypt($signable, "DES-EDE3", $key, OPENSSL_RAW_DATA);
        return base64_encode($ciphertext);
    }
}