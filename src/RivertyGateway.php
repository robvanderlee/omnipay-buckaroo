<?php

namespace Omnipay\Buckaroo;

use Omnipay\Common\AbstractGateway;

/**
 * Buckaroo Riverty (Afterpay) Gateway
 */
class RivertyGateway extends AbstractGateway
{
    public function getName()
    {
        return 'Buckaroo Riverty';
    }

    /** Performs a credit check and creates the Riverty order ("Pay" action) */
    public function purchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Buckaroo\Message\RivertyPurchaseRequest', $parameters);
    }
}
