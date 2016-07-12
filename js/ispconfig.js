/**
 * ISPConfig Frontend class
 * - used to check if a domain is avilable (but not guaranteed)
 * - and display ether domain or subdomain preview
 */
function ISPConfigClass() {
    var that = this;
    
    var domainMsgSelector = '#domainMessage';
    var subdomainTextSelector = '#domain_part';

    var $productList;
    
    
    var init = function(){
        // whenever the domain changed, check if its available using checkDomain
        jQuery('input[data-ispconfig-checkdomain]').change(function(){
            var dom = jQuery(this).val();
            that.checkDomain( dom );
        });
        
        jQuery('input[data-ispconfig-subdomain]').keyup(function(){
            jQuery(subdomainTextSelector).text( jQuery(this).val().toLowerCase() );
        });
        
        $productList = jQuery('select[data-ispconfig-selectproduct]');
        
        $productList.change(function(){
            that.selectProduct( jQuery(this).val() );
        });
        
        that.selectProduct( $productList.val() );
    };
    
    /**
     * Show either domain or domain part of the default domain for FREE offers
     */
    this.selectProduct = function(product_id){
        var $current = $productList.find('option[value="'+product_id+'"]');
        if($current.data('isfree')) {
            jQuery('#domain').hide();
            jQuery('#subdomain').show();
        } else {
            jQuery('#domain').show();
            jQuery('#subdomain').hide();
        }
    };
    
    /**
     * Callback function from checkDomain
     */
    this.checkDomainCallback = function(response){
        var msg = jQuery(domainMsgSelector);
        console.log(response);
        msg.removeClass('ispconfig-msg-error ispconfig-msg-success');
        if(response < 0) {
            msg.text('Die Verfügbarkeit der Domain kann nicht verifiziert werden');
        } else if (response == 0) {
            msg.text('Der Domainname ist bereits vergeben');
            msg.addClass('ispconfig-msg-error');
        } else {
            msg.text('Der Domainname ist verfügbar');
            msg.addClass('ispconfig-msg-success');
        }
        msg.show();
    };
    
    /**
     * Do an ajax request (which uses 'whois' in background) to verify if a domain has already been registered
     */
    this.checkDomain = function(domainName){
        // WP AJAX request defined in ispconfig-register.php
        ispconfig_whois(domainName, that.checkDomainCallback);
    };
    
    init();
}

jQuery(function(){
    var ISPConfig = new ISPConfigClass();
});
