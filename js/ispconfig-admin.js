/**
 * ISPConfig Admin class
 */
function ISPConfigAdminClass() {
    var $ = jQuery;
    var self = this;

    var jsonRequest = function (data) {
        $.extend(data, { action: 'ispconfig_backend' });
        return jQuery.post(ajaxurl, data, null, 'json');
    };
    /**
     * Update the due date of an invoice using ajax action
     */
    var ajax_update_due_date = function (id, due_date) {
        var data = { 'action': 'update_invoice_due_date', 'invoice_id': id, 'due_date': due_date };
        return jQuery.post(ajaxurl, data, null, 'json');
    };

    /** 
     * confirm deletion
     */
    this.ConfirmDelete = function (obj) {
        var invoice = $(obj).data('name');
        var ok = confirm("Really delete invoice " + invoice + "?");
        if (!ok) event.preventDefault();
    };

    /**
     * Edit due date through ajax
     */
    this.EditDueDate = function (obj) {
        var d = $(obj).text();
        var invoice_id = parseInt($(obj).data('id'));

        $(obj).hide();

        var container = openDateInput(d, function (newDate) {
            jsonRequest({ invoice_id: invoice_id, due_date: newDate }).done(function (resp) {
                $(obj).text(resp);
                $(obj).show();
            });
        }, function () {
            $(obj).show();
        });

        $(obj).after(container);
    }

    this.EditPaidDate = function (obj) {
        var d = $(obj).text();
        var invoice_id = parseInt($(obj).data('id'));

        $(obj).hide();

        var container = openDateInput(d, function (newDate) {
            jsonRequest({ invoice_id: invoice_id, paid_date: newDate }).done(function (resp) {
                $(obj).text(resp);
                $(obj).show();
            });
        }, function () {
            $(obj).show();
        });

        $(obj).after(container);
    }

    this.UpdatePeriod = function(obj){
        var order_id = parseInt($(obj).data('id'));
        var value = $(obj).val();

        var loading = $('<img />');
        loading.attr('src', '/wp-admin/images/loading.gif');

        $(obj).after(loading);

        jsonRequest({ order_id: order_id, period: value}).done(function(resp){
            $(obj).val(resp);
        }).fail(function(){
            alert('An error occured');
        }).always(function () { loading.remove(); });
    }

    this.RunReminder = function(obj){
        var tmp = $(obj).text();
        $(obj).text('Loading...');

        jsonRequest({ payment_reminder: true}).done(function(resp){
            if(resp < -1) {
                alert("Invalid email address");
                return;
            }
            if(resp < 0) {
                alert("Payment reminder disabled");
                return;
            }

            if (resp <= 0) {
                alert("Payment reminder executed (no email)");
                return;
            }
            alert("Payment reminder executed");
        }).always(function () { $(obj).text(tmp); });
    }

    this.RunRecurrReminder = function(obj){
        var tmp = $(obj).text();
        $(obj).text('Loading...');

        jsonRequest({ recurr_reminder: true }).done(function(resp){
            if(resp < -1)
            {
                alert("Please select 'Test Recurring' first.");
                return;
            }
            if (resp < 0) {
                alert("Recurring reminder is disabled");
                return;
            }
            alert("Recurring reminder executed");
        }).always(function () { $(obj).text(tmp); });
    };

    var openDateInput = function (defaultValue, onSaveCallback, onCancelCallback) {
        var container = $('<div />');

        var $input = $('<input type="text" style="width: 150px;" />');
        $input.val(defaultValue);

        var btnSave = $('<a />', { href: '#', text: 'Save' }).click(function () {
            onSaveCallback($input.val());
            container.remove();
        });
        var btnCancel = $('<a />', { style: 'margin-left: 1em;', href: '#', text: 'Cancel' }).click(function () {
            container.remove();
            onCancelCallback();
        });

        container.append($input);
        container.append($('<br />'));
        container.append(btnSave);
        container.append(btnCancel);
        return container;
    };

    var hideTabs = function(){
        $('#ispconfig-tabs a').each(function () {
            var other_id = $(this).attr('href');
            $(other_id).hide();
        })
        $('#ispconfig-tabs > li').removeClass('tabs');
    };

    var initTabs = function(){
        $('#ispconfig-tabs a').click(function(event){
            event.preventDefault();

            var id = $(this).attr('href');
            hideTabs();           
            $(this).closest('li').addClass('tabs');
            $(id).show();
        })

        hideTabs();
        $('#ispconfig-tabs a:first').trigger('click');
    };

    var _constructor = function () {
        initTabs();
    }();
}

jQuery(function () {
    var ISPConfigAdmin = window['ISPConfigAdmin'] = new ISPConfigAdminClass();
});