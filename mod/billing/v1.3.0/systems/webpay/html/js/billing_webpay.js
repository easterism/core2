
var billing_webpay = {

    /**
     * @param form
     * @param operation_name
     * @returns {boolean}
     */
    createComing: function(form, operation_name) {

        preloader.show();

        var price_raw = $('#webpay-coming-price', form).val();
        var price     = parseFloat(price_raw.replace(/\s/g, ''));
        var result    = false;

        $.ajax({
            method: 'post',
            url: 'index.php?module=billing',
            dataType: 'json',
            async: false,
            data: {
                system_name: 'webpay',
                type_operation: 'coming',
                operation_name: operation_name,
                price: price
            },
            success: function(response) {
                if (response.status == 'success') {
                    $('input[name="wsb_total"]', form).val(response.data.total);
                    $('input[name="wsb_seed"]', form).val(response.data.seed);
                    $('input[name="wsb_order_num"]', form).val(response.data.order_num);
                    $('input[name="wsb_signature"]', form).val(response.data.signature);
                    $('input[name="wsb_invoice_item_price[]"]', form).val(price);

                    result = true;
                } else {
                    if (response.error_message) {
                        alertify.alert(response.error_message);
                    } else {
                        alertify.alert('Ошибка при проведения платежа');
                    }

                    preloader.hide();
                }
            },
            error: function() {
                alertify.alert('Ошибка при проведения платежа');
                preloader.hide();
            }
        });

        return result;
    }
};
