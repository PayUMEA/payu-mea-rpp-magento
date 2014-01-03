<?php	//root\app\code\local\Safeshop\Pro\Model\Auth\Type.php

class PayUMEA_RedirectPaymentPage_Model_AdminConfig_EnvironmentType
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'STAGING', 'label' => Mage::helper('payuMeaRedirectPaymentPage')->__('STAGING')),
            array('value' => 'PRODUCTION', 'label' => Mage::helper('payuMeaRedirectPaymentPage')->__('PRODUCTION')),
        );
    }
}

?>