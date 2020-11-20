/**
 * @constructor
 */
function CoreLogin() {}


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
                $('.form-main .text-danger').text(data.message);
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

    var pass1 = $("#users_password").val();
    var pass2 = $("#users_password2").val();

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
            $('.form-main .text-danger').text(data.message);
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
                $('.form-main .text-danger').text(data.message);
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

    var pass1 = $("#users_password").val();
    var pass2 = $("#users_password2").val();

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
            $('.form-main .text-danger').text(data.message);
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
    $btn.attr('data-btn-text', $btn.text());
    $btn.html('<div class="btn-loader"></div>Загрузка...');
}


/**
 *
 */
CoreLogin.loaderHide = function () {

    var $btn = $('.has-spinner');

    $btn.html($btn.attr('data-btn-text'));
    $btn.removeAttr("disabled");
}


$(function () {
    if ($("#UserTEL")[0]) {
        $("#UserTEL").mask("+375(99) 999-99-99");
    }
});


