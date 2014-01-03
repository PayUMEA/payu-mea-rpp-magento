<?php	//root\app\code\local\Safeshop\Pro\Block\Payment\Info.php

class PayUMEA_RedirectPaymentPage_Block_Payment_Info extends Mage_Payment_Block_Info
{
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $payment = $this->getInfo();
        $ssInfo = Mage::getModel('payuMeaRedirectPaymentPage/info');
        
        if( !$this->getIsSecureMode())
        {
            $info = $ssInfo->getPaymentInfo($payment,true);
        }
        else
        {
            $info = $ssInfo->getPublicPaymentInfo($payment,true);
		}
        return($transport->addData($info));
    }
}

?>