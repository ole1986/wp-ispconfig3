<?php

if(!class_exists('WC_Product')) return;

abstract class WC_ISPConfigProduct extends WC_Product {
    abstract public function OnCheckout($checkout);
    abstract public function OnCheckoutValidate();
    abstract public function OnCheckoutSubmit($order_id, $item_key, $item);

    public function is_purchasable() {
        return true;
    }

    public function get_sold_individually($context = 'view') {
        return true;
    }

    public function get_price($context = 'view'){
        return $this->get_regular_price();
    }

    /**
     * Get the add to url used mainly in loops.
     *
     * @return string
     */
    public function add_to_cart_url() {
        $url = $this->is_purchasable() && $this->is_in_stock() ? remove_query_arg( 'added-to-cart', add_query_arg( 'add-to-cart', $this->id ) ) : get_permalink( $this->id );
        return $url;
    }

    /**
     * Get the add to cart button text.
     *
     * @return string
     */
    public function add_to_cart_text() {
        $text = $this->is_purchasable() && $this->is_in_stock() ? __( 'Add to cart', 'woocommerce' ) : __( 'Read more', 'woocommerce' );

        return apply_filters( 'woocommerce_product_add_to_cart_text', $text, $this );
    }
}