<?php

namespace Omnipay\Buckaroo\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * Buckaroo Riverty (Afterpay) Response.
 *
 * Shared across Pay/Authorize/Capture/CancelAuthorize/Refund - they all return the same JSON
 * envelope shape. See https://docs.buckaroo.io/docs/riverty-requests for example bodies and
 * status codes.
 */
class RivertyResponse extends AbstractResponse implements RedirectResponseInterface
{
    /** Status.Code.Code values, per docs.buckaroo.io/docs/riverty-requests */
    const STATUS_SUCCESS = 190;
    const STATUS_PENDING_INPUT = 790;
    const STATUS_PENDING_PROCESSING = 791;
    const STATUS_REJECTED = 690;
    const STATUS_FAILED = 490;
    const STATUS_CANCELLED_BY_USER = 890;
    const STATUS_CANCELLED_BY_MERCHANT = 891;

    public function isSuccessful()
    {
        return self::STATUS_SUCCESS === $this->getStatusCode();
    }

    /**
     * True when Buckaroo/Riverty needs an extra step (e.g. the credit check / consumer
     * acceptance redirect) before the transaction can be considered final.
     *
     * @return bool
     */
    public function isPending()
    {
        return in_array($this->getStatusCode(), array(self::STATUS_PENDING_INPUT, self::STATUS_PENDING_PROCESSING), true);
    }

    public function isRedirect()
    {
        return $this->isPending() && null !== $this->getRedirectUrl();
    }

    public function getRedirectUrl()
    {
        return isset($this->data['RequiredAction']['RedirectURL']) && '' !== $this->data['RequiredAction']['RedirectURL']
            ? $this->data['RequiredAction']['RedirectURL']
            : null;
    }

    /**
     * Unlike Buckaroo's own generic hosted-checkout redirect (BuckarooGateway/PurchaseResponse,
     * which is a POST with signed Brq_* form data), Riverty's RequiredAction.RedirectURL is a
     * plain, self-contained GET link - no form data needs to accompany it.
     */
    public function getRedirectMethod()
    {
        return 'GET';
    }

    public function getRedirectData()
    {
        return array();
    }

    /**
     * @return int|null
     */
    public function getStatusCode()
    {
        return isset($this->data['Status']['Code']['Code']) ? (int) $this->data['Status']['Code']['Code'] : null;
    }

    public function getTransactionReference()
    {
        return isset($this->data['Key']) ? $this->data['Key'] : null;
    }

    public function getMessage()
    {
        if (!empty($this->data['Status']['SubCode']['Description'])) {
            return $this->data['Status']['SubCode']['Description'];
        }

        return isset($this->data['Status']['Code']['Description']) ? $this->data['Status']['Code']['Description'] : null;
    }
}
