<?php	//root\app\code\local\Safeshop\Pro\controllers\RedirectController.php

require_once(__DIR__.'/../../library.payu/classes/class.PayuRedirectPaymentPage.php');

class PayUMEA_RedirectPaymentPage_RedirectController extends Mage_Core_Controller_Front_Action
{
    protected $_order;
    public function getOrder()
    {
        return( $this->_order );
    }

    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    public function getStandard()
    {
        return Mage::getSingleton('payuMeaRedirectPaymentPage/standard');
    }

    public function getConfig()
    {
        return $this->getStandard()->getConfig();
    }

    protected function _getPendingPaymentStatus()
    {
        return Mage::helper('payuMeaRedirectPaymentPage')->getPendingPaymentStatus();
    }

    public function redirectAction()
    {
        $errorMessage = "Invalid Request";
        $deleteOrderOnError = true;
        try
        {
            $session = Mage::getSingleton('checkout/session');

            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($session->getLastRealOrderId());

            $setTransactionArray = Mage::getModel('payuMeaRedirectPaymentPage/standard' )->getSetTransactionValueArray();

            //print "<pre>";
            //var_dump($setTransactionArray);
            //var_dump($order->quote_id);
            //die();


            //Creating a constructor array for RPP instantiation
            $constructorArray = array();
            $constructorArray['username'] = $setTransactionArray['config']['username'];
            $constructorArray['password'] = $setTransactionArray['config']['password'];

            $constructorArray['logEnable'] = true;
            $constructorArray['extendedDebugEnable'] = true;

            if(strtolower($setTransactionArray['config']['environment']) == 'production') {
                $constructorArray['logEnable'] = false;
                $constructorArray['extendedDebugEnable'] = false;
                $constructorArray['production'] = true;
            }

            if(!$order->getId())
                Mage::throwException('No order found');

            try {
                $payuRppInstance = new PayuRedirectPaymentPage($constructorArray);
                $setTransactionResponse = $payuRppInstance->doSetTransactionSoapCall($setTransactionArray['setTransactionArray']);

                if(isset($setTransactionResponse['redirectPaymentPageUrl'])) {

                    $comment = "";
                    if( isset($setTransactionResponse['soapResponse']['payUReference'])) {
                        $comment .= " PayU Reference: ".$setTransactionResponse['soapResponse']['payUReference'];
                    }
                    if($order->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)
                    {
                        $order->setState(
                            Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                            $this->_getPendingPaymentStatus(),
                            Mage::helper('payuMeaRedirectPaymentPage')->__('Customer redirected to PayU for payment.'.$comment)
                        )->save();
                    }

                    if( $session->getQuoteId() && $session->getLastSuccessQuoteId())
                    {
                        $session->setProQuoteId( $session->getQuoteId());
                        $session->setProSuccessQuoteId($session->getLastSuccessQuoteId());
                        $session->setProRealOrderId( $session->getLastRealOrderId());
                        $session->getQuote()->setIsActive(false)->save();
                        $session->clear();
                    }
                    $session->unsQuoteId();
                    $this->_redirectUrl($setTransactionResponse['redirectPaymentPageUrl'], array( '_secure' => true ) );
                    return;
                }
            }
            catch(Exception $e) {
                throw new Mage_Core_Exception($e->getMessage());
            }
        }
        catch( Mage_Core_Exception $e ) {
            //exit;
            $errorMessage = $e->getMessage();
        }
        catch( Exception $e ) {
            Mage::logException($e);
        }

        $this->__doCancelFailedTransaction($errorMessage, $deleteOrderOnError);
        $this->_redirect( 'checkout/cart' );
    }

    public function cancelAction()
    {
        $payuReference = $this->getRequest()->getParam('payUReference');
        $errorMessageAppend = ".";
        if(!empty($payuReference)) {
            $errorMessageAppend = " for PayU Reference: ".$payuReference;
        }
        $errorMessage = 'Order has been cancelled'.$errorMessageAppend;
        $this->__doCancelFailedTransaction($errorMessage);
        $this->_redirect( 'checkout/cart' );
    }


