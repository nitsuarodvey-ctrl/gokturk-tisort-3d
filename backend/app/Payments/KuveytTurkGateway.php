<?php

namespace App\Payments;

use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
use App\Models\PaymentAttempt;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Throwable;

class KuveytTurkGateway
{
    /**
     * @param  array{
     *     cardHolderName: string,
     *     cardNumber: string,
     *     expiryMonth: string,
     *     expiryYear: string,
     *     cvv: string,
     *     email: string,
     *     billingCity: string,
     *     billingState: string,
     *     billingPostalCode: string,
     *     billingAddress: string,
     *     clientIp: string
     * }  $paymentData
     */
    public function start(PaymentAttempt $attempt, Order $order, array $paymentData): string
    {
        $configuration = $this->configuration();
        $callbackUrl = $configuration['callback_url'];
        $hashData = $this->hash([
            $configuration['merchant_id'],
            $attempt->merchant_order_id,
            (string) $attempt->amount,
            $callbackUrl,
            $callbackUrl,
            $configuration['username'],
            $this->hashedPassword($configuration['password']),
        ]);

        [$document, $root] = $this->newMessage();
        $this->append($document, $root, 'APIVersion', 'TDV2.0.0');
        $this->append($document, $root, 'OkUrl', $callbackUrl);
        $this->append($document, $root, 'FailUrl', $callbackUrl);
        $this->append($document, $root, 'HashData', $hashData);
        $this->append($document, $root, 'MerchantId', $configuration['merchant_id']);
        $this->append($document, $root, 'CustomerId', $configuration['customer_id']);

        $deviceData = $this->append($document, $root, 'DeviceData', '');
        $this->append($document, $deviceData, 'DeviceChannel', '02');
        $this->append($document, $deviceData, 'ClientIP', $paymentData['clientIp']);

        $cardHolderData = $this->append($document, $root, 'CardHolderData', '');
        $this->append($document, $cardHolderData, 'BillAddrCity', $paymentData['billingCity']);
        $this->append($document, $cardHolderData, 'BillAddrCountry', '792');
        $this->append($document, $cardHolderData, 'BillAddrLine1', $paymentData['billingAddress']);
        $this->append($document, $cardHolderData, 'BillAddrPostCode', $paymentData['billingPostalCode']);
        $this->append($document, $cardHolderData, 'BillAddrState', $paymentData['billingState']);
        $this->append($document, $cardHolderData, 'Email', $paymentData['email']);
        $mobilePhone = $this->append($document, $cardHolderData, 'MobilePhone', '');
        $this->append($document, $mobilePhone, 'Cc', '90');
        $this->append($document, $mobilePhone, 'Subscriber', $this->subscriber($order->phone));

        $this->append($document, $root, 'UserName', $configuration['username']);
        $this->append($document, $root, 'CardNumber', $paymentData['cardNumber']);
        $this->append($document, $root, 'CardExpireDateYear', $paymentData['expiryYear']);
        $this->append($document, $root, 'CardExpireDateMonth', $paymentData['expiryMonth']);
        $this->append($document, $root, 'CardCVV2', $paymentData['cvv']);
        $this->append($document, $root, 'CardHolderName', $paymentData['cardHolderName']);
        $this->append($document, $root, 'TransactionType', 'Sale');
        $this->append($document, $root, 'InstallmentCount', '0');
        $this->append($document, $root, 'Amount', (string) $attempt->amount);
        $this->append($document, $root, 'DisplayAmount', (string) $attempt->amount);
        $this->append($document, $root, 'CurrencyCode', $attempt->currency_code);
        $this->append($document, $root, 'MerchantOrderId', $attempt->merchant_order_id);
        $this->append($document, $root, 'TransactionSecurity', '3');

        return $this->postXml($configuration['pay_url'], $document->saveXML());
    }

