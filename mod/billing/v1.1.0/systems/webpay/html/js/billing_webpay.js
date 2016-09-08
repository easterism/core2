
var billing_webpay = {

    /**
     * @param form
     * @param paid_operation
     * @returns {boolean}
     */
    coming: function(form, paid_operation) {

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
                paid_operation: paid_operation,
                price: price
            },
            success: function(data) {
                if (data.status == 'success') {
                    $('input[name="wsb_total"]', form).val(data.total);
                    $('input[name="wsb_seed"]', form).val(data.seed);
                    $('input[name="wsb_order_num"]', form).val(data.order_num);
                    $('input[name="wsb_signature"]', form).val(data.signature);
                    $('input[name="wsb_invoice_item_price[]"]', form).val(price);

                    result = true;
                } else {
                    if (data.error_message) {
                        alert(data.error_message);
                    } else {
                        alert('Ошибка при проведения платежа');
                    }
                }
            },
            complete: function() {
                preloader.hide();
            }
        });

        return result;
    }
};
