

function lastFocus(f){
	f.elements[f.elements.length - 1].focus();
}

//var isIE = window.navigator.userAgent.indexOf("MSIE")>-1;

var edit = {
	ev: {},
	xfiles: {},
	dateBlur : function(id) {
		var year = document.getElementById(id + '_year').value;
		var month = document.getElementById(id + '_month').value;
		var day = document.getElementById(id + '_day').value;
		document.getElementById('date_' + id).value = year + '-' + month + '-' + day;
		if (document.getElementById(id + '_hour')) {
			var h = document.getElementById(id + '_hour').value;
			var m = document.getElementById(id + '_min').value;
			if (!h) {
				if (m) {
					h = '00';
					document.getElementById(id + '_hour').value = h;
				}
			}
			else if (!m) {
				m = '00';
			}
			if (h && m) {
				document.getElementById('date_' + id).value += ' ' + h + ':' + m;
			}
		}
	},
	radioClick : function(o) {
		o.blur();
		o.focus();
	},
	dateKeyup : function (id, obj) {
		if (obj.id == id + '_day' && Number(obj.value) > 31) {
			obj.value = 31;
			obj.focus();
		}
		if (obj.id == id + '_month' && Number(obj.value) > 12) {
			obj.value = 12;
			obj.focus();
		}
		this.dateBlur(id);
	},
	timeKeyup : function (id, obj) {
		if (obj.id == id + '_hour' && Number(obj.value) > 23) {
			obj.value = 23;
			obj.focus();
		}
		if (obj.id == id + '_min' && Number(obj.value) > 59) {
			obj.value = 59;
			obj.focus();
		}
		this.dateBlur(id);
	},
	onsubmit: function (obj) {
		lastFocus(obj);
		if (typeof PrepareSave == 'function') {
			PrepareSave();
		}
	},
	showMark : function(obj) {
		$(obj).find('.helpMark').show();
	},
	hideMark : function(obj) {
		$(obj).find('.helpMark').hide();
	},
	create_date: function (cal) {
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
		}
		if (this.ev[cal]) {
			opt['beforeShowDay'] = function (day) {
				var s = "";
				var t = "";
				var v = day.valueOf();
				if (edit.ev[cal][v]) {
					if (edit.ev[cal][v][0]["title"]) {
						t = edit.ev[cal][v][0]["title"];
					}
					if (edit.ev[cal][v][0]["disabled"]) {
						s = "ui-datepicker-unselectable ui-state-disabled";
					}
					if (edit.ev[cal][v][0]["cssclass"]) {
						s += " " + edit.ev[cal][v][0]["cssclass"];
					}
				}
				return [true, s, t];
			}
		}
		$('#date_' + cal).datepicker(opt);
	},
	create_datetime: function (cal) {

			var h = $('#date_' + cal).val().substr(11, 2);
			if (!h) h = '00';
			var m = $('#date_' + cal).val().substr(14, 2);
			if (!m) m = '00';
            var opt = {
                firstDay: 1,
                currentText: 'Сегодня',
                dateFormat: 'yy-mm-dd',
                timeFormat: 'HH:mm',
                defaultDate: $('#date_' + cal).val().substr(0, 10),
                hour: h,
                minute: m,
                buttonImage: 'core2/html/' + coreTheme + '/img/calendar.png',
                buttonImageOnly: true,
                showOn: "button",
                onSelect: function (dateText, inst) {
                    //$('#date_' + cal).val(dateText);
                    $('#' + cal + '_day').val(dateText.substr(8, 2));
                    $('#' + cal + '_month').val(dateText.substr(5, 2));
                    $('#' + cal + '_year').val(dateText.substr(0, 4));
                    $('#' + cal + '_hour').val(dateText.substr(11, 2));
                    $('#' + cal + '_min').val(dateText.substr(14, 2));

                },
                beforeShow: function (event, ui) {
                    setTimeout(function () {
                        ui.dpDiv.css({ 'margin-top': '20px', 'margin-left': '-180px'});
                    }, 5);
                }
            }
            if (this.ev[cal]) {
                opt['beforeShowDay'] = function (day) {
                    var s = "";
                    var t = "";
                    var v = day.valueOf();
                    if (block.ev[cal][v]) {
                        if (block.ev[cal][v][0]["title"]) {
                            t = block.ev[cal][v][0]["title"];
                        }
                        if (block.ev[cal][v][0]["disabled"]) {
                            s = "ui-datepicker-unselectable ui-state-disabled";
                        }
                        if (block.ev[cal][v][0]["cssclass"]) {
                            s += " " + block.ev[cal][v][0]["cssclass"];
                        }
                    }
                    return [true, s, t];
                }
            }
			//$('#cal' + cal).datetimepicker(opt);
			$('#date_' + cal).datetimepicker(opt);
	},
	docSize : function (){
		return [
		document.body.scrollWidth > document.body.offsetWidth ? 
			document.body.scrollWidth : document.body.offsetWidth,
		document.body.scrollHeight > document.body.offsetHeight ? 
			document.body.scrollHeight : document.body.offsetHeight
		];
	},
	getClientSize : function (){
		if(document.compatMode=='CSS1Compat')
			return [document.documentElement.clientWidth, document.documentElement.clientHeight];
		else
			return [document.body.clientWidth, document.body.clientHeight];
	},
	getDocumentScroll : function(){
		return [
		self.pageXOffset || (document.documentElement && document.documentElement.scrollLeft) 
			|| (document.body && document.body.scrollLeft),
		self.pageYOffset || (document.documentElement && document.documentElement.scrollTop) 
			|| (document.body && document.body.scrollTop)
		];
	},
	getCenter : function (){
		var sizes = this.getClientSize();
		var scrl  = this.getDocumentScroll();
		return [parseInt(sizes[0]/2)+scrl[0], parseInt(sizes[1]/2)+scrl[1]];
	},
	
	changePass : function(id) {
		var obj = document.getElementById(id);
		var obj2 = document.getElementById(id + '2');
		if (obj.disabled) {
			obj.disabled = false;
			obj.value='';
			obj2.disabled=false;
			obj2.value='';
			obj2.nextSibling.value = 'отмена';
		} else {
			obj.disabled = true;
			obj.value='******';
			obj2.disabled = true;
			obj2.value='******';
			obj2.nextSibling.value = 'изменить';
		}
	},
	
	displayError: function(id, text) {
		var obj = document.getElementById('main_' + id + '_error');
		if (obj) {
			obj.innerHTML = text;
			obj.style.display = 'block';
		}
	},
	changeButtonSwitch: function(obj) {
		var i = $(obj).find('img');
		if (document.getElementById(obj.id + 'hid').value == 'Y') {
			document.getElementById(obj.id + 'hid').value = 'N';
			for (var j = 0; j< i.length; j++) {
				if ($(i[j]).data('switch') == 'on') i[j].className = 'hide';
				else i[j].className = 'block';
			}
		} else {
			document.getElementById(obj.id + 'hid').value = 'Y';
			for (var j = 0; j < i.length; j++) {
				if ($(i[j]).data('switch') == 'on') i[j].className = 'block';
				else i[j].className = 'hide';
			}
		}
	},
	modalClear: function(id) {
		document.getElementById(id).value = '';
		document.getElementById(id + '_text').value = '';
	},
	maskMe: function(id, options) {
		options = $.extend({
			allowZero: true,
			thousands: ' ',
			defaultZero: false,
			allowNegative: true,
			precision: 0
		}, options);

		$('#' + id).maskMoney(options);
		$('#' + id).maskMoney('mask');
	},
    modal2: {
        key: '',

        load: function(theme_src, key, url) {
            this.key            = key;
            var modal_container = $('#' + this.key + '-modal').appendTo('#main_body');
            var $body_container = $('.modal-dialog>.modal-content>.modal-body', modal_container);

            $body_container.html(
                '<div style="text-align:center">' +
                    '<img src="' + theme_src + '/img/load.gif" alt="loading">' +
                    ' Загрузка' +
                '</div>'
            );

            $body_container.load(url);


            $('#' + this.key + '-modal').modal('show');
        },

        clear: function(key) {
            $('#' + key).val('');
            $('#' + key + '-title').val('');
        },

        hide: function() {
            $('#' + this.key + '-modal').modal('hide');
        },

        choose: function(value, title) {
            $('#' + this.key).val(value);
            $('#' + this.key + '-title').val(title);
            this.hide();
        }
    },

    /**
     * @param toggleObject
     */
    toggleGroup(toggleObject) {
        $(toggleObject).parent().next().slideToggle('fast');
    }
};


/**
 * @param id
 * @param opt
 */
function mceSetup(id, opt) {

    var options = {
		selector : '#' + id,
        language : 'ru',
        theme : 'modern',
        forced_root_block : false,
        force_br_newlines : true,
        force_p_newlines : false,
		verify_html : true,
        convert_urls : false,
        relative_urls : false,
        plugins: [
			"advlist autolink link image lists charmap print preview hr anchor pagebreak spellchecker",
			"searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking",
			"save table contextmenu directionality emoticons template paste textcolor"
		],
		toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | print preview media fullpage | forecolor backcolor emoticons",
		theme_advanced_resizing : true
       };
    for (k in opt) {
        options[k] = opt[k];
    }
    tinymce.remove();
	tinymce.init(options);
}
