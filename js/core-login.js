/**
 * @constructor
 */
function CoreLogin() {}


/**
 * @param form
 */
CoreLogin.login = function (form) {

    CoreLogin.loaderShow();
    $('.form-main .text-danger').text('');

    $.ajax({
        url: "index.php?core=login",
        method: "POST",
        data: {
            login: $('[name=login]', form).val(),
            password: hex_md5($('[name=password]', form).val())
        }
    })

        .always (function (jqXHR) {
            CoreLogin.loaderHide();

            var response     = typeof jqXHR === 'string' ? jqXHR : jqXHR.responseText;
            var errorMessage = '';

            try {
                var data = JSON.parse(response);
                errorMessage = typeof data.error_message === 'string' ? data.error_message : '';

            } catch (err) {
                errorMessage = response || "Ошибка. Попробуйте позже, либо обратитесь к администратору";
            }

            if (errorMessage !== '') {
                $('.form-main .text-danger').text(errorMessage);
            } else {
                location.reload();
            }
        });
};


/**
 * @param form
 */
CoreLogin.registration = function (form) {

    CoreLogin.loaderShow();
    $('.form-main .text-danger').text('');

    $.ajax({
        url: "index.php?core=registration",
        dataType: "json",
        method: "POST",
        data: $(form).serialize()
    })
        .done(function (data) {
            CoreLogin.loaderHide();
            if (data.status === 'success') {
                $('.form-main .text-success').text(data.message).css('margin-bottom', '50px');
                $('.form-registration').hide();
            } else {
                $('.form-main .text-danger').text(data.error_message);
            }
        })

        .fail(function () {
            CoreLogin.loaderHide();
            $('.form-main .text-danger').text('Ошибка. Попробуйте позже, либо обратитесь к администратору');
        });
};


/**
 * @param form
 * @constructor
 */
CoreLogin.registrationComplete = function (form) {

    var pass1 = $("[name=password]").val();
    var pass2 = $("[name=password2]").val();

    if ( ! pass1 || ! pass2) {
        $('.form-main .text-danger').text('Введите пароль');
        return false;
    }

    if (pass1 !== pass2) {
        $('.form-main .text-danger').text('Пароли не совпадают').show();
        return false;
    }

    CoreLogin.loaderShow();
    $('.form-main .text-danger').text('');

    $.ajax({
        url: "index.php?core=registration_complete",
        dataType: "json",
        method: "POST",
        data: {
            key: form.key.value,
            password: hex_md5(form.password.value)
        }
    }).done(function (data) {
        CoreLogin.loaderHide();
        if (data.status === 'success') {
            $('.form-main .text-success').html(data.message).css('margin-bottom', '50px');
            $('.form-registration').hide();
        } else {
            $('.form-main .text-danger').text(data.error_message);
        }

    }).fail(function () {
        CoreLogin.loaderHide();
        $('.form-main .text-danger').text('Ошибка. Попробуйте позже, либо обратитесь к администратору');
    });
};


/**
 * @param form
 * @constructor
 */
CoreLogin.restore = function (form) {

    CoreLogin.loaderShow();
    $('.form-main .text-danger').text('');

    $.ajax({
        url: "index.php?core=restore",
        dataType: "json",
        method: "POST",
        data: $(form).serialize()
    })
        .done(function (data) {
            CoreLogin.loaderHide();

            if (data.status === 'success') {
                $('.form-main .text-success').text(data.message).css('margin-bottom', '50px');
                $('.form-restore').hide();

            } else {
                $('.form-main .text-danger').text(data.error_message);
            }
        })

        .fail(function () {
            CoreLogin.loaderHide();
            $('.form-main .text-danger').text('Ошибка. Попробуйте позже, либо обратитесь к администратору');
        });
};


/**
 * @param form
 * @constructor
 */
CoreLogin.restoreComplete = function (form) {

    var pass1 = $("[name=password]").val();
    var pass2 = $("[name=password2]").val();

    if (pass1 !== pass2) {
        $('.form-main .text-danger').text('Пароли не совпадают').show();
        return false;
    }

    CoreLogin.loaderShow();
    $('.form-main .text-danger').text('');

    $.ajax({
        url: "index.php?core=restore_complete",
        dataType: "json",
        method: "POST",
        data: {
            key: form.key.value,
            password: hex_md5(form.password.value)
        }
    }).done(function (data) {
        CoreLogin.loaderHide();

        if (data.status === 'success') {
            $('.form-main .text-success').html(data.message).css('margin-bottom', '50px');
            $('.form-restore').hide();
        } else {
            $('.form-main .text-danger').text(data.error_message);
        }

    }).fail(function () {
        CoreLogin.loaderHide();
        $('.form-main .text-danger').text('Ошибка. Попробуйте позже, либо обратитесь к администратору');
    });
};


/**
 *
 */
CoreLogin.loaderShow = function () {

    var $btn = $('.has-spinner');

    $btn.attr("disabled", "disabled");

    if ($btn.find('.btn-loader').length == 0) {
        $btn.prepend('<span class="btn-loader"></span>');
    }
}


/**
 *
 */
CoreLogin.loaderHide = function () {

    var $btn = $('.has-spinner');

    $btn.find('.btn-loader').remove();
    $btn.removeAttr("disabled");
}


$(function () {

    // Добавление маски для полей с телефоном
    if ($("input[type=phone]")[0] && typeof window.Cleave === 'function') {

        $("input[type=phone]").each(function (key, input) {
            $(input).on('keyup', function () {
                if ($(this).val() !== '' && $(this).val().substr(0, 1) !== '+') {
                    $(this).val('+' + $(this).val());
                }
            });
            new Cleave(input, {
                phone: true,
                phoneRegionCode: 'RU'
            });
        });
    }
});


