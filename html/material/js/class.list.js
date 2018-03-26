
/**
 * @param res
 * @param preffix
 * @param activeTab
 * @param closeParamName
 */
function customDel(res, preffix, activeTab, closeParamName) {
    if (typeof (activeTab) == 'undefined') {
        activeTab = 1;
    }
    if (typeof (preffix) == 'undefined') {
        preffix = '';
    }
    if (typeof (closeParamName) == 'undefined') {
        closeParamName = 'close';
    }
    var id = 'main_' + res + preffix;
    
    var tabParam = 'tab_' + res;    
    var val = listx.getCheked(id, true);
    
    var res = res.split('_');
    
    if (typeof(res[1]) == 'undefined') {
        var act = new Array('');
    } else {
        var act = res[1].split('xxx');
    }
    
    $('.error')[0].style.display = 'none';
    
    var closeParam = '';
    for (i = 0; i < val.length; i++) {
        closeParam = closeParam + '&' + closeParamName + '[]=' + val[i];  
    }
    
    $.post('index.php',
        'module=' + res[0] + '&action=' + act[0] + closeParam + '&' + tabParam + '=' + activeTab,
        function(data) {
            if (data.error) {
                $('.error').html(data.error);
                $('.error')[0].style.display = 'block';
            } else {
                load('index.php?module=' + res[0] + '&action=' + act[0] + '&' + tabParam + '=' + activeTab);
            }
        },
        "json"
    );
}


/**
 * @param id
 */
function dateBlur(id) {
    if (document.getElementById('date_' + id)) {
        document.getElementById('date_' + id).value = document.getElementById(id + '_year').value + '-' + document.getElementById(id + '_month').value + '-' +document.getElementById(id + '_day').value;
        if (document.getElementById('date_' + id).value == "--") document.getElementById('date_' + id).value = '';
    }
}


/**
 * @param evt
 * @returns {boolean}
 */
function dateInt(evt) {
    var code = evt.charCode;
    if (document.all) {
        code = evt.keyCode;
    }
    var av = [0,48,49,50,51,52,53,54,55,56,57];
    for (var i = 0; i < av.length; i++) {
        if (av[i] == code) return true;
    }
    return false;
}


