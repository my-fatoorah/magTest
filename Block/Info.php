<?php

namespace MyFatoorah\Gateway\Block;

use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Gateway\ConfigInterface;
use MyFatoorah\Gateway\Model\ResourceModel\MyfatoorahInvoice\CollectionFactory;
use MyFatoorah\Gateway\Gateway\Config\Config;

/**
 * Displays the MyFatoorah order information in the admin panel
 */
class Info extends ConfigurableInfo
{

    /**
     * Template path
     *
     * @var string
     */
    protected $_template = 'info/default.phtml';

    /**
     * MyFatoorah invoice information object
     *
     * @var CollectionFactory
     */
    protected $mfInvoiceFactory;

    /**
     * MyFatoorah Config Data
     *
     * @var Config
     */
    protected $gatewayConfig;

    public function __construct(
        CollectionFactory $mfInvoiceFactory,
        Config $mfconfig,
        Context $context,
        ConfigInterface $config,
        array $data = []
    ) {
        parent::__construct($context, $config, $data);
        $this->mfInvoiceFactory = $mfInvoiceFactory;
        $this->gatewayConfig    = $mfconfig;
    }

    /**
     * Returns label
     *
     * @param string $field
     *
     * @return Phrase
     */
    protected function getLabel($field)
    {
        return __($field);
    }

    //for emails
    public function getSpecificInformation()
    {

        $item = $this->getInvoiceData();
        if ($item) {
            $data = [
                'Invoice ID'   => $item['invoice_id'],
                'Invoice Ref.' => $item['invoice_reference'],
                'Gateway'      => $item['gateway_name'],
                'Invoice URL'  => $item['invoice_url'],
            ];

            if (isset($item['reference_id'])) {
                $data['Trans. Ref. ID'] = $item['reference_id'];
            }
            if (isset($item['track_id'])) {
                $data['Track ID'] = $item['track_id'];
            }
            if (isset($item['authorization_id'])) {
                $data['Authorization ID'] = $item['authorization_id'];
            }
            if (isset($item['gateway_transaction_id'])) {
                $data['Transaction ID'] = $item['gateway_transaction_id'];
            }
            if (isset($item['payment_id'])) {
                $data['Payment ID'] = $item['payment_id'];
            }
            return $data;
        }
    }

    //for Admin and user
    public function getMFInformation()
    {

        $item = $this->getInvoiceData();
        if ($item) {
            $payment['invoice']['id']  = $item['invoice_id'];
            $payment['invoice']['url'] = $item['invoice_url'];

            if (isset($item['invoice_reference'])) {
                $payment['info']['Invoice Ref.'] = $item['invoice_reference'];
            }

            $payment['info']['Gateway'] = $item['gateway_name'];

            if (isset($item['reference_id'])) {
                $payment['info']['Trans. Ref. ID'] = $item['reference_id'];
            }
            if (isset($item['track_id'])) {
                $payment['info']['Track ID'] = $item['track_id'];
            }
            if (isset($item['authorization_id'])) {
                $payment['info']['Auth. ID'] = $item['authorization_id'];
            }
            if (isset($item['gateway_transaction_id'])) {
                $payment['info']['Trans. ID'] = $item['gateway_transaction_id'];
            }
            if (isset($item['payment_id'])) {
                $payment['info']['Payment ID'] = $item['payment_id'];
            }

            return $payment;
        }
    }

    public function getInvoiceData()
    {

        $mfOrder = $this->getInfo()->getOrder();
        $orderId = $mfOrder->getRealOrderId();
        if (!$orderId) {
            return;
        }

        $collection = $this->mfInvoiceFactory->create()->addFieldToFilter('order_id', $orderId);
        $items      = $collection->getData();

        if (isset($items[0])) {
            return $items[0];
        }
    }
}
