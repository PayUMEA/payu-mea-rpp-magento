<?php

/**
 * This file contains the class for doign PayuRedirectPaymentPage transactions
 * Date:
 * 
 * @version 1.0
 * 
 * 
 */

//Requiring the PayU base class
require_once('class.PayuBase.php');


class PayuSafeShopEnterprise extends PayuBase {
    
private $merchantSafeKey = null;
    private static $payuSafeShopUrlCurlUrlArray  = array('production' => 'https://secure.safeshop.co.za/s2s/SafePay.asp');
    private static $payuSafeShopUrlHtmlUrlArray  = array('production' => 'https://secure.SafeShop.co.za/SafePay/Lite/Index.asp');
    private $payuSafeShopCurlUrlToQuery = null;
    private $payuSafeShopFormUrlToQuery = null;
    /*
     private static $safeModeXmlConstructionArray = array(   'MerchantReference','MerchantOrderNr','Amount','CardHolderName','BuyerCreditCardNr','BuyerCreditCardExpireDate',
     
                                                            'BuyerCreditCardCVV2','BuyerCreditCardBudgetTerm','CurrencyCode',
                                                            'VatCost','VatShippingCost','ShipperCost','SURCharge','SafeTrack','Secure3D_XID',
                                                            'Secure3D_CAVV','AdditionalInfo1','AdditionalInfo2','CallCentre','TerminalID','MemberGUID');
    */
    private static $safeModeXmlConstructionArray = array(   'MerchantReference','MerchantOrderNr','Amount','CardHolderName','BuyerCreditCardNr','BuyerCreditCardExpireDate',
                                                            'BuyerCreditCardCVV2','BuyerCreditCardBudgetTerm','CurrencyCode',
                                                            'VatCost','VatShippingCost','ShipperCost','SURCharge',
                                                            'AdditionalInfo1','AdditionalInfo2','CallCentre','TerminalID');
    
    private static $safeModeHtmlFormConstructionArray = array(   'MerchantReference','MerchantOrderNr','Amount','CardHolderName','BuyerCreditCardNr','BuyerCreditCardExpireDate',
                                                            'BuyerCreditCardCVV2','BuyerCreditCardBudgetTerm','CurrencyCode',
                                                            'VatCost','VatShippingCost','ShipperCost','SURCharge','SafeTrack','Secure3D_XID',
                                                            'Secure3D_CAVV','AdditionalInfo1','AdditionalInfo2','CallCentre','TerminalID','MemberGUID');
    
    private $safeMode = false;
    
    public function __construct($optionsArray = array()) {
        
        $this->payuSafeShopCurlUrlToQuery = self::$payuSafeShopUrlCurlUrlArray['production'];
        $this->payuSafeShopFormUrlToQuery = self::$payuSafeShopUrlHtmlUrlArray['production'];
        
        //instatiate which ss url to query
        if(isset($payuSafeShopCurlUrlToQuery['test'])) {
            $this->payuSafeShopCurlUrlToQuery = self::$payuSafeShopUrlCurlUrlArray['production'];
            $this->payuSafeShopFormUrlToQuery = self::$payuSafeShopUrlHtmlUrlArray['production'];
        }
        
        if(isset($optionsArray['safeKey']) && (!empty($optionsArray['safeKey'])) ) {
            $this->merchantSafeKey = $optionsArray['safeKey'];
        }
        else {
            throw new exception("please specify a merchant safeKey");
        }
        
        $this->safeMode = true;
        if( isset($optionsArray['safeMode']) && ($optionsArray['safeMode'] === false) ) {
            $this->safeMode = false;            
        }
    }

    public function doAuthSettleTransaction($transactionDetailsArray = array(), $memberDetailsArray = array(), $basketDetailsArray = array() ) {
        // put in some exceptiion code here for emty details
        
        $transactionXml = '<?xml version="1.0" ?>'."\r\n";
        $transactionXml .= "<Safe>"."\r\n";
        $transactionXml .= $this->generateMerchantDetailsXml(array('SafeKey' => $this->merchantSafeKey));
        $transactionXml .= "<Transactions>"."\r\n";
        $transactionXml .= $this->generateTransactionDetailsXml($transactionDetailsArray,'Auth_Settle');
        
        // $thisPropertyXml = str_ireplace ( "</Agents>" , $agentsXml."</Agents>" , $thisPropertyXml );
        //$thisPropertyXml = str_ireplace ( '<Agents/>' , "<Agents>".$agentsXml."</Agents>" , $thisPropertyXml );
        
        if(!empty($basketDetailsArray)) {
            $basketLineItemXml = "";
            foreach($basketDetailsArray as $thisBasketLineItem) {
                $basketLineItemXml .= $this->generateBasketLineItemDetailsXml($thisBasketLineItem);            
            }
            $transactionXml = str_ireplace ( "</Auth_Settle>" , $basketLineItemXml."</Auth_Settle>" , $transactionXml );
        }
        
        $transactionXml .= "</Transactions>"."\r\n";
        $transactionXml .= "</Safe>"."\r\n"; 
        
        //var_dump($transactionXml);
        //die();
        
        return $this->sendTransactionToPayUViaXmlOverCurlPost($transactionXml);
    }

    // responible for generating the xml from transactionArray
    private function generateMerchantDetailsXml($merchantArray = array()) {
        return self::arrayToXml($merchantArray, new SimpleXMLElement('<Merchant/>'));                                    
    }
    
    // responible for generating the xml from transactionArray
    private function generateBasketLineItemDetailsXml($basketLineItemDetailArray = array()) {
        return self::arrayToXml($basketLineItemDetailArray, new SimpleXMLElement('<BasketLineItem/>'));                                    
    }
    
    // responible for generating the xml from transactionArray
    private function generateMemberDetailsXml($basketLineItemDetailArray = array()) {
        return self::arrayToXml($basketLineItemDetailArray, new SimpleXMLElement('<BasketLineItem/>'));                                    
    }
    
    // responible for generating the xml from transactionArray
    private function generateTransactionDetailsXml($transactionDetailsArray = array(), $typeTransactionXmlElement = "") {
        if($this->safeMode === true) {
            $notFilledInArray = array(); 
            $filledInArray = array(); 
            foreach(self::$safeModeXmlConstructionArray as $thisKey) {
                if(isset($transactionDetailsArray[$thisKey])) {
                    $filledInArray[$thisKey] = $transactionDetailsArray[$thisKey];
                }                    
                else {
                    $notFilledInArray[] = $thisKey;
                }
            }
            if(!empty($notFilledInArray)) {
                throw new Exception('Running in safe xml generation mode. the following values were not given or empty: '.implode(",",$notFilledInArray) );
            }
            else {
               $transactionDetailsArray = $filledInArray;
            }
        }
        
        if(isset($transactionDetailsArray['MemberGUID'])) {
            unset($transactionDetailsArray['MemberGUID']);
        }
        
        return self::arrayToXml($transactionDetailsArray, new SimpleXMLElement("<{$typeTransactionXmlElement}/>"));                            
    }
    

}

