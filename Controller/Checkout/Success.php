<?php

namespace MyFatoorah\Gateway\Controller\Checkout;

use Magento\Sales\Model\Order;
use Exception as MFException;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\CatalogInventory\Api\StockManagementInterface;

class Success extends AbstractAction
{

    private $isAddHistory = false;

    public function execute()
    {

        $error = $this->validateMFData();
        if (!$error) {
            //redirect to success page
            $this->getMessageManager()->addSuccessMessage(__('Your payment is complete'));
            $this->_redirect('checkout/onepage/success', ['_secure' => false]);
            return;
        }

        //restore cart
        $this->getCheckoutHelper()->restoreQuote();

        //redirect to cancel page
        $this->getMessageManager()->addErrorMessage($error);
        $this->_redirect('checkout/cart', ['_secure' => false]);
    }

    private function validateMFData()
    {
        $paymentId = $this->getRequest()->get('paymentId');
        if (!$paymentId) {
            return 'Order not found.';
        }

        try {
            return $this->checkStatus($paymentId, 'paymentId', $this->mfObj);
        } catch (Exception $ex) {
            $error = $ex->getMessage();
            $this->mfObj->log('In Exception Block: ' . $error);
            return $error;
        }
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
    public function checkStatus($keyId, $KeyType, $mfObj, $source = '', $escape = false, $orderId = null)
    {

        //check for the invoice or payment id
        $data = $mfObj->getPaymentStatus($keyId, $KeyType, $orderId);

        //get the order
        /**
         * @var Order $order
         */
        $order = $this->getOrderById($data->CustomerReference);
        if (!$order) {
            throw new MFException('Order not found.');
        }

        //order is not pending or canceled
        $status = $order->getState();
        if (!$escape && $status !== Order::STATE_PENDING_PAYMENT && $status !== Order::STATE_CANCELED) {
            return false;
        }

        $message = "MyFatoorah$source: $data->InvoiceStatus Payment. ";
        if (isset($data->focusTransaction)) {
            $paymentId      = $data->focusTransaction->PaymentId;
            $paymentGateway = $data->focusTransaction->PaymentGateway;

            $message .= 'Payment Id #' . $paymentId . '. Gateway used is ' . $paymentGateway . '. ';
        }

        if ($data->InvoiceStatus == 'Paid') {
            $this->savePaymentData($data);
            $this->processPaidPayment($order, $data->InvoiceId, $message);
        } elseif ($data->InvoiceStatus == 'Failed') {
            $this->savePaymentData($data);
            $this->processCancelPayment($order, $message . 'Gateway error is ' . $data->InvoiceError);
        } elseif ($data->InvoiceStatus == 'Expired') {
            $this->processCancelPayment($order, $message . $data->InvoiceError);
        }

        return $data->InvoiceError;
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param  object $data
     * @return void
     */
    private function savePaymentData($data)
    {

        $orderId = $data->CustomerReference;
        //save the invoice id in myfatoorah_invoice table
        //see this sol: https://stackoverflow.com/questions/12570752/how-do-i-select-a-single-row-in-magento-in-a-custom-database-for-display-in-a-bl
        //$collection = Mage::getModel('brands/brands')->getCollection();

        $collection = $this->mfInvoiceFactory->create()->addFieldToFilter('invoice_id', $data->InvoiceId);
        $item       = $collection->getFirstItem();
        $itemData   = $item->getData();

        if (empty($itemData['invoice_id'])) {
            $this->mfObj->log(
                'Order #' . $orderId . ' ----- Get Payment Status - '
                . 'can not save transaction information into database due to pending payment or worng order id'
            );
            return;
        }

        //save payment data
        $transaction        = $data->focusTransaction;
        $this->isAddHistory = false;

        if (empty($itemData['payment_id']) || $itemData['payment_id'] != $transaction->PaymentId) {

            $item->setData('gateway_name', $transaction->PaymentGateway);
            $item->setData('gateway_transaction_id', $transaction->TransactionId);
            $item->setData('payment_id', $transaction->PaymentId);
            $item->setData('authorization_id', $transaction->AuthorizationId);
            $item->setData('reference_id', $transaction->ReferenceId);
            $item->setData('track_id', $transaction->TrackId);

            $item->setData('invoice_reference', $data->InvoiceReference);

            $this->isAddHistory = true;
        }

        $item->save();
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param string                     $invoiceId
     * @param string                     $message
     */
    private function processPaidPayment($order, $invoiceId, $message)
    {
        if ($order->isCanceled()) {
            $this->unCancelorder($order, "MyFatoorah: remove the cancel status");
        }

        $orderStatus = $this->getGatewayConfig()->getMyFatoorahApprovedOrderStatus();
        if (!$this->isStatusExists($orderStatus)) {
            $orderStatus = $order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING);
        }
        if ($order->isCanceled()) {
            $order->setState(Order::STATE_HOLDED)
                ->setStatus(Order::STATE_HOLDED)
                ->addStatusHistoryComment($message);
            $order->save();
            // $order->hold()->save();
        } else {
            //set order status
            $order->setState(Order::STATE_PROCESSING)
                ->setStatus($orderStatus)
                ->addStatusHistoryComment($message)
                ->setIsCustomerNotified($this->getGatewayConfig()->isEmailCustomer());
            $order->save();

            //set payment
            $payment = $order->getPayment();
            $payment->setTransactionId($invoiceId);
            $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);
            $order->save();

            if ($this->getGatewayConfig()->isAutomaticInvoice()) {
                $this->createMagentoInvoice($order, $invoiceId);
            }

            //send email
            $emailSender = $this->getObjectManager()->create(OrderSender::class);
            $emailSender->send($order);
        }
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param string                     $message
     */
    private function processCancelPayment($order, $message)
    {

        if ($order->getState() === Order::STATE_PENDING_PAYMENT) {
            $order->registerCancellation($message)->save();
        } elseif ($this->isAddHistory) {
            $order->addStatusHistoryComment($message)->save();
        }
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param  type $orderStatus
     * @return boolean
     */
    private function isStatusExists($orderStatus)
    {
        $statuses = $this->getObjectManager()
            ->get(\Magento\Sales\Model\Order\Status::class)
            ->getResourceCollection()
            ->getData();

        foreach ($statuses as $status) {
            if ($orderStatus === $status['status']) {
                return true;
            }
        }

        return false;
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param string                     $invoiceId
     */
    private function createMagentoInvoice($order, $invoiceId)
    {
        $orderId = $order->getRealOrderId();

        $msgLog = "Order #$orderId ----- Get Payment Status";

        $this->mfObj->log("$msgLog - In Create Invoice");
        if ($order->canInvoice()) {

            $invoice = $this->getObjectManager()->create(InvoiceService::class)->prepareInvoice($order);
            if ($invoice->getTotalQty()) {
                $this->mfObj->log("$msgLog - Can create an invoice.");
            } else {
                $this->mfObj->log("$msgLog - Can't create an invoice without products.");
            }

            /*
             * Look Magento/Sales/Model/Order/Invoice.register() for CAPTURE_OFFLINE explanation.
             * Basically, if
             * !config/can_capture and
             * config/is_gateway and
             * CAPTURE_OFFLINE and
             * Payment.IsTransactionPending => pay (Invoice.STATE = STATE_PAID...)
             */
            $invoice->setTransactionId($invoiceId);
            $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
            $invoice->register();

            $transaction = $this->getObjectManager()->create(\Magento\Framework\DB\Transaction::class)
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transaction->save();
        } else {
            $err = 'Can\'t create the invoice.';
            $order->addStatusHistoryComment("MyFatoorah: $err");
            $this->mfObj->log("$msgLog - $err");
        }
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * https://magento.stackexchange.com/questions/297133/magento-2-2-10-change-cancelled-order-to-pending
     *
     * @param \Magento\Sales\Model\Order $order
     * @param type                       $comment
     */
    public function unCancelorder($order, $comment)
    {

        $productStockQty = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $productStockQty[$item->getProductId()] = $item->getQtyCanceled();
            foreach ($item->getChildrenItems() as $child) {
                $productStockQty[$child->getProductId()] = $item->getQtyCanceled();
                $child->setQtyCanceled(0);
                $child->setTaxCanceled(0);
                $child->setDiscountTaxCompensationCanceled(0);
            }
            $item->setQtyCanceled(0);
            $item->setTaxCanceled(0);
            $item->setDiscountTaxCompensationCanceled(0);
        }

        $order->setSubtotalCanceled(0);
        $order->setBaseSubtotalCanceled(0);
        $order->setTaxCanceled(0);
        $order->setBaseTaxCanceled(0);
        $order->setShippingCanceled(0);
        $order->setBaseShippingCanceled(0);
        $order->setDiscountCanceled(0);
        $order->setBaseDiscountCanceled(0);
        $order->setTotalCanceled(0);
        $order->setBaseTotalCanceled(0);

        /* Reverting inventory */
        try {
            $stockManagement = $this->getObjectManager()->create(StockManagementInterface::class);
            $isProductSale   = $stockManagement->registerProductsSale(
                $productStockQty,
                $order->getStore()->getWebsiteId()
            );

            $objectManager = $this->getObjectManager();

            /**
             * @var \Magento\Framework\DB\Adapter\AdapterInterface $connection
             */
            $connection = $objectManager->create(\Magento\Framework\App\ResourceConnection::class)->getConnection();

            //get table name
            $deploymentConfig = $objectManager->get(\Magento\Framework\App\DeploymentConfig::class);
            $prefix           = ($deploymentConfig->get('db/table_prefix'));
            $tableName        = $prefix . 'inventory_reservation';

            foreach ($order->getAllItems() as $item) {
                $sku     = $item->getSku();
                $orderId = $order->getId();

                $metadata = "{\"event_type\":\"order_canceled\",\"object_type\":\"order\",\"object_id\":\"$orderId\"%";

                $selectQuery = "SELECT * FROM $tableName WHERE sku='$sku' AND metadata LIKE '$metadata'";
                $result = $connection->fetchAll($selectQuery);
                
                if ($result && count($result) > 0) {
                    $deleteQuery = "DELETE FROM $tableName WHERE sku='$sku' AND metadata LIKE '$metadata'";
                    $connection->query($deleteQuery);
                }
            }
            $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT);

            if (!empty($comment)) {
                $order->addStatusHistoryComment($comment);
            }
            $order->setInventoryProcessed(true);

            if ($order->save()) {
                $quoteFactory = $this->getObjectManager()->create(\Magento\Quote\Model\QuoteFactory::class);
                $quote        = $quoteFactory->create()->load($order->getQuoteId());
                $quote->setIsActive(false)->save();
            }
        } catch (MFException $e) {
            $order->addStatusHistoryComment($e->getMessage());
            $order->save();
        }
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
