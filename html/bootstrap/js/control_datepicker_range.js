
var control_datepicker_range = {

    /**
     * Создание календаря
     * @param {object} wrapper
     */
    create : function(wrapper) {
        var $input_from = $('.ctrl-dpr-from-value', wrapper);
        var $input_to   = $('.ctrl-dpr-to-value', wrapper);

        control_datepicker_range.createTime($input_from, $input_to, wrapper);
        control_datepicker_range.createEvents(wrapper);

        var dateFormat = 'yy-mm-dd';
        $('.ctrl-dpr-container', wrapper).datepicker({
            firstDay: 1,
            defaultDate: $input_from.val(),
            numberOfMonths: 2,
            dateFormat: dateFormat,
            beforeShowDay: function(date) {
                try {
                    var date1 = $.datepicker.parseDate(dateFormat, $input_from.val());
                } catch (err) {
                    date1 = '';
                    $input_from.val('')
                }
                try {
                    var date2   = $.datepicker.parseDate(dateFormat, $input_to.val());
                } catch (err) {
                    date2 = '';
                    $input_to.val('')
                }
                var classes = date1 && ((date.getTime() == date1.getTime()) || (date2 && date >= date1 && date < date2))
                    ? 'ctrl-dpr-highlight'
                    : '';
                var classes2 = control_datepicker_range.callbackDayClass(date);
                classes += classes2 ? ' ' + classes2 + ' ' : '';
                return [true, classes];
            },
            onSelect: function(dateText, inst) {

                var date1 = $.datepicker.parseDate(dateFormat, $input_from.val());
                var date2 = $.datepicker.parseDate(dateFormat, $input_to.val());
                var selectedDate = $.datepicker.parseDate(dateFormat, dateText);


                if ( ! date1 || date2) {
                    $input_from.val(dateText);
                    $input_to.val("");

                } else if( selectedDate < date1 ) {
                    date1.setDate(date1.getDate() + 1);
                    var new_year  = date1.getFullYear();
                    var new_month = ('0' + (date1.getMonth() + 1)).slice(-2);
                    var new_day   = ('0' + (date1.getDate())).slice(-2);
                    var to_date_text = new_year + '-' + new_month + '-' + new_day;

                    $input_to.val( to_date_text );
                    $input_from.val( dateText );

                    control_datepicker_range.callbackChange(dateText, to_date_text, wrapper);

                } else {
                    selectedDate.setDate(selectedDate.getDate() + 1);
                    var new_year  = selectedDate.getFullYear();
                    var new_month = ('0' + (selectedDate.getMonth() + 1)).slice(-2);
                    var new_day   = ('0' + (selectedDate.getDate())).slice(-2);
                    dateText = new_year + '-' + new_month + '-' + new_day;

                    $input_to.val(dateText);
                    control_datepicker_range.callbackChange($input_from.val(), dateText, wrapper);
                }

                control_datepicker_range.createTime($input_from, $input_to, wrapper);
            }
        });
    },


    /**
     * Обновление календаря
     * @param {object} wrapper
     */
    rebuildCalendar : function(wrapper) {
        var $input_from = $('.ctrl-dpr-from-value', wrapper);
        var $input_to   = $('.ctrl-dpr-to-value', wrapper);
        var dateFormat  = 'yy-mm-dd';
        var $ctrl       = $('.ctrl-dpr-container', wrapper);

        $ctrl.datepicker('setDate', $input_from.val());
        $ctrl.datepicker( "option", "beforeShowDay", function(date) {
            var date1 = $.datepicker.parseDate(dateFormat, $input_from.val());
            var date2 = $.datepicker.parseDate(dateFormat, $input_to.val());
            var classes = date1 && ((date.getTime() == date1.getTime()) || (date2 && date >= date1 && date < date2))
                ? ' ctrl-dpr-highlight '
                : '';
            var classes2 = control_datepicker_range.callbackDayClass(date);
            classes += classes2 ? ' ' + classes2 + ' ' : '';
            return [true, classes];
        });
    },


    /**
     * Заполнение значения
     * @param {object} wrapper
     */
    dateBlur : function(wrapper) {

        var day_from   = $('.ctrl-dpr-from-day', wrapper).val();
        var month_from = $('.ctrl-dpr-from-month', wrapper).val();
        var year_from  = $('.ctrl-dpr-from-year', wrapper).val();

        var day_to   = $('.ctrl-dpr-to-day', wrapper).val();
        var month_to = $('.ctrl-dpr-to-month', wrapper).val();
        var year_to  = $('.ctrl-dpr-to-year', wrapper).val();


        day_from   = Number(day_from) > 0 ? day_from : '';
        month_from = Number(month_from) > 0 ? month_from : '';
        year_from  = Number(year_from) > 0 ? year_from : '';

        day_from   = day_from.length > 2 ? day_from.substr(0, 2) : day_from;
        month_from = month_from.length > 2 ? month_from.substr(0, 2) : month_from;
        year_from  = year_from.length > 4 ? year_from.substr(0, 4) : year_from;

        day_from   = day_from   ? '00'.substring(0, 2 - day_from.length) + day_from     : '';
        month_from = month_from ? '00'.substring(0, 2 - month_from.length) + month_from : '';
        year_from  = year_from  ? '0000'.substring(0, 4 - year_from.length) + year_from : '';


        day_to   = Number(day_to) > 0 ? day_to : '';
        month_to = Number(month_to) > 0 ? month_to : '';
        year_to  = Number(year_to) > 0 ? year_to : '';

        day_to   = day_to.length > 2 ? day_to.substr(0, 2) : day_to;
        month_to = month_to.length > 2 ? month_to.substr(0, 2) : month_to;
        year_to  = year_to.length > 4 ? year_to.substr(0, 4) : year_to;

        day_to   = day_to   ? '00'.substring(0, 2 - day_to.length) + day_to     : '';
        month_to = month_to ? '00'.substring(0, 2 - month_to.length) + month_to : '';
        year_to  = year_to  ? '0000'.substring(0, 4 - year_to.length) + year_to : '';


        var date_from = '';
        if (year_from !== '' && month_from !== '' && day_from !== '') {
            date_from = year_from + '-' + month_from + '-' + day_from;
        }
        $('.ctrl-dpr-from-value', wrapper).val(date_from);

        var date_to = '';
        if (year_to !== '' && month_to !== '' && day_to !== '') {
            date_to = year_to + '-' + month_to + '-' + day_to;
        }
        $('.ctrl-dpr-to-value', wrapper).val(date_to);

        control_datepicker_range.callbackChange(date_from, date_to, wrapper);
        this.rebuildCalendar(wrapper);
    },


    /**
     * Заполнение дат
     * @param {object} $input_from
     * @param {object} $input_to
     * @param {object} wrapper
     */
    createDate : function($input_from, $input_to, wrapper) {

        var split_from = $input_from.val().split(' ');
        var split_to   = $input_to.val().split(' ');

        var date_from = split_from[0].split('-');
        var date_to   = split_to[0].split('-');

        $('.ctrl-dpr-from-day', wrapper).val(date_from[2] ? date_from[2] : '');
        $('.ctrl-dpr-from-month', wrapper).val(date_from[1] ? date_from[1] : '');
        $('.ctrl-dpr-from-year', wrapper).val(date_from[0] ? date_from[0] : '');

        $('.ctrl-dpr-to-day', wrapper).val(date_to[2] ? date_to[2] : '');
        $('.ctrl-dpr-to-month', wrapper).val(date_to[1] ? date_to[1] : '');
        $('.ctrl-dpr-to-year', wrapper).val(date_to[0] ? date_to[0] : '');
    },


    /**
     * Валидация
     * @param e
     */
    eventKeyPress : function (e) {
        var keyCode;
        if (e.keyCode) keyCode = e.keyCode;
        else if(e.which) keyCode = e.which;
        var av = [8, 9, 35, 36, 37, 38, 39, 40, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57];
        for (var i = 0; i < av.length; i++) {
            if (av[i] == keyCode) {
                return;
            }
        }
        e.preventDefault();
    },


    /**
     * Проверка наличия класса
     * @param   {object}  element
     * @param   {string}  cls
     * @returns {boolean}
     */
    hasClass : function (element, cls) {
        return (' ' + element.className + ' ').indexOf(' ' + cls + ' ') > -1;
    },


    /**
     * Валидация
     * @param {object} input
     * @param {object} wrapper
     */
    eventKeyUp : function(input, wrapper) {

        if (control_datepicker_range.hasClass(input, 'ctrl-dpr-from-day')) {
            var from_month    = parseInt($('.ctrl-dpr-from-month', wrapper).val());
            var from_year     = parseInt($('.ctrl-dpr-from-year', wrapper).val());
            var from_last_day = 31;

            if (from_month > 0) {
                from_last_day = 32 - new Date(from_year, from_month - 1, 32).getDate();
            }
            if (input.value.length > 2) {
                input.value = input.value.substr(0, 2);
            }
            if (input.value == '00' || Number(input.value) < 0) {
                input.value = '01';
            } else if (Number(input.value) > from_last_day) {
                input.value = from_last_day;
            } else if (input.value == '') {
                input.value = '';
            }

        } else if (control_datepicker_range.hasClass(input, 'ctrl-dpr-to-day')) {
            var to_month    = parseInt($('.ctrl-dpr-to-month', wrapper).val());
            var to_year     = parseInt($('.ctrl-dpr-to-year', wrapper).val());
            var to_last_day = 31;

            if (to_month > 0) {
                to_last_day = 32 - new Date(to_year, to_month - 1, 32).getDate();
            }
            if (input.value.length > 2) {
                input.value = input.value.substr(0, 2);
            }
            if (input.value == '00' || Number(input.value) < 0) {
                input.value = '01';
            } else if (Number(input.value) > to_last_day) {
                input.value = to_last_day;
            } else if (input.value == '') {
                input.value = '';
            }
            
        } else if (control_datepicker_range.hasClass(input, 'ctrl-dpr-from-month') ||
                   control_datepicker_range.hasClass(input, 'ctrl-dpr-to-month')
        ) {
            if (input.value.length > 2) {
                input.value = input.value.substr(0, 2);
            }
            if (input.value == '00' || Number(input.value) < 0) {
                input.value = '01';
            } else if (Number(input.value) > 12) {
                input.value = 12;
            } else if (input.value == '') {
                input.value = '';
            }

        } else if (control_datepicker_range.hasClass(input, 'ctrl-dpr-from-year') ||
                   control_datepicker_range.hasClass(input, 'ctrl-dpr-to-year')
        ) {
            if (input.value.length > 4) {
                input.value = input.value.substr(0, 4);
            }
        }
        input.focus();
        this.dateBlur(wrapper);
    },


    /**
     * Установка событий
     * @param {object} wrapper
     */
    createEvents : function(wrapper) {

        /**
         * Показ/скрытие календаря
         */
        $('.ctrl-dpr-trigger', wrapper).click(function(){
            if ($('.ctrl-dpr-container', wrapper).is(':visible')) {
                $('.ctrl-dpr-container', wrapper).hide('fast');
            } else {
                $('.ctrl-dpr-container', wrapper).show('fast');
            }
            return false;
        });

        /**
         * Очистка даты
         */
        $('.ctrl-dpr-clear', wrapper).click(function() {
            $('.ctrl-dpr-from-value, .ctrl-dpr-to-value', wrapper).val('');
            $('.ctrl-dpr-from-day, .ctrl-dpr-to-day', wrapper).val('');
            $('.ctrl-dpr-from-month, .ctrl-dpr-to-month', wrapper).val('');
            $('.ctrl-dpr-from-year, .ctrl-dpr-to-year', wrapper).val('');

            control_datepicker_range.callbackChange('', '', wrapper);
            $('.ctrl-dpr-container', wrapper).datepicker('refresh');
        });


        /**
         * Валидация
         */
        $('.ctrl-dpr-from-day, .ctrl-dpr-from-month, .ctrl-dpr-from-year', wrapper).keyup(function(event) {
            control_datepicker_range.eventKeyUp(this, wrapper);

            var keyCode;
            if (event.keyCode) keyCode = event.keyCode;
            else if (event.which) keyCode = event.which;
            var av = [48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105];
            for (var i = 0; i < av.length; i++) {
                if (av[i] == keyCode) {
                    if ($(event.currentTarget).hasClass("ctrl-dpr-from-day") ||
                        $(event.currentTarget).hasClass("ctrl-dpr-from-month")
                    ) {
                        if ($(this, wrapper).val().length >= 2) {
                            event.preventDefault();
                            control_datepicker_range.nextFocus(this, wrapper);
                        }

                    } else {
                        if ($(this, wrapper).val().length >= 4) {
                            event.preventDefault();
                            control_datepicker_range.nextFocus(this, wrapper);
                        }
                    }
                    break;
                }
            }


        });
        $('.ctrl-dpr-to-day, .ctrl-dpr-to-month, .ctrl-dpr-to-year', wrapper).keyup(function(event) {
            control_datepicker_range.eventKeyUp(this, wrapper);

            var keyCode;
            if (event.keyCode) keyCode = event.keyCode;
            else if (event.which) keyCode = event.which;
            var av = [48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105];
            for (var i = 0; i < av.length; i++) {
                if (av[i] == keyCode) {
                    if ($(event.currentTarget).hasClass("ctrl-dpr-to-day") ||
                        $(event.currentTarget).hasClass("ctrl-dpr-to-month")
                    ) {
                        if ($(this, wrapper).val().length >= 2) {
                            event.preventDefault();
                            control_datepicker_range.nextFocus(this, wrapper);
                        }

                    } else {
                        if ($(this, wrapper).val().length >= 4) {
                            event.preventDefault();
                            control_datepicker_range.nextFocus(this, wrapper);
                        }
                    }
                    break;
                }
            }
        });
        $('.ctrl-dpr-from-day, .ctrl-dpr-from-month, .ctrl-dpr-from-year', wrapper).keypress(function(event) {
            control_datepicker_range.eventKeyPress(event);
        });
        $('.ctrl-dpr-to-day, .ctrl-dpr-to-month, .ctrl-dpr-to-year', wrapper).keypress(function(event) {
            control_datepicker_range.eventKeyPress(event);
        });
    },


    /**
     * Выполнение функции после изменения даты
     */
    callbackChange : function(date_from, date_to, wrapper) {
        if (typeof this.callback_change == 'function') {
            this.callback_change(date_from, date_to, wrapper);
        }
    },


    /**
     * Фокусировка на следующем контроле
     * @param {object} currentTarget
     * @param {object} wrapper
     */
    nextFocus: function(currentTarget, wrapper) {

        var now = new Date().getTime() / 1000;

        if ( ! this.lastNextChange || this.lastNextChange + 0.3 < now) {
            this.lastNextChange = now;
            var isFind = false;
            $('input[class*="ctrl-dpr-"]:visible, select[class*="ctrl-dpr-"]:visible', wrapper).each(function () {
                if (isFind === false) {
                    if (currentTarget == this) {
                        isFind = true;
                    }
                } else {
                    $(this).focus().select();
                    return false;
                }
            });
        }
    },


    /**
     * Функция выполняющаяся после изменения даты
     * @param func
     */
    setCallbackChange : function(func) {
        if (typeof func == 'function') {
            this.callback_change = func;
        }
    },


    /**
     * Выполнение функции для раскраски календаря
     */
    callbackDayClass : function(date) {
        if (typeof this.callback_day_class == 'function') {
            return this.callback_day_class(date);
        }
    },


    /**
     * Функция выполняющаяся для раскраски календаря
     * @param func
     */
    setCallbackDayClass : function(func) {
        if (typeof func == 'function') {
            this.callback_day_class = func;
        }
    }
};



$(document).ready(function(){
    /**
     * Cкрытие календаря
     */
    $(document).click(function(e) {
        var target = $(e.target);
        if ($(target).parents('.ctrl-dpr-container, .ui-datepicker-group, .ui-datepicker-next, .ui-datepicker-prev').length) {
            return false;

        } else if ($('.ctrl-dpr-container').is(':visible')) {
            $('.ctrl-dpr-container:visible').hide('fast');
        }
    });
});