<?php
declare(strict_types=1);

final class PaymentGatewayException extends RuntimeException
{
}

final class KuveytTurkGateway
{
    private array $config;
    private ?Closure $httpPost;

    public function __construct(array $config, ?callable $httpPost = null)
    {
        $this->config = $config;
        $this->httpPost = $httpPost === null ? null : Closure::fromCallable($httpPost);
    }

    public function start(array $attempt, array $order, array $paymentData): string
    {
        $this->assertConfigured();
        $callbackUrl = $this->config['callback_url'];
        $hashData = $this->hash([
            $this->config['merchant_id'], $attempt['merchant_order_id'], (string) $attempt['amount'],
            $callbackUrl, $callbackUrl, $this->config['username'], $this->hashedPassword(),
        ]);

        [$document, $root] = $this->newMessage();
        $this->append($document, $root, 'APIVersion', 'TDV2.0.0');
        $this->append($document, $root, 'OkUrl', $callbackUrl);
        $this->append($document, $root, 'FailUrl', $callbackUrl);
        $this->append($document, $root, 'HashData', $hashData);
        $this->append($document, $root, 'MerchantId', $this->config['merchant_id']);
        $this->append($document, $root, 'CustomerId', $this->config['customer_id']);
        $device = $this->append($document, $root, 'DeviceData');
        $this->append($document, $device, 'DeviceChannel', '02');
        $this->append($document, $device, 'ClientIP', $paymentData['client_ip']);
        $holder = $this->append($document, $root, 'CardHolderData');
        $this->append($document, $holder, 'BillAddrCity', $paymentData['billing_city']);
        $this->append($document, $holder, 'BillAddrCountry', '792');
        $this->append($document, $holder, 'BillAddrLine1', $paymentData['billing_address']);
        $this->append($document, $holder, 'BillAddrPostCode', $paymentData['billing_postal_code']);
        $this->append($document, $holder, 'BillAddrState', $paymentData['billing_state']);
        $this->append($document, $holder, 'Email', $order['email']);
        $mobile = $this->append($document, $holder, 'MobilePhone');
        $this->append($document, $mobile, 'Cc', '90');
        $this->append($document, $mobile, 'Subscriber', $this->subscriber($order['phone']));
        $this->append($document, $root, 'UserName', $this->config['username']);
        $this->append($document, $root, 'CardNumber', $paymentData['card_number']);
        $this->append($document, $root, 'CardExpireDateYear', $paymentData['expiry_year']);
        $this->append($document, $root, 'CardExpireDateMonth', $paymentData['expiry_month']);
        $this->append($document, $root, 'CardCVV2', $paymentData['cvv']);
        $this->append($document, $root, 'CardHolderName', $paymentData['card_holder_name']);
        $this->append($document, $root, 'TransactionType', 'Sale');
        $this->append($document, $root, 'InstallmentCount', '0');
        $this->append($document, $root, 'Amount', (string) $attempt['amount']);
        $this->append($document, $root, 'DisplayAmount', (string) $attempt['amount']);
        $this->append($document, $root, 'CurrencyCode', $attempt['currency_code']);
        $this->append($document, $root, 'MerchantOrderId', $attempt['merchant_order_id']);
        $this->append($document, $root, 'TransactionSecurity', '3');

        return $this->postXml($this->config['pay_url'], (string) $document->saveXML());
    }

    public function authenticate(string $authenticationResponse): array
    {
        $this->assertConfigured();
        $xpath = $this->parse($authenticationResponse);
        $merchantOrderId = $this->value($xpath, 'MerchantOrderId');
        $responseCode = $this->value($xpath, 'ResponseCode');
        $orderId = $this->value($xpath, 'OrderId');
        $receivedHash = $this->value($xpath, 'HashData');
        $expectedHash = $this->hash([$merchantOrderId, $responseCode, $orderId, $this->hashedPassword()]);

        return [
            'hash_valid' => hash_equals($expectedHash, $receivedHash),
            'merchant_order_id' => $merchantOrderId,
            'merchant_id' => $this->nestedValue($xpath, 'VPosMessage', 'MerchantId'),
            'customer_id' => $this->nestedValue($xpath, 'VPosMessage', 'CustomerId'),
            'amount' => $this->integerValue($this->nestedValue($xpath, 'VPosMessage', 'Amount')),
            'currency_code' => $this->nestedValue($xpath, 'VPosMessage', 'CurrencyCode'),
            'transaction_security' => $this->nestedValue($xpath, 'VPosMessage', 'TransactionSecurity'),
            'response_code' => $responseCode,
            'response_message' => $this->value($xpath, 'ResponseMessage', false),
            'order_id' => $orderId,
            'md' => $this->value($xpath, 'MD', false),
            'reference_id' => $this->value($xpath, 'ReferenceId', false),
            'business_key' => $this->value($xpath, 'BusinessKey', false),
        ];
    }

