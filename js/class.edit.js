
function dateBlur(id) {
	var year = document.getElementById(id + '_year').value;
	var month = document.getElementById(id + '_month').value;
	var day = document.getElementById(id + '_day').value;
	if (year && month && day) {
		document.getElementById('date_' + id).value = year + '-' + month + '-' + day;
	}
}
function dateKeyup(id, obj) {
	if (obj.id == id + '_day' && Number(obj.value) > 31) {
		obj.value = ''; 
		obj.focus(); 
		return;
	}
	if (obj.id == id + '_month' && Number(obj.value) > 12) {
		obj.value = ''; 
		obj.focus(); 
		return;
	}
}
function timeBlur(id) {
	dateBlur(id);
	document.getElementById('date_' + id).value += ' ' + document.getElementById(id + '_hour').value + ':' + document.getElementById(id + '_min').value; 
}
function timeKeyup(id, obj) {
	if (obj.id == id + '_hour' && Number(obj.value) > 23) {
		obj.value = ''; 
		obj.focus(); 
		return;
	}
	if (obj.id == id + '_min' && Number(obj.value) > 59) {
		obj.value = ''; 
		obj.focus(); 
		return;
	}
}

function lastFocus(f){
	f.elements[f.elements.length - 1].focus();
}

//var isIE = window.navigator.userAgent.indexOf("MSIE")>-1;

function radioClick(o) {
	o.blur();
	o.focus();
}

var edit = {
	onsubmit: function (obj) {
		lastFocus(obj);
		if (typeof PrepareSave == 'function') {
			PrepareSave();
		}
	},
	ev: {},
	xfiles: {},
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
			buttonImage: 'core2/html/default/img/calendar.png',
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
                buttonImage: 'core2/html/default/img/calendar.png',
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
	}
	
}
function changeButtonSwitch(obj) {
	if (document.getElementById(obj.id + 'hid').value == 'Y') {
		document.getElementById(obj.id + 'hid').value = 'N';
		document.getElementById('switch_on').className = 'hide';
		document.getElementById('switch_off').className = 'block';
	} else {
		document.getElementById(obj.id + 'hid').value = 'Y';
		document.getElementById('switch_on').className = 'block';
		document.getElementById('switch_off').className = 'hide';
	}
}

function mceSetup(id, opt) {

    var options = {
        //script_url : 'core2/ext/tinymce/jscripts/tiny_mce/tiny_mce.js',
		selector : '#' + id,
        language : 'ru',
        theme : 'modern',
        forced_root_block : false,
        force_br_newlines : true,
        force_p_newlines : false,
        verify_html : false,
        convert_urls : false,
        relative_urls : false,

		plugins: [
			"advlist autolink link image lists charmap print preview hr anchor pagebreak spellchecker",
			"searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking",
			"save table contextmenu directionality emoticons template paste textcolor"
		],
		toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | l      ink image | print preview media fullpage | forecolor backcolor emoticons",
		theme_advanced_resizing : true
       };
    for (k in opt) {
        //options[k] = opt[k];
    }
	tinymce.init(options);
}
