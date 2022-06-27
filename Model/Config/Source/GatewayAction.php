<?php

namespace MyFatoorah\Gateway\Model\Config\Source;

class GatewayAction implements \Magento\Framework\Option\ArrayInterface {

    /**
     * {@inheritdoc}
     */
    public function toOptionArray() {
        return array(
            ['value' => 'myfatoorah', 'label' => 'Redirect to MyFatoorah Invoice Page'],
            ['value' => 'multigateways', 'label' => 'List All Enabled Gateways in Checkout Page'],
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray() {
        
    }

}
