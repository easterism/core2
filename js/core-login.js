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
    
    var $passInput = $('[name=password]', form);
    var passValue  = $passInput.val();
    
    if ( ! $passInput.data('ldap') || $('[name=login]', form).val() === 'root') {
        passValue = hex_md5(passValue);
    }
    
    $.ajax({
        url: "index.php?core=login",
        method: "POST",
        data: {
            login: $('[name=login]', form).val(),
            password: passValue
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
 * Авторизация через соц сеть
 * @param socialName
 * @param code
 */
CoreLogin.authSocial = function (socialName, code) {

    CoreLogin.loaderShow();
    $('.form-main .text-danger').text('');

    $.ajax({
        url: "index.php?core=auth_" + socialName,
        method: "POST",
        data: {
            code: code
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
                location.href = "index.php";
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


/**
 * Получение параметров из адреса
 * @param queryString
 * @returns {{}}
 */
CoreLogin.parseQuery = function (queryString) {

    var query = {};
    var pairs = (queryString[0] === '?' ? queryString.substr(1) : queryString).split('&');

    for (var i = 0; i < pairs.length; i++) {
        var pair = pairs[i].split('=');
        query[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '');
    }

    return query;
}


$(function () {

    var parameters = CoreLogin.parseQuery(location.search);

    if (parameters.hasOwnProperty('core') &&
        parameters.hasOwnProperty('code') &&
        parameters['core'] &&
        parameters['code']
    ) {
        switch (parameters['core']) {
            case 'auth_vk':
                if ($('.auth-social-vk')[0]) {
                    CoreLogin.authSocial('vk', parameters['code']);
                }
                break;

            case 'auth_ok':
                if ($('.auth-social-ok')[0]) {
                    CoreLogin.authSocial('ok', parameters['code']);
                }
                break;

            case 'auth_fb':
                if ($('.auth-social-fb')[0]) {
                    CoreLogin.authSocial('fb', parameters['code']);
                }
                break;
        }
    }

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


