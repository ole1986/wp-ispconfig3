<?php

if(!class_exists('WC_Product_Simple')) return;

add_filter('woocommerce_cart_item_quantity', ['WC_Product_Webspace', 'Period'], 10, 3);
add_filter('woocommerce_update_cart_action_cart_updated', ['WC_Product_Webspace', 'CartUpdated']);
add_filter('woocommerce_add_cart_item', ['WC_Product_Webspace', 'AddToCart'], 10, 3 );

add_action( 'admin_footer', ['WC_Product_Webspace', 'jsRegister'] );
add_filter( 'product_type_selector', ['WC_Product_Webspace','register'] );


class WC_Product_Webspace extends WC_ISPConfigProduct {
    public static $DEFAULT_PERIOD = 'm';

    public static $OPTIONS;

    public $sold_individually = true;

    public function __construct( $product ) {
        self::$OPTIONS = ['m' => __('monthly', 'wp-ispconfig3'), 'y' => __('yearly', 'wp-ispconfig3') ];

        $this->product_type = 'webspace';
        parent::__construct( $product );
    }

    public static function register($types){
        $types[ 'webspace' ] = __( 'Webspace', 'wp-ispconfig3' );
	    return $types;
    }

    public static function jsRegister(){
	if ( 'product' != get_post_type() ) return;

	?><script type='text/javascript'>
		jQuery( document ).ready( function() {
            jQuery( '.options_group.pricing' ).addClass( 'show_if_webspace' ).show();
            jQuery( '.options_group.ispconfig' ).addClass( 'show_if_webspace' ).show();
            jQuery( '.inventory_options' ).addClass( 'show_if_webspace' ).show();
		});
	</script><?php
    }

    public function getISPConfigTemplateID(){
        return get_post_meta($this->get_id(), '_ispconfig_template_id', true);
    }

    public function OnCheckout($checkout){
        $templateID = $this->getISPConfigTemplateID();

        error_log("Template: $templateID");

        if($templateID >= 1 && $templateID <= 3) {   
            woocommerce_form_field( 'order_domain', [
            'type'              => 'text',
            'label'             => 'Ihre Wunschdomain',
            'placeholder'       => '',
            'custom_attributes' => ['data-ispconfig-checkdomain'=>'1']
            ], $checkout->get_value( 'order_domain' ));
        }
        
        echo '<div id="domainMessage" class="ispconfig-msg" style="display:none;"></div>';
        echo '<div><sup>Bitte beachten Sie das die Domainregistrierung innerhalb von 24 Stunden nach Zahlungseingang erfolgt</sup></div>';
    }

    public function OnCheckoutValidate(){
        $templateID = $this->getISPConfigTemplateID();
        
        // all products require a DOMAIN to be entered
        if($templateID >= 1 && $templateID <= 3) {
            try{
                $dom = IspconfigRegister::validateDomain( $_POST['order_domain'] );
                $available = IspconfigRegister::isDomainAvailable($dom);
                if($available == 0) {
                    wc_add_notice( __("The domain is not available", 'wp-ispconfig3'), 'error');
                } else if($available == -1) {
                    wc_add_notice( __("The domain might not be available", 'wp-ispconfig3'), 'notice');
                }
            } catch(Exception $e){
                wc_add_notice( $e->getMessage(), 'error');
            }
        }
    }

    public function OnCheckoutSubmit($order_id, $item_key, $item){
        if ( ! empty( $_POST['order_domain'] ) ) {
            update_post_meta( $order_id, 'Domain', sanitize_text_field( $_POST['order_domain'] ) );

            //error_log("### WC_Product_Webspace -> OnCheckoutSubmit($order_id) called");
            //error_log(print_r($this, true));

            $templateID = $this->getISPConfigTemplateID();
            // no ispconfig product found in order - so skip doing ispconfig related stuff
            if(empty($templateID)) return;

            if($item['quantity'] == 12) 
                update_post_meta($order_id, 'ispconfig_period', 'y');
            else
                update_post_meta($order_id, 'ispconfig_period', 'm');
        }
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

    public static function AddToCart($item, $item_key){
        if(get_class($item['data']) != 'WC_Product_Webspace') return $item;
        // empty cart when a webspace product is being added
        // ONLY ONE webspace product is allowed in cart
        WC()->cart->empty_cart();
        return $item; 
    }

    /**
     * Display a DropDown (per webspace product) for selecting the period (month / year / ...)
     * Can be customized in $OPTIONS property  
     */
    public static function Period($item_qty, $item_key, $item){
        if(get_class($item['data']) != 'WC_Product_Webspace') return;
        
        $period = ($item['quantity'] == 12)?'y':'m';
        
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
        }
        return $isUpdated;
    }
}