/**
 * @constructor
 */
function RegistryUser() {}


/**
 * @param form
 */
RegistryUser.registration = function(form) {
    preloader.buttonLoader('start');
    $.ajax({
        url: "/registration",
        dataType: "json",
        method: "POST",
        data: $(form).serialize()
    })
        .done(function(data, status) {
            preloader.buttonLoader('stop');
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
            preloader.buttonLoader('stop');
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
    preloader.buttonLoader('start');
    $.ajax({
        url:  "/registration/complete",
        dataType: "json",
        method: "POST",
        data: {
            key: form.key.value,
            password: hex_md5(form.password.value)
        }
    }).done(function (data) {
        preloader.buttonLoader('stop');
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
        preloader.buttonLoader('stop');
        swal("Попробуйте позже.", '', 'error').catch(swal.noop);

    });
};

RegistryUser.RestorePassUser = function(form) {
    preloader.buttonLoader('start');
    $.ajax({
        url: "/restore",
        dataType: "json",
        method: "GET",
        data: $(form).serialize()
    })
        .done(function(data, status) {
            preloader.buttonLoader('stop');
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
            preloader.buttonLoader('stop');
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
    preloader.buttonLoader('start');
    $.ajax({
        url:  "/restore/complete",
        dataType: "json",
        method: "POST",
        data: {
            key: form.key.value,
            password: hex_md5(form.password.value)
        }
    }).done(function (data) {
        preloader.buttonLoader('stop');
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
        preloader.buttonLoader('stop');
        swal("Попробуйте позже.", '', 'error').catch(swal.noop);
    });
};



$(function(){
    if ($("#UserTEL")[0]) {
        $("#UserTEL").mask("+375(99) 999-99-99");
    }
});

var preloader = {
    buttonLoader : function (action) {
        let self = $('.has-spinner');
        if (action === 'start') {
            if ($(self).attr("disabled") === "disabled") {
                e.preventDefault();
            }
            $(self).attr("disabled", "disabled");
            $(self).attr('data-btn-text', $(self).text());
            $(self).html('<div class="lds-dual-ring"></div>Загрузка');
            $(self).addClass('active');
        }
        if (action === 'stop') {
            $(self).html($(self).attr('data-btn-text'));
            $(self).removeClass('active');
            $('.has-spinner').removeAttr("disabled");
        }
    }
};


