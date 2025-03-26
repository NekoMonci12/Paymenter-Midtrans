<?php
namespace App\Extensions\Gateways\Midtrans;

use App\Classes\Extensions\Gateway;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class Midtrans extends Gateway
{
    public function getMetadata()
    {
        return [
            'display_name' => 'Midtrans',
            'version'      => '1.0.0',
            'author'       => 'NekoMonci12',
            'website'      => 'https://github.com/NekoMonci12',
        ];
    }

    public function pay($total, $products, $invoiceId)
    {
        $hash = substr(hash('sha256', time()), 0, 16);
        $orderId = 'PAYMENTER-' . $invoiceId . '-' . $hash;

        $serverKey  = ExtensionHelper::getConfig('Midtrans', 'server_key');
        $merchantId = ExtensionHelper::getConfig('Midtrans', 'merchant_id');
        $clientKey  = ExtensionHelper::getConfig('Midtrans', 'client_key');
        $debugMode  = ExtensionHelper::getConfig('Midtrans', 'debug_mode');
        if ($debugMode) {
            $url   = 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        } else {
            $url   = 'https://app.midtrans.com/snap/v1/transactions';
        }
        $transactionDetails = [
            'order_id'     => $orderId,
            'gross_amount' => round($total, 2),
        ];
        $itemDetails = [];
        foreach ($products as $product) {
            $itemDetails[] = [
                'id'       => $product['id'] ?? uniqid(),
                'price'    => $product['price'] ?? 0,
                'quantity' => $product['quantity'] ?? 1,
                'name'     => $product['name'] ?? 'Product',
            ];
        }
        $payload = [
            'transaction_details' => $transactionDetails,
            'item_details'        => $itemDetails,
        ];
        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($serverKey . ':'),
        ];
        $response = Http::withHeaders($headers)
            ->post($url, $payload);

        $responseJson = $response->json();
        if ($response->failed() || isset($responseJson['error_messages'])) {
            ExtensionHelper::error('Midtrans', $responseJson);
        }

        if (!isset($responseJson['redirect_url'])) {
            ExtensionHelper::error('Midtrans', [
                'error'    => 'Missing redirect_url in response.',
                'response' => $responseJson,
            ]);
        }

        return $responseJson['redirect_url'];
    }

    public function webhook(Request $request)~
    {
        if (!$request->isMethod('post')) {
            return response('Method Not Allowed', 405);
        }

        $data = json_decode($request->getContent(), true);
        if (
            isset($data['status_code'], $data['transaction_status']) &&
            $data['status_code'] === "200" &&
            in_array($data['transaction_status'], ['capture', 'settlement'])
        ) {
            try {
                $orderId = $data['order_id'];
                $parts = explode('-', $orderId);
                if (count($parts) >= 3) {
                    $invoiceId = $parts[1];
                } else {
                    \Log::error('Invalid order_id format: ' . $orderId);
                    return response('Invalid order id format', 400);
                }
                ExtensionHelper::paymentDone($invoiceId, 'Midtrans', $data['transaction_id']);
            } catch (\Exception $e) {
                \Log::error('Error processing paymentDone: ' . $e->getMessage());
                return response('Error processing webhook', 500);
            }
        }
        return response('OK', 200);
    }

    public function getConfig()
    {
        return [
            [
                'name'         => 'server_key',
                'type'         => 'text',
                'friendlyName' => 'Server Key',
                'required'     => true,
            ],
            [
                'name'         => 'merchant_id',
                'type'         => 'text',
                'friendlyName' => 'Merchant ID',
                'required'     => true,
            ],
            [
                'name'         => 'client_key',
                'type'         => 'text',
                'friendlyName' => 'Client Key',
                'required'     => true,
            ],
            [
                'name'         => 'debug_mode',
                'type'         => 'boolean',
                'friendlyName' => 'Should Debug Mode be enabled?',
                'required'     => false,
            ],
        ];
    }
}