    public function responseAction()
    {
        $errorMessage = "Invalid Gateway Response";

        try
        {
            $payuReference = $this->getRequest()->getParam('PayUReference');

            if(!empty($payuReference)) {
                try {

                    $getTransactionArray = Mage::getModel('payuMeaRedirectPaymentPage/standard' )->getGetTransactionValueArray();

                    //Creating a constructor array for RPP instantiation
                    $constructorArray = array();
                    $constructorArray['username'] = $getTransactionArray['config']['username'];
                    $constructorArray['password'] = $getTransactionArray['config']['password'];

                    $constructorArray['logEnable'] = true;
                    $constructorArray['extendedDebugEnable'] = true;

                    if(strtolower($getTransactionArray['config']['environment']) == 'production') {
                        $constructorArray['logEnable'] = false;
                        $constructorArray['extendedDebugEnable'] = false;
                        $constructorArray['production'] = true;
                    }

                    $getTransactionArray['getTransactionArray']['AdditionalInformation']['payUReference'] = $payuReference;

                    $payuRppInstance = new PayuRedirectPaymentPage($constructorArray);
                    $getTransactionResponse = $payuRppInstance->doGetTransactionSoapCall($getTransactionArray['getTransactionArray']);

                    if(isset($getTransactionResponse['soapResponse']['resultCode']) && (strtolower($getTransactionResponse['soapResponse']['resultCode']) == '00'))  {
                        if(isset($getTransactionResponse['soapResponse']['transactionType']) && (strtolower($getTransactionResponse['soapResponse']['transactionType']) == strtolower($getTransactionArray['getTransactionArray']['TransactionType'])) ) {

                            if(isset($getTransactionResponse['soapResponse']['transactionState']) && (strtolower($getTransactionResponse['soapResponse']['transactionState']) == 'successful') ) {
                                //checking if this is fraud
                                if(isset($getTransactionResponse['soapResponse']['fraud']['resultCode'])) {
                                    $definitionLookUpResult = Mage::getModel('payuMeaRedirectPaymentPage/standard' )->__doPayuVasFraudResponseDefinitionLookup($getTransactionResponse['soapResponse']['fraud']['resultCode']);

                                    if($definitionLookUpResult === false) {
                                        throw new Exception("No fraud definiton exist for returned value: ".$getTransactionResponse['soapResponse']['fraud']['resultCode']);
                                    }
                                    else {
                                        if(strtolower($definitionLookUpResult[0]) == 'accept') {
                                            $getTransactionResponse['FraudResultCodeDefinition'] = $definitionLookUpResult[0];
                                            $getTransactionResponse['FraudResultCodeDefinitionExplanation'] = $definitionLookUpResult[1];
                                            $this->__doSuccessTransaction($getTransactionResponse['soapResponse']);
                                        }
                                        elseif(strtolower($definitionLookUpResult[0]) == 'challenge') {
                                            $getTransactionResponse['FraudResultCodeDefinition'] = $definitionLookUpResult[0];
                                            $getTransactionResponse['FraudResultCodeDefinitionExplanation'] = $definitionLookUpResult[1];
                                            $this->__doSuccessTransaction($getTransactionResponse['soapResponse'],false);
                                        }
                                        else {
                                            $getTransactionResponse['FraudResultCodeDefinition'] = $definitionLookUpResult[0];
                                            $getTransactionResponse['FraudResultCodeDefinitionExplanation'] = $definitionLookUpResult[1];
                                            throw new Exception('Error for fraud lookup definition: '.$definitionLookUpResult[1]);
                                        }
                                    }
                                }
                                else {
                                    $transactionState = "paymentSuccessfull"; //funds reserved need to finalize in the admin box
                                    $this->__doSuccessTransaction($getTransactionResponse['soapResponse']);
                                }
                                return;
                            }
                            elseif(isset($getTransactionResponse['soapResponse']['transactionState']) && (strtolower($getTransactionResponse['soapResponse']['transactionState']) == 'awaiting_payment') ) {
                                $this->__doSuccessTransaction($getTransactionResponse['soapResponse'],false);
                                return;
                            }

                        }
                    }

                    //var_dump($getTransactionResponse);

                    $errorMessage = "Invalid Gateway Response";

                    if(!isset($transactionState)) {
                        $errorMessageAppend = "";
                        if(isset($getTransactionResponse['soapResponse']['displayMessage'])) {
                            $errorMessageAppend = "PayU Refererence: ".$payuReference.", Response: ".$getTransactionResponse['soapResponse']['displayMessage'];
                        }
                        //die($errorMessage);
                        $errorMessage = 'Payment has been declined. '.$errorMessageAppend;
                        $this->__doCancelFailedTransaction($errorMessage);
                    }

                }
                catch(Exception $e) {
                    throw new Mage_Core_Exception($e->getMessage());
                }
            }
            else {
                //throw new Mage_Core_Exception('Invalid response from PayU payment gateway');
            }
        }
        catch( Mage_Core_Exception $e ) {
            //exit;
            $errorMessage = $e->getMessage();
        }
        catch( Exception $e ) {
            Mage::logException($e);
        }
        $this->__doCancelFailedTransaction($errorMessage);
        $this->_redirect( 'checkout/cart' );
    }

