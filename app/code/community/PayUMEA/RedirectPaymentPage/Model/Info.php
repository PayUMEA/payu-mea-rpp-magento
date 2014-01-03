<?php	//root\app\code\local\Safeshop\Pro\Model\Info.php

class PayUMEA_RedirectPaymentPage_Model_Info
{
    const PAYMENT_STATUS = 'payment_status';
    const M_PAYMENT_ID   = 'm_payment_id';   

    protected $_paymentMap = array(
        self::PAYMENT_STATUS => 'payment_status',
        self::M_PAYMENT_ID   => 'm_payment_id',
        );

	protected $_paymentPublicMap = array(
        'email_address'
        );


    protected $_paymentMapFull = array();


	public function getPaymentInfo(Mage_Payment_Model_Info $payment, $labelValuesOnly = false)
    {
        $result = $this->_getFullInfo(array_values( $this->_paymentMap ), $payment, $labelValuesOnly);
        return ($result);
    }

	public function getPublicPaymentInfo(Mage_Payment_Model_Info $payment, $labelValuesOnly = false)
    {
        $result = $this->_getFullInfo($this->_paymentPublicMap, $payment, $labelValuesOnly);
        return ($result);;
    }

    public function importToPayment( $from, Mage_Payment_Model_Info $payment)
    {
        Varien_Object_Mapper::accumulateByMap( $from, array($payment, 'setAdditionalInformation'), $this->_paymentMap);
    }

    public function &exportFromPayment( Mage_Payment_Model_Info $payment, $to, array $map = null)
    {
        Varien_Object_Mapper::accumulateByMap( array( $payment, 'getAdditionalInformation'), $to, $map ? $map : array_flip( $this->_paymentMap));
        
        return( $to );
    }

	protected function _getFullInfo(array $keys, Mage_Payment_Model_Info $payment, $labelValuesOnly)
    {
        $result = array();
        
        foreach( $keys as $key )
        {
            if( !isset( $this->_paymentMapFull[$key] ) )
                $this->_paymentMapFull[$key] = array();
            
            if( !isset( $this->_paymentMapFull[$key]['label'] ) )
            {
                if( !$payment->hasAdditionalInformation( $key ) )
                {
                    $this->_paymentMapFull[$key]['label'] = false;
                    $this->_paymentMapFull[$key]['value'] = false;
                }
                else
                {
                    $value = $payment->getAdditionalInformation( $key );
                    $this->_paymentMapFull[$key]['label'] = $this->_getLabel( $key );
                    $this->_paymentMapFull[$key]['value'] = $this->_getValue( $value, $key );
                }
            }
            
            if( !empty( $this->_paymentMapFull[$key]['value']))
            {
                if($labelValuesOnly)
                    $result[$this->_paymentMapFull[$key]['label']] = $this->_paymentMapFull[$key]['value'];
                else
                    $result[$key] = $this->_paymentMapFull[$key];
            }
        }
        
        return($result);
    }
    
}

?>