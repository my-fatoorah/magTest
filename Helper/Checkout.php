<?php

namespace MyFatoorah\Gateway\Helper;

use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Catalog\Helper\Data as TaxHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\ScopeInterface;

class Checkout
{

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var TaxHelper
     */
    protected $taxHelper;

    //---------------------------------------------------------------------------------------------------------------------------------------------------
    public function __construct(
        Session $session,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        PriceCurrencyInterface $priceCurrency,
        TaxHelper $taxHelper
    ) {
        $this->session       = $session;
        $this->scopeConfig   = $scopeConfig;
        $this->storeManager  = $storeManager;
        $this->priceCurrency = $priceCurrency;
        $this->taxHelper     = $taxHelper;
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Cancel last placed order with specified comment message
     *
     * @param string $comment Comment appended to order history
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return bool True if order cancelled, false otherwise
     */
    public function cancelCurrentOrder($comment)
    {
        $order = $this->session->getLastRealOrder();
        if ($order && $order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation('MyFatoorah: ' . $comment)->save();

            return true;
        }

        return false;
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Restores quote (restores cart)
     *
     * @return bool
     */
    public function restoreQuote()
    {
        return $this->session->restoreQuote();
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    private function getShippingData($isMFShipping, $item, $product, $productId, $name, $storeId, $weightRate)
    {
        $isShippingProduct = ($isMFShipping && $item->getProductType() != 'downloadable' && !$item->getIsVirtual());
        if ($isShippingProduct) {

            //get weight
            $data['weight'] = $item->getWeight() * $weightRate;

            //get dimensions
            $ressource      = $product->getResource();
            $data['width']  = $ressource->getAttributeRawValue($productId, 'width', $storeId);
            $data['height'] = $ressource->getAttributeRawValue($productId, 'height', $storeId);
            $data['depth']  = $ressource->getAttributeRawValue($productId, 'depth', $storeId);

            if (empty($data['weight']) || empty($data['width']) || empty($data['height']) || empty($data['depth'])) {
                $err    = 'Kindly, contact the site admin to set weight and dimensions for ';
                $msgErr = __($err) . $name;
                throw new \Magento\Framework\Exception\LocalizedException(__($msgErr));
            }
        } else {
            $data['weight'] = $data['width']  = $data['height'] = $data['depth']  = 0;
        }

        return $data;
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Gets invoice Items Array
     *
     * @param array    $items          order item array
     * @param float    $mfCurrencyRate currency rate
     * @param int/bool $isMFShipping   is Shipping integer flag
     * @param bool     $isPayment      is Payment flag
     *                                 return array
     */
    public function getOrderItems($items, $mfCurrencyRate, $isMFShipping, $isPayment)
    {

        $store   = $this->storeManager->getStore();
        $storeId = $store->getId();

        //Magento\Tax\Model\Calculation::CALC_UNIT_BASE,
        //Magento\Tax\Model\Calculation::CALC_ROW_BASE,
        //Magento\Tax\Model\Calculation::CALC_TOTAL_BASE,
        $taxBasedOn = $this->scopeConfig->getValue('tax/calculation/algorithm', ScopeInterface::SCOPE_STORE);

        $weightUnit = $this->scopeConfig->getValue('general/locale/weight_unit', ScopeInterface::SCOPE_STORE);
        $weightRate = \MyFatoorah\Library\MyfatoorahApiV2::getWeightRate($weightUnit);

        $invoiceItemsArr = [];
        $amount          = 0;
        $objectManager   = ObjectManager::getInstance();
        foreach ($items as $item) {

            if ($item->getProductType() != 'simple' &&
                (!$isPayment || ($isPayment && !$item->getIsVirtual() && $item->getProductType() != 'downloadable')
                )) {
                continue;
            }

            $productId = $item->getProductId();

            //must create a new Product from the object manager for each product
            /**
             * @var \Magento\Catalog\Model\Product $product
             */
            $product = $objectManager->create(\Magento\Catalog\Model\Product::class)->load($productId);

            $name = $product->getName();
            $qty  = $isPayment ? (int) $item->getQtyOrdered() : (int) $item->getQty();

            $priceExTax = $this->taxHelper->getTaxPrice($product, $product->getFinalPrice(), false);
            if ($taxBasedOn == \Magento\Tax\Model\Calculation::CALC_UNIT_BASE) {
                $priceExTax = $this->taxHelper->getTaxPrice($product, $product->getFinalPrice(), false);
            } else {
                $rowPriceExTax = $this->taxHelper->getTaxPrice($product, ($product->getFinalPrice() * $qty), false);
                $priceExTax    = $rowPriceExTax / $qty;
            }

            $itemPrice    = round($priceExTax * $mfCurrencyRate, 3);
            $shippingData = $this->getShippingData(
                $isMFShipping,
                $item,
                $product,
                $productId,
                $name,
                $storeId,
                $weightRate
            );

            $invoiceItemsArr[] = [
                'ProductName' => $name,
                'Description' => $name,
                'ItemName'    => $name,
                'Quantity'    => $qty,
                'UnitPrice'   => "$itemPrice",
                'weight'      => $shippingData['weight'],
                'Width'       => $shippingData['width'],
                'Height'      => $shippingData['height'],
                'Depth'       => $shippingData['depth']
            ];
            $amount            += $itemPrice * $qty;
        }

        return [
            'invoiceItemsArr' => $invoiceItemsArr,
            'amount'          => $amount
        ];
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @var \Magento\Sales\Model\Order $order
     */
    public function getInvoiceItems($order, $currencyRate, $isMFShipping, &$amount, $isPayment = false)
    {

        $priceIncTax = $this->scopeConfig->getValue('tax/calculation/price_includes_tax', ScopeInterface::SCOPE_STORE);

        /**
         * @var \Magento\Sales\Api\Data\OrderItemInterface[]  $items
         */
        $items            = $order->getAllItems();
        $orderItemsReturn = $this->getOrderItems($items, $currencyRate, $isMFShipping, $isPayment); //restore cart
        $invoiceItemsArr  = $orderItemsReturn['invoiceItemsArr'];
        $amount           = $orderItemsReturn['amount'];

        //------------------------------
        //Discounds and Coupon
        $discount1 = $order->getBaseDiscountAmount();
        if (!$priceIncTax) {
            $discount1 += $order->getBaseDiscountTaxCompensationAmount();
        }

        $discount = round($discount1 * $currencyRate, 3);
        if ($discount) {
            $invoiceItemsArr[] = [
                'ItemName'  => 'Discount Amount', 'Quantity'  => '1', 'UnitPrice' => "$discount",
                'Weight'    => '0', 'Width'     => '0', 'Height'    => '0', 'Depth'     => '0'];
            $amount            += $discount;
        }

        //------------------------------
        //Shippings
        $mfShipping = 0;

        $shipping1 = $order->getBaseShippingAmount(); // + $order->getBaseShippingTaxAmount();
        $shipping  = round($shipping1 * $currencyRate, 3);
        if ($shipping) {
            if ($isMFShipping) {
                $mfShipping = $shipping;
            } else {
                $invoiceItemsArr[] = [
                    'ItemName'  => 'Shipping Amount', 'Quantity'  => '1', 'UnitPrice' => "$shipping",
                    'Weight'    => '0', 'Width'     => '0', 'Height'    => '0', 'Depth'     => '0'];
                $amount            += $shipping;
            }
        }

        //------------------------------
        //Other fees
        //Mageworx
        $fees1 = $order->getBaseMageworxFeeAmount();
        $fees  = round($fees1 * $currencyRate, 3);
        if ($fees) {
            $invoiceItemsArr[] = [
                'ItemName'  => 'Additional Fees', 'Quantity'  => 1, 'UnitPrice' => "$fees",
                'Weight'    => '0', 'Width'     => '0', 'Height'    => '0', 'Depth'     => '0'];
            $amount            += $fees;
        }

        $productFees1 = $order->getBaseMageworxProductFeeAmount();
        $productFees  = round($productFees1 * $currencyRate, 3);
        if ($productFees) {
            $invoiceItemsArr[] = [
                'ItemName'  => 'Additional Product Fees', 'Quantity'  => 1, 'UnitPrice' => "$productFees",
                'Weight'    => '0', 'Width'     => '0', 'Height'    => '0', 'Depth'     => '0'];
            $amount            += $productFees;
        }

        //        $amount = round($amount, 3);

        /*
          (print_r('FeeAmount' . $order->getBaseMageworxFeeAmount(),1));
          (print_r('FeeInvoiced' . $order->getBaseMageworxFeeInvoiced(),1));
          (print_r('FeeCancelled' . $order->getBaseMageworxFeeCancelled(),1));
          (print_r('FeeTaxAmount' . $order->getBaseMageworxFeeTaxAmount(),1));
          (print_r('FeeDetails' . $order->getMageworxFeeDetails(),1));
          (print_r('FeeRefunded' . $order->getMageworxFeeRefunded(),1));

          (print_r('ProductFeeAmount' . $order->getBaseMageworxProductFeeAmount(),1));
          (print_r('ProductFeeInvoiced' . $order->getBaseMageworxProductFeeInvoiced(),1));
          (print_r('ProductFeeCancelled' . $order->getBaseMageworxProductFeeCancelled(),1));
          (print_r('ProductFeeTaxAmount' . $order->getBaseMageworxProductFeeTaxAmount(),1));
          (print_r('ProductFeeDetails' . $order->getMageworxProductFeeDetails(),1));
          (print_r('ProductFeeRefunded' . $order->getMageworxProductFeeRefunded(),1));
         */

        //------------------------------
        //Tax
        $tax1 = $order->getBaseTotalDue() - $amount - $mfShipping;
        $tax  = round($tax1 * $currencyRate, 3);
        if ($tax) {
            $invoiceItemsArr[] = [
                'ItemName'  => 'Tax Amount', 'Quantity'  => '1', 'UnitPrice' => "$tax",
                'Weight'    => '0', 'Width'     => '0', 'Height'    => '0', 'Depth'     => '0'];
            $amount            += $tax;
        }

        return $invoiceItemsArr;
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
