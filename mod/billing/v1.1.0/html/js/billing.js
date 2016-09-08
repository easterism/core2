
var billing = {

    /**
     * @param select
     */
    changePayMethod: function(select) {

        var system_name = select.options[select.selectedIndex].value;

        $('#systems-container').children().hide();
        $('#system-' + system_name).show();
    },


    /**
     * @param operationName
     * @returns {boolean}
     */
    expenseBalance: function(operationName) {
        preloader.show();

        $.ajax({
            method: 'post',
            url: 'index.php?module=billing',
            dataType: 'json',
            data: {
                system_name: 'balance',
                type_operation: 'expense',
                operation_name: operationName
            },
            success: function(data) {
                if (data.status == 'success') {
                    alertify.alert('Платеж успешно совершен', function(){
                        load('index.php?module=billing');
                    });
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
    }
};
