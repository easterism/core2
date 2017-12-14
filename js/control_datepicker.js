
var control_datepicker = {

    defaults_options : {
        firstDay: 1,
        dateFormat: 'yy-mm-dd'
    },


    /**
     * Создание календаря
     * @param {object} wrapper
     * @param {object} options
     */
    create : function(wrapper, options) {
        var $input = $('.ctrl-dp-value', wrapper);
        control_datepicker.createDate($input, wrapper);
        control_datepicker.createEvents(wrapper);


        var settings = $.extend({}, control_datepicker.defaults_options, options);
        settings['defaultDate']   = $input.val();
        settings['beforeShowDay'] = function(date) {
            try {
                var date1 = $.datepicker.parseDate(settings.dateFormat, $input.val());
            } catch (err) {
                date1 = '';
            }
            var classes = date1 && date.getTime() == date1.getTime()
                ? ' ctrl-dp-highlight '
                : '';
            var classes2 = control_datepicker.callbackDayClass(date);
            classes += classes2 ? ' ' + classes2 + ' ' : '';
            return [true, classes];
        };

        settings['onSelect'] = function(dateNew, inst) {
            var dateOld = $input.val();
            $input.val(dateNew);
            control_datepicker.createDate($input, wrapper);
            control_datepicker.callbackChange(dateNew, dateOld, wrapper);
            $('.ctrl-dp-container', wrapper).hide('fast');
        };


        $('.ctrl-dp-container', wrapper).datepicker(settings);
    },


    /**
     * Обновление календаря
     * @param {object} wrapper
     */
    rebuildCalendar : function(wrapper) {
        var input_value = $('.ctrl-dp-value', wrapper).val();
        $('.ctrl-dp-container', wrapper).datepicker('setDate', input_value);
    },


    /**
     * Заполнение значения
     * @param {object} wrapper
     */
    dateBlur : function(wrapper) {

        var day   = $('.ctrl-dp-day', wrapper).val();
        var month = $('.ctrl-dp-month', wrapper).val();
        var year  = $('.ctrl-dp-year', wrapper).val();

        day   = Number(day) > 0 ? day : '';
        month = Number(month) > 0 ? month : '';
        month  = Number(month) > 0 ? month : '';

        day   = day.length > 2 ? day.substr(0, 2) : day;
        month = month.length > 2 ? month.substr(0, 2) : month;
        year  = year.length > 4 ? year.substr(0, 4) : year;

        day   = day   ? '00'.substring(0, 2 - day.length) + day     : '';
        month = month ? '00'.substring(0, 2 - month.length) + month : '';
        year  = year  ? '0000'.substring(0, 4 - year.length) + year : '';

        var date = '';
        if (year !== '' && month !== '' && day !== '') {
            date = year + '-' + month + '-' + day;
        }

        $('.ctrl-dp-value', wrapper).val(date);
        control_datepicker.callbackChange(date, '', wrapper);
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

        $('.ctrl-dp-day', wrapper).val(date[2] ? date[2] : '');
        $('.ctrl-dp-month', wrapper).val(date[1] ? date[1] : '');
        $('.ctrl-dp-year', wrapper).val(date[0] ? date[0] : '');
    },


    /**
     * Валидация
     * @param e
     */
    eventKeyPress : function (e) {
        var keyCode;
        if (e.keyCode) keyCode = e.keyCode;
        else if(e.which) keyCode = e.which;
        var av = new Array(8, 9, 35, 36, 37, 38, 39, 40, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57);
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

        if (control_datepicker.hasClass(input, 'ctrl-dp-day')) {
            var month    = parseInt($('.ctrl-dp-month', wrapper).val());
            var year     = parseInt($('.ctrl-dp-year', wrapper).val());
            var last_day = 31;

            if (month > 0) {
                last_day = 32 - new Date(year, month - 1, 32).getDate();
            }
            if (input.value.length > 2) {
                input.value = input.value.substr(0, 2);
            }
            if (input.value == '00' || Number(input.value) < 0) {
                input.value = '01';
            } else if (Number(input.value) > last_day) {
                input.value = last_day;
            } else if (input.value == '') {
                input.value = '';
            }

        } else if (control_datepicker.hasClass(input, 'ctrl-dp-month')) {
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

        } else if (control_datepicker.hasClass(input, 'ctrl-dp-year')) {
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
        $('.ctrl-dp-trigger', wrapper).click(function(){
            if ($('.ctrl-dp-container', wrapper).is(':visible')) {
                $('.ctrl-dp-container', wrapper).hide('fast');
            } else {
                $('.ctrl-dp-container', wrapper).show('fast');
            }
            return false;
        });

        /**
         * Очистка даты
         */
        $('.ctrl-dp-clear', wrapper).click(function() {
            $('.ctrl-dp-value', wrapper).val('');
            $('.ctrl-dp-day', wrapper).val('');
            $('.ctrl-dp-month', wrapper).val('');
            $('.ctrl-dp-year', wrapper).val('');

            control_datepicker.callbackChange('', '', wrapper);
            $('.ctrl-dp-container', wrapper).datepicker('refresh');
        });


        /**
         * Валидация
         */
        $('.ctrl-dp-day, .ctrl-dp-month, .ctrl-dp-year', wrapper).keyup(function(event) {
            control_datepicker.eventKeyUp(this, wrapper);

            var keyCode;
            if (event.keyCode) keyCode = event.keyCode;
            else if (event.which) keyCode = event.which;
            var av = new Array(48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105);
            for (var i = 0; i < av.length; i++) {
                if (av[i] == keyCode) {
                    if ($(event.currentTarget).hasClass("ctrl-dp-day") ||
                        $(event.currentTarget).hasClass("ctrl-dp-month")
                    ) {
                        if ($(this, wrapper).val().length >= 2) {
                            event.preventDefault();
                            control_datepicker.nextFocus(this, wrapper);
                        }

                    } else {
                        if ($(this, wrapper).val().length >= 4) {
                            event.preventDefault();
                            control_datepicker.nextFocus(this, wrapper);
                        }
                    }
                    break;
                }
            }
        });
        $('.ctrl-dp-day, .ctrl-dp-month, .ctrl-dp-year', wrapper).keypress(function(event) {
            control_datepicker.eventKeyPress(event);
        });
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
            $('input[class*="ctrl-dp-"]:visible, select[class*="ctrl-dp-"]:visible', wrapper).each(function () {
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
     * Выполнение функции после изменения даты
     */
    callbackChange : function(date, date2, wrapper) {
        if (typeof this.callback_change == 'function') {
            this.callback_change(date, date2, wrapper);
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
        if ($(target).parents('.ctrl-dp-container, .ui-datepicker-group, .ui-datepicker-next, .ui-datepicker-prev').length) {
            return false;

        } else if ($('.ctrl-dp-container').is(':visible')) {
            $('.ctrl-dp-container:visible').hide('fast');
        }
    });
});