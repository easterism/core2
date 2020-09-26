/**
 * @constructor
 */
function RegistryUser() {}


/**
 * @param form
 */
RegistryUser.registry = function(form) {

    $.ajax({
        url: "/registration/user",
        dataType: "json",
        method: "POST",
        data: $(form).serialize()
    })
        .done(function(data, status) {

            if (data.status === 'success') {
                swal("На указанную вами почту отправлены данные для входа в систему.", '', 'success').catch(swal.noop);

            }else {
                swal("Такой Email уже есть в системе.", '', 'error').catch(swal.noop);
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
RegistryUser.ConfirmRegistryUser = function(form){

    $('.form-group > input[type=password]').each(function () {

        let valueX = $("#users_password").val();
        let valueY = $("#users_password2").val();

        if (valueX !== valueY) {
            $(this).parent().addClass('has-error');
            $(this).parent().find('.error-message').text('пароли не совподают').show();

        }

    });

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
                swal("Успешно <br> После прохождения модерации, вы сможете зайти в систему", '', 'success').catch(swal.noop);
                $('body').removeClass('popup-open');
                $('#registration').removeClass('visible');
                setTimeout( 'location="/";', 2000 );

            } else {
                swal("Попробуйте позже.", '', 'error').catch(swal.noop);
            }

        }).fail(function () {
            swal("Попробуйте позже.", '', 'error').catch(swal.noop);

        });


};

RegistryUser.RestorePassUser = function(form) {

    $.ajax({
        url: "/restore_pass_user",
        dataType: "json",
        method: "POST",
        data: $(form).serialize()
    })
        .done(function(data, status) {

            if (data.status === 'success') {
                swal("На указанную вами почту отправлены данные для смены пароля", '', 'success').catch(swal.noop);
                setTimeout( 'location="/";', 2000 );
            }else if (data.message === 'no_email') {
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

    $('.form-group > input[type=password]').each(function () {

        let valueX = $("#users_password").val();
        let valueY = $("#users_password2").val();

        if (valueX !== valueY) {
            $(this).parent().addClass('has-error');
            $(this).parent().find('.error-message').text('пароли не совподают').show();

        }

    });

    $.ajax({
        url:  "/restore/pass/complete",
        dataType: "json",
        method: "POST",
        data: {
            key: form.key.value,
            password: hex_md5(form.password.value)
        }
    }).done(function (data) {
        if (data.status === 'success') {
            swal("Успешно <br> Пароль изменен", '', 'success').catch(swal.noop);
            $('body').removeClass('popup-open');
            $('#registration').removeClass('visible');
            setTimeout( 'location="/";', 2000 );

        } else {
            swal("Попробуйте позже.", '', 'error').catch(swal.noop);
        }

    }).fail(function () {
        swal("Попробуйте позже.", '', 'error').catch(swal.noop);

    });


};
$(function(){
    $("#UserTEL").mask("+375(99) 999-99-99");
});