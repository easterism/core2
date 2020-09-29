/**
 * @constructor
 */
function RegistryUser() {}


/**
 * @param form
 */
RegistryUser.registration = function(form) {

    $.ajax({
        url: "/registration",
        dataType: "json",
        method: "POST",
        data: $(form).serialize()
    })
        .done(function(data, status) {
            if (data.status === 'success') {
                swal("На указанную вами почту отправлены данные для входа в систему.", '', 'success').catch(swal.noop);
            } else if (data.status === 'repeat_login') {
                swal("Пользователь c таким Email уже есть в системе.", '', 'error').catch(swal.noop);
            } else if (data.status === 'repeat_email') {
                swal("Пользователь с таким Email уже есть в системе.", '', 'error').catch(swal.noop);
            } else if (data.status === 'repeat_contractor') {
                swal("Контрагент с таким Email уже есть в системе.", '', 'error').catch(swal.noop);
            }
        })

        .fail(function(){
            swal('Ошибка запроса', '', 'error').catch(swal.noop);
        });
};


/**
 * @param form
 * @constructor
 */
RegistryUser.ConfirmRegistryUser = function(form){

    let valueX = $("#users_password").val();
    let valueY = $("#users_password2").val();

    if (valueX !== valueY) {
        $('#users_password2').parent().addClass('has-error');
        $('#users_password2').parent().find('.error-message').text('пароли не совпадают').show();
        return false;
    }

    $.ajax({
        url:  "/registration/complete",
        dataType: "json",
        method: "POST",
        data: {
            key: form.key.value,
            password: hex_md5(form.password.value)
        }
    }).done(function (data) {
        if (data.status === 'success') {
            swal({
                title: "Готово!",
                text: "Вы сможете зайти в систему, после прохождения модерации",
                type: "success"
            }).then(function() {
                window.location.href = "/";
            });

        } else {
            swal("Попробуйте позже.", '', 'error').catch(swal.noop);
        }

    }).fail(function () {
        swal("Попробуйте позже.", '', 'error').catch(swal.noop);

    });
};

RegistryUser.RestorePassUser = function(form) {

    $.ajax({
        url: "/restore",
        dataType: "json",
        method: "GET",
        data: $(form).serialize()
    })
        .done(function(data, status) {

            if (data.status === 'success') {
                swal({
                    title: "На указанную вами почту отправлены данные для смены пароля",
                    text: "",
                    type: "success"
                }).then(function() {
                    window.location.href = "/";
                });

            } else if (data.message === 'no_email') {
                swal("Такого email нету в системе", '', 'error').catch(swal.noop);
            }
        })

        .fail(function(){
            swal('Ошибка запроса', '', 'error').catch(swal.noop);
        });
};


/**
 *
 * @param form
 * @constructor
 */
RegistryUser.ConfirmRestorePassUser = function(form){

    let valueX = $("#users_password").val();
    let valueY = $("#users_password2").val();

    if (valueX !== valueY) {
        $('#users_password2').parent().addClass('has-error');
        $('#users_password2').parent().find('.error-message').text('пароли не совпадают').show();
        return false;
    }

    $.ajax({
        url:  "/restore/complete",
        dataType: "json",
        method: "POST",
        data: {
            key: form.key.value,
            password: hex_md5(form.password.value)
        }
    }).done(function (data) {
        if (data.status === 'success') {
            swal({
                title: "Пароль изменен!",
                text: "Вернитесь на форму входа и войдите в систему с новым паролем",
                type: "success"
            }).then(function() {
                window.location.href = "/";
            });

        } else {
            swal("Попробуйте позже.", '', 'error').catch(swal.noop);
        }

    }).fail(function () {
        swal("Попробуйте позже.", '', 'error').catch(swal.noop);
    });
};



$(function(){
    if ($("#UserTEL")[0]) {
        $("#UserTEL").mask("+375(99) 999-99-99");
    }
});