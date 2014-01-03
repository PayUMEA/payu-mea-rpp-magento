<?php

require_once(__DIR__.'/../../library.payu/classes/class.PayuRedirectPaymentPage.php');

class PayUMEA_RedirectPaymentPage_NotifyController extends Mage_Core_Controller_Front_Action
{
    protected $_order;
    public function getOrder() {
        return( $this->_order );
    }

    protected function _getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

	public function getQuote() {
        return $this->getCheckout()->getQuote();
    }

    public function getStandard() {
        return Mage::getSingleton('payuMeaRedirectPaymentPage/standard');
    }

	public function getConfig() {
        return $this->getStandard()->getConfig();
    }

    protected function _getPendingPaymentStatus() {
        return Mage::helper('payuMeaRedirectPaymentPage')->getPendingPaymentStatus();
    }

    public function responseAction() {      

		
		$postData = file_get_contents("php://input");

//        $postData = '<PaymentNotification Stage="false">
//  <MerchantReference>100004117</MerchantReference>
//  <TransactionType>PAYMENT</TransactionType>
//  <TransactionState>SUCCESSFUL</TransactionState>
//  <ResultCode>00</ResultCode>
//  <ResultMessage>Successful</ResultMessage>
//  <PayUReference>1167986976014</PayUReference>
//  <Basket>
//    <Description>Store Order Number:100004117</Description>
//    <AmountInCents>100</AmountInCents>
//    <CurrencyCode>ZAR</CurrencyCode>
//    <Products/>
//  </Basket>
//  <PaymentMethodsUsed>
//    <Eft BankName="ABSA" AmountInCents="100" Reference="CUMVSIUPFG" AccountNumber="4077920871" BranchNumber="632005" AccountType="Cheque" TimeLimit="168" Currency="ZAR"/>
//  </PaymentMethodsUsed>
//  <IpnExtraInfo>
//    <ResponseHash></ResponseHash>
//  </IpnExtraInfo>
//</PaymentNotification>';
		
		
		ob_start();
		echo "***RAW POST <br /><br />";
		var_dump($postData);
		print "<br /><br />";
		echo "***GET <br /><br />";
		var_dump($_GET);
		print "<br /><br />";
		echo "***POST <br /><br />";
		var_dump($_POST);
		$bufferOutput = ob_get_contents();
		ob_end_clean();
		
		$returnData = json_decode(json_encode(simplexml_load_string($postData)),true);
		
//		if(isset($returnData['PaymentMethodsUsed']['Eft'])) {
//			$orderId = $this->getRequest()->getParam('orderId');
            $orderId = $returnData['MerchantReference'];
			if(isset($orderId) && !empty($orderId) && is_numeric($orderId)) {
				$payuReference = $returnData['PayUReference'];				
			}			
//		}
		
		try
        {               
            if(isset($payuReference) && !empty($payuReference) && is_numeric($payuReference)) {
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

//                    $getTransactionResponse['soapResponse']['transactionState'] = 'SUCCESSFUL'; //used to test with, must not be activated for production

//					$validTransactionStateArray = array('successful');
                    $validTransactionStateArray = array('successful','over_payment');
					$validTransactionTypesArray = array('payment','reserve','credit');

					if(isset($getTransactionResponse['soapResponse']['resultCode']) && (strtolower($getTransactionResponse['soapResponse']['resultCode']) == '00'))  {                        
						if(isset($getTransactionResponse['soapResponse']['transactionState']) && (in_array ( strtolower($getTransactionResponse['soapResponse']['transactionState']) , $validTransactionStateArray )) )  {
							if(isset($getTransactionResponse['soapResponse']['transactionType']) && (in_array ( strtolower($getTransactionResponse['soapResponse']['transactionType']) , $validTransactionTypesArray )) )  {								
								
//								if(isset($getTransactionResponse['soapResponse']['transactionState']) && (strtolower($getTransactionResponse['soapResponse']['transactionState']) == 'successful') ) {
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
//								}
                            }            
                        }
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
			$errorMessage = $e->getMessage();			         
        }
        catch( Exception $e ) {
			Mage::logException($e);
        }		
		
		$to = Mage::getModel('payuMeaRedirectPaymentPage/standard' )->getConfigData('NotificationEmailAddress');	 
		$subject = "PayU transaction notification for order: ".$this->getRequest()->getParam('orderId');
		$orderUrl = Mage::getUrl( '', array( '_secure' => true ) );
		$orderUrl .= '/admin/sales_order/';
					
		//sent mail
		$message = '
					<html>
					<head>
						<title>PayU transaction notification for order: '.$this->getRequest()->getParam('orderId')	.', recieved on '.date('l jS \of F Y h:i:s A').'</title>
						<style type="text/css"> 
							body {
								font-size:12pt;	
								font family: arial, helvetica , sans-serif;
							}
						</style>
					</head>
					<body>
						<p>
							The following transaction notification was recieved from PayU for order:'.$this->getRequest()->getParam('orderId').' at '.date('l jS \of F Y h:i:s A').':
						</p>
						<p>
							Merchant Reference: '.$returnData['MerchantReference'].'<br />
							PayU Reference:'.$returnData['PayUReference'].'<br /><br />

							Transaction State: '.$returnData['TransactionState'].'<br />
							Transaction Type: '.$returnData['TransactionType'].'<br /><br />
						</p>
						';
        if (isset($returnData['PaymentMethodsUsed']) && is_array($returnData['PaymentMethodsUsed']) ) {
            foreach($returnData['PaymentMethodsUsed'] as $key => $value) {
                $message .= '<p>Payment Method Used: '.$key."<br />";

                foreach($value["@attributes"] as $key1 => $value1) {
                    $message .= "&nbsp;&nbsp;&middot;&nbsp;".$key1.": ".$value1."<br />";
                }
                $message .= '</p>';
            }
        }
					$message .= '
						<p>
							You will need to manually update order (if neccesary) <a href="'.$orderUrl.'">here</a> - '.$orderUrl.'
						</p>
						<p>
							Original IPN data sent by PayU: <br />
							'.htmlspecialchars($postData, ENT_QUOTES).'
							<br /><br />
							'.$bufferOutput.'
						</p>	  	
					</body>
					</html>
					';
//		var_dump($message);
		//die();
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

		// Mail it
//		mail($to, $subject, $message, $headers);
		mail($to, $subject, $message, $headers); die(); //The die() must be here
    }
	
	protected function __doSuccessTransaction($getTransactionResponse = array())
    {
		try
        {            
			$amountBasket = Mage::getModel('payuMeaRedirectPaymentPage/standard' )->getNumberFormat(( $getTransactionResponse['basket']['amountInCents'] / 100));
			$amountPaid = Mage::getModel('payuMeaRedirectPaymentPage/standard' )->getNumberFormat(( $getTransactionResponse['paymentMethodsUsed']['amountInCents'] / 100));
			
            $comment = "";
			$comment .= "-----PAYU IPN RECIEVED---<br />";		
			$comment .= "Basket Amount: ".$amountBasket."\r\n";			
			$comment .= "Merchant Reference : ".$getTransactionResponse['merchantReference']."\r\n";
			$comment .= "PayU Reference: ".$getTransactionResponse['payUReference']."\r\n\r\n";
            $comment .= "PayU Payment Status: ". $getTransactionResponse["transactionState"]."\r\n\r\n";						
             
            
//			if(isset($getTransactionResponse['paymentMethodsUsed']['gatewayReference']) ) {
//				$comment .= "Gateway Reference: ".$getTransactionResponse['paymentMethodsUsed']['gatewayReference']."\r\n";
//			}
//
//			$comment .= "Payment Method Details".$getTransactionResponse['paymentMethodsUsed']['gatewayReference']."\r\n";
			foreach($getTransactionResponse['paymentMethodsUsed'] as $key => $value) {
				$comment .= "&nbsp;&nbsp;&middot;&nbsp;".$key.": ".$value."\r\n";
			}
			
			$order = Mage::getModel( 'sales/order' )->loadByIncrementId( $getTransactionResponse['merchantReference'] );
			
			if($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)
            {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    $this->_getPendingPaymentStatus(),
                    Mage::helper('payuMeaRedirectPaymentPage')->__($comment)
                )->save();
                
				$payment = $order->getPayment(); 
				//$payment->setAdditionalInformation( "SafePayRefNr", $this->getRequest()->getPost('SafePayRefNr') );
				//$payment->setAdditionalInformation( "BankRefNr", $this->getRequest()->getPost('BankRefNr') );
				$payment->setAmount($amountPaid); 
				$payment->save();
			
                $invoice = $order->prepareInvoice();

                $invoice->register()->capture();
                Mage::getModel( 'core/resource_transaction' )
                ->addObject( $invoice )
                ->addObject( $invoice->getOrder() )
                ->save();
                $invoice->sendEmail();

                $message = Mage::helper( 'payuMeaRedirectPaymentPage' )->__( 'Notified customer about invoice #%s.', $invoice->getIncrementId() );
                $comment = $order->sendNewOrderEmail()->addStatusHistoryComment( $message )
                    ->setIsCustomerNotified( true )
                    ->save();
            }
			
			//$session->unsProRealOrderId();
			//$session->setQuoteId( $session->getProQuoteId( true ) );
			//$session->setLastSuccessQuoteId( $session->getProSuccessQuoteId( true ) );
		}        
        catch( Exception $e )
        {
            Mage::logException( $e );
		}		
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
	
	
 }

?>
