<?php

namespace Paymenter\Extensions\Gateways\Midtrans;

use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

class Midtrans extends Gateway
{
    public function boot()
    {
        require __DIR__ . '/routes/web.php';
        View::addNamespace('gateways.midtrans', __DIR__ . '/resources/views');
    }

    public function getMetadata(): array
    {
        return [
            'display_name' => 'Midtrans',
            'version'      => '1.2.0',
            'author'       => 'NekoMonci12',
            'website'      => 'https://github.com/NekoMonci12',
        ];
    }

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'server_key',
                'label' => 'Server Key',
                'type' => 'text',
                'description' => 'Find your server key in Midtrans dashboard.',
                'required' => true,
            ],
            [
                'name' => 'merchant_id',
                'label' => 'Merchant ID',
                'type' => 'text',
                'description' => 'Your Midtrans Merchant ID.',
                'required' => true,
            ],
            [
                'name' => 'client_key',
                'label' => 'Client Key',
                'type' => 'text',
                'description' => 'Your Midtrans client key.',
                'required' => true,
            ],
            [
                'name' => 'debug_mode',
                'label' => 'Enable Sandbox Mode',
                'type' => 'checkbox',
                'description' => 'Use the Midtrans sandbox environment for testing.',
                'required' => false,
            ],
        ];
    }

    public function pay($invoice, $total)
    {
        $orderId = 'PAYMENTER-' . $invoice->id . '-' . substr(hash('sha256', time()), 0, 16);
        $debugMode = $this->config('debug_mode');

        $url = $debugMode
            ? 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            : 'https://app.midtrans.com/snap/v1/transactions';

        $itemDetails = collect($invoice->items)->map(function ($item) {
            return [
                'id'       => $item->id ?? uniqid(),
                'price'    => round($item->price, 2),
                'quantity' => $item->quantity ?? 1,
                'name'     => substr($item->description ?? 'Item', 0, 50),
            ];
        })->toArray();
        
        $grossAmount = collect($itemDetails)->sum(function ($item) {
            return $item['price'] * $item['quantity'];
        });

        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'item_details' => $itemDetails,
        ];

        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Basic ' . $this->generateAuthKey(),
        ];

        $response = Http::withHeaders($headers)->post($url, $payload);
        $json = $response->json();

        if ($response->failed() || !isset($json['redirect_url'])) {
            ExtensionHelper::error('Midtrans', [
                'message' => 'Midtrans payment request failed.',
                'response' => $json,
            ]);
            return redirect()->back()->with('error', 'Failed to initiate Midtrans payment.');
        }

        return view('gateways.midtrans::pay', [
            'invoice'   => $invoice,
            'snapToken' => isset($json['token']) ? $json['token'] : null,
            'clientKey' => $this->config('client_key'),
            'debugMode' => $this->config('debug_mode'),
        ]);

    }

    /**
     * Generate Base64 Authorization string for Midtrans API requests.
     * Formula: Base64(ServerKey + ":")
     */
    public function generateAuthKey(?string $serverKey = null): string
    {
        $serverKey = $serverKey ?? (string) $this->config('server_key');
        return base64_encode($serverKey . ':');
    }

    /**
     * Generate Midtrans SHA-512 signature key.
     * Formula: SHA512(order_id + status_code + gross_amount + ServerKey)
     */
    public function generateSignature(string $orderId, string $statusCode, string $grossAmount, ?string $serverKey = null): string
    {
        $serverKey = $serverKey ?? (string) $this->config('server_key');
        return hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
    }

    /**
     * Verify callback signature key from Midtrans notification payload.
     */
    public function verifySignature(array $data, ?string $serverKey = null): bool
    {
        if (
            !isset($data['order_id'], $data['status_code'], $data['gross_amount'], $data['signature_key'])
        ) {
            return false;
        }

        $expectedSignature = $this->generateSignature(
            (string) $data['order_id'],
            (string) $data['status_code'],
            (string) $data['gross_amount'],
            $serverKey
        );

        return hash_equals($expectedSignature, (string) $data['signature_key']);
    }

    public function webhook(Request $request)
    {
        $data = $request->json()->all();
        \Log::debug('Midtrans webhook payload received:', $data);

        if (!$this->verifySignature($data)) {
            \Log::warning('Midtrans webhook signature verification failed.', [
                'order_id' => $data['order_id'] ?? null,
                'signature_key' => $data['signature_key'] ?? null,
            ]);
            return response('Invalid signature', 403);
        }

        if (
            isset($data['status_code'], $data['transaction_status']) &&
            $data['status_code'] === "200" &&
            in_array($data['transaction_status'], ['capture', 'settlement'])
        ) {
            try {
                $orderId = $data['order_id'] ?? null;
                \Log::debug("Parsed order_id: $orderId");

                $invoiceId = explode('-', $orderId)[1] ?? null;
                \Log::debug("Parsed invoice ID: $invoiceId");

                if (!$invoiceId) {
                    \Log::warning("Midtrans webhook received invalid order_id format: $orderId");
                    return response('Invalid order id format', 400);
                }

                $amount = isset($data['gross_amount']) ? floatval($data['gross_amount']) : null;
                $transactionId = $data['transaction_id'] ?? null;

                if (!$amount || !$transactionId) {
                    \Log::warning("Midtrans webhook missing amount or transaction_id.");
                    return response('Invalid payload', 400);
                }

                \Log::debug("Creating payment: invoice_id=$invoiceId, amount=$amount, transaction_id=$transactionId");

                ExtensionHelper::addPayment($invoiceId, 'Midtrans', $amount, null, $transactionId);
                \Log::info("Midtrans payment successfully recorded for invoice ID: $invoiceId");

            } catch (\Throwable $e) {
                \Log::error("Midtrans webhook error: {$e->getMessage()}");
                return response('Error', 500);
            }
        } else {
            \Log::debug('Midtrans webhook ignored due to invalid status or transaction_status.', [
                'status_code' => $data['status_code'] ?? null,
                'transaction_status' => $data['transaction_status'] ?? null,
            ]);
        }

        return response('OK', 200);
    }

    public function canUseGateway($total, $currency, $type, $items = [])
    {
        if ($currency != 'IDR') return false;
        if ($total < 5000) return false;

        return true;
    }
}
