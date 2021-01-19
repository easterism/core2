/**
 * @constructor
 */
function AdminUsers() {};


/**
 * @param userId
 */
AdminUsers.loginUser = function(userId) {

    /**
     * @param userId
     */
    function sendLoginUser(userId) {

        preloader.show();

        $.post('index.php?module=admin&action=users&data=login_user', {
                user_id: userId
            },
            function (data) {
                preloader.hide();

                if (data.status !== 'success') {
                    if (data.error_message) {
                        alert(data.error_message);
                    } else {
                        alert("Ошибка. Попробуйте обновить страницу и выполнить это действие еще раз.");
                    }

                } else {
                    window.location = 'index.php';
                }
            },
            'json');
    }


    if (typeof swal === 'undefined') {
        alertify.confirm('Вы уверены, что хотите войти под выбранным пользователем?', function(e){
            if (e) {

                sendLoginUser(userId);

            } else return false;
        });

    } else {
        swal({
            title: 'Вы уверены, что хотите войти под выбранным пользователем?',
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: '#f0ad4e',
            confirmButtonText: "Да",
            cancelButtonText: "Нет"
        }).then(
            function(result) {

                sendLoginUser(userId);

            }, function(dismiss) {}
        );
    }
};