    public function provision(array $attempt, string $md): array
    {
        $this->assertConfigured();
        $hashData = $this->hash([
            $this->config['merchant_id'], $attempt['merchant_order_id'], (string) $attempt['amount'],
            $this->config['username'], $this->hashedPassword(),
        ]);
        [$document, $root] = $this->newMessage();
        $this->append($document, $root, 'APIVersion', 'TDV2.0.0');
        $this->append($document, $root, 'HashData', $hashData);
        $this->append($document, $root, 'MerchantId', $this->config['merchant_id']);
        $this->append($document, $root, 'CustomerId', $this->config['customer_id']);
        $this->append($document, $root, 'UserName', $this->config['username']);
        $this->append($document, $root, 'TransactionType', 'Sale');
        $this->append($document, $root, 'InstallmentCount', '0');
        $this->append($document, $root, 'Amount', (string) $attempt['amount']);
        $this->append($document, $root, 'MerchantOrderId', $attempt['merchant_order_id']);
        $this->append($document, $root, 'TransactionSecurity', '3');
        $additional = $this->append($document, $root, 'KuveytTurkVPosAdditionalData');
        $item = $this->append($document, $additional, 'AdditionalData');
        $this->append($document, $item, 'Key', 'MD');
        $this->append($document, $item, 'Data', $md);

        $xpath = $this->parse($this->postXml($this->config['provision_url'], (string) $document->saveXML()));
        $merchantOrderId = $this->value($xpath, 'MerchantOrderId');
        $responseCode = $this->value($xpath, 'ResponseCode');
        $orderId = $this->value($xpath, 'OrderId');
        $rrn = $this->value($xpath, 'RRN', false);
        $receivedHash = $this->value($xpath, 'HashData');
        $expectedHash = $this->hash([$merchantOrderId, $rrn, $responseCode, $orderId, $this->hashedPassword()]);

        return [
            'hash_valid' => hash_equals($expectedHash, $receivedHash),
            'merchant_order_id' => $merchantOrderId,
            'merchant_id' => $this->nestedValue($xpath, 'VPosMessage', 'MerchantId'),
            'customer_id' => $this->nestedValue($xpath, 'VPosMessage', 'CustomerId'),
            'amount' => $this->integerValue($this->nestedValue($xpath, 'VPosMessage', 'Amount')),
            'currency_code' => $this->nestedValue($xpath, 'VPosMessage', 'CurrencyCode'),
            'transaction_security' => $this->nestedValue($xpath, 'VPosMessage', 'TransactionSecurity'),
            'response_code' => $responseCode,
            'response_message' => $this->value($xpath, 'ResponseMessage', false),
            'order_id' => $orderId,
            'provision_number' => $this->value($xpath, 'ProvisionNumber', false),
            'rrn' => $rrn,
            'stan' => $this->value($xpath, 'Stan', false),
            'business_key' => $this->value($xpath, 'BusinessKey', false),
        ];
    }

    public function createResponseHash(string $merchantOrderId, string $responseCode, string $orderId, string $rrn = ''): string
    {
        $parts = $rrn === ''
            ? [$merchantOrderId, $responseCode, $orderId, $this->hashedPassword()]
            : [$merchantOrderId, $rrn, $responseCode, $orderId, $this->hashedPassword()];
        return $this->hash($parts);
    }

