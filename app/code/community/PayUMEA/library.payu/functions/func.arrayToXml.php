<?php
function array2xml($array, $xml = false){

    if($xml === false){
        $xml = new SimpleXMLElement('<?xml version=\'1.0\' encoding=\'utf-8\'?><'.key($array).'/>');
        $array = $array[key($array)];
    }
    foreach($array as $key => $value){
        if(is_array($value)){
            $this->array2xml($value, $xml->addChild($key));
        }else{
            $xml->addChild($key, $value);
        }
    }
    return $xml->asXML();
}

