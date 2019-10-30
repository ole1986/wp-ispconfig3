/**
 * ISPConfig Frontend class
 */

function ISPConfigClass() 
{
    /**
     * Check if a domain is valid and available either through whois or ISPConfig API (dependent on the settings)
     * @param {string} domainName The domain name (E.g. yourdomain.net)
     */
    this.checkDomain = function (domainName) {
        // WP AJAX request defined in ispconfig-register.php
        return jQuery.post(ajaxurl, { action: 'ispconfig_whois', 'domain': domainName }, null, 'json');
    };
}

window['ISPConfig'] = new ISPConfigClass();