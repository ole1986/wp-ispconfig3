<?php

if(!class_exists('WC_Product')) return;

abstract class WC_ISPConfigProduct extends WC_Product {
    abstract public function OnCheckout($checkout);
    abstract public function OnCheckoutValidate();
    abstract public function OnCheckoutSubmit($order_id, $item_key, $item);
}