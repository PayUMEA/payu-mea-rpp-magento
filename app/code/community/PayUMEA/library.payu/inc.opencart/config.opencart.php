<?php


class PayuOpenCartConfig {
    
    public static function getConfig() {
        
        $payuOpenCartConfig = array();
        
        //RPP
        $payuOpenCartConfig['PayuRedirectPaymentPage']['safekey']  = '{8AA08872-1533-4CE5-BAED-B7E66E8B614D}';        
        $payuOpenCartConfig['PayuRedirectPaymentPage']['username'] = 'PayU RPP OpenCart';
        $payuOpenCartConfig['PayuRedirectPaymentPage']['password'] = 'Vnwm5f4b';
        $payuOpenCartConfig['PayuRedirectPaymentPage']['supportedCurrencies'] = 'ZAR';
        $payuOpenCartConfig['PayuRedirectPaymentPage']['defaultOrderNumberPrepend'] = 'OC_RPP_';   
        
        
        //SafeShop Pro Details
        $payuOpenCartConfig['PayuSafeshopPro']['safekey']  = '{E7831EC1-AA49-4ADB-893E-408C3683B633}';        
        $payuOpenCartConfig['PayuSafeshopPro']['supportedCurrencies'] = 'ZAR';
        $payuOpenCartConfig['PayuSafeshopPro']['defaultOrderNumberPrepend'] = 'OC_SSPRO_';   
        
        
        return $payuOpenCartConfig;        
    }
}