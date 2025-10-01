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
        url: location.pathname + location.search,
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
                if (data.return_url) {
                    location.href = data.return_url
                } else {
                    location.reload();
                }
            }
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




