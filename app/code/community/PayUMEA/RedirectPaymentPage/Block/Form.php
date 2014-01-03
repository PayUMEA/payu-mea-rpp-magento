<?php	//root\app\code\local\Safeshop\Pro\Block\Form.php

class PayUMEA_RedirectPaymentPage_Block_Form extends Mage_Payment_Block_Form
{   
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate( 'payuMeaRedirectPaymentPage/form.phtml' );
    }
}

?>