    protected function __doSuccessTransaction($getTransactionResponse = array(), $doPayment = true)
    {
        try
        {
            $session = Mage::getSingleton( 'checkout/session' );

            // Adding comment to if response comes back succesful


            $amountBasket = Mage::getModel('payuMeaRedirectPaymentPage/standard' )->getNumberFormat(( $getTransactionResponse['paymentMethodsUsed']['amountInCents'] / 100));

            $comment = "";
            $comment .= "Basket Amount: ".$amountBasket."\r\n";
            $comment .= "Merchant Reference : ".$getTransactionResponse['merchantReference']."\r\n";
            $comment .= "PayU Reference: ".$getTransactionResponse['payUReference']."\r\n\r\n";
            $comment .= "PayU Payment Status: ". $getTransactionResponse["transactionState"]."\r\n\r\n";


            //if(isset($getTransactionResponse['paymentMethodsUsed']['gatewayReference']) ) {
            //$comment .= "Gateway Reference: ".$getTransactionResponse['paymentMethodsUsed']['gatewayReference']."\r\n";
            //}

            //$comment .= "Payment Method Details".$getTransactionResponse['paymentMethodsUsed']['gatewayReference']."\r\n";
            foreach($getTransactionResponse['paymentMethodsUsed'] as $key => $value) {
                $comment .= "&nbsp;&nbsp;&middot;&nbsp;".$key.": ".$value."\r\n";
            }


            if(isset($getTransactionResponse['fraud'])) {
                //$comment .= "\r\nFraud Result".$getTransactionResponse['paymentMethodsUsed']['gatewayReference']."\r\n";
                foreach($getTransactionResponse['fraud'] as $key => $value) {
                    $comment .= "&nbsp;&nbsp;&middot;&nbsp;".$key.": ".$value."\r\n";
                }
            }

            $order = Mage::getModel( 'sales/order' )->loadByIncrementId( $session->getLastRealOrderId() );
            if($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                if($order->hasInvoices() == true) {
                    //do nothing
                }
                else {

                    $realOrderId = $session->getLastRealOrderId();
					$doubleCheckRealOrderId = Mage::getSingleton('core/session')->getDoubleCheckRealOrderId();
					//In some cases there was double logging of the payments - we are setting a session var to make sure tha only one order is always logged
					if( $realOrderId != $doubleCheckRealOrderId ) {

                        $order->setState(
                            Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                            $this->_getPendingPaymentStatus(),
                            Mage::helper('payuMeaRedirectPaymentPage')->__($comment)
                        )->save();

                        // now we are setting a session variable to make sure the double logging does not occur
                        Mage::getSingleton('core/session')->setDoubleCheckRealOrderId( $realOrderId );
                        $doubleCheckRealOrderId = Mage::getSingleton('core/session')->getDoubleCheckRealOrderId();

                        if($doPayment === true) {
                            $payment = $order->getPayment();
                            //$payment->setAdditionalInformation( "SafePayRefNr", $this->getRequest()->getPost('SafePayRefNr') );
                            //$payment->setAdditionalInformation( "BankRefNr", $this->getRequest()->getPost('BankRefNr') );
                            $payment->save();

                            $invoice = $order->prepareInvoice();
                            $invoice->register()->capture();
                            Mage::getModel( 'core/resource_transaction' )
                                ->addObject( $invoice )
                                ->addObject( $invoice->getOrder() )
                                ->save();
                            //$invoice->sendEmail();

                            $message = Mage::helper( 'payuMeaRedirectPaymentPage' )->__( 'Notified customer about invoice #%s.', $invoice->getIncrementId() );
                            $comment = $order->sendNewOrderEmail()->addStatusHistoryComment( $message )
                                ->setIsCustomerNotified( true )
                                ->save();
                        }
                    }
				}
            }

            $session->unsProRealOrderId();
            $session->setQuoteId( $session->getProQuoteId( true ) );
            $session->setLastSuccessQuoteId( $session->getProSuccessQuoteId( true ) );

            $this->_redirect( 'checkout/onepage/success', array( '_secure' => true ) );
            return;
        }
        catch( Mage_Core_Exception $e )
        {
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
        }
        catch( Exception $e )
        {
            Mage::logException( $e );
        }
        $this->_redirect( 'checkout/cart' );
        return;
    }


