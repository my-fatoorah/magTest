<?php

namespace MyFatoorah\Gateway\Plugin;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use MyFatoorah\Gateway\Controller\Checkout\Success;
use MyFatoorah\Library\PaymentMyfatoorahApiV2;

/**
 * The class checks MyFatoorah order statuses before cleaning expired quotes by cron
 */
class CleanExpiredOrdersPlugin
{

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var CollectionFactory
     */
    private $orderCollection;

    /**
     * @var Success
     */
    private $successObj;

    //---------------------------------------------------------------------------------------------------------------------------------------------------
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        Success $successObj,
        CollectionFactory $orderCollection
    ) {

        //used to list stores
        $this->storeManager = $storeManager;
        $this->scopeConfig  = $scopeConfig;

        //used in check MyFatoorah Status
        $this->successObj = $successObj;

        //used in list orders
        $this->orderCollection = $orderCollection;
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Clean expired quotes (cron process)
     *
     * @return void
     */
    public function beforeExecute()
    {

        $stores = $this->storeManager->getStores(true);

        /**
         * @var $store \Magento\Store\Model\Store
         */
        $scope = ScopeInterface::SCOPE_STORE;
        foreach ($stores as $sId => $store) {
            try {

                //get store needed config value
                $path     = 'payment/myfatoorah_payment/';
                $lifetime = $this->scopeConfig->getValue('sales/orders/delete_pending_after', $scope, $sId);

                $apiKey      = $this->scopeConfig->getValue($path . 'api_key', $scope, $sId);
                $isTesting   = $this->scopeConfig->getValue($path . 'is_testing', $scope, $sId);
                $countryMode = $this->scopeConfig->getValue($path . 'countryMode', $scope, $sId);

                $this->mfObj = new PaymentMyfatoorahApiV2($apiKey, $countryMode, $isTesting, MYFATOORAH_LOG_FILE);

                $this->checkPendingOrderByStore($sId, $lifetime);
            } catch (NoSuchEntityException $ex) {
                // Store doesn't really exist, so move on.
                continue;
            }
        }
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get pending orders within the life time
     *
     * @param type $storeId
     * @param type $lifetime
     */
    public function checkPendingOrderByStore($storeId, $lifetime)
    {
        /**
         * @var $orders \Magento\Sales\Model\ResourceModel\Order\Collection
         */
        $orders = $this->orderCollection->create();
        $orders->addFieldToFilter('store_id', $storeId);
        $orders->addFieldToFilter('status', Order::STATE_PENDING_PAYMENT);
        $orders->getSelect()->where(
            new \Zend_Db_Expr('TIME_TO_SEC(TIMEDIFF(CURRENT_TIMESTAMP, `updated_at`)) >= ' . $lifetime * 60)
        );

        //check MyFatoorah status
        $this->checkMFStatus($orders);
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param  \Magento\Sales\Model\ResourceModel\Order\Collection $orders
     * @return type
     */
    public function checkMFStatus($orders)
    {
        /**
         * @var \Magento\Sales\Model\Order $order
         */
        foreach ($orders as $order) {
            $orderId = $order->getRealOrderId();

            $collection = $this->successObj->mfInvoiceFactory->create()->addFieldToFilter('order_id', $orderId);
            $item       = $collection->getFirstItem()->getData();
            if (empty($item['invoice_id'])) {
                continue;
            }

            $invoiceId = $item['invoice_id'];

            $this->mfObj->log("Order #$orderId ----- Cron Job - Check Order Status with Invoice Id #$invoiceId");
            try {
                $this->successObj->checkStatus($invoiceId, 'InvoiceId', $this->mfObj, '-Cron', $orderId);
            } catch (\Exception $ex) {
                $this->mfObj->log('In Cron Exception Block: ' . $ex->getMessage());
            }
        }
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
