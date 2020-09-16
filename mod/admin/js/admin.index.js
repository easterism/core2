/**
 * @constructor
 */
function AdminIndex() {};


/**
 *
 */
AdminIndex.clearCache = function() {

    preloader.show();

    $.post('index.php?module=admin&action=index&data=clear_cache', {},
        function (data, textStatus) {
            preloader.hide();

            if (data.status !== 'success') {
                if (data.error_message) {
                    alertify.alert(data.error_message);
                } else {
                    alertify.alert("Ошибка. Попробуйте обновить страницу и выполнить это действие еще раз.");
                }

            } else {
                alertify.alert('Кэш очищен');
            }
        },
        'json');
};