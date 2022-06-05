<?php

namespace MyFatoorah\Gateway\Controller\Checkout;

use MyFatoorah\Gateway\Gateway\Config\Config;
use MyFatoorah\Gateway\Helper\Checkout;
use MyFatoorah\Gateway\Helper\Data;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;
use MyFatoorah\Gateway\Model\ResourceModel\MyfatoorahInvoice\CollectionFactory;

abstract class AbstractAction extends Action {

//    const LOG_FILE = 'myfatoorah.log';

    private $_context;
    private $_checkoutSession;
    private $_orderFactory;
    private $_dataHelper;
    private $_checkoutHelper;
    protected $_gatewayConfig;
    private $_messageManager;
    public $mfInvoiceFactory;
    public $mfObj;

    public function __construct(
            Config $gatewayConfig,
            Session $checkoutSession,
            Context $context,
            OrderFactory $orderFactory,
            Data $dataHelper,
            Checkout $checkoutHelper,
            CollectionFactory $mfInvoiceFactory
    ) {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory    = $orderFactory;
        $this->_dataHelper      = $dataHelper;
        $this->_checkoutHelper  = $checkoutHelper;
        $this->_gatewayConfig   = $gatewayConfig;
        $this->_messageManager  = $context->getMessageManager();
        $this->mfInvoiceFactory = $mfInvoiceFactory;

        $this->mfObj = $gatewayConfig->getMyfatoorahObject();
    }

    protected function getContext() {
        return $this->_context;
    }

    protected function getCheckoutSession() {
        return $this->_checkoutSession;
    }

    protected function getOrderFactory() {
        return $this->_orderFactory;
    }

    protected function getDataHelper() {
        return $this->_dataHelper;
    }

    protected function getCheckoutHelper() {
        return $this->_checkoutHelper;
    }

    protected function getGatewayConfig() {
        return $this->_gatewayConfig;
    }

    protected function getMessageManager() {
        return $this->_messageManager;
    }

    protected function getOrder() {
        $order = $this->_checkoutSession->getLastRealOrder();
        if (!$order) {
            throw new \Exception('Unable to get order from last loaded order id. Possibly related to a failed database call');
        }
        return $order;
    }

    public function getOrderById($orderId) {
        $order = $this->_orderFactory->create()->loadByIncrementId($orderId);

        if (!$order->getId()) {
            return null;
        }

        return $order;
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------
    protected function getObjectManager() {
        return \Magento\Framework\App\ObjectManager::getInstance();
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------
    protected function getPendingOrderLifetime() {
        /** @var \Magento\Framework\App\Config\ScopeConfigInterface $ScopeConfigInterface */
        $ScopeConfigInterface = $this->getObjectManager()->create('\Magento\Framework\App\Config\ScopeConfigInterface');

        return $ScopeConfigInterface->getValue('sales/orders/delete_pending_after', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------   
}
