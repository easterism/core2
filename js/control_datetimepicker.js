
var control_datetimepicker = {

    /**
     * Создание календаря
     * @param {object} wrapper
     */
    create : function(wrapper) {
        var $input = $('.ctrl-dtp-value', wrapper);
        control_datetimepicker.createDate($input, wrapper);
        control_datetimepicker.createEvents(wrapper);

        var dateFormat  = 'yy-mm-dd';
        $('.ctrl-dtp-container', wrapper).datepicker({
            firstDay: 1,
            dateFormat: dateFormat,
            defaultDate: $input.val(),
            beforeShowDay: function(date) {
                var date1   = $.datepicker.parseDate(dateFormat, $input.val());
                var classes = date1 && date.getTime() == date1.getTime()
                    ? ' ctrl-dtp-highlight '
                    : '';
                var classes2 = control_datetimepicker.callbackDayClass(date);
                classes += classes2 ? ' ' + classes2 + ' ' : '';
                return [true, classes];
            },
            onSelect: function(dateText, inst) {
                var hour   = $('.ctrl-dtp-hour', wrapper).val();
                var minute = $('.ctrl-dtp-minute', wrapper).val();

                if (hour != '' && minute != '') {
                    dateText += ' ' + hour + ':' + minute;
                } else if (hour != '' && minute == '') {
                    dateText += ' ' + hour + ':00';
                } else if (hour == '' && minute != '') {
                    dateText += ' 00:' + minute;
                }

                $input.val(dateText);
                control_datetimepicker.createDate($input, wrapper);
                control_datetimepicker.callbackChange(dateText);
                $('.ctrl-dtp-container', wrapper).hide('fast');
            }
        });
    },


    /**
     * Обновление календаря
     * @param {object} wrapper
     */
    rebuildCalendar : function(wrapper) {
        var input_value = $('.ctrl-dtp-value', wrapper).val();
        $('.ctrl-dtp-container', wrapper).datepicker('setDate', input_value);
    },


    /**
     * Заполнение значения
     * @param {object} wrapper
     */
    dateBlur : function(wrapper) {

        var day    = $('.ctrl-dtp-day', wrapper).val();
        var month  = $('.ctrl-dtp-month', wrapper).val();
        var year   = $('.ctrl-dtp-year', wrapper).val();
        var hour   = $('.ctrl-dtp-hour', wrapper).val();
        var minute = $('.ctrl-dtp-minute', wrapper).val();

        day     = Number(day) > 0 ? day : '';
        month   = Number(month) > 0 ? month : '';
        month   = Number(month) > 0 ? month : '';
        hour    = Number(hour) > 0 ? hour : '';
        minute  = Number(minute) > 0 ? minute : '';

        day    = day.length > 2 ? day.substr(-2, 2) : day;
        month  = month.length > 2 ? month.substr(-2, 2) : month;
        year   = year.length > 4 ? year.substr(-4, 4) : year;
        hour   = hour.length > 2 ? hour.substr(-2, 2) : hour;
        minute = minute.length > 2 ? minute.substr(-2, 2) : minute;

        day    = day    ? '00'.substring(0, 2 - day.length) + day       : '';
        month  = month  ? '00'.substring(0, 2 - month.length) + month   : '';
        year   = year   ? '0000'.substring(0, 4 - year.length) + year   : '';
        hour   = hour   ? '00'.substring(0, 2 - hour.length) + hour     : '';
        minute = minute ? '00'.substring(0, 2 - minute.length) + minute : '';

        var date = '';
        if (year !== '' && month !== '' && day !== '') {
            date = year + '-' + month + '-' + day;
            if (hour !== '' && minute !== '') {
                date += ' ' + hour + ':' + minute;
            } else if (hour !== '' && minute === '') {
                date += ' ' + hour + ':00';
            } else if (hour === '' && minute !== '') {
                date += ' 00:' + minute;
            }
        }

        $('.ctrl-dtp-value', wrapper).val(date);
        control_datetimepicker.callbackChange(date);
        this.rebuildCalendar(wrapper);
    },


    /**
     * Заполнение дат
     * @param {object} $input
     * @param {object} wrapper
     */
    createDate : function($input, wrapper) {

        var split = $input.val().split(' ');
        var date  = split[0].split('-');
        var time  = split[1] ? split[1].split(':') : [];

        $('.ctrl-dtp-day', wrapper).val(date[2] ? date[2] : '');
        $('.ctrl-dtp-month', wrapper).val(date[1] ? date[1] : '');
        $('.ctrl-dtp-year', wrapper).val(date[0] ? date[0] : '');
        $('.ctrl-dtp-hour', wrapper).val(time[0] ? time[0] : '');
        $('.ctrl-dtp-minute', wrapper).val(time[1] ? time[1] : '');
    },


    /**
     * Валидация
     * @param e
     */
    eventKeyPress : function (e) {
        var keyCode;
        if (e.keyCode) keyCode = e.keyCode;
        else if(e.which) keyCode = e.which;
        var av = new Array(8, 9, 35, 36, 37, 38, 40, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57);
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

        if (control_datetimepicker.hasClass(input, 'ctrl-dtp-day')) {
            var month    = parseInt($('.ctrl-dtp-month', wrapper).val());
            var year     = parseInt($('.ctrl-dtp-year', wrapper).val());
            var last_day = 31;

            if (month > 0) {
                last_day = 32 - new Date(year, month - 1, 32).getDate();
            }
            if (input.value.length > 2) {
                input.value = input.value.substr(-2, 2);
            }
            if (input.value == '00') {
                input.value = '01';
            } else if (Number(input.value) > last_day) {
                input.value = last_day;
            } else if (input.value == '') {
                input.value = '';
            } else if (Number(input.value) < 0) {
                input.value = 0;
            }
        } else if (control_datetimepicker.hasClass(input, 'ctrl-dtp-month')) {
            if (input.value.length > 2) {
                input.value = input.value.substr(-2, 2);
            }
            if (input.value == '00') {
                input.value = '01';
            } else if (Number(input.value) > 12) {
                input.value = 12;
            } else if (input.value == '') {
                input.value = '';
            } else if (Number(input.value) < 0) {
                input.value = 0;
            }

        } else if (control_datetimepicker.hasClass(input, 'ctrl-dtp-hour')) {
            if (input.value.length > 2) {
                input.value = input.value.substr(-2, 2);
            }

            if (Number(input.value) > 23) {
                input.value = 23;
            } else if (input.value == '') {
                input.value = '';
            } else if (Number(input.value) < 0) {
                input.value = 0;
            }

        } else if (control_datetimepicker.hasClass(input, 'ctrl-dtp-minute')) {
            if (input.value.length > 2) {
                input.value = input.value.substr(-2, 2);
            }

            if (Number(input.value) > 59) {
                input.value = 59;
            } else if (input.value == '') {
                input.value = '';
            } else if (Number(input.value) < 0) {
                input.value = 0;
            }

        } else if (control_datetimepicker.hasClass(input, 'ctrl-dtp-year')) {
            if (Number(input.value) > 9999) {
                input.value = 9999;
            } else if (input.value.length > 4) {
                input.value = input.value.substr(-4, 4);
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
        $('.ctrl-dtp-trigger', wrapper).click(function(){
            if ($('.ctrl-dtp-container', wrapper).is(':visible')) {
                $('.ctrl-dtp-container', wrapper).hide('fast');
            } else {
                $('.ctrl-dtp-container', wrapper).show('fast');
            }
            return false;
        });

        /**
         * Очистка даты
         */
        $('.ctrl-dtp-clear', wrapper).click(function() {
            $('.ctrl-dtp-value', wrapper).val('');
            $('.ctrl-dtp-day', wrapper).val('');
            $('.ctrl-dtp-month', wrapper).val('');
            $('.ctrl-dtp-year', wrapper).val('');
            $('.ctrl-dtp-hour', wrapper).val('');
            $('.ctrl-dtp-minute', wrapper).val('');

            control_datetimepicker.callbackChange('');
            $('.ctrl-dtp-container', wrapper).datepicker('refresh');
        });


        /**
         * Валидация
         */
        $('.ctrl-dtp-day, .ctrl-dtp-month, .ctrl-dtp-year, .ctrl-dtp-hour, .ctrl-dtp-minute', wrapper).keyup(function() {
            control_datetimepicker.eventKeyUp(this, wrapper);
        });
        $('.ctrl-dtp-day, .ctrl-dtp-month, .ctrl-dtp-year, .ctrl-dtp-hour, .ctrl-dtp-minute', wrapper).keypress(function(event) {
            control_datetimepicker.eventKeyPress(event);
        });
    },


    /**
     * Выполнение функции после изменения даты
     */
    callbackChange : function(date) {
        if (typeof this.callback_change == 'function') {
            this.callback_change(date);
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
        if ($(target).parents('.ctrl-dtp-container, .ui-datepicker-group, .ui-datepicker-next, .ui-datepicker-prev').length) {
            return false;

        } else if ($('.ctrl-dtp-container').is(':visible')) {
            $('.ctrl-dtp-container:visible').hide('fast');
        }
    });
});