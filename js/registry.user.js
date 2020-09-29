/**
 * @constructor
 */
function RegistryUser() {}


/**
 * @param form
 */
RegistryUser.registration = function(form) {
    preloader.show();
    $.ajax({
        url: "/registration",
        dataType: "json",
        method: "POST",
        data: $(form).serialize()
    })
        .done(function(data, status) {
            preloader.hide();
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
            preloader.hide();
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
    preloader.show();
    $.ajax({
        url:  "/registration/complete",
        dataType: "json",
        method: "POST",
        data: {
            key: form.key.value,
            password: hex_md5(form.password.value)
        }
    }).done(function (data) {
        preloader.hide();
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
        preloader.hide();
        swal("Попробуйте позже.", '', 'error').catch(swal.noop);

    });
};

RegistryUser.RestorePassUser = function(form) {
    preloader.show();
    $.ajax({
        url: "/restore",
        dataType: "json",
        method: "GET",
        data: $(form).serialize()
    })
        .done(function(data, status) {
            preloader.hide();
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
            preloader.hide();
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
    preloader.show();
    $.ajax({
        url:  "/restore/complete",
        dataType: "json",
        method: "POST",
        data: {
            key: form.key.value,
            password: hex_md5(form.password.value)
        }
    }).done(function (data) {
        preloader.hide();
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
        preloader.hide();
        swal("Попробуйте позже.", '', 'error').catch(swal.noop);
    });
};



$(function(){
    if ($("#UserTEL")[0]) {
        $("#UserTEL").mask("+375(99) 999-99-99");
    }
});

var preloader = {
    extraLoad : {},
    oldHash : {},
    show : function() {
        //$("#preloader").css('margin-top', ($("#menu-container").height()));
        $("#preloader").show();
    },
    hide : function() {
        $("#preloader").hide();
    },
    callback : function (response, status, xhr) {
        if (status == "error") {

        } else {
            if (preloader.extraLoad) {
                for (var el in preloader.extraLoad) {
                    var aUrl = preloader.extraLoad[el];
                    if (aUrl) {
                        aUrl = JSON.parse(aUrl);
                        var bUrl = [];
                        for (var k in aUrl) {
                            if (typeof aUrl.hasOwnProperty == 'function' && aUrl.hasOwnProperty(k)) {
                                bUrl.push(encodeURIComponent(k) + '=' + encodeURIComponent(aUrl[k]));
                            }
                        }
                        $('#' + el).load("index.php?" + bUrl.join('&'));
                    }
                }
                preloader.extraLoad = {};
            }
            $('html').animate({
                scrollTop: 0
            });
        }
        preloader.hide();
        //resize();
    },
    qs : function(url) {
        //PARSE query string
        var qs = new QueryString(url);

        var keys = qs.keys();
        url = {};
        //PREPARE location and hash
        for (var k in keys) {
            url[keys[k]] = qs.value(keys[k]);
        }
        return url;
    },
    prepare : function(url) {
        url = this.qs(url);
        //CREATE the new location
        var pairs = [];
        for (var key in url)
            if (typeof url.hasOwnProperty == 'function' && url.hasOwnProperty(key)) {
                var pu = encodeURIComponent(key) + '=' + encodeURIComponent(url[key]);
                pairs.push(pu);
            }
        url = pairs.join('&');
        return url;
    },
    toJson: function (url) {
        url = this.qs(url);
        //CREATE the new location
        var pairs = [];
        for (var key in url)
            if (typeof url.hasOwnProperty == 'function' && url.hasOwnProperty(key)) {
                var pu = '"' + encodeURIComponent(key) + '":"' + encodeURIComponent(url[key]) + '"';
                pairs.push(pu);
            }
        return '{' + pairs.join(',') + '}';
    },
    normUrl: function () {

    }
};