    protected function __doCancelFailedTransaction($stringToLogOnOrderNotes = "")
    {
        // Get the user session
        $session = Mage::getSingleton( 'checkout/session' );
        $session->setQuoteId( $session->getProQuoteId( true ) );
        $session = $this->_getCheckout();

        if( $quoteId = $session->getProQuoteId() )
        {
            $quote = Mage::getModel( 'sales/quote' )->load( $quoteId );

            if( $quote->getId() )
            {
                $quote->setIsActive( true )->save();
                $session->setQuoteId( $quoteId );
            }
        }

        $order = Mage::getModel( 'sales/order' )->loadByIncrementId( $session->getLastRealOrderId() );

        if ($order->getState() != Mage_Sales_Model_Order::STATE_CANCELED) {
            $errorMessage = "Order has been cancelled.";
            if(!empty($stringToLogOnOrderNotes)){
                $errorMessage = $stringToLogOnOrderNotes;
            }
            $order->registerCancellation($errorMessage)->save();
        }
        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());

        //Return quote
        if ($quote->getId()) {
            $quote->setIsActive(1)
                ->setReservedOrderId(NULL)
                ->save();
            $session->replaceQuote($quote);
        }
        //Unset data
        $session->unsLastRealOrderId();

        // Cancel orderadmin    
        if( $order->getId() )
            $order->cancel()->save();

        if(isset($errorMessage)) {
            Mage::getSingleton('checkout/session')->addError($this->__($errorMessage));
        }

        return;
    }

    protected function __reloadTransactionOnInternalError($messageOnError = "", $deleteOrderOnError = false) {
        // Get the user session
        $session = Mage::getSingleton( 'checkout/session' );
        $session->setQuoteId( $session->getProQuoteId( true ) );
        $session = $this->_getCheckout();

        if( $quoteId = $session->getProQuoteId() )
        {
            $quote = Mage::getModel( 'sales/quote' )->load( $quoteId );

            if( $quote->getId() )
            {
                $quote->setIsActive( true )->save();
                $session->setQuoteId( $quoteId );
            }
        }

        $order = Mage::getModel( 'sales/order' )->loadByIncrementId( $session->getLastRealOrderId() );
        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());

        //Return quote
        if ($quote->getId()) {
            $quote->setIsActive(1)
                ->setReservedOrderId(NULL)
                ->save();
            $session->replaceQuote($quote);
        }
        //Unset data
        $session->unsLastRealOrderId();

        if($deleteOrderOnError === true) {
            try{
                Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId())->delete();
            }catch(Exception $e){
                //var_dump($e->getMessage());
                //die();
            }
        }

        if(!empty($messageOnError)) {
            Mage::getSingleton('checkout/session')->addError($this->__($messageOnError));
        }
        return;
    }
}

?>
