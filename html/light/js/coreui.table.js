
var CoreUI = typeof CoreUI !== 'undefined' ? CoreUI : {};

CoreUI.table = {

    loc: {},

    preloader : {
        show : function(resource) {
            var wrapper = document.getElementById('table-' + resource + '-wrapper');
            var nodes   = wrapper.childNodes;
            for (var i = 0; i < nodes.length; i++) {
                if (/(\\s|^)preloader(\\s|$)/.test(nodes[i].className)) {
                    nodes[i].style.display = 'block';
                    break;
                }
            }
        },

        hide : function(resource) {
            var wrapper = document.getElementById('table-' + resource + '-wrapper');
            var nodes   = wrapper.childNodes;
            for (var i = 0; i < nodes.length; i++) {
                if (/(\\s|^)preloader(\\s|$)/.test(nodes[i].className)) {
                    nodes[i].style.display = 'none';
                    break;
                }
            }
        }
    },


    search: {

        /**
         * @param resource
         */
        toggle : function(resource) {
            var $search_container = $("#search-" + resource);
            $search_container.toggle('fast');

            var f = $search_container.find("form");
            f[0].elements[0].focus();
        },


        /**
         * @param resource
         * @param isAjax
         */
        clear : function(resource, isAjax) {

            var post      = {};
            var container = '';

            post['search_clear_' + resource] = 1;

            if (CoreUI.table.loc[resource]) {
                if (isAjax) {
                    CoreUI.table.preloader.show(resource);
                    container = document.getElementById("table-" + resource + "-wrapper").parentNode;

                    load(CoreUI.table.loc[resource] + '&__clear=1', post, container, function () {
                        CoreUI.table.preloader.hide(resource);
                        preloader.callback();
                    });

                } else {
                    load(CoreUI.table.loc[resource], post, container, function () {
                        preloader.callback();
                    });
                }
            }
        },


        /**
         * @param resource
         * @param form
         * @param isAjax
         */
        submit : function(resource, form, isAjax) {

            var allInputs = $(form).find(":input");
            var post      = {};
            var container = '';

            post = allInputs.serializeArray();

            if (CoreUI.table.loc[resource]) {
                if (isAjax) {
                    CoreUI.table.preloader.show(resource);
                    container = document.getElementById("table-" + resource + "-wrapper").parentNode;

                    load(CoreUI.table.loc[resource] + '&__search=1', post, container, function () {
                        CoreUI.table.preloader.hide(resource);
                        preloader.callback();
                    });

                } else {
                    load(CoreUI.table.loc[resource], post, container, function () {
                        preloader.callback();
                    });
                }
            }
        }
    },


    filter: {
        /**
         *
         * @param id
         */
        show : function(id) {
            $("#filterColumn" + id).toggle('fast');
        },


        /**
         * @param id
         * @param isAjax
         */
        submit : function(id, isAjax) {
            var o = $('#filterColumn' + id + ' form').find(':checkbox:checked');
            var l = o.length;
            var post = {};
            var t = [];

            for (var i = 0; i < l; i++) {
                t.push(o[i].value);
            }
            post['column_' + id] = t;
            var container = '';

            if (CoreUI.table.loc[id]) {
                if (isAjax) {
                    container = document.getElementById("list" + id).parentNode;
                    load(CoreUI.table.loc[id] + '&__filter=1', post, container);
                } else {
                    load(CoreUI.table.loc[id], post, container);
                }
            }
        }
    },


    template: {

        /**
         * Создание критерия поиска
         * @param resource
         * @param isAjax
         */
        create: function (resource, isAjax) {

            var post = $("#filter" + resource).find(":input").serializeArray();

            if ($('#filterColumn' + resource)[0]) {
                var columnsCheckboxes = $('#filterColumn' + resource + ' form').find(':checkbox:checked');

                for (var i = 0; i < columnsCheckboxes.length; i++) {
                    post.push({
                        'name' : 'column_' + resource + '[]',
                        'value': columnsCheckboxes[i].value
                    });
                }
            }

            if ( ! post || post.length === 0) {
                swal('Не заполнены критерии для сохранения', '', 'warning').catch(swal.noop);
                return false;
            }

            if (isAjax) {
                // FIXME бех этого не ставится курсор в поле ввода названия
                $('.modal.in').removeAttr('tabindex');
            }

            swal({
                title: "Укажите название для шаблона",
                input: "text",
                showCancelButton: true,
                confirmButtonColor: '#5bc0de',
                confirmButtonText: "Сохранить",
                cancelButtonText: "Отмена",
                preConfirm: (templateTitle) => {

                    return new Promise(function (resolve, reject) {
                        if ( ! templateTitle || $.trim(templateTitle) === '') {
                            reject('Укажите название');
                        } else {
                            resolve();
                        }
                    });
                },
            }).then(
                function(templateTitle) {

                    preloader.show();

                    post.push({
                        'name' : 'template_create_' + resource,
                        'value': templateTitle,
                    });

                    if (listx.loc[resource]) {
                        if (isAjax) {
                            var container = document.getElementById("list" + resource).parentNode;
                            load(listx.loc[resource] + '&__template_create=1', post, container, function () {
                                preloader.hide();
                            });

                        } else {
                            load(listx.loc[resource] + '&__template_create=1', post, '', function () {
                                preloader.hide();
                            });
                        }
                    } else {
                        swal('Ошибка', 'Обновите страницу и попробуйте снова', 'error').catch(swal.noop);
                        preloader.hide();
                    }

                }, function(dismiss) {}
            );
        },


        /**
         * Удаление критерия поиска
         * @param resource
         * @param id
         * @param isAjax
         */
        remove: function (resource, id, isAjax) {

            swal({
                title: 'Удалить этот шаблон?',
                type: "warning",
                showCancelButton: true,
                confirmButtonColor: '#f0ad4e',
                confirmButtonText: "Да",
                cancelButtonText: "Нет"
            }).then(
                function(result) {

                    preloader.show();

                    let post = [{
                        'name' : 'template_remove_' + resource,
                        'value': id,
                    }];

                    if (listx.loc[resource]) {
                        if (isAjax) {
                            var container = document.getElementById("list" + resource).parentNode;
                            load(listx.loc[resource] + '&__template_remove=1', post, container, function () {
                                preloader.hide();
                            });

                        } else {
                            load(listx.loc[resource] + '&__template_remove=1', post, '', function () {
                                preloader.hide();
                            });
                        }
                    } else {
                        swal('Ошибка', 'Обновите страницу и попробуйте снова', 'error').catch(swal.noop);
                        preloader.hide();
                    }

                }, function(dismiss) {}
            );
        },


        /**
         * Выбор критерия поиска
         * @param resource
         * @param id
         * @param isAjax
         */
        select: function (resource, id, isAjax) {

            preloader.show();

            let post = [{
                'name' : 'template_select_' + resource,
                'value': id,
            }];

            if (listx.loc[resource]) {
                if (isAjax) {
                    var container = document.getElementById("list" + resource).parentNode;
                    load(listx.loc[resource] + '&__template_select=1', post, container, function () {
                        preloader.hide();
                    });

                } else {
                    load(listx.loc[resource] + '&__template_select=1', post, '', function () {
                        preloader.hide();
                    });
                }
            } else {
                swal('Ошибка', 'Обновите страницу и попробуйте снова', 'error').catch(swal.noop);
                preloader.hide();
            }
        }
    },


    /**
     * @param obj
     * @param resource
     * @param isAjax
     */
    pageSw: function(obj, resource, isAjax) {

        var container = '';
        var p         = '_page_' + resource + '=' + obj.getAttribute('title');

        if (isAjax) {
            CoreUI.table.preloader.show(resource);

            container = document.getElementById("table-" + resource + "-wrapper").parentNode;

            if (CoreUI.table.loc[resource].indexOf('&__') < 0) {
                if (container.id) {
                    location.hash = preloader.prepare(location.hash.substr(1) + '&--' + container.id + '=' + preloader.toJson(CoreUI.table.loc[resource] + "&" + p));
                }

            } else {
                load(CoreUI.table.loc[resource] + '&' + p, '', container, function () {
                    CoreUI.table.preloader.hide(resource);
                    preloader.callback();
                });
            }
        } else load(CoreUI.table.loc[resource] + '&' + p, '', container, function () {
            preloader.callback();
        });
    },


    /**
     * @param obj
     * @param resource
     * @param isAjax
     */
    goToPage: function(obj, resource, isAjax) {

        var container = '';
        var p         = '_page_' + resource + '=' + $('#table-' + resource + '-gotopage').val();

        if (isAjax) {
            container = document.getElementById("table-" + resource + "-wrapper").parentNode;

            if (CoreUI.table.loc[resource].indexOf('&__') < 0) {
                if (container.id) {
                    location.hash = preloader.prepare(location.hash.substr(1) + '&--' + container.id + '=' + preloader.toJson(CoreUI.table.loc[resource] + "&" + p));
                }
            } else {
                load(CoreUI.table.loc[resource] + '&' + p, '', container, function () {
                    preloader.callback();
                });
            }

        } else {
            load(CoreUI.table.loc[resource] + '&' + p, '', container, function () {
                preloader.callback();
            });
        }
    },


    /**
     * @param resource
     * @param columnNumber
     * @param isAjax
     */
    order : function(resource, columnNumber, isAjax) {

        var container = '';
        var post      = {};

        post['order_' + resource] = columnNumber;

        if (isAjax) {
            CoreUI.table.preloader.show(resource);
            container = document.getElementById("table-" + resource + "-wrapper").parentNode;

            load(CoreUI.table.loc[resource] + '&__order=1', post, container, function () {
                CoreUI.table.preloader.hide(resource);
                preloader.callback();
            });

        } else {
            load(CoreUI.table.loc[resource], post, container, function () {
                preloader.callback();
            });
        }
    },


    switchActive: function(img, resource, rec_id) {
        var src   = $(img).attr('src');
        var value = $(img).data('value');

        if (value == 'Y' || value == '1') {
            var new_value = value === 'Y' ? 'N' : 0;
            var new_src   = src.replace("on.png", "off.png");
            var msg       = "Деактивировать запись?";
        } else {
            var new_value = value === 'N' ? 'Y' : 1;
            var new_src   = src.replace("off.png", "on.png");
            var msg       = "Активировать запись?";
        }
        if (confirm(msg)) {
            var preloader_src = src.substr(0, src.lastIndexOf('/')+1) + 'preloader_circle.gif';
            $(img).attr('src', preloader_src);

            var token = $('#table-' + resource).data('csrf-token');
            var url   = window.location.pathname + window.location.search;

            $.ajax({
                url: url,
                type: 'POST',
                dataType : 'json',
                data : {
                    rec_id: rec_id,
                    new_value: new_value
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-CMB-CSRF-TOKEN', token)
                        .setRequestHeader('X-CMB-RESOURCE',   resource)
                        .setRequestHeader('X-CMB-PROCESS',    'status')
                },
                success: function(data, textStatus) {
                    if (data.status == "success") {
                        $(img).attr('src', new_src);
                        $(img).data('value', new_value);
                    } else {
                        $(img).attr('src', src);
                        if (data.message) {
                            alert(data.message);
                        }
                    }
                },
                error : function(xhr, textStatus) {
                    if (xhr.status == 0) {
                        alert('You are offline!\nCheck you network.');
                    } else if (xhr.status == 404) {
                        alert('404 - page not found');
                    } else if (xhr.status == 500) {
                        alert('500 - server error');
                    } else if (textStatus == 'parsererror') {
                        alert('parse error');
                    } else if (textStatus == 'timeout') {
                        alert('timeout');
                    } else {
                        alert(xhr.status + ' - ' + xhr.responseText);
                    }
                    $(img).attr('src', src);
                }
            });
        }
    },


    getChecked : function (resource, returnArray) {
        var j = 1;
        if (returnArray === true) {
            var val = [];
        } else {
            var val = "";
        }

        for (var i = 0; i < j; i++) {
            if (document.getElementById("check-" + resource + '-' + j)) {
                if (document.getElementById("check-" + resource + '-' + j).checked) {
                    if (returnArray === true) {
                        val.push(document.getElementById("check-" + resource + '-' + j).value);
                    } else {
                        val += val === ''
                            ? document.getElementById("check-" + resource + '-' + j).value
                            : ',' + document.getElementById("check-" + resource + '-' + j).value;
                    }
                }
                j++;
            }
        }

        return val;
    },

    /**
     *
     * @param resource
     * @param confirmMsg
     * @param noSelectMsg
     * @param isAjax
     */
    deleteRows: function (resource, confirmMsg, noSelectMsg, isAjax) {

        var val = this.getChecked(resource, true);

        if (val) {
            if (val.length) {
                swal({
                    title: confirmMsg,
                    type: "warning",
                    showCancelButton: true,
                    confirmButtonColor: '#f0ad4e',
                    confirmButtonText: "Да",
                    cancelButtonText: "Нет"
                }).then(
                    function(result) {
                        preloader.show();

                        var errorContainer = $("#table-" + resource + "-error");
                        var container      = '';

                        errorContainer.hide();

                        if (isAjax) {
                            CoreUI.table.preloader.show(resource);
                            container = document.getElementById("table-" + resource + "-wrapper").parentNode;
                        }

                        $.ajax({
                            method: "DELETE",
                            dataType: "json",
                            url: "index.php?res=" + resource + "&id=" + val,
                            success: function (data) {
                                if (data === true) {
                                    load(CoreUI.table.loc[resource], '', container, function () {
                                        preloader.callback();
                                    });

                                } else {
                                    if ( ! data || data.error) {
                                        var msg = data.error ? data.error : "Не удалось выполнить удаление";
                                        errorContainer.html(msg);
                                        errorContainer.show();

                                    } else {
                                        if (data.alert) {
                                            alert(data.alert);
                                        }
                                        if (data.loc) {
                                            load(data.loc, '', container, function () {
                                                preloader.callback();
                                            });
                                        }
                                    }
                                }
                            }

                        }).fail(function () {
                            swal("Не удалось выполнить удаление", '', 'error').catch(swal.noop);
                        }).always(function () {
                            CoreUI.table.preloader.hide(resource);
                            preloader.hide();
                        });
                    }, function(dismiss) {}
                );
            } else {
                swal(noSelectMsg, '', 'warning').catch(swal.noop);
            }
        }
    },


    checkAll : function (obj, resource) {
        var j = 1;
        var check = !! obj.checked;
        for (var i = 0; i < j; i++) {
            if (document.getElementById("check-" + resource + '-' + j)) {
                document.getElementById("check-" + resource + '-' + j).checked = check;
                j++;
            }
        }
        return;
    },


    /**
     * @param resource
     * @param select
     * @param isAjax
     */
    recordsPerPage : function(resource, select, isAjax) {

        var container = '';

        if (isAjax) {
            CoreUI.table.preloader.show(resource);
            container = document.getElementById("table-" + resource + "-wrapper").parentNode;
        }

        var post = {};
        post['count_' + resource] = select.value;
        load(CoreUI.table.loc[resource], post, container, function () {
            CoreUI.table.preloader.hide(resource);
            preloader.callback();
        });
    }
};


$(document).ready(function(){
    /**
     * Очистка даты в календаре
     */
    $('.table-datepicker-clear, .table-datetimepicker-clear').click(function() {
        var container = $(this).parent();
        var $from_input = $('.table-datepicker-from-value, .table-datetimepicker-from-value', container);
        var $to_input   = $('.table-datepicker-to-value, .table-datetimepicker-to-value', container);

        $from_input.val('');
        $to_input.val('');

        CoreUI.table.search.setDate($from_input, $to_input, container);

        $('.datepicker-container, .datetimepicker-container', $(container).parent()).datepicker('refresh');
    });


    /**
     * Сткрытие открытых календарей
     */
    $(document).click(function(e) {
        var target = $(e.target);
        if ($(target).parents('.datepicker-container, .datetimepicker-container, .ui-datepicker-group').length) {
            return false;

        } else {
            $('.datepicker-container, .datetimepicker-container').hide('fast');
        }
    });


    /**
     * Создание календарей
     */
    $('.table-datepicker, .table-datetimepicker').each(function() {
        CoreUI.table.search.createCalendar(this);
    });
});