

var control_datepicker = {

    /**
     * Создание календаря
     * @param {object} wrapper
     */
    create : function(wrapper) {
        var $input = $('.ctrl-dp-value', wrapper);
        control_datepicker.createDate($input, wrapper);
        control_datepicker.createEvents(wrapper);

        var dateFormat  = 'yy-mm-dd';
        $('.ctrl-dp-container', wrapper).datepicker({
            firstDay: 1,
            dateFormat: dateFormat,
            defaultDate: $input.val(),
            beforeShowDay: function(date) {
                var date1   = $.datepicker.parseDate(dateFormat, $input.val());
                var classes = date1 && date.getTime() == date1.getTime()
                    ? ' ctrl-dp-highlight '
                    : '';
                var classes2 = control_datepicker.callbackDayClass(date);
                classes += classes2 ? ' ' + classes2 + ' ' : '';
                return [true, classes];
            },
            onSelect: function(dateText, inst) {
                $input.val(dateText);
                control_datepicker.createDate($input, wrapper);
                control_datepicker.callbackChange(dateText);
                $('.ctrl-dp-container', wrapper).hide('fast');
            }
        });
    },


    /**
     * Обновление календаря
     * @param {object} wrapper
     */
    rebuildCalendar : function(wrapper) {
        var input_value = $('.ctrl-dp-value', wrapper).val();
        var dateFormat  = 'yy-mm-dd';
        $('.ctrl-dp-container', wrapper).datepicker( "option", "beforeShowDay", function(date) {
            var date1 = $.datepicker.parseDate(dateFormat, input_value);
            var classes = date1 && date.getTime() == date1.getTime()
                ? ' ctrl-dp-highlight '
                : '';
            var classes2 = control_datepicker.callbackDayClass(date);
            classes += classes2 ? ' ' + classes2 + ' ' : '';
            return [true, classes];
        });
    },


    /**
     * Заполнение значения
     * @param {object} wrapper
     */
    dateBlur : function(wrapper) {

        var day   = $('.ctrl-dp-day', wrapper).val();
        var month = $('.ctrl-dp-month', wrapper).val();
        var year  = $('.ctrl-dp-year', wrapper).val();

        day   = day   ? '00'.substring(0, 2 - day.length) + day     : '';
        month = month ? '00'.substring(0, 2 - month.length) + month : '';
        year  = year  ? '0000'.substring(0, 4 - year.length) + year : '';

        var date = '';
        if (year !== '' && month !== '' && day !== '') {
            date = year + '-' + month + '-' + day;
        }

        $('.ctrl-dp-value', wrapper).val(date);
        control_datepicker.callbackChange(date);
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

        if (control_datepicker.hasClass(input, 'ctrl-dp-day')) {
            var month    = parseInt($('.ctrl-dp-month', wrapper).val());
            var year     = parseInt($('.ctrl-dp-year', wrapper).val());
            var last_day = 31;

            if (month > 0) {
                last_day = 32 - new Date(year, month - 1, 32).getDate();
            }

            if (Number(input.value) > last_day) {
                input.value = last_day;
            } else if (input.value == '') {
                input.value = '';
            } else if (Number(input.value) < 1) {
                input.value = 1;
            }
        } else if (control_datepicker.hasClass(input, 'ctrl-dp-month')) {
            if (Number(input.value) > 12) {
                input.value = 12;
            } else if (input.value == '') {
                input.value = '';
            } else if (Number(input.value) < 1) {
                input.value = 1;
            }
        } else if (control_datepicker.hasClass(input, 'ctrl-dp-year') && Number(input.value) > 9999) {
            input.value = 9999;
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

            control_datepicker.callbackChange('');
            $('.ctrl-dp-container', wrapper).datepicker('refresh');
        });


        /**
         * Валидация
         */
        $('.ctrl-dp-day, .ctrl-dp-month, .ctrl-dp-year', wrapper).keyup(function() {
            control_datepicker.eventKeyUp(this, wrapper);
        });
        $('.ctrl-dp-day, .ctrl-dp-month, .ctrl-dp-year', wrapper).keypress(function(event) {
            control_datepicker.eventKeyPress(event);
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
        if ($(target).parents('.ctrl-dp-container, .ui-datepicker-group, .ui-datepicker-next, .ui-datepicker-prev').length) {
            return false;

        } else if ($('.ctrl-dp-container').is(':visible')) {
            $('.ctrl-dp-container:visible').hide('fast');
        }
    });
});