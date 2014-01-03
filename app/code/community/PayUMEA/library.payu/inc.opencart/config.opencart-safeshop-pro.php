<?php


class PayuOpenCartConfig {
    
    public static function getConfig() {
        
        $payuOpenCartConfig = array();
        
        $payuOpenCartConfig['PayuRedirectPaymentPage']['safekey']  = '{E7831EC1-AA49-4ADB-893E-408C3683B633}';        
        //$payuOpenCartConfig['PayuRedirectPaymentPage']['username'] = 'PayU RPP OpenCart';
        //$payuOpenCartConfig['PayuRedirectPaymentPage']['password'] = 'Vnwm5f4b';
        $payuOpenCartConfig['PayuRedirectPaymentPage']['supportedCurrencies'] = 'ZAR';
        $payuOpenCartConfig['PayuRedirectPaymentPage']['defaultOrderNumberPrepend'] = 'OC_SSPRO_';    
        
        return $payuOpenCartConfig;        
    }
}