    /**
     * @return array{
     *     hashValid: bool,
     *     merchantOrderId: string,
     *     merchantId: string,
     *     amount: int,
     *     responseCode: string,
     *     responseMessage: string,
     *     orderId: string,
     *     md: string,
     *     referenceId: string,
     *     businessKey: string
     * }
     */
    public function authenticate(string $authenticationResponse): array
    {
        $configuration = $this->configuration();
        $xpath = $this->parse($authenticationResponse);
        $merchantOrderId = $this->value($xpath, 'MerchantOrderId');
        $responseCode = $this->value($xpath, 'ResponseCode');
        $orderId = $this->value($xpath, 'OrderId');
        $receivedHash = $this->value($xpath, 'HashData');
        $expectedHash = $this->hash([
            $merchantOrderId,
            $responseCode,
            $orderId,
            $this->hashedPassword($configuration['password']),
        ]);

        return [
            'hashValid' => hash_equals($expectedHash, $receivedHash),
            'merchantOrderId' => $merchantOrderId,
            'merchantId' => $this->nestedValue($xpath, 'VPosMessage', 'MerchantId'),
            'amount' => (int) $this->nestedValue($xpath, 'VPosMessage', 'Amount'),
            'responseCode' => $responseCode,
            'responseMessage' => $this->value($xpath, 'ResponseMessage'),
            'orderId' => $orderId,
            'md' => $this->value($xpath, 'MD', false),
            'referenceId' => $this->value($xpath, 'ReferenceId', false),
            'businessKey' => $this->value($xpath, 'BusinessKey', false),
        ];
    }

    /**
     * @return array{
     *     hashValid: bool,
     *     merchantOrderId: string,
     *     responseCode: string,
     *     responseMessage: string,
     *     orderId: string,
     *     provisionNumber: string,
     *     rrn: string,
     *     stan: string,
     *     businessKey: string
     * }
     */
    public function provision(PaymentAttempt $attempt, string $md): array
    {
        $configuration = $this->configuration();
        $hashData = $this->hash([
            $configuration['merchant_id'],
            $attempt->merchant_order_id,
            (string) $attempt->amount,
            $configuration['username'],
            $this->hashedPassword($configuration['password']),
        ]);

        [$document, $root] = $this->newMessage();
        $this->append($document, $root, 'APIVersion', 'TDV2.0.0');
        $this->append($document, $root, 'HashData', $hashData);
        $this->append($document, $root, 'MerchantId', $configuration['merchant_id']);
        $this->append($document, $root, 'CustomerId', $configuration['customer_id']);
        $this->append($document, $root, 'UserName', $configuration['username']);
        $this->append($document, $root, 'TransactionType', 'Sale');
        $this->append($document, $root, 'InstallmentCount', '0');
        $this->append($document, $root, 'Amount', (string) $attempt->amount);
        $this->append($document, $root, 'MerchantOrderId', $attempt->merchant_order_id);
        $this->append($document, $root, 'TransactionSecurity', '3');
        $additionalData = $this->append($document, $root, 'KuveytTurkVPosAdditionalData', '');
        $item = $this->append($document, $additionalData, 'AdditionalData', '');
        $this->append($document, $item, 'Key', 'MD');
        $this->append($document, $item, 'Data', $md);

        $xpath = $this->parse($this->postXml($configuration['provision_url'], $document->saveXML()));
        $merchantOrderId = $this->value($xpath, 'MerchantOrderId');
        $responseCode = $this->value($xpath, 'ResponseCode');
        $orderId = $this->value($xpath, 'OrderId');
        $rrn = $this->value($xpath, 'RRN', false);
        $receivedHash = $this->value($xpath, 'HashData');
        $expectedHash = $this->hash([
            $merchantOrderId,
            $rrn,
            $responseCode,
            $orderId,
            $this->hashedPassword($configuration['password']),
        ]);

        return [
            'hashValid' => hash_equals($expectedHash, $receivedHash),
            'merchantOrderId' => $merchantOrderId,
            'responseCode' => $responseCode,
            'responseMessage' => $this->value($xpath, 'ResponseMessage'),
            'orderId' => $orderId,
            'provisionNumber' => $this->value($xpath, 'ProvisionNumber', false),
            'rrn' => $rrn,
            'stan' => $this->value($xpath, 'Stan', false),
            'businessKey' => $this->value($xpath, 'BusinessKey', false),
        ];
    }

