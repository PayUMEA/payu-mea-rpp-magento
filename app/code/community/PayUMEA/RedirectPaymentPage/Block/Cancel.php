<?php	//root\app\code\local\Safeshop\Pro\Block\Request.php

class PayUMEA_RedirectPaymentPage_Block_Cancel extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $standard = Mage::getModel( 'payuMeaRedirectPaymentPage/standard' );
        $form = new Varien_Data_Form();
        
                
        $html = 'hello';
        return $html;
    }
}

?>