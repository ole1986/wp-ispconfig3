<?php
add_filter('woocommerce_product_data_tabs', ['WC_Product_Hour','hour_product_data_tab']);

add_action('admin_footer', ['WC_Product_Hour', 'jsRegister']);
add_filter('product_type_selector', ['WC_Product_Hour','register']);

class WC_Product_Hour extends WC_Product
{

    public function __construct( $product ) 
    {
        $this->product_type = 'hour';
        parent::__construct($product);
    }

    public static function register($types)
    {
        // Key should be exactly the same as in the class product_type parameter
        $types[ 'hour' ] = __('Working hours', 'wp-ispconfig3');
        return $types;
    }

    public static function jsRegister()
    {
        global $product_object;
        ?>
        <script type='text/javascript'>
            jQuery( document ).ready( function() {
                jQuery( '.options_group.pricing' ).addClass( 'show_if_hour' ).show();
                <?php if($product_object instanceof self) : ?>
                jQuery('.general_options').show();
                jQuery('.general_options > a').trigger('click');
                <?php endif; ?>
            });
        </script>
        <?php
    }

    public static function hour_product_data_tab($product_data_tabs)
    {
        $product_data_tabs['general']['class'][] = 'show_if_hour';
        $product_data_tabs['linked_product']['class'][] = 'hide_if_hour';
        $product_data_tabs['attribute']['class'][] = 'hide_if_hour';
        $product_data_tabs['advanced']['class'][] = 'hide_if_hour';
        $product_data_tabs['shipping']['class'][] = 'hide_if_hour';
        return $product_data_tabs;
    }
}