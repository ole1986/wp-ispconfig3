<?php
/**
 * This should be in its own separate file.
 */

if(!class_exists('WC_Product_Simple')) return;

class WC_Product_Hour extends WC_Product_Simple {

    public function __construct( $product ) {

        $this->product_type = 'hour';

        parent::__construct( $product );
    }
}