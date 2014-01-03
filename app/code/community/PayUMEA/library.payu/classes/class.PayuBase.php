<?php
/**
 * This file contains the base class doing Payu transactions. 
 * Within this file various common functionality is specified
 * Date:
 * 
 * @version 1.0
 * 
 * 
 */
abstract class PayuBase {
    
    private $logEnable = false;
    private $logLocation = "./../logs/";
    private $logLevel = 1;
    
    public $soapClientInstance;
    public $curlClientInstance;
    
    protected $payuUrlArray = array( 'staging' => 'https://staging.payu.co.za' , 'production' => 'https://secure.payu.co.za'  );
    protected $payuBaseUrlToUse = "";
    protected $soapWdslUrl = "";    
    protected $soapAuthHeader = "";
    protected $enableExtendedDebug = false;
    
    protected $merchantSoapUsername = "";
    protected $merchantSoapPassword = "";
    protected $soapApiVersion = "ONE_ZERO";
    
    public function __construct($constructorArray = array()) {
        
        if(isset($constructorArray['logEnable']) && ($constructorArray['logEnable'] === true) ) {
            $this->logEnable = true;            
        }        
        if(isset($constructorArray['logLocation']) && (!empty($constructorArray['logLocation'])) ) {        
            $this->logLocation = $constructorArray['logLocation'];            
        }
        else {
            $this->logLocation = dirname(__FILE__)."/".$this->logLocation;
        }
        
        if(isset($constructorArray['logLocation']) && (!empty($constructorArray['logLocation'])) && (is_numeric($constructorArray['logLocation'])) ) {        
            $this->logLevel = $constructorArray['logLocation'];            
        }
    }
    
    /**    
    *
    * Do the logging of various given strings and an optional instruction
    *
    * @param string $stringToLog This is the string to log to the log file
    * @param array $instructionToLog The instruction to log the string against e.g. a sop function called
    *
    * @return void
    */
    protected function log( $stringToLog = null, $instructionToLog = null ) {
        
        if($this->logEnable === true) {
            if(empty($stringToLog)) {
                throw new Exception("Please specify a value to log");
            }
            elseif(empty($this->logLocation)) {
                throw new Exception("Please specify a log file directory location");
            }
            else {
                if(!is_file($this->logLocation) && !is_dir($this->logLocation)) {
                    mkdir($this->logLocation,0777);
                }
                
                if(!file_exists($this->logLocation)) {
                    throw new Exception("Could not create the log file directory location:".$this->logLocation);                    
                }
            }
            
            $logFile = $this->logLocation."/payuRedirectPaymentPage.".date('Y-m-d').".log";
            
            $stringToLog = "'".date('Y-m-d H:i:s')."','".$stringToLog."'";
            if(!empty($instructionToLog)) {
                $stringToLog .= ",'".$instructionToLog."'";    
            }
            $stringToLog .= "\r\n";            
            
            file_put_contents($logFile, $stringToLog, FILE_APPEND | LOCK_EX);
        }        
    }
    
