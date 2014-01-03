<?php	//root\app\code\local\Safeshop\Pro\Model\Standard.php

class PayUMEA_RedirectPaymentPage_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
	protected $_code = 'payuMeaRedirectPaymentPage';
	protected $_formBlockType = 'payuMeaRedirectPaymentPage/form';
	protected $_infoBlockType = 'payuMeaRedirectPaymentPage/payment_info';
	protected $_order;
	protected $_isGateway              = true;
	protected $_canAuthorize           = true;
	protected $_canCapture             = true;
	protected $_canCapturePartial      = false;
	protected $_canRefund              = false;
	protected $_canVoid                = true;
	protected $_canUseInternal         = true;
	protected $_canUseCheckout         = true;
	protected $_canUseForMultishipping = true;
	protected $_canSaveCc			   = false;


	public function getCheckout()
	{
		return Mage::getSingleton('checkout/session');
	}


	public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }


	public function getConfig()
    {
        return Mage::getSingleton('payuMeaRedirectPaymentPage/config');
    }
	
	public function getReturnUrl()
	{
		return Mage::getUrl( 'payuMeaRedirectPaymentPage/redirect/response', array( '_secure' => true ) );
	}	
	
	public function getCancelUrl()
	{
		return Mage::getUrl( 'payuMeaRedirectPaymentPage/redirect/cancel', array( '_secure' => true ) );
	}
	
	public function getNotificationUrl()
	{
		return Mage::getUrl( 'payuMeaRedirectPaymentPage/notify/response', array( '_secure' => true ) );
	}


	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getUrl( 'payuMeaRedirectPaymentPage/redirect/redirect/', array('_secure' => true));
	}

	public function getNotificationUrlss()
	{
		return Mage::getUrl( 'payuMeaRedirectPaymentPage/notify/response', array( '_secure' => true ) );
	}
	
	public function getRealOrderId()
    {
        return Mage::getSingleton('checkout/session')->getLastRealOrderId();
    }


	public function getNumberFormat($number)
    {
        return number_format($number,2,'.','');
    }

	public function getTotalAmount($order)
    {
		if( $this->getConfigData('use_store_currency'))
            $price = $this->getNumberFormat($order->getGrandTotal());
    	else
        	$price = $this->getNumberFormat($order->getBaseGrandTotal());
		return $price;
	}

    
    public function getGetTransactionValueArray()
	{
           
        $getTransactionSoapDataArray = array();
        $getTransactionSoapDataArray['Safekey'] = $this->getConfigData('SafeKey');
        $getTransactionSoapDataArray['TransactionType'] = $this->getConfigData('TransactionType');
    
		$environment = 'staging';
		if($this->getConfigData('Environment') != 1) {
			$environment = 'production';
		}
        
	
        $configArray = array('username' => $this->getConfigData('SoapUsername'),
                             'password' => $this->getConfigData('SoapPassword'),
                             'environment' => $environment);
        $returnArray = array('config' => $configArray, 'getTransactionArray' => $getTransactionSoapDataArray);
        return($returnArray);
    }

	public function getSetTransactionValueArray()
	{
        $orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
		
		$customerDetails = Mage::getSingleton('customer/session')->getCustomer();
		
		//$quote = Mage::getSingleton('checkout/session')->getQuote();
		//$billingAddress = $quote->getBillingAddress();
		//$country = $billingAddress->getCountryId();
		//$city = $billingAddress->getCity();
		//$zipcode = $billingAddress->getPostcode();
		
		
        foreach($order->getAllItems() as $items)
        {
			$totalPrice = $this->getNumberFormat($items->getQtyOrdered() * $items->getPrice());
		}
        
        $MerchantReference = $this->getRealOrderId();
        
        $setTransactionSoapDataArray = array();
        $setTransactionSoapDataArray['Safekey'] = $this->getConfigData('SafeKey');
        $setTransactionSoapDataArray['TransactionType'] = $this->getConfigData('TransactionType');
        
        // Creating Basket Array
        $basketArray = array();
        $basketArray['amountInCents'] =  ($this->getTotalAmount($order) * 100);
        $basketArray['description'] =  $this->getConfigData('InvoiceDescriptionPrepend').$MerchantReference;
        $basketArray['currencyCode'] = $this->getConfigData('BillingCurrency');
        $setTransactionSoapDataArray = array_merge($setTransactionSoapDataArray, array('Basket' => $basketArray ));
        $basketArray = null; unset($basketArray);
        
        $additionalInformationArray = array();
        $additionalInformationArray['supportedPaymentMethods'] = $this->getConfigData('PaymentMethods');
        
//		$additionalInformationArray['notificationUrl'] = $this->getNotificationUrl()."?orderId=".$MerchantReference;
        $additionalInformationArray['notificationUrl'] = $this->getNotificationUrl();
        $additionalInformationArray['returnUrl'] = $this->getReturnUrl()."?orderId=".$MerchantReference;
        $additionalInformationArray['cancelUrl'] = $this->getCancelUrl()."?orderId=".$MerchantReference;		
		
        $additionalInformationArray['merchantReference'] = $MerchantReference;
		
        $setTransactionSoapDataArray = array_merge($setTransactionSoapDataArray, array('AdditionalInformation' => $additionalInformationArray ));
        $additionalInformationArray = null; unset($additionalInformationArray);
        
		$emailAddress = '';
		$setTransactionSoapDataArray['Customer'] = array();		
		
        if($order->getCustomerId() === NULL) { 
			$setTransactionSoapDataArray['Customer']['firstName'] =  $order->getBillingAddress()->getFirstname();
            $setTransactionSoapDataArray['Customer']['lastName'] =  $order->getBillingAddress()->getLastname();
            $setTransactionSoapDataArray['Customer']['email'] =  $order->getBillingAddress()->getEmail();
            $setTransactionSoapDataArray['Customer']['mobile'] =  (int) $order->getBillingAddress()->getTelephone();	
			
			$setTransactionSoapDataArray['Customer']['address'] =  $order->getBillingAddress()->getStreet();
			$setTransactionSoapDataArray['Customer']['city'] =  $order->getBillingAddress()->getCity();	
			/*
			if ($customer->getDefaultBillingAddress()->getCountry() == 'ZA'){
				$setTransactionSoapDataArray['Customer']['countryCode'] = '27';
			}else{
				//$setTransactionSoapDataArray['Customer']['countryCode'] =  $customer->getDefaultBillingAddress()->getCountry();
			}
			*/
			$setTransactionSoapDataArray['customer']['countryName'] =  $order->getBillingAddress()->getCountryModel()->getName();             
            $setTransactionSoapDataArray['customer']['known'] =  0;			 
			$setTransactionSoapDataArray['Customer']['ip'] = $_SERVER['REMOTE_ADDR'];
        } 
         else { 
		 
			 $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
             $emailAddress = $customer->getEmail();
             if ($customer->getDefaultBillingAddress()->getEmail() != '') {
                 $emailAddress = $customer->getDefaultBillingAddress()->getEmail();
             }

            $setTransactionSoapDataArray['Customer']['firstName'] =  $customer->getDefaultBillingAddress()->getFirstname();
            $setTransactionSoapDataArray['Customer']['lastName'] =  $customer->getDefaultBillingAddress()->getLastname();
            $setTransactionSoapDataArray['Customer']['email'] =  $emailAddress;
			$setTransactionSoapDataArray['Customer']['mobile'] =  (int) $customer->getDefaultBillingAddress()->getTelephone();		
			
            $setTransactionSoapDataArray['Customer']['address'] =  implode(' ',$customer->getDefaultBillingAddress()->getStreet());			
            $setTransactionSoapDataArray['Customer']['city'] =  $customer->getDefaultBillingAddress()->getCity();      
			if ($customer->getDefaultBillingAddress()->getCountry() == 'ZA'){
				$setTransactionSoapDataArray['Customer']['countryCode'] = '27';
			}else{
				//$setTransactionSoapDataArray['Customer']['countryCode'] =  $customer->getDefaultBillingAddress()->getCountry();
			}			
            
			$setTransactionSoapDataArray['Customer']['countryName'] =  $customer->getDefaultBillingAddress()->getCountryModel()->getName();
			$setTransactionSoapDataArray['Customer']['known'] =  0;
			$setTransactionSoapDataArray['Customer']['ip'] = $_SERVER['REMOTE_ADDR'];     
			
			$setTransactionSoapDataArray['Customer']['merchantUserId'] =  $order->getCustomerId();
			$setTransactionSoapDataArray['Customer']['postCode'] =  $customer->getDefaultBillingAddress()->getPostcode(); 			
        } 
		
		
		$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingAddress1'] = implode(" ", $order->getShippingAddress()->getStreet()); 
		$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingAddressCity'] = $order->getShippingAddress()->getCity();
		$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingEmail'] = $order->getBillingAddress()->getEmail();
		//$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingEmail'] = $order->getShippingAddress()->getEmail();
		if ($order->getShippingAddress()->getCountry() == 'ZA'){
			//$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingCountryCode'] = '27';
			$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingCountryCode'] = 'ZAR';
		}else{
			//$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingCountryCode'] = $order->getShippingAddress()->getCountry(); 
		}
		$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingStateCode'] = $order->getShippingAddress()->getRegion();
		$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingFirstName'] = $order->getShippingAddress()->getFirstname();
		$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingLastName'] = $order->getShippingAddress()->getLastname();
		$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingFax'] = null;
		$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingMethod'] = 'P';
		//$setTransactionSoapDataArray['Basket']['shippingDetails']['shippingPostCode'] = $order->getShippingAddress()>getPostcode();		
		
		$i = 0;
		$cartItems = $order->getAllItems();
		foreach ($cartItems as $item) {
			//$itemPriceInCents = ($item->getFinalPrice()*100);
			//$itemPriceInCents = ((Mage::helper('core')->currency($item->getFinalPrice(),true,false)*100)*100);
			//$itemPriceInCents = ($item->getPrice()*100);
			
			$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['amount'] = ($item->getPrice()*100);
			$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['costAmount'] = ($item->getPrice()*100);
			
			$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['description'] = $item->getDescription();
			if(strlen($setTransactionSoapDataArray['Basket']['productLineItem'][$i]['description']) > 26) {
				$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['description'] = substr($doTransactionArray['Basket']['productLineItem']['description'],0,22);
			}
			
			//$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['giftMessage'] = '';
			$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['productCode'] = $item->getSku();
			
			//$itemQuantity = $item->getQty();
			//$doTransactionArray['Basket']['productLineItem'][$i]['quantity'] = ($itemQuantity*10000);
			$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['quantity'] = $item->getQty();
			$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['recipientAddress1'] = implode(' ',$order->getShippingAddress()->getStreet()); 
			$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['recipientCity'] = $order->getShippingAddress()->getCity();
			if ($order->getShippingAddress()->getCountry() == 'ZA'){
				$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['countryCode'] = '27';
			}else{
				//$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['countryCode'] = $order->getShippingAddress()->getCountry();
			}
			$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['postalCode'] = $order->getShippingAddress()->getPostcode();					
			$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['firstName'] = $order->getBillingAddress()->getFirstname();
			$setTransactionSoapDataArray['Basket']['productLineItem'][$i]['lastName'] = $order->getBillingAddress()->getLastname();
			$i++;

		}	
		
		$setTransactionSoapDataArray['Fraud'] = array();
		$setTransactionSoapDataArray['Fraud']['checkFraudOverride'] = false;
		$setTransactionSoapDataArray['Fraud']['merchantWebSite'] = Mage::getUrl( '', array('_secure' => true));		
		
		$environment = 'staging';
		if($this->getConfigData('Environment') != 1) {
			$environment = 'production';
		}
        
        $configArray = array('username' => $this->getConfigData('SoapUsername'),
                             'password' => $this->getConfigData('SoapPassword'),
                             'environment' => $environment);
        
        $returnArray = array('config' => $configArray, 'setTransactionArray' => $setTransactionSoapDataArray);
        
		return($returnArray);
	}


    public function initialize($paymentAction,$stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }
	
	public function __doPayuVasFraudResponseDefinitionLookup($lookUpvalue = null) {
        $fraudArray = array();
        $fraudArray["V001"] = array("Error","Invalid Merchant ID");
        $fraudArray["V002"] = array("Error","Invalid XML Message");
        $fraudArray["V003"] = array("Error","General System Error");
        $fraudArray["V004"] = array("Error","IP not allowed");
        $fraudArray["V006"] = array("Error","VAS ID not enabled for Merchant ID");
        $fraudArray["V007"] = array("Error","Invalid VAS ID");
        $fraudArray["V008"] = array("Error","VAS Provider Down");
        $fraudArray["V009"] = array("Error","VAS CustomerID Invalid");
        $fraudArray["V010"] = array("Error","Unsupported Transaction Type");
        $fraudArray["V011"] = array("Error","Invalid Amount");
        $fraudArray["V012"] = array("Error","VAS Duplicate Reference");
        $fraudArray["V013"] = array("Error","Transaction not found");
        $fraudArray["V014"] = array("Error","VAS Provider System Error");
        $fraudArray["V020"] = array("Error","VAS Provider Bill Issuer not supported");
        $fraudArray["V021"] = array("Error","RSA ID number does not match VAS account");
        $fraudArray["V022"] = array("Error","Voucher invalid");
        $fraudArray["V023"] = array("Error","Meter is blocked");
        $fraudArray["V030"] = array("Accept","Transaction accepted");
        $fraudArray["V031"] = array("Accept","An attribute associated with an Order matched a pre-configured 'Always Accept' rule.");
        $fraudArray["V032"] = array("Deny","The card number appeared in a bank or card association negative file database.");
        $fraudArray["V033"] = array("Deny","An attribute associated with an Order matched a pre-configured 'Always Deny' rule.");
        $fraudArray["V034"] = array("Challenge","A combination of customized rules and neural-based fraud assessments has determined the card usage is suspicious and possibly fraudulent.");
        $fraudArray["V035"] = array("Challenge","A customized rule in the ReDShield Velocity Rules Engine returned a CHALLENGE response.");
        $fraudArray["V036"] = array("Deny","A combination of customized rules and neural-based fraud assessments has determined the card usage is suspicious and possibly fraudulent and the card number appeared in a Retail Decisions card database.");
        $fraudArray["V037"] = array("Challenge","A combination of customized rules and neural-based fraud assessments has determined the card usage is questionable and possibly fraudulent. The overall ReDShield assessment has fallen into a \"gray area\", as defined by Retail Decisions and the Client.");
        $fraudArray["V038"] = array("Deny","The card number associated with the Order was found in a Retail Decisions card database.");
        $fraudArray["V039"] = array("Deny","Velocity or Rules Threshold Violation ? An attribute associated with an Order has exceeded a preconfigured rules threshold.");
        $fraudArray["V040"] = array("Deny","Tumbling and/or Swapping Pattern Detected ? The ReDShield Tumbling and Swapping engine detected an unusual usage pattern in the card number, expiration date, or customer email address associated with a transaction.");
        $fraudArray["V041"] = array("Deny","The transaction has been flagged in a screening database.");
        $fraudArray["V042"] = array("Error","An internal ReDShield error has occurred. Contact ReD Support");
        $fraudArray["V043"] = array("Error","The format of a particular field is invalid or a required input field is missing. Please check your transaction string");
        $fraudArray["V050"] = array("Error","Fraud: Invalid Card Type");
        $fraudArray["V051"] = array("Error","Fraud: Invalid Merchant ID");
        $fraudArray["V052"] = array("Error","Fraud: Invalid Expiration Date");
        $fraudArray["V053"] = array("Error","Fraud: Invalid Country");
        $fraudArray["V054"] = array("Error","Fraud: Invalid Currency");
        $fraudArray["V055"] = array("Error","Fraud: Invalid Card Number");
        $fraudArray["V056"] = array("Error","Fraud: Invalid UserID, must be less than 17");
        $fraudArray["V057"] = array("Error","Fraud: Invalid CustomerID, must be less than 17");
        $fraudArray["V058"] = array("Error","Fraud: Invalid Customer Birth Date");
        $fraudArray["V059"] = array("Error","Fraud: Invalid Customer Email Address");
        $fraudArray["V060"] = array("Error","Fraud: Invalid Customer Phone Number");
        $fraudArray["V061"] = array("Error","Fraud: Invalid Customer First Name");
        $fraudArray["V062"] = array("Error","Fraud: Invalid Ship Method Code");
        $fraudArray["V063"] = array("Error","Fraud: Invalid Item Product Code");
        $fraudArray["V064"] = array("Error","Fraud: Invalid Item Description");
        $fraudArray["V065"] = array("Error","Fraud: Invalid Item Cost Amount");
        $fraudArray["V066"] = array("Error","Fraud: Invalid Item Quantity");
        $fraudArray["V067"] = array("Error","Fraud: Invalid Item Amount");
        $fraudArray["V068"] = array("Deny","Case manager rejected transaction - Customer Request");
        $fraudArray["V069"] = array("Deny","Case manager rejected transaction - Confirmed Fraudulent");
        $fraudArray["V070"] = array("Deny","Case manager rejected transaction - No Customer Response");
        $fraudArray["V071"] = array("Deny","Case manager rejected transaction - CCC Re Cancel");
        $fraudArray["V072"] = array("Deny","Case manager rejected transaction - Inadequate Verification");
        $fraudArray["V073"] = array("Error","Reserved");
        $fraudArray["V074"] = array("Accept","Case manager approved transaction");
        $fraudArray["V075"] = array("Error","Case manager delayed transaction case management");
        $fraudArray["XV030"] = array("Deny","Fraud Recommend Deny");
        $fraudArray["XV031"] = array("Challenge","Fraud Recommend Challenge");            
        
        $returnValue = false;
        if(isset($fraudArray[$lookUpvalue])) {
            $returnValue = $fraudArray[$lookUpvalue];
        }
        
        return $returnValue;
    }
}

