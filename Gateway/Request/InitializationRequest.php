<?php

namespace MyFatoorah\Gateway\Gateway\Request;

use MyFatoorah\Gateway\Gateway\Config\Config;
use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Data\Order\OrderAdapter;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class InitializationRequest implements BuilderInterface {

    private $_session;
    private $_gatewayConfig;

    /**
     * @param Config $gatewayConfig
     * @param Session $session
     */
    public function __construct(
            Config $gatewayConfig,
            Session $session
    ) {
        $this->_gatewayConfig = $gatewayConfig;
        $this->_session       = $session;
    }

    /**
     * Checks the quote for validity
     *
     * @param OrderAdapter $order
     *
     * @return bool;
     */
    private function validateQuote($order) {
        return true;
    }

    /**
     * Builds ENV request
     * From: https://github.com/magento/magento2/blob/2.1.3/app/code/Magento/Payment/Model/Method/Adapter.php
     * The $buildSubject contains:
     * 'payment' => $this->getInfoInstance()
     * 'paymentAction' => $paymentAction
     * 'stateObject' => $stateObject
     *
     * @param array $buildSubject
     *
     * @return array
     */
    public function build(array $buildSubject) {

        $payment     = $buildSubject['payment'];
        $stateObject = $buildSubject['stateObject'];

        $order = $payment->getOrder();

        if ($this->validateQuote($order)) {
            $stateObject->setState(Order::STATE_PENDING_PAYMENT);
            $stateObject->setStatus(Order::STATE_PENDING_PAYMENT);
            $stateObject->setIsNotified(false);
        } else {
            $stateObject->setState(Order::STATE_CANCELED);
            $stateObject->setStatus(Order::STATE_CANCELED);
            $stateObject->setIsNotified(false);
        }

        return ['IGNORED' => ['IGNORED']];
    }

}