    /** @return array{0: DOMDocument, 1: DOMElement} */
    private function newMessage(): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = false;
        $root = $document->createElement('KuveytTurkVPosMessage');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $document->appendChild($root);

        return [$document, $root];
    }

    private function append(DOMDocument $document, DOMElement $parent, string $name, string $value): DOMElement
    {
        $element = $document->createElement($name);
        if ($value !== '') {
            $element->appendChild($document->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }

    private function postXml(string $url, string|false $xml): string
    {
        if (! is_string($xml) || $xml === '') {
            throw new PaymentGatewayException('Ödeme isteği oluşturulamadı.');
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(20)
                ->withBody($xml, 'application/xml; charset=UTF-8')
                ->post($url);
        } catch (Throwable) {
            throw new PaymentGatewayException('Banka ödeme servisine bağlanılamadı.');
        }

        $body = $response->body();
        if (! $response->successful() || $body === '' || strlen($body) > 2_000_000) {
            throw new PaymentGatewayException('Banka ödeme servisi geçerli bir yanıt vermedi.');
        }

        return $body;
    }

    private function parse(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new PaymentGatewayException('Banka yanıtı doğrulanamadı.');
        }

        return new DOMXPath($document);
    }

    private function value(DOMXPath $xpath, string $name, bool $required = true): string
    {
        $value = trim((string) $xpath->evaluate("string((//*[local-name()='{$name}'])[1])"));
        if ($required && $value === '') {
            throw new PaymentGatewayException('Banka yanıtında zorunlu bir alan eksik.');
        }

        return $value;
    }

    private function nestedValue(DOMXPath $xpath, string $parent, string $name): string
    {
        $value = trim((string) $xpath->evaluate("string((//*[local-name()='{$parent}']/*[local-name()='{$name}'])[1])"));
        if ($value === '') {
            throw new PaymentGatewayException('Banka yanıtında zorunlu bir alan eksik.');
        }

        return $value;
    }

    /** @param array<int, string> $parts */
    private function hash(array $parts): string
    {
        $value = mb_convert_encoding(implode('', $parts), 'ISO-8859-9', 'UTF-8');

        return base64_encode(sha1($value, true));
    }

    private function hashedPassword(string $password): string
    {
        return $this->hash([$password]);
    }

    private function subscriber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0090')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    /**
     * @return array{
     *     merchant_id: string,
     *     customer_id: string,
     *     username: string,
     *     password: string,
     *     pay_url: string,
     *     provision_url: string,
     *     callback_url: string,
     *     return_url: string
     * }
     */
    private function configuration(): array
    {
        $configuration = config('services.kuveyt_turk', []);
        if (! ($configuration['enabled'] ?? false)) {
            throw new PaymentGatewayException('Kartla ödeme şu anda kullanılamıyor.');
        }

        $required = ['merchant_id', 'customer_id', 'username', 'password', 'pay_url', 'provision_url', 'callback_url', 'return_url'];
        foreach ($required as $key) {
            if (! is_string($configuration[$key] ?? null) || trim($configuration[$key]) === '') {
                throw new PaymentGatewayException('Kartla ödeme yapılandırması eksik.');
            }
        }

        if (! str_starts_with($configuration['pay_url'], 'https://') || ! str_starts_with($configuration['provision_url'], 'https://')) {
            throw new PaymentGatewayException('Banka bağlantısı HTTPS kullanmalıdır.');
        }
        if (app()->isProduction() && (! str_starts_with($configuration['callback_url'], 'https://') || ! str_starts_with($configuration['return_url'], 'https://'))) {
            throw new PaymentGatewayException('Canlı ödeme dönüş adresleri HTTPS kullanmalıdır.');
        }

        return $configuration;
    }
}
