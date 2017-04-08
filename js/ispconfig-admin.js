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

    var _constructor = function () {

    }();
}

jQuery(function () {
    var ISPConfigAdmin = window['ISPConfigAdmin'] = new ISPConfigAdminClass();
});