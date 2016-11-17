<?php

if(!class_exists('WC_Product_Simple')) return;

add_filter('woocommerce_cart_item_quantity', ['WC_Product_Webspace', 'Period'], 10, 3);
add_filter('woocommerce_update_cart_action_cart_updated', ['WC_Product_Webspace', 'CartUpdated']);
add_filter('woocommerce_add_cart_item', ['WC_Product_Webspace', 'AddToCart'], 10, 3 );
add_action('woocommerce_checkout_order_processed', ['WC_Product_Webspace', 'Checkout']);
//add_action('woocommerce_after_cart_contents', ['WC_Product_Webspace', 'CartNotice']);

class WC_Product_Webspace extends WC_Product_Simple {
    public static $DEFAULT_PERIOD = 'm';

    public static $OPTIONS;

    public $sold_individually = true;

    public function __construct( $product ) {
        self::$OPTIONS = ['m' => __('monthly', 'wp-ispconfig3'), 'y' => __('yearly', 'wp-ispconfig3') ];

        $this->product_type = 'webspace';
        parent::__construct( $product );
    }

    public function getISPConfigTemplateID(){
        return get_post_meta($this->get_id(), '_ispconfig_template_id', true);
    }

    public function get_price_html($price = ''){
        $display_price         = $this->get_display_price();
        $display_regular_price = $this->get_display_price( $this->get_regular_price() );
        if ( $this->get_price() > 0 ) {
            if ( $this->is_on_sale() && $this->get_regular_price() ) {
                $price .= $this->get_price_html_from_to( $display_regular_price, $display_price ) . $this->get_price_suffix();
                $price = apply_filters( 'woocommerce_sale_price_html', $price, $this );
            } else {
                $price .= wc_price( $display_price ) . $this->get_price_suffix();
                $price = apply_filters( 'woocommerce_price_html', $price, $this );
            }
        } elseif ( $this->get_price() === '' ) {
            $price = apply_filters( 'woocommerce_empty_price_html', '', $this );
            return $price;
        } elseif ( $this->get_price() == 0 ) {
            if ( $this->is_on_sale() && $this->get_regular_price() ) {
                $price .= $this->get_price_html_from_to( $display_regular_price, __( 'Free!', 'woocommerce' ) );
                $price = apply_filters( 'woocommerce_free_sale_price_html', $price, $this );
                // skip the "per Month" suffix when its free
                return $price;
            } else {
                $price = '<span class="amount">' . __( 'Free!', 'woocommerce' ) . '</span>';
                $price = apply_filters( 'woocommerce_free_price_html', $price, $this );
                // skip the "per Month" suffix when its free
                return $price;
            }
        }
        // return the price shown ing "per Month" where ever the price is displayed
        return $price . '&nbsp;' . __('per month', 'wp-ispconfig3');;
    }

    public static function AddToCart($cart_data, $item_key){
        // make sure ONLY ONE webspace product is in the cart
        $items = WC()->cart->get_cart();
        if(count($items) > 0) {
            foreach($items as $key => $item) {
                if(get_class($item['data']) == get_called_class())
                    WC()->cart->remove_cart_item($key);
            }
        }
        //WC()->cart->empty_cart();
        // set the DEFAULT period to every new item
        WC()->session->set("period_{$item_key}", self::$DEFAULT_PERIOD);
        return $cart_data; 
    }

    /**
     * Display a DropDown (per webspace product) for selecting the period (month / year / ...)
     * Can be customized in $OPTIONS property  
     */
    public static function Period($item_qty, $item_key, $item){
        $period = WC()->session->get("period_{$item_key}");
        ?>
        <select style="width:70%;margin-right: 0.3em" name="period[<?php echo $item_key?>]" onchange="jQuery('input[name=\'update_cart\']').prop('disabled', false).trigger('click');">
        <?php foreach(self::$OPTIONS as $k => $v) { ?>
            <option value="<?php echo $k ?>" <?php echo ($period == $k)?'selected':'' ?> ><?php echo $v ?></option>
        <?php } ?>
        </select>
        <?php
        return "";
    }

    /*public static function CartNotice(){
        wc_clear_notices();
        $c = sizeof( WC()->cart->get_cart() );
        if($c > 1) {
            wc_add_notice("More then ONE webspace item is not fully supported", 'error'); 
        }
        wc_print_notices();
    }*/

    /**
     * when the cart gets updated - E.g. the selection has changed
     */
    public static function CartUpdated($isUpdated){
        
        if(!isset($_POST['period'])) return $isUpdated;

        foreach($_POST['period'] as $item_key => $v) {
            $qty = ($v == 'y')?12:1;
            // update the qty of the product
            WC()->cart->set_quantity( $item_key, $qty, false );
            // save the current period for the product into session using the cart item key
            WC()->session->set("period_{$item_key}", $v);
        }
        return $isUpdated;
    }

    /**
     * Used to add the 'ispconfig_period' meta info for further checks.
     * For example invoice generation
     *
     * This meta info is only set, when the product has an ispconfig template assigned 
     */
    public static function Checkout($order_id){
        error_log("### WC_Product_Webspace -> Checkout($order_id) called");

        // get all but actually interessted in only the first
        $items = WC()->cart->get_cart();

        if(count($items) <= 0) return;
        $item = each($items);

        $product = $item['value']['data'];
        $period = WC()->session->get("period_" . $item['key']);

        $templateID = $product->getISPConfigTemplateID();

        // no ispconfig product found in order - so skip doing ispconfig related stuff
        if(empty($templateID)) return;

        update_post_meta($order_id, 'ispconfig_period', $period);
        wc_clear_notices();
    }
}