<?php
/**
 * This file contains the class for doign Payu Sahefshop Pro transactions
 * Date:
 * 
 * @version 1.0
 * 
 * 
 */

//Requiring the PayU base class
require_once('class.PayuBase.php');

Class PayuSafeShopPro extends PayuBase {
    
    private $merchantSafeKey = null;
    private static $payuSafeShopSafepayRefCheckCurlUrlArray  = array('production' => ' 	https://secure.safeshop.co.za/s2s/SafepayOrderDetail.asp');
    private static $payuSafeShopHtmlFormPostArray  = array('production' => 'https://secure.SafeShop.co.za/SafePay/Lite/Index.asp');
    private static $payuSafeShopMerchantRefCheckUrlArray  = array('production' => 'https://secure.safeshop.co.za/s2s/SafepayMerchant.asp');
    private $payuSafeShopSafepayRefCheckCurlUrlToQuery = null;
    private $payuSafeShopHtmlFormPostUrlToQuery = null;
    private $payuSafeShopMerchantRefCheckUrlToQuery = null;
    
    public function __construct($constructorArray = array()) {
        
        //instantiate parent
        parent::__construct($constructorArray);
        
        $this->payuSafeShopHtmlFormPostUrlToQuery = self::$payuSafeShopHtmlFormPostArray['production'];
        $this->payuSafeShopMerchantRefCheckUrlToQuery = self::$payuSafeShopMerchantRefCheckUrlArray['production'];
        $this->payuSafeShopSafepayRefCheckCurlUrlToQuery = self::$payuSafeShopSafepayRefCheckCurlUrlArray['production'];
        
        if(isset($constructorArray['production']) && ($constructorArray['production'] !== false) ) {
            $this->payuSafeShopHtmlFormPostUrlToQuery = self::$payuSafeShopUrlHtmlUrlArray['production'];
            $this->payuSafeShopSafepayCurlUrlToQuery = self::$payuSafeShopSafepayRefCurlUrlArray['production'];            
            $this->payuSafeShopMerchantRefUrlToQuery = self::$payuSafeShopMerchantRefUrlArray['production'];
        }
        
        if(isset($constructorArray['safeKey']) && (!empty($constructorArray['safeKey'])) ) {
            $this->merchantSafeKey = $constructorArray['safeKey'];
        }
        else {
            throw new exception("please specify a merchant safeKey");
        }
        
    }
    
    public function getFormHtmlData($transactionDetailsArray = array() ) {
        
        $transactionDetailsArray['SafeKey'] = $this->merchantSafeKey;        
        $formName = "frmPay_".rand(0,100);
        $htmlString = "";
        $htmlString .= '<form action="'.$this->payuSafeShopHtmlFormPostUrlToQuery.'" method="post" id="'.$formName.'" name="'.$formName.'">'."\r\n";
        foreach($transactionDetailsArray as $key => $value) {
            $htmlString .= '<input type="hidden" name="'.$key.'" value="'.$value.'">'."\r\n";
        }
        $htmlString .= '</form>'."\r\n";
        
        $returnArray = array('htmlString' => $htmlString, 'formName' => $formName);
        return $returnArray; 
        /*
         * This is how the form should look coming back
         * 
        <form action="https://secure.safeshop.co.za/SafePay/Lite/Index.asp" method="post" id=frmPay name=frmPay>
            <input type="hidden" name="SafeKey" value="{XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX}"> <!-- The Safe Key-->
            <input type="hidden" name="MerchantReferenceNumber" value="Strawberry_2005/03/29 09:32:57 AM"> <!-- Merchant Transaction Reference-->
            <input type="hidden" name="TransactionAmount" value="599">  <!-- Transaction Amount in Cents -->
            <input type="hidden" name="CurrencyCode" value="ZAR">  <!-- Transaction Amount in Cents -->
            <input type="hidden" name="SafeTrack" value=""> <!-- Optional - SafeTrack GUID -->
            <input type="hidden" name="ReceiptURL" value="http://strawberry.safeshop.co.za/ThankYou.asp"> <!-- Transaction Redirect Url-->
            <input type="hidden" name="FailURL" value="http://strawberry.safeshop.co.za/Failed.asp"> <!-- Transaction Failure Redirect Url-->
            <input type="hidden" name="TransactionType" value="Auth">  <!-- Transaction  Type (Auth, Auth_Settle) -->
        </form>
        */        
    }
    
    public function doTransactionResultByMerchantRefCurlCall($merchantReference = "" ) {
        
        $transactionXml = '<?xml version="1.0" ?>'."\r\n";
        $transactionXml .= "<SafeShop>"."\r\n";
        $transactionXml .= "<Store>"."\r\n";        
        $transactionXml .= "<SafeKey>".$this->merchantSafeKey."</SafeKey>"."\r\n";
        $transactionXml .= "</Store>"."\r\n";
        $transactionXml .= "<Transaction>"."\r\n";
        $transactionXml .= "<MerchantReference>".$merchantReference."</MerchantReference>"."\r\n";        
        $transactionXml .= "</Transaction>"."\r\n";
        $transactionXml .= "</SafeShop>"."\r\n"; 
        
        return $this->doCurlCallToApi($this->payuSafeShopMerchantRefUrlToQuery, $transactionXml);    
    }
    
    
    public function doTransactionResultBySafepayRefCurlCall($safepayReferenceReference = "" ) {
        
        $transactionXml = '<?xml version="1.0" ?>'."\r\n";
        $transactionXml .= "<SafeShop>"."\r\n";
        $transactionXml .= "<Store>"."\r\n";        
        $transactionXml .= "<SafeKey>".$this->merchantSafeKey."</SafeKey>"."\r\n";
        $transactionXml .= "</Store>"."\r\n";
        $transactionXml .= "<SafepayOrderDetail>"."\r\n";
        $transactionXml .= "<SafePayRefNr>".$safepayReferenceReference."</SafePayRefNr>"."\r\n";        
        $transactionXml .= "</SafepayOrderDetail>"."\r\n";
        $transactionXml .= "</SafeShop>"."\r\n"; 
        
        return $this->doCurlCallToApi($this->payuSafeShopSafepayCurlUrlToQuery, $transactionXml);    
    }
}