    /**    
    *
    * Do the curl call against the PayU API
    *
    * @param string $urlToQuery Url to do curl call against
    * @param array $xmlRequestString The xml string used in the body of the curl request
    *
    * @return array returns the xml
    */
    protected function doCurlCallToApi($urlToQuery = "", $xmlRequestString = "") {
        
        
        $ch = curl_init($urlToQuery);
        //curl_setopt($ch, CURLOPT_MUTE, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, "$xmlRequestString");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);      
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
        $xmlOutput = curl_exec($ch);
        $this->curlClientInstance = $ch;        
        curl_close($ch);
        
        ob_start();
        if(!simplexml_load_string($xmlOutput)) {
            // do nothing 
        }        
        $possibleErrorString = ob_get_contents();
        ob_end_clean();
        
        if (stripos($possibleErrorString, 'warning:') !== false) {
            throw new exception('Return is not valid XML. Return value:'.$xmlOutput);
        }
        else {
            $returnData = json_decode(json_encode(simplexml_load_string($xmlOutput)),true);
        }
        return $returnData;
    }
    
    
    /**    
    *
    * Do the soap call against the PayU API
    *
    * @param string soapFunctionToCall The Soap function the needs to be called
    * @param array soapDataArray The array containing the data to
    *
    * @return array Returns the soap result in array format
    */
    public function doSoapCallToApi( $soapFunctionToCall = null , $soapDataArray = array() ) {
        
        // A couple of validation business ruless before doing the soap call
        if(empty($soapDataArray)) {
            throw new Exception("Please provide data to be used on the soap call");
        }
        elseif(empty($soapFunctionToCall)) {
            throw new Exception("Please provide a soap function to call");
        }

        //Setting the soap header if not already set
        if(empty($this->soapAuthHeader)) {
            $this->setSoapHeader();
        }            
       
        //log an entry indicating that a soap call is about to happen
        $this->log("------------------   SOAP CALL TRANSACTION ABOUT TO START: ".$soapFunctionToCall."   -----------------------------\r\n");
                
        //Make new instance of the PHP Soap client
        $this->soapClientInstance = new SoapClient($this->soapWdslUrl, array("trace" => 1, "exception" => 0, 'cache_wsdl' => WSDL_CACHE_NONE )); 

        //Set the Headers of soap client. 
        $this->soapClientInstance->__setSoapHeaders($this->soapAuthHeader); 

        //Adding the api version to the soap data packet array
        $soapDataArray = array_merge($soapDataArray, array('Api' => $this->soapApiVersion ));
        
       
        //Do Soap call
        try{
            $soapCallResult = $this->soapClientInstance->$soapFunctionToCall($soapDataArray); 
            if(is_object($this->soapClientInstance)) {
                $this->log($this->soapClientInstance->__getLastRequestHeaders(), "SOAP CALL REQUEST HEADERS: ".$soapFunctionToCall);
                $this->log("SOAP CALL REQUEST HEADERS: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastRequestHeaders());
                $this->log("SOAP CALL REQUEST: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastRequest());        
                $this->log("SOAP CALL RESPONSE HEADERS: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastResponseHeaders());
                $this->log("SOAP CALL RESPONSE: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastResponse());        
            }
        }
        catch(Exception $e) {
            if(is_object($this->soapClientInstance)) {
                $this->log($this->soapClientInstance->__getLastRequestHeaders(), "SOAP CALL REQUEST HEADERS: ".$soapFunctionToCall);
                $this->log("SOAP CALL REQUEST HEADERS: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastRequestHeaders());
                $this->log("SOAP CALL REQUEST: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastRequest());        
                $this->log("SOAP CALL RESPONSE HEADERS: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastResponseHeaders());
                $this->log("SOAP CALL RESPONSE: ".$soapFunctionToCall, "\r\n".$this->soapClientInstance->__getLastResponse());        
            }
            throw new Exception($e->getMessage(),null,$e);
        }        
        // Decode the Soap Call Result for returning
        $returnData = json_decode(json_encode($soapCallResult),true);

        return $returnData;
    }
    
    
    /**    
     * Set the soap header string used to call in the Soap to PayU API
     */        
    private function setSoapHeader() {
        
        if(empty($this->merchantSoapUsername)) {
            throw new exception('Please specify a merchant username for soap trasaction');
        }
        elseif(empty($this->merchantSoapPassword)) {
            throw new exception('Please specify a merchant password for soap trasaction');
        }
        
        //Creating a soap xml
        $headerXml = '<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">';
        $headerXml .= '<wsse:UsernameToken wsu:Id="UsernameToken-9" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">';
        $headerXml .= '<wsse:Username>'.$this->merchantSoapUsername.'</wsse:Username>';
        $headerXml .= '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.$this->merchantSoapPassword.'</wsse:Password>';
        $headerXml .= '</wsse:UsernameToken>';
        $headerXml .= '</wsse:Security>';
        $headerbody = new SoapVar($headerXml, XSD_ANYXML, null, null, null);  
        
        $ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'; //Namespace of the WS.         
        $this->soapAuthHeader = new SOAPHeader($ns, 'Security', $headerbody, true);        
    }
    
    
    private static function arrayToXml($arrayToConverttoXml, $xml = false){
        if($xml === false){
            $xml = new SimpleXMLElement('<root/>');
        }
        foreach($arrayToConverttoXml as $key => $value){
            if(is_array($value)){
                throw new exception('Function cannot handle arrays of more than one level deep');
            }else{
                //var_dump($value);
                if($value == '<![CDATA[]]>') {
                    $value = null;
                }
                elseif(empty($value)) {
                    $value = null;
                }
                elseif($value == "") {
                    $value = null;
                }

                ob_start();
                $value = self::takeOutIllegalCharsFromStringThatBreakXml($value);
                if(!empty($value)) {                
                    $xml->addChild($key, $value);      
                }
                $possibleErrorString = ob_get_contents();
                ob_end_clean();
            }
        }

        ob_start();
        $xmlString = $xml->asXML();
        $possibleErrorString = ob_get_contents();
        ob_end_clean();

        $xmlString = str_ireplace ( '&lt;![CDATA[' , '<![CDATA[' , $xmlString );
        $xmlString = str_ireplace ( ']]&gt;' , ']]>' , $xmlString );
        
        $tempArray = explode('?>',$xmlString,2);                        
        $tempArray = explode("\n",$tempArray[1],2);
        $tempArray[1] = str_ireplace ( '><' , ">\r\n<" , $tempArray[1] );
        return $tempArray[1];
    }

    private static function takeOutIllegalCharsFromStringThatBreakXml($inString)
    {
       $normalizeSpecialChars = array(
                            'Å '=>'S', 'Å¡'=>'s', 'Ã'=>'Dj','Å½'=>'Z', 'Å¾'=>'z', 'Ã€'=>'A', 'Ã'=>'A', 'Ã‚'=>'A', 'Ãƒ'=>'A', 'Ã„'=>'A', 
                            'Ã…'=>'A', 'Ã†'=>'A', 'Ã‡'=>'C', 'Ãˆ'=>'E', 'Ã‰'=>'E', 'ÃŠ'=>'E', 'Ã‹'=>'E', 'ÃŒ'=>'I', 'Ã'=>'I', 'ÃŽ'=>'I', 
                            'Ã'=>'I', 'Ã‘'=>'N', 'Ã’'=>'O', 'Ã“'=>'O', 'Ã”'=>'O', 'Ã•'=>'O', 'Ã–'=>'O', 'Ã˜'=>'O', 'Ã™'=>'U', 'Ãš'=>'U', 
                            'Ã›'=>'U', 'Ãœ'=>'U', 'Ã'=>'Y', 'Ãž'=>'B', 'ÃŸ'=>'Ss','Ã '=>'a', 'Ã¡'=>'a', 'Ã¢'=>'a', 'Ã£'=>'a', 'Ã¤'=>'a', 
                            'Ã¥'=>'a', 'Ã¦'=>'a', 'Ã§'=>'c', 'Ã¨'=>'e', 'Ã©'=>'e', 'Ãª'=>'e', 'Ã«'=>'e', 'Ã¬'=>'i', 'Ã­'=>'i', 'Ã®'=>'i', 
                            'Ã¯'=>'i', 'Ã°'=>'o', 'Ã±'=>'n', 'Ã²'=>'o', 'Ã³'=>'o', 'Ã´'=>'o', 'Ãµ'=>'o', 'Ã¶'=>'o', 'Ã¸'=>'o', 'Ã¹'=>'u', 
                            'Ãº'=>'u', 'Ã»'=>'u', 'Ã½'=>'y', 'Ã½'=>'y', 'Ã¾'=>'b', 'Ã¿'=>'y', 'Æ’'=>'f'
                        );

        $normalizelHtmlChars = array(
                '&Aacute;'=>'A', '&Agrave;'=>'A', '&Acirc;'=>'A', '&Atilde;'=>'A', '&Aring;'=>'A', '&Auml;'=>'A', '&AElig;'=>'AE', '&Ccedil;'=>'C',
                '&Eacute;'=>'E', '&Egrave;'=>'E', '&Ecirc;'=>'E', '&Euml;'=>'E', '&Iacute;'=>'I', '&Igrave;'=>'I', '&Icirc;'=>'I', '&Iuml;'=>'I', '&ETH;'=>'Eth',
                '&Ntilde;'=>'N', '&Oacute;'=>'O', '&Ograve;'=>'O', '&Ocirc;'=>'O', '&Otilde;'=>'O', '&Ouml;'=>'O', '&Oslash;'=>'O',
                '&Uacute;'=>'U', '&Ugrave;'=>'U', '&Ucirc;'=>'U', '&Uuml;'=>'U', '&Yacute;'=>'Y',    
                '&aacute;'=>'a', '&agrave;'=>'a', '&acirc;'=>'a', '&atilde;'=>'a', '&aring;'=>'a', '&auml;'=>'a', '&aelig;'=>'ae', '&ccedil;'=>'c',
                '&eacute;'=>'e', '&egrave;'=>'e', '&ecirc;'=>'e', '&euml;'=>'e', '&iacute;'=>'i', '&igrave;'=>'i', '&icirc;'=>'i', '&iuml;'=>'i', '&eth;'=>'eth',
                '&ntilde;'=>'n', '&oacute;'=>'o', '&ograve;'=>'o', '&ocirc;'=>'o', '&otilde;'=>'o', '&ouml;'=>'o', '&oslash;'=>'o',
                '&uacute;'=>'u', '&ugrave;'=>'u', '&ucirc;'=>'u', '&uuml;'=>'u', '&yacute;'=>'y',            
                '&szlig;'=>'sz', '&thorn;'=>'thorn', '&yuml;'=>'y', ' &amp; '=>' and '
            );
        $outString = $inString;
        //$outString = trim(preg_replace('/[^\w\d_ -]/si', '', $inString));    
        //$outString = strtr($outString, $normalizeSpecialChars);
        //$outString = strtr($outString, $normalizeChars);
        $outString = str_ireplace ( array_keys($normalizelHtmlChars) , $normalizelHtmlChars , $outString );
        $outString = str_replace ( "\t" , " " , $outString );
        $outString = str_replace ( "\v" , "\r\n" , $outString );
        $outString = str_replace ( "Ã‚â€™" , "'" , $outString );
        $outString = str_replace ( "\â€™" , "'" , $outString );
        $outString = str_replace ( "â€™" , "'" , $outString );
        $outString = str_replace ( "Ã‚" , "A" , $outString );
        $outString = str_replace ( "Â’A" , "A" , $outString );
        $outString = str_replace ( "Â’" , "" , $outString );
        $outString = str_replace ( "ï¿½" , "" , $outString );    
        $outString = preg_replace("/[^\x9\xA\xD\x20-\x7F]/", "", $outString); 
        return $outString;    
    }
}

        