    private function assertConfigured(): void
    {
        if (!($this->config['enabled'] ?? false)) throw new PaymentGatewayException('Kartla ödeme şu anda kullanılamıyor.');
        foreach (['customer_id', 'merchant_id', 'username', 'password', 'pay_url', 'provision_url', 'callback_url'] as $key) {
            if (!is_string($this->config[$key] ?? null) || trim($this->config[$key]) === '') throw new PaymentGatewayException('Kartla ödeme yapılandırması eksik.');
        }
        if (!str_starts_with($this->config['pay_url'], 'https://') || !str_starts_with($this->config['provision_url'], 'https://')) {
            throw new PaymentGatewayException('Banka bağlantısı HTTPS kullanmalıdır.');
        }
    }

    private function postXml(string $url, string $xml): string
    {
        if ($xml === '' || strlen($xml) > 131072) throw new PaymentGatewayException('Ödeme isteği oluşturulamadı.');
        if ($this->httpPost !== null) {
            $body = ($this->httpPost)($url, $xml);
            if (!is_string($body) || $body === '' || strlen($body) > 2000000) throw new PaymentGatewayException('Banka ödeme servisi geçerli bir yanıt vermedi.');
            return $body;
        }
        if (!function_exists('curl_init')) throw new PaymentGatewayException('Sunucuda güvenli banka bağlantısı kullanılamıyor.');
        $body = '';
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => ['Content-Type: application/xml; charset=UTF-8', 'Accept: text/html, application/xml'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 2000000) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $success = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($success === false || $status < 200 || $status >= 300 || $body === '') {
            error_log('Kuveyt Turk HTTP error: status=' . $status . ' error=' . $error);
            throw new PaymentGatewayException('Banka ödeme servisine bağlanılamadı.');
        }
        return $body;
    }

    private function parse(string $xml): DOMXPath
    {
        if ($xml === '' || strlen($xml) > 2000000 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) throw new PaymentGatewayException('Banka yanıtı doğrulanamadı.');
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) throw new PaymentGatewayException('Banka yanıtı doğrulanamadı.');
        return new DOMXPath($document);
    }

    private function value(DOMXPath $xpath, string $name, bool $required = true): string
    {
        $value = trim((string) $xpath->evaluate("string((//*[local-name()='{$name}'])[1])"));
        if ($required && $value === '') throw new PaymentGatewayException('Banka yanıtında zorunlu alan eksik.');
        return $value;
    }

    private function nestedValue(DOMXPath $xpath, string $parent, string $name, bool $required = true): string
    {
        $value = trim((string) $xpath->evaluate("string((//*[local-name()='{$parent}']/*[local-name()='{$name}'])[1])"));
        if ($required && $value === '') throw new PaymentGatewayException('Banka yanıtında zorunlu alan eksik.');
        return $value;
    }

    private function integerValue(string $value): int
    {
        if (!preg_match('/^[0-9]+$/', $value)) throw new PaymentGatewayException('Banka yanıtında tutar geçersiz.');
        return (int) $value;
    }

    private function hash(array $parts): string
    {
        $encoded = iconv('UTF-8', 'ISO-8859-9', implode('', $parts));
        if ($encoded === false) throw new PaymentGatewayException('Ödeme imzası oluşturulamadı.');
        return base64_encode(sha1($encoded, true));
    }

    private function hashedPassword(): string
    {
        return $this->hash([$this->config['password']]);
    }

    private function subscriber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0090')) $digits = substr($digits, 4);
        elseif (str_starts_with($digits, '90') && strlen($digits) === 12) $digits = substr($digits, 2);
        elseif (str_starts_with($digits, '0') && strlen($digits) === 11) $digits = substr($digits, 1);
        if (!preg_match('/^[0-9]{10}$/', $digits)) throw new PaymentGatewayException('Telefon numarası ödeme için uygun değil.');
        return $digits;
    }

    private function newMessage(): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElement('KuveytTurkVPosMessage');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $document->appendChild($root);
        return [$document, $root];
    }

    private function append(DOMDocument $document, DOMElement $parent, string $name, string $value = ''): DOMElement
    {
        $element = $document->createElement($name);
        if ($value !== '') $element->appendChild($document->createTextNode($value));
        $parent->appendChild($element);
        return $element;
    }
}
