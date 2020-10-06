/**
 * @constructor
 */
function Login() {}


/**
 * @param form
 */
Login.registration = function(form) {
    preloader.buttonLoader('start');
    $.ajax({
        url: "index.php?core=registration",
        dataType: "json",
        method: "POST",
        data: $(form).serialize()
    })
        .done(function(data, status) {
            preloader.buttonLoader('stop');
            if (data.status === 'success') {
                $('#success').text(data.message).css('margin-bottom','100px');
                $('.form-registry').css('display','none');
            } else {
                $('#error').text(data.message);
            }
        })

        .fail(function(){
            preloader.buttonLoader('stop');
            $('#error').text('Попробуйте позже');
        });
};


/**
 * @param form
 * @constructor
 */
Login.ConfirmRegistryUser = function(form){

    let valueX = $("#users_password").val();
    let valueY = $("#users_password2").val();

    if (valueX !== valueY) {
        $('#users_password2').parent().addClass('has-error');
        $('#users_password2').parent().find('.error-message').text('пароли не совпадают').show();
        return false;
    }
    preloader.buttonLoader('start');
    $.ajax({
        url:  "index.php?core=registration_complete",
        dataType: "json",
        method: "POST",
        data: {
            key: form.key.value,
            password: hex_md5(form.password.value)
        }
    }).done(function (data) {
        preloader.buttonLoader('stop');
        if (data.status === 'success') {
            $('#success').html(data.message).css('margin-bottom','100px');
            $('.form-registry').css('display','none');
        } else {
            $('#error').text(data.message);
        }

    }).fail(function () {
        preloader.buttonLoader('stop');
        $('#error').text('Попробуйте позже');

    });
};


/**
 * @param form
 * @constructor
 */
Login.RestorePassUser = function(form) {
    preloader.buttonLoader('start');
    $.ajax({
        url: "index.php?core=restore",
        dataType: "json",
        method: "POST",
        data: $(form).serialize()
    })
        .done(function(data, status) {
            preloader.buttonLoader('stop');
            if (data.status === 'success') {
                $('#error').text('');
                $('#success').text(data.message).css('margin-bottom','100px');
                $('.form-registry').css('display','none');

            } else  {
                $('#error').text(data.message);
            }
        })

        .fail(function(){
            preloader.buttonLoader('stop');
            $('#error').text('Попробуйте позже');
        });
};


/**
 *
 * @param form
 * @constructor
 */
Login.ConfirmRestorePassUser = function(form){

    let valueX = $("#users_password").val();
    let valueY = $("#users_password2").val();

    if (valueX !== valueY) {
        $('#users_password2').parent().addClass('has-error');
        $('#users_password2').parent().find('.error-message').text('пароли не совпадают').show();
        return false;
    }
    preloader.buttonLoader('start');
    $.ajax({
        url:  "index.php?core=restore_complete",
        dataType: "json",
        method: "POST",
        data: {
            key: form.key.value,
            password: hex_md5(form.password.value)
        }
    }).done(function (data) {
        preloader.buttonLoader('stop');
        if (data.status === 'success') {
            $('#error').text('');
            $('#success').html(data.message).css('margin-bottom','100px');
            $('.form-registry').css('display','none');
        } else {
            $('#error').text(data.message);
        }

    }).fail(function () {
        preloader.buttonLoader('stop');
        $('#error').text('Попробуйте позже');
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


