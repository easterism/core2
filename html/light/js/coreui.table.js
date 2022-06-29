
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
            var templateContainer = $("#templates-" + resource);
            var columns           = $("#column-switcher-" + resource);

            if (columns.is(":visible")) {
                columns.hide();
            }

            if (templateContainer.is(":visible")) {
                templateContainer.hide();
            }

            $search_container.toggle('fast');

            var form = $search_container.find("form");
            form[0].elements[0].focus();
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
         * @param resource
         * @param isAjax
         */
        clear : function(resource, isAjax) {

            var post      = {};
            var container = '';

            post['filter_clear_' + resource] = 1;

            if (CoreUI.table.loc[resource]) {
                if (isAjax) {
                    CoreUI.table.preloader.show(resource);
                    container = document.getElementById("table-" + resource + "-wrapper").parentNode;

                    load(CoreUI.table.loc[resource] + '&__filter_clear=1', post, container, function () {
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
         * @param isAjax
         */
        submit : function(resource, isAjax) {

            var allInputs = $("#filter-" + resource).find(":input");
            var post      = {};
            var container = '';

            $.each(allInputs, function(key, input) {
                var name = $(input).attr('name');

                if (name) {
                    if (name.slice(-2) === '[]') {
                        if ( ! post.hasOwnProperty(name)) {
                            post[name] = [];
                        }

                        if ($(input).attr('type') === 'checkbox' && ! $(input).is(':checked')) {
                            return true;
                        }

                        post[name].push($(input).val());

                    } else {
                        if ($(input).attr('type') === 'radio') {
                            if ($(input).is(':checked')) {
                                post[name] = $(input).val();
                            }
                        } else {
                            post[name] = $(input).val();
                        }
                    }
                }
            });

            $.each(post, function (name, value) {
                if (name.slice(-2) === '[]' && typeof value === 'object' && value.length === 0) {
                    post[name.substring(0, name.length - 2)] = '';
                    delete post[name];
                }
            });

            //post = allInputs.serializeArray();

            if (CoreUI.table.loc[resource]) {
                if (isAjax) {
                    CoreUI.table.preloader.show(resource);
                    container = document.getElementById("table-" + resource + "-wrapper").parentNode;

                    load(CoreUI.table.loc[resource] + '&__filter=1', post, container, function () {
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


    columnSwitcher: {

        /**
         * Переключение панели управления колонками
         * @param resource
         */
        toggleContainer : function(resource) {

            var searchContainer   = $("#search-" + resource);
            var templateContainer = $("#templates-" + resource);
            var columnsContainer  = $("#column-switcher-" + resource);

            if (searchContainer.is(":visible")) {
                searchContainer.hide();
            }

            if (templateContainer.is(":visible")) {
                templateContainer.hide();
            }

            columnsContainer.toggle('fast');
        },


        /**
         * @param resource
         */
        toggleAllColumns : function(resource) {

            var filterContainer = $("#column-switcher-" + resource + ' form');
            var inputAll        = filterContainer.find('.checkbox-all input');

            if (inputAll.is(":checked")) {
                filterContainer.find('.checkbox input').prop("checked", true);
            } else {
                filterContainer.find('.checkbox input').prop("checked", false);
            }
        },


        /**
         * @param resource
         * @param isAjax
         */
        submit : function(resource, isAjax) {

            var checkboxes = $('#column-switcher-' + resource + ' form').find('.table-switch-column :checkbox:checked');
            var post       = {};
            var columns    = [];
            var container  = '';

            for (var i = 0; i < checkboxes.length; i++) {
                columns.push(checkboxes[i].value);
            }
            post['columns_' + resource] = columns;

            if (CoreUI.table.loc[resource]) {
                if (isAjax) {
                    //CoreUI.table.preloader.show(resource);
                    container = document.getElementById("table-" + resource + "-wrapper").parentNode;

                    load(CoreUI.table.loc[resource] + '&__filter=1', post, container, function () {
                        //CoreUI.table.preloader.hide(resource);
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


    template: {

        /**
         * Переключение панели
         * @param resource
         */
        toggleContainer : function(resource) {

            var searchContainer   = $("#search-" + resource);
            var columnsContainer  = $("#column-switcher-" + resource);
            var templateContainer = $("#templates-" + resource);

            if (searchContainer.is(":visible")) {
                searchContainer.hide();
            }

            if (columnsContainer.is(":visible")) {
                columnsContainer.hide();
            }

            templateContainer.toggle('fast');
        },


        /**
         * Создание критерия поиска
         * @param resource
         * @param isAjax
         */
        create: function (resource, isAjax) {

            var searchControls = $("#search-" + resource).find(":input").serializeArray();

            if ($('#column-switcher-' + resource)[0]) {
                var columnsCheckboxes = $("#column-switcher-" + resource).find(':checkbox:checked');

                for (var i = 0; i < columnsCheckboxes.length; i++) {
                    if (columnsCheckboxes[i].value !== 'on') {
                        searchControls.push({
                            'name': 'columns_' + resource + '[]',
                            'value': columnsCheckboxes[i].value
                        });
                    }
                }
            }


            if ( ! searchControls || searchControls.length === 0) {
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

                    searchControls.push({
                        'name' : 'template_create_' + resource,
                        'value': templateTitle,
                    });

                    if (CoreUI.table.loc[resource]) {
                        if (isAjax) {
                            var container = document.getElementById("table-" + resource).parentNode;
                            load(CoreUI.table.loc[resource], searchControls, container, function () {
                                preloader.hide();
                            });

                        } else {
                            load(CoreUI.table.loc[resource], searchControls, '', function () {
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

                    if (CoreUI.table.loc[resource]) {
                        if (isAjax) {
                            var container = document.getElementById("table-" + resource).parentNode;
                            load(CoreUI.table.loc[resource], post, container, function () {
                                preloader.hide();
                            });

                        } else {
                            load(CoreUI.table.loc[resource], post, '', function () {
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

            if (CoreUI.table.loc[resource]) {
                if (isAjax) {
                    var container = document.getElementById("table-" + resource).parentNode;
                    load(CoreUI.table.loc[resource], post, container, function () {
                        preloader.hide();
                    });

                } else {
                    load(CoreUI.table.loc[resource], post, '', function () {
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

            preloader.hide();

        } else {
            load(CoreUI.table.loc[resource], post, container, function () {
                preloader.callback();
            });
        }
    },


    /**
     * @param resource
     * @param field
     * @param id
     * @param container
     */
    switchToggle: function (resource, field, id, container) {

        var isActiveControl = $(container).find(':checked').hasClass('coreui-table-switch-active');

        swal({
            title: isActiveControl ? "Деактивировать?" : "Активировать?",
            type: isActiveControl ? "warning" : "info",
            showCancelButton: true,
            confirmButtonColor: isActiveControl ? '#f0ad4e' : '#5bc0de',
            confirmButtonText: "Да",
            cancelButtonText: "Нет"
        }).then(
            function(result) {
                var value = isActiveControl
                    ? $(container).find('.coreui-table-switch-inactive').val()
                    : $(container).find('.coreui-table-switch-active').val();

                $.post('index.php?module=admin&action=switch&loc=core', {
                        data:      field,
                        is_active: value,
                        value:     id
                    }, function(data, textStatus) {
                        if (textStatus === 'success' && data.status === "ok") {

                            if (isActiveControl) {
                                $(container).find('.coreui-table-switch-active').prop('checked', false);
                                $(container).find('.coreui-table-switch-inactive').prop('checked', true);

                            } else {
                                $(container).find('.coreui-table-switch-active').prop('checked', true);
                                $(container).find('.coreui-table-switch-inactive').prop('checked', false);
                            }

                        } else {
                            if (data.status) {
                                swal("Ошибка", data.status, 'error').catch(swal.noop);
                            }
                        }
                    },
                    'json');
            }, function(dismiss) {}
        );
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
     * Раскрытие / скрытие дополнительных данных строки
     * @param resource
     * @param columnNmbr
     * @param url
     * @param isAjax
     */
    toggleExpandColumn : function (resource, columnNmbr, url, isAjax) {

        var urlHash = this.crc32(url);
        var row     = $('#table-' + resource + ' > tbody > tr.row-table').eq(columnNmbr);
        var isLoad  = ! row.next().hasClass('row-expand-name-' + urlHash);

        if (row.hasClass('row-expanded')) {
            row.removeClass('row-expanded');
            row.next().hide('fast', function () {
                $(this).remove();
            })
        }

        if (isLoad) {
            if (isAjax) {
                CoreUI.table.preloader.show(resource);
            } else {
                preloader.show();
            }

            $.ajax({
                method : 'get',
                url    : url,
                async  : false,
                success: function (response) {
                    row.after('<tr class="row-expand" style="display: none"><td colspan="1000">' + response + '</td></tr>');
                    row.addClass('row-expanded');
                    row.next()
                        .addClass('row-expand-name-' + urlHash)
                        .show('fast');

                    if (isAjax) {
                        CoreUI.table.preloader.hide(resource);
                    } else {
                        preloader.hide();
                    }
                },
                error  : function () {
                    CoreUI.notice.create('Ошибка получения содержимого', 'danger');

                    if (isAjax) {
                        CoreUI.table.preloader.hide(resource);
                    } else {
                        preloader.hide();
                    }
                }
            });
        }
    },


    /**
     * CRC32 hash
     * @param str
     * @param isNumber
     * @returns {number}
     */
    crc32: function(str, isNumber) {

        isNumber = typeof isNumber === 'undefined' ? false : !! isNumber;

        for (var a, o = [], c = 0; c < 256; c++) {
            a = c;
            for (var f = 0; f < 8; f++) {
                a = 1 & a ? 3988292384 ^ a >>> 1 : a >>> 1;
            }
            o[c] = a;
        }

        for (var n = -1, t = 0; t < str.length; t++) {
            n = n >>> 8 ^ o[255 & (n ^ str.charCodeAt(t))];
        }

        var result = (-1 ^ n) >>> 0;

        if ( ! isNumber) {
            result = result.toString(16);
        }

        return result;
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