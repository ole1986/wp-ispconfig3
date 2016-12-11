<?php
/**
 * This should be in its own separate file.
 */

if(!class_exists('WC_Product_Simple')) return;

add_action( 'admin_footer', ['WC_Product_Hour', 'jsRegister'] );
add_filter( 'product_type_selector', ['WC_Product_Hour','register'] );

class WC_Product_Hour extends WC_Product_Simple {

    public function __construct( $product ) {

        $this->product_type = 'hour';

        parent::__construct( $product );
    }

    public static function register($types){
        // Key should be exactly the same as in the class product_type parameter
	    $types[ 'hour' ] = __( 'Working hours', 'wp-ispconfig3' );
        return $types;
    }

    public static function jsRegister(){
        if ( 'product' != get_post_type() ) return;

        ?><script type='text/javascript'>
            jQuery( document ).ready( function() {
                jQuery( '.options_group.pricing' ).addClass( 'show_if_hour' ).show();
            });
        </script><?php
    }
}