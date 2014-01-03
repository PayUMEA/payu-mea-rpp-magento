<?php	//root\app\code\local\Safeshop\Pro\Model\Auth\Type.php

class PayUMEA_RedirectPaymentPage_Model_AdminConfig_CurrencyType
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'ZAR', 'label' => Mage::helper('payuMeaRedirectPaymentPage')->__('ZAR'))            
        );
    }
}

?>