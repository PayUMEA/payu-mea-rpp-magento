<?php	//root\app\code\local\Safeshop\Pro\Model\Auth\Type.php

class PayUMEA_RedirectPaymentPage_Model_Url_ReturnUrl
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'PAYMENT', 'label' => Mage::helper('payuMeaRedirectPaymentPage')->__('PAYMENT')),
            array('value' => 'RESERVE', 'label' => Mage::helper('payuMeaRedirectPaymentPage')->__('RESERVE')),
        );
    }
}

?>