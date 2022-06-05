<?php

namespace MyFatoorah\Gateway\Gateway\Request;

use MyFatoorah\Gateway\Gateway\Config\Config;
use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RefundRequest implements BuilderInterface {

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
     * Builds ENV request
     * From: https://github.com/magento/magento2/blob/2.1.3/app/code/Magento/Payment/Model/Method/Adapter.php
     * The $buildSubject contains:
     * 'payment' => $this->getInfoInstance()
     * 'paymentAction' => $paymentAction
     * 'stateObject' => $stateObject
     * 
     * rs: The $buildSubject contains:
     * 
     *
     * @param array $buildSubject
     * 'payment'
     * 'amount'
     *
     * @return array
     */
    public function build(array $buildSubject) {

        return [
            'GATEWAY_REFUND_GATEWAY_URL' => $this->_gatewayConfig->getRefundUrl(),
            'GATEWAY_Myfatoorah_OBJ'     => $this->_gatewayConfig->getMyfatoorahObject(),
        ];
    }

}
