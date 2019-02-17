/**
 * ISPConfig Admin class
 */
function ISPConfigAdminClass() {
    var $ = jQuery;
    var self = this;

    var jsonRequest = function (data, action) {
        if(!action) {
            alert('No ajax action defined');
            return;
        }
        $.extend(data, { action: action });
        return jQuery.post(ajaxurl, data, null, 'json');
    };

    this.WebsiteStatus = function(obj, status){
        var website_id = parseInt($(obj).data('id'));

        if (status == 'inactive' && !confirm("Change to status to inactive cause the website to be unavailable.\nDo you really want to continue?"))
            return;
        
        $(obj).hide();

        jsonRequest({ website_id: website_id, status: status }, 'ispconfig_website').always(function () { document.location.reload(true); });
    };

    var _constructor = function () {
        //initTabs();
    }();
}

jQuery(function () {
    var ISPConfigAdmin = window['ISPConfigAdmin'] = new ISPConfigAdminClass();
});