var listx = {

    gMonths : ["","Январь","Февраль","Март","Апрель","Май","Июнь","Июль","Август","Сентябрь","Октябрь","Ноябрь","Декабрь"],
    loc : {},
    checkAllEvents: [],
    reloadEvents: [],

    /**
     * @param id
     */
    look: function (id) {
        $('#' + id).toggle();
    },


    /**
     * @param id
     * @param obj
     * @returns {boolean}
     */
    dateKeyup: function (id, obj) {
        if (obj.id === id + '_day' && Number(obj.value) > 31) {
            obj.value = '';
            obj.focus();
            return false;
        }
        if (obj.id === id + '_month' && Number(obj.value) > 12) {
            obj.value = '';
            obj.focus();
            return false;
        }
    },


    /**
     * @param cal
     */
    create_date:function (cal) {
        var t = $('#date_' + cal).val().substr(10);
        var opt = {
            firstDay: 1,
            currentText: 'Сегодня',
            dateFormat: 'yy-mm-dd',
            defaultDate: t,
            buttonImage: 'core2/html/' + coreTheme + '/img/calendar.png',
            buttonImageOnly: true,
            showOn: "button",
            onSelect: function (dateText, inst) {
                $('#date_' + cal).val(dateText + t);
                $('#' + cal + '_day').val(dateText.substr(8, 2));
                $('#' + cal + '_month').val(dateText.substr(5, 2));
                $('#' + cal + '_year').val(dateText.substr(0, 4));
                $('#cal' + cal).datepicker('destroy');
            },
            beforeShow: function (event, ui) {
                setTimeout(function () {
                    ui.dpDiv.css({ 'margin-top': '20px', 'margin-left': '-100px'});
                }, 5);
            }
        };
        $('#date_' + cal).datepicker(opt);
    },


    /**
     * @param id
     * @param caption
     */
    modalClose: function(id, caption) {
        parent.xxxx = {id:id, name:caption};
        parent.$.modal.close();
    },


    /**
     * @param obj
     * @param id
     * @param isAjax
     */
    pageSw: function(obj, id, isAjax) {
        var o = $('#pagin_' + id).find('input');
        o.value = obj.getAttribute('title');
        var container = '';
        var p = '_page_' + id + '=' + o.value;
        if (isAjax)    {
            container = document.getElementById("list" + id).parentNode;
            if (listx.loc[id].indexOf('&__') < 0) {
                if (container.id) {
                    location.hash = preloader.prepare(location.hash.substr(1) + '&--' + container.id + '=' + preloader.toJson(listx.loc[id] + "&" + p));
                }
            } else {
                load(listx.loc[id] + '&' + p, '', container, function () {
                    if (listx.reloadEvents.length > 0) {
                        $.each(listx.reloadEvents, function () {
                            if (this.list_id === id) {
                                this.func();
                            }
                        })
                    }
                    preloader.callback();
                });
            }
        }
        else load(listx.loc[id] + '&' + p, '', container, function () {
            if (listx.reloadEvents.length > 0) {
                $.each(listx.reloadEvents, function () {
                    if (this.list_id === id) {
                        this.func();
                    }
                })
            }
            preloader.callback();
        });
    },


    /**
     * @param obj
     * @param id
     * @param isAjax
     */
    goToPage: function(obj, id, isAjax) {
        var container = '';
        var o = $('#pagin_' + id).find('input');
        var p = '_page_' + id + '=' + o.val();
        if (isAjax)    {
            container = document.getElementById("list" + id).parentNode;
            if (listx.loc[id].indexOf('&__') < 0) {
                if (container.id) {
                    location.hash = preloader.prepare(location.hash.substr(1) + '&--' + container.id + '=' + preloader.toJson(listx.loc[id] + "&" + p));
                }
            } else {
                load(listx.loc[id] + '&' + p, '', container, function () {
                    if (listx.reloadEvents.length > 0) {
                        $.each(listx.reloadEvents, function () {
                            if (this.list_id === id) {
                                this.func();
                            }
                        })
                    }
                    preloader.callback();
                });
            }
        }
        else {
            load(listx.loc[id] + '&' + p, '', container, function () {
                if (listx.reloadEvents.length > 0) {
                    $.each(listx.reloadEvents, function () {
                        if (this.list_id === id) {
                            this.func();
                        }
                    })
                }
                preloader.callback();
            });
        }
    },


    /**
     * @param obj
     * @param id
     * @param isAjax
     */
    countSw: function(obj, id, isAjax) {
        var container = '';
        if (isAjax)    container = document.getElementById("list" + id).parentNode;
        var post = {};
        post['count_' + id] = obj.value;
        load(listx.loc[id], post, container, function () {
            if (listx.reloadEvents.length > 0) {
                $.each(listx.reloadEvents, function () {
                    if (this.list_id === id) {
                        this.func();
                    }
                })
            }
            preloader.callback();
        });
    },


    /**
     * @param $this
     * @param e
     */
    switch_active: function($this, e) {
        e.cancelBubble = true;
        var data = String($($this).attr('t_name'));
        var src = String($($this).attr('src'));
        var alt = $($this).attr('alt');
        var val = $($this).attr('val');
        if (alt == 'on') {
            var is_active = "N";
            var new_src   = src.replace("on.png", "off.png");
            var new_alt   = "off";
            var str       = "Деактивировать запись?";
        } else {
            var is_active = "Y";
            var new_src   = src.replace("off.png", "on.png");
            var new_alt   = "on";
            var str       = "Активировать запись?";
        }

        swal({
            title: str,
            type: is_active == 'Y' ? "info" : "warning",
            showCancelButton: true,
            confirmButtonColor: is_active == 'Y' ? '#5bc0de' : '#f0ad4e',
            confirmButtonText: "Да",
            cancelButtonText: "Нет"
        }).then(
            function(result) {
                $.post('index.php?module=admin&action=switch&loc=core', {
                    data:      data,
                    is_active: is_active,
                    value:     val
                }, function(data, textStatus) {
                    if (textStatus == 'success' && data.status == "ok") {
                        $($this).attr('src', new_src);
                        $($this).attr('alt', new_alt);
                    } else {
                        if (data.status) {
                            swal(data.status).catch(swal.noop);
                        }
                    }
                },
                'json');
            }, function(dismiss) {}
        );
    },


    /**
     * @param id
     * @param url
     * @param text
     * @param nocheck
     * @param obj
     * @param callback
     * @returns {boolean}
     */
    buttonAction : function(id, url, text, nocheck, obj, callback) {
        obj.disabled = true;
        obj.className = "buttonDisabled";
        if ( ! url) {
            alert('Временно недоступна.');
            obj.disabled = false;
            obj.className = "button";
            return;
        }
        var val = "";
        if ( ! nocheck) {
            var j = 1;
            for (var i = 0; i < j; i++) {
                if (document.getElementById("check" + id + i)) {
                    if (document.getElementById("check" + id + i).checked) {
                        val += document.getElementById("check" + id + i).value + ",";
                    }
                    j++;
                }
            }
        }
        if ( ! val && ! nocheck) {
            alert("Вы должны выбрать как минимум одну запись.");
            obj.disabled = false;
            obj.className = "button";
            return false;
        } else {
            if (text) {
                if (!confirm(text)) {
                    obj.disabled = false;
                    obj.className = "button";
                    return false;
                }
            }
            if (val && !nocheck) {
                val = val.slice(0, -1);
            }
            if ( ! callback) {
                callback = function(data) {
                    if (data && data.error) {
                        $('.error').html(data.error);
                        $('.error')[0].style.display = 'block';
                    } else {
                        load(url);
                    }
                }
            }
            $.post(url,
                {record_id: val},
                callback,
                "json"
            );
        }
        obj.className = "button";
        obj.disabled = false;

    },


    /**
     * @param id
     * @param returnArray
     * @returns {Array|string}
     */
    getCheked : function (id, returnArray) {
        var j = 1;
        if (returnArray == true) {
            var val = [];
        } else {
            var val = "";
        }

        for(var i = 0; i < j; i++) {
            if (document.getElementById("check" + id + i)) {
                if (document.getElementById("check" + id + i).checked) {
                    if (returnArray == true) {
                        val.push(document.getElementById("check" + id + i).value);
                    } else {
                        val += document.getElementById("check" + id + i).value + ",";
                    }
                }
                j++;
            }
        }
        return val;
    },


    /**
     * @param id
     * @param text
     * @param isAjax
     */
    del: function (id, text, isAjax) {
        var val = this.getCheked(id, true);
        if (val) {
            if (val.length) {
                swal({
                    title: text,
                    type: "warning",
                    showCancelButton: true,
                    confirmButtonColor: '#f0ad4e',
                    confirmButtonText: "Да",
                    cancelButtonText: "Нет"
                }).then(
                    function(result) {
                        preloader.show();
                        $("#main_" + id + "_error").hide();
                        var container = '';
                        if (isAjax) {
                            container = document.getElementById("list" + id).parentNode;
                        }
                        if (listx.loc[id]) {
                            $.ajax({
                                method: "DELETE",
                                dataType: "json",
                                url: "index.php?res=" + id + "&id=" + val,
                                success: function (data) {
                                    if (data === true) {
                                        load(listx.loc[id], '', container, function () {
                                            if (listx.reloadEvents.length > 0) {
                                                $.each(listx.reloadEvents, function () {
                                                    if (this.list_id === id) {
                                                        this.func();
                                                    }
                                                })
                                            }
                                            preloader.callback();
                                        });
                                    } else {
                                        if (!data || data.error) {
                                            var msg = data.error ? data.error : "Не удалось выполнить удаление";
                                            $("#main_" + id + "_error").html(msg);
                                            $("#main_" + id + "_error").show();
                                        } else {
                                            if (data.alert) {
                                                alert(data.alert);
                                            }
                                            if (data.loc) {
                                                load(data.loc, '', container, function () {
                                                    if (listx.reloadEvents.length > 0) {
                                                        $.each(listx.reloadEvents, function () {
                                                            if (this.list_id === id) {
                                                                this.func();
                                                            }
                                                        })
                                                    }
                                                    preloader.callback();
                                                });
                                            }
                                        }
                                    }
                                }
                            }).fail(function () {
                                swal("Не удалось выполнить удаление", '', 'error').catch(swal.noop);
                            }).always(function () {
                                preloader.hide();
                            });
                        }
                    }, function(dismiss) {}
                );
            } else {
                swal('Нужно выбрать хотя бы одну запись', '', 'warning').catch(swal.noop);
            }
        }
    },


    /**
     * @param e
     * @param id
     * @returns {boolean}
     */
    cancel : function (e, id) {
        e.cancelBubble = true;
        if (id) listx.checkChecked(id);
        return false;
    },


    /**
     * @param e
     * @param id
     * @returns {boolean}
     */
    cancel2 : function (e, id) {
        e.cancelBubble = true;
        this.look(id);
        return false;
    },


    /**
     * @param id
     */
    checkChecked : function (id) {
        var obj = document.getElementById("edit_" + id);
        if (obj) {
            var j = 1;
            var gotit = 0;
            for (var i = 0; i < j; i++) {
                if (document.getElementById("check" + id + i)) {
                    if (gotit >= 2) break;
                    if (document.getElementById("check" + id + i).checked) {
                        gotit++;
                    }
                    j++;
                }
            }
            if (gotit >= 2) obj.style.display = '';
            else obj.style.display = 'none';
        }

    },


    /**
     * @param obj
     * @param id
     */
    checkAll : function (obj, id) {
        var j = 1;
        var check = false;

        if (obj.checked) {
            check = true;
        }

        for(var i = 0; i < j; i++) {
            if (document.getElementById("check" + id + i)) {
                document.getElementById("check" + id + i).checked = check;
                j++;
            }
        }

        if (listx.checkAllEvents.length > 0) {
            $.each(listx.checkAllEvents, function () {
                if (this.list_id === id) {
                    this.func();
                }
            })
        }
    },


    /**
     * @param list_id
     * @param func
     */
    onCheckAll : function(list_id, func) {
        if (typeof func === 'function') {
            listx.checkAllEvents.push({
                list_id: list_id,
                func: func
            });
        }
    },


    /**
     * @param id
     */
    showFilter : function(id) {
        var f = $("#filter" + id);
        this.toggle(f);
        f.find("form")[0].elements[0].focus();

    },


    /**
     * @param f
     */
    toggle : function(f) {
        if (f.hasClass('hide')) {
            f.toggle('fast');
            f.removeClass('hide');
        } else {
            f.toggle('fast');
            f.addClass('hide');
        }
    },


    /**
     * @param id
     */
    columnFilter : function(id) {
        var f = $("#filterColumn" + id);
        this.toggle(f);
    },


    /**
     * @param id
     * @param isAjax
     */
    columnFilterStart : function(id, isAjax) {
        var o = $('#filterColumn' + id + ' form').find(':checkbox:checked');
        var l = o.length;
        var post = {};
        var t = [];

        for (var i = 0; i < l; i++) {
            t.push(o[i].value);
        }
        post['column_' + id] = t;
        var container = '';

        if (listx.loc[id]) {
            if (isAjax) {
                container = document.getElementById("list" + id).parentNode;
                load(listx.loc[id] + '&__filter=1', post, container, function () {
                    if (listx.reloadEvents.length > 0) {
                        $.each(listx.reloadEvents, function () {
                            if (this.list_id === id) {
                                this.func();
                            }
                        })
                    }
                    preloader.callback();
                });

            } else {
                load(listx.loc[id], post, container, function () {
                    if (listx.reloadEvents.length > 0) {
                        $.each(listx.reloadEvents, function () {
                            if (this.list_id === id) {
                                this.func();
                            }
                        })
                    }
                    preloader.callback();
                });
            }
        }
    },


    /**
     * @param id
     * @param isAjax
     */
    clearFilter: function(id, isAjax) {
        var post = {};
        post['clear_form' + id] = 1;
        var container = '';
        if (listx.loc[id]) {
            if (isAjax) {
                container = document.getElementById("list" + id).parentNode;
                load(listx.loc[id] + '&__clear=1', post, container, function () {
                    if (listx.reloadEvents.length > 0) {
                        $.each(listx.reloadEvents, function () {
                            if (this.list_id === id) {
                                this.func();
                            }
                        })
                    }
                    preloader.callback();
                });
            } else {
                load(listx.loc[id], post, container, function () {
                    if (listx.reloadEvents.length > 0) {
                        $.each(listx.reloadEvents, function () {
                            if (this.list_id === id) {
                                this.func();
                            }
                        })
                    }
                    preloader.callback();
                });
            }
        }
    },


    /**
     * @param id
     * @param isAjax
     */
    startSearch : function(id, isAjax) {
        var allInputs = $("#filter" + id).find(":input");
        var l = allInputs.length;
        var post = {};
        for (var i = 0; i < l; i++) {
            post[allInputs[i].name] = allInputs[i].value;
        }
        post = allInputs.serializeArray();
        var container = '';

        if (listx.loc[id]) {
            if (isAjax) {
                container = document.getElementById("list" + id).parentNode;
                load(listx.loc[id] + '&__search=1', post, container, function () {
                    if (listx.reloadEvents.length > 0) {
                        $.each(listx.reloadEvents, function () {
                            if (this.list_id === id) {
                                this.func();
                            }
                        })
                    }
                    preloader.callback();
                });
            } else {
                load(listx.loc[id], post, container, function () {
                    if (listx.reloadEvents.length > 0) {
                        $.each(listx.reloadEvents, function () {
                            if (this.list_id === id) {
                                this.func();
                            }
                        })
                    }
                    preloader.callback();
                });
            }
        }
    },


    /**
     * @param id
     * @param data
     * @param isAjax
     */
    doOrder : function(id, data, isAjax) {
        var container = '';
        var post = {};
        post['orderField_main_' + id] = data;
        if (listx.loc[id]) {
            if (isAjax) {
                container = document.getElementById("list" + id).parentNode;
                load(listx.loc[id] + '&__order=1', post, container, function () {
                    if (listx.reloadEvents.length > 0) {
                        $.each(listx.reloadEvents, function () {
                            if (this.list_id === id) {
                                this.func();
                            }
                        })
                    }
                    preloader.callback();
                });
            } else {
                load(listx.loc[id], post, container, function () {
                    if (listx.reloadEvents.length > 0) {
                        $.each(listx.reloadEvents, function () {
                            if (this.list_id === id) {
                                this.func();
                            }
                        })
                    }
                    preloader.callback();
                });
            }
        }
    },


    /**
     * @param id
     * @param tbl
     */
    initSort : function(id, tbl) {
        $("#list" + id + " > table > tbody").sortable({
            opacity: 0.6,
            distance: 5,
            cursor: "move",
            start: function (event, ui) {
                ui.helper.click(function (event) {
                    event.stopImmediatePropagation();
                    event.stopPropagation();
                    return false;
                });
            },
            update : function (event, ui) {

                var src = ui.item[0].parentNode.childNodes;
                var so = [];
                if (src) {
                    for (var k in src) {
                        if (src[k].childNodes && src[k].childNodes.length) {
                            var el = src[k].childNodes[0];
                            if (el && el.nodeName === "TD") {
                                if (typeof el.getAttribute === "function") {
                                    var id = el.getAttribute("title");
                                    if (id) {
                                        so.push(id);
                                    }
                                }
                            }
                        }
                    }
                }
                $.post("index.php?module=admin&action=seq",
                    {data : so, tbl : tbl},
                    function (data, textStatus) {
                        if (textStatus !== 'success') {
                            alert(textStatus);
                        } else {
                            if (data && data.error) {
                                swal(data.error, '', 'error').catch(swal.noop);
                            }
                        }
                    },
                    "json"
                );
            }
        });
        $("#list" + id + " tbody").disableSelection();
    },


    /**
     * @param list_id
     * @param func
     */
    onReload : function(list_id, func) {
        if (typeof func === 'function') {
            listx.reloadEvents.push({
                list_id: list_id,
                func: func
            });
        }
    },


    /**
     * @param id
     */
    fixHead: function (id) {
        $('#' + id + ' table').floatThead({scrollingTop: 50, zIndex: 1, headerCellSelector: 'tr.headerText>td:visible'})
    }
};