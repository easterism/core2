/**
 * @constructor
 */
function AdminIndex() {};


/**
 *
 */
AdminIndex.clearCache = function() {

    preloader.show();

    fetch("index.php?module=admin&action=index&data=clear_cache")
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            preloader.hide();

            if (data.status !== 'success') {
                if (data.error_message) {
                    alertify.alert(data.error_message);
                } else {
                    alertify.alert("Ошибка. Попробуйте обновить страницу и выполнить это действие еще раз.");
                }

            } else {
                if (typeof Snarl === 'object') {
                    Snarl.addNotification({title: "Кэш очищен", icon: '<i class="fa fa-check"></i>'});
                } else {
                    alertify.success('Кэш очищен')
                }
            }
        })
};