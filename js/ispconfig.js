/**
 * ISPConfig Frontend class
 */

function ISPConfigClass() 
{
    var self = this;
    /**
     * Check if a domain is valid and available either through whois or ISPConfig API (dependent on the settings)
     * @param {string} domainName The domain name (E.g. yourdomain.net)
     * @param {string} selector An optional jQuery selector to display the message
     * @returns {Promise}
     */
    this.checkDomain = function (domainName, selector) {
        // WP AJAX request defined in ispconfig-register.php
        var deferred = jQuery.post(ajaxurl, { action: 'ispconfig_whois', 'domain': domainName }, null, 'json');
        
        if (selector) {
            // when a selector is given, output the message
            deferred.done(function(resp) {
                var msg = jQuery(selector);
                msg.removeClass('ispconfig-msg-error ispconfig-msg-success');
                msg.addClass(resp.class);
                msg.text(resp.text);
                msg.show();
            });
        }

        return deferred;
    };

    jQuery(function() {
        console.log("ISPConfigClass -> constructor");

        jQuery('input[data-ispconfig-checkdomain]').change(function () {
            var dom = jQuery(this).val();
            self.checkDomain(dom, '#domainMessage');
        });
    });
}

window['ISPConfig'] = new ISPConfigClass();