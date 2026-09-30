<?php

namespace Omnipay\Buckaroo\Message;

use Omnipay\Common\Exception\InvalidResponseException;

/**
 * Buckaroo Riverty (Afterpay) Abstract Request.
 */
abstract class RivertyAbstractRequest extends \Omnipay\Common\Message\AbstractRequest
{
    public $testEndpoint = 'https://testcheckout.buckaroo.nl/html/';
    public $liveEndpoint = 'https://checkout.buckaroo.nl/html/';

    const SERVICE_NAME = 'afterpay';
    const SERVICE_VERSION = 1;

    public function getWebsiteKey()
    {
        return $this->getParameter('websiteKey');
    }

    public function setWebsiteKey($value)
    {
        return $this->setParameter('websiteKey', $value);
    }

    public function getSecretKey()
    {
        return $this->getParameter('secretKey');
    }

    public function setSecretKey($value)
    {
        return $this->setParameter('secretKey', $value);
    }

    public function getEndpoint()
    {
        return $this->getTestMode() ? $this->testEndpoint : $this->liveEndpoint;
    }

    /**
     * A previous transaction's Buckaroo key required for Capture/CancelAuthorize/Refund.
     *
     * @return string|null
     */
    public function getOriginalTransactionKey()
    {
        return $this->getParameter('originalTransactionKey');
    }

    public function setOriginalTransactionKey($value)
    {
        return $this->setParameter('originalTransactionKey', $value);
    }

    /**
     * @return array<int, string>
     */
    public function getLineVatPercentages()
    {
        return (array) $this->getParameter('lineVatPercentages');
    }

    /** @param array<int, string> $value */
    public function setLineVatPercentages($value)
    {
        return $this->setParameter('lineVatPercentages', $value);
    }

    /**
     * The Riverty action for this request, must be one of 'Pay', 'Authorize', 'Capture', 'CancelAuthorize' or
     * 'Refund'
     *
     * @return string
     */
    abstract public function getServiceAction();

    /**
     * @return array<string, string>
     */
    abstract protected function getAmountData();

    /**
     * @return array<int, array<string, string>>
     */
    abstract public function getServiceParameters();

    /**
     * Builds a single Riverty service parameter entry.
     *
     * @param string $name
     * @param mixed  $value
     * @param string $groupType
     * @param string $groupId
     *
     * @return array<string, string>
     */
    protected function buildParameter($name, $value, $groupType = '', $groupId = '')
    {
        return array(
            'Name' => $name,
            'GroupType' => $groupType,
            'GroupID' => $groupId,
            'Value' => (string) $value,
        );
    }

    /**
     * @param float|string $amount
     *
     * @return string
     */
    protected function formatAmount($amount)
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * @return string
     */
    public function getOrder()
    {
        $order = $this->getParameter('order');

        if (!$order) {
            $order = \uniqid();
            $this->setOrder($order);
        }

        return $order;
    }

    public function setOrder($value)
    {
        return $this->setParameter('order', $value);
    }

    public function getData()
    {
        $this->validate('websiteKey', 'secretKey', 'transactionId');

        $data = array(
            'Currency' => $this->getCurrency(),
            'Order' => $this->getOrder(),
        );

        $data = array_merge($data, $this->getAmountData());

        if ($this->getOriginalTransactionKey()) {
            $data['OriginalTransactionKey'] = $this->getOriginalTransactionKey();
        }

        if ($this->getClientIp()) {
            $data['ClientIP'] = array(
                'Address' => $this->getClientIp(),
                // 0 = IPv4, 1 = IPv6
                'Type' => false !== strpos($this->getClientIp(), ':') ? 1 : 0,
            );
        }

        $data['Services'] = array(
            'ServiceList' => array(
                array(
                    'Name' => static::SERVICE_NAME,
                    'Action' => $this->getServiceAction(),
                    'Version' => static::SERVICE_VERSION,
                    'Parameters' => $this->getServiceParameters(),
                ),
            ),
        );

        return $data;
    }

    /**
     * @param array $data
     *
     * @return RivertyResponse
     */
    public function sendData($data)
    {
        $body = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $endpoint = $this->getEndpoint();

        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => $this->getAuthorizationHeader('POST', $endpoint, $body),
        );

        $httpResponse = $this->httpClient->request('POST', $endpoint, $headers, $body);
        $responseData = json_decode((string) $httpResponse->getBody(), true);

        if (!is_array($responseData)) {
            throw new InvalidResponseException('Unable to parse Riverty/Buckaroo JSON response');
        }

        return $this->response = new RivertyResponse($this, $responseData);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param string $body
     *
     * @return string
     */
    protected function getAuthorizationHeader($method, $uri, $body)
    {
        $timestamp = (string) time();
        $nonce = $this->generateNonce();
        $strippedUri = (string) preg_replace('#^[^:/.]*[:/]+#i', '', $uri);
        $encodedUri = strtolower(urlencode($strippedUri));

        $base64Content = '';
        if ('' !== $body) {
            $base64Content = base64_encode(md5($body, true));
        }

        $hashString = $this->getWebsiteKey().$method.$encodedUri.$timestamp.$nonce.$base64Content;
        $hmac = base64_encode(hash_hmac('sha256', $hashString, (string) $this->getSecretKey(), true));

        return sprintf('hmac %s:%s:%s:%s', $this->getWebsiteKey(), $hmac, $nonce, $timestamp);
    }

    /**
     * @return string
     */
    protected function generateNonce()
    {
        return bin2hex(random_bytes(16));
    }
}
