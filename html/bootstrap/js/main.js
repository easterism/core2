/*
 * jQuery hashchange event - v1.3 - 7/21/2010
 * http://benalman.com/projects/jquery-hashchange-plugin/
 *
 * Copyright (c) 2010 "Cowboy" Ben Alman
 * Dual licensed under the MIT and GPL licenses.
 * http://benalman.com/about/license/
 */
(function($,e,b){var c="hashchange",h=document,f,g=$.event.special,i=h.documentMode,d="on"+c in e&&(i===b||i>7);function a(j){j=j||location.href;return"#"+j.replace(/^[^#]*#?(.*)$/,"$1")}$.fn[c]=function(j){return j?this.bind(c,j):this.trigger(c)};$.fn[c].delay=50;g[c]=$.extend(g[c],{setup:function(){if(d){return false}$(f.start)},teardown:function(){if(d){return false}$(f.stop)}});f=(function(){var j={},p,m=a(),k=function(q){return q},l=k,o=k;j.start=function(){p||n()};j.stop=function(){p&&clearTimeout(p);p=b};function n(){var r=a(),q=o(m);if(r!==m){l(m=r,q);$(e).trigger(c)}else{if(q!==m){location.href=location.href.replace(/#.*/,"")+q}}p=setTimeout(n,$.fn[c].delay)}$.browser.msie&&!d&&(function(){var q,r;j.start=function(){if(!q){r=$.fn[c].src;r=r&&r+a();q=$('<iframe tabindex="-1" title="empty"/>').hide().one("load",function(){r||l(a());n()}).attr("src",r||"javascript:0").insertAfter("body")[0].contentWindow;h.onpropertychange=function(){try{if(event.propertyName==="title"){q.document.title=h.title}}catch(s){}}}};j.stop=k;o=function(){return a(q.location.href)};l=function(v,s){var u=q.document,t=$.fn[c].domain;if(v!==s){u.title=h.title;u.open();t&&u.write('<script>document.domain="'+t+'"<\/script>');u.close();q.location.hash=v}}})();return j})()})(jQuery,this);

var main_menu = {

    /**
     * Проверяет влезают ли модули в одну строку
     * @returns {boolean}
     */
    is_overflow : function() {
        var menu_container = document.getElementById('menu-modules');
        var wieght_max     = menu_container.offsetWidth;
        var wieght_modules = 0;
        var overflow_menu  = false;

        for (var i = 0; i < menu_container.children.length; i++) {
            if (typeof window.hasOwnProperty == 'function' && window.hasOwnProperty('getComputedStyle')) {
                var marginLeft = window.getComputedStyle(menu_container.children[i]).marginLeft.replace(/[^0-9]/g, '');
                var marginRight = window.getComputedStyle(menu_container.children[i]).marginRight.replace(/[^0-9]/g, '');

                if (marginLeft) {
                    wieght_modules += parseInt(marginLeft);
                }
                if (marginRight) {
                    wieght_modules += parseInt(marginRight);
                }
            }
            wieght_modules += menu_container.children[i].offsetWidth;

            if (wieght_modules > wieght_max) {
                overflow_menu  = true;
                break;
            }
        }

        return overflow_menu;
    },

    /**
     * Добавление класса к модулям
     * @param {string} class_name
     */
    addClassModules : function(class_name) {
        var menu_container = document.getElementById('menu-modules');
        for (var i = 0; i < menu_container.children.length; i++) {
            var re = new RegExp("(^|\\s)" + class_name + "(\\s|$)", "g");
            if (re.test(menu_container.children[i].className)) continue;
            menu_container.children[i].className = (menu_container.children[i].className + " " + class_name).replace(/\s+/g, " ").replace(/(^ | $)/g, "");
        }
    }
};


function changeSub(obj, path) {
	if (!obj) return;
	var parent = obj.parentNode;
	for (var i = 0; i < parent.childNodes.length; i++) {
		if (parent.childNodes[i].nodeName == 'LI') {
			parent.childNodes[i].className = 'menu-submodule';
			if (parent.childNodes[i] == obj) {
				parent.childNodes[i].className = 'menu-submodule-selected';
				if (path) load(path);
			}
		}
	}
}

function changeRoot(obj, to) {
	if ( ! obj) return;
	var parent = obj.parentNode;
	for (var i = 0; i < parent.childNodes.length; i++) {
		if (parent.childNodes[i].nodeName == 'LI') {
            parent.childNodes[i].className = 'menu-module';
			if (parent.childNodes[i] == obj) {
                parent.childNodes[i].className = 'menu-module-selected';
				var sub = document.getElementById('menu-submodules');
				for (var x = 0; x < sub.childNodes.length; x++) {
					if (sub.childNodes[x].nodeName == 'LI') {
						sub.childNodes[x].className = 'menu-submodule';
						sub.childNodes[x].style.display = 'none';
						if (sub.childNodes[x].id.indexOf(parent.childNodes[i].id + '-') != -1) {
							sub.childNodes[x].style.display = '';
						}
					}
				}
			}
		}
	}
	if (to) load(to);
}

function checkInt(evt) {
	var keycode;
	if (evt.keyCode) keycode = evt.keyCode;
	else if(evt.which) keycode = evt.which;
	var av = [8, 9, 35, 36, 37, 38, 39, 40, 45, 46, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57];
	for (var i = 0; i < av.length; i++) {
		if (av[i] == keycode) return true;
	}
	return false;
}

function goHome() {
	$('.menu-module-selected').addClass('menu-module');
	$('.menu-module_selected').removeClass('menu-module-selected');
	load('index.php?module=admin&action=welcome');
}


function logout() {
	swal({
		title: 'Вы уверены, что хотите выйти?',
		type: "warning",
		showCancelButton: true,
		confirmButtonColor: '#f0ad4e',
		confirmButtonText: "Да",
		cancelButtonText: "Нет"
	}, function(isConfirm) {
		if (isConfirm) {
			$.ajax({url:'index.php?module=admin&action=exit'})
				.done(function (n) {
					window.location='index.php';
				}).fail(function (a,b,t){
				alert("Произошла ошибка: " + a.statusText);
			});
		}
	});
}

function jsToHead(src) {
	var s = $('head').children();
	var h = '';
	for (var i = 0; i < s.length; i++) {
		if (s[i].src) {
			if (!h) {
				var temp = s[i].src.split('core2');
				if (temp[1]) {
					h = temp[0];
				}
			}
			if (s[i].src == src || s[i].src == h + src.replace(/^\//, '')) {
				return;
			}
		}
	}
	s = document.createElement("script");
	s.src = src;
	$('head').append(s);
}

/**
 * @param {string} id
 */
function toAnchor(id){
    setTimeout(function(){
        if (id.indexOf('#') < 0) {
            id = "#" + id;
        }
        var ofy = $(id);
        if (ofy[0]) {
            $('html,body').animate({
                scrollTop : ofy.offset().top - $("#menu-container").height()
            }, 'fast');
        }
    }, 0);
}

var locData = {};
var loc = ''; //DEPRECATED

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

$(document).ajaxError(function (event, jqxhr, settings, exception) {
	preloader.hide();
	if (jqxhr.status == '0') {
		//alert("Соединение прервано.");
	} else if (jqxhr.statusText == 'error') {
		alert("Отсутствует соединение с Интернет.");
	} else if (jqxhr.status == 403) {
		alert("Время жизни вашей сессии истекло. Чтобы войти в систему заново, обновите страницу.");
	} else if (jqxhr.status == 500) {
		alert("Ой! Что-то сломалось, подождите пока мы починим.");
	} else {
		if (exception != 'abort') {
			alert("Произошла ошибка: " + jqxhr.status + ' ' + exception);
		}
	}
});
$(document).ajaxSuccess(function (event, xhr, settings) {
	if (xhr.status == 203) {
		top.document.location = settings.url;
	}
});

var load = function (url, data, id, callback) {
	preloader.show();
	if (!id) id = '#main_body';
	else if (typeof id === 'string') {
		id = '#' + id;
	}
	if (url.indexOf("index.php") == 0) {
		url = url.substr(10);
	}

	var h = preloader.prepare(location.hash.substr(1));
	url = preloader.prepare(url);

	if (h != url && url.indexOf('&__') < 0) {
        if (typeof callback === 'function') {
            locData.callback = callback;
        }
		document.location.hash = url;
	} else {
		if (url) {
			url = '?' + url;
			var qs = preloader.qs(url);
			var r = [];
			var ax = {};
			for (var key in qs) {
				if (key.indexOf('--') != 0) {
					r.push(key + '=' + qs[key]);
				} else {
					ax[key] = qs[key];
				}
			}
			r = r.join('&');
			if (r == preloader.oldHash['--root']) {
				var gotIt = false;
				for (var key in ax) {
					if (preloader.oldHash[key] != ax[key]) {
						gotIt = true;
						preloader.oldHash[key] = ax[key];
						var aUrl = JSON.parse(ax[key]);
						var bUrl = [];
						for (var k in aUrl) {
							if (typeof aUrl.hasOwnProperty == 'function' && aUrl.hasOwnProperty(k)) {
								bUrl.push(encodeURIComponent(k) + '=' + encodeURIComponent(aUrl[k]));
							}
						}
						$('#' + key.substr(2)).load("index.php?" + bUrl.join('&'));
					}
				}
				if (gotIt) {
					preloader.hide();
					return;
				}
			} else {
				preloader.oldHash['--root'] = r;
			}
			//Activate root menu
			if (qs['module']) {
				changeRoot($('#module-' + qs['module'])[0]);
				if (qs['action']) {
					changeSub($('#submodule-' + qs['module'] + '-' + qs['action'])[0])
				}
			}
		}
		else {
			url = '?module=admin&action=welcome';
		}
		if (url == '?module=admin&action=welcome') {
			$('#menu-modules li').removeClass("menu-module-selected").addClass('menu-module');
			$('#menu-submodules .menu-submodule-selected, #menu-submodules .menu-submodule').hide();
		}

        if ($('#module-profile.menu-module-selected, #module-settings.menu-module-selected')[0]) {
            $('#user-section').addClass('active');
        } else {
            $('#user-section').removeClass('active');
        }

		if (!callback) {
			if (ax) {
				for (var key in ax) {
					preloader.extraLoad[key.substr(2)] = ax[key];
				}
			}
			callback = preloader.callback;
		}

        var match_module   = typeof locData['loc'] === 'string' ? locData['loc'].match(/module=([a-zA-Z0-9_]+)/) : '';
        var current_module = match_module !== null && typeof match_module === 'object' && typeof match_module[1] === 'string'
            ? match_module[1] : 'admin';

        var match_action   = typeof locData['loc'] === 'string' ? locData['loc'].match(/action=([a-zA-Z0-9_]+)/) : '';
        var current_action = match_action !== null && typeof match_action === 'object' && typeof match_action[1] === 'string'
            ? match_action[1] : 'index';

            match_module   = document.location.hash.match(/module=([a-zA-Z0-9_]+)/);
        var load_module    = match_module !== null && typeof match_module === 'object' && typeof match_module[1] === 'string'
            ? match_module[1] : 'admin';

            match_action = document.location.hash.match(/action=([a-zA-Z0-9_]+)/);
        var load_action  = match_action !== null && typeof match_action === 'object' && typeof match_action[1] === 'string'
            ? match_action[1] : 'index';

		locData['id']   = id;
		locData['data'] = data;
        locData['loc']  = 'index.php' + url;
		loc = 'index.php' + url; //DEPRECATED

        var $container = $(locData.id);
		if (locData.data) {
			$container.load('index.php' + url, locData.data, function() {
                if (current_module != load_module || current_action != load_action ||
                    document.location.hash.match(/^#module=([a-zA-Z0-9_]+)$/) ||
                    document.location.hash.match(/^#module=([a-zA-Z0-9_]+)&action=([a-zA-Z0-9_]+)$/)
                ) {
                    $container.hide();
                    $container.fadeIn('fast');
                } else {
					$container.hide();
					$container.fadeIn(50);
				}
                if (typeof locData.callback === 'function') {
                    locData.callback();
                    locData.callback = null;
                }
                callback();
			});
		} else {
			$container.load('index.php' + url, function() {
                if (current_module != load_module || current_action != load_action ||
                    document.location.hash.match(/^#module=([a-zA-Z0-9_]+)$/) ||
                    document.location.hash.match(/^#module=([a-zA-Z0-9_]+)&action=([a-zA-Z0-9_]+)$/)
                ) {
                    $container.hide();
                    $container.fadeIn('fast');
                } else {
					$container.hide();
					$container.fadeIn(50);
				}
                if (typeof locData.callback === 'function') {
                    locData.callback();
                    locData.callback = null;
                }
                callback();
			});
		}
	}
};

var loadPDF = function (url) {
	preloader.show();
    $("#main_body").css('height', ($("body").height() - ($("#menu-container").height() + 25)));
	$("#main_body").html('<iframe frameborder="0" width="100%" height="100%" src="' + url + '"></iframe>');
	$("iframe").load( function() {
		preloader.hide();
	});

};

function resize() {
	//$("#mainContainer").css('padding-top', $("#menu-container").height() + 5);
    $("iframe").css('height', $("#rootContainer").height() - ($("#menu-container").height() + 35));
}

$(function(){
	$(window).hashchange( function() {
		var hash = location.hash;
		var url = preloader.prepare(hash.substr(1));
		load(url);
	});
	// Since the event is only triggered when the hash changes, we need to trigger
	// the event now, to handle the hash the page may have loaded with.
	$(window).hashchange();
});

$(window).resize(resize);

$(document).ready(function() {
	xajax.callback.global.onRequest = function () {
		preloader.show();
	};
	xajax.callback.global.onFailure = function (a) {
		preloader.hide();
		if (a.request.status == '0') {
			alert("Превышено время ожидания ответа. Проверьте соединение с Интернет.");
		} else if (a.request.status == 500) {
			alert("Ой! Что-то сломалось, подождите пока мы починим.");
		} else if (a.request.status == 203) {
			alert("Время жизни вашей сессии истекло. Чтобы войти в систему заново, обновите страницу.");
		} else {
			alert("Произошла ошибка: " + a.request.status + ' ' + a.request.statusText);
		}
	};
	xajax.callback.global.onResponseDelay = function () {
		//alert("Отсутствует соединение с Интернет.");
	};
	xajax.callback.global.onExpiration = function () {
		//alert("Отсутствует соединение с Интернет.");
	};
	xajax.callback.global.onComplete = function () {
		preloader.hide();
	};
	var h = top.document.location.hash;
	resize();

    $.datepicker.setDefaults($.datepicker.regional[ "ru_RU" ]);
	$.timepicker.regional['ru'] = {
		timeOnlyTitle: 'Выберите время',
		timeText: 'Время',
		hourText: 'Часы',
		minuteText: 'Минуты',
		secondText: 'Секунды',
		millisecText: 'Миллисекунды',
		timezoneText: 'Часовой пояс',
		currentText: 'Сейчас',
		closeText: 'Закрыть',
		timeFormat: 'HH:mm',
		amNames: ['AM', 'A'],
		pmNames: ['PM', 'P'],
		isRTL: false
	};
	$.timepicker.setDefaults($.timepicker.regional['ru']);

    try {
		alert = function(title, message) {
			swal(title, message);
		};
		// !!!!!!!!! DEPRECATED !!!!!!!!!
		alertify = {
			alert: function(title) {
				swal(title);
			},
			confirm: function(question, callback) {
				swal({
					title: question,
					type: "info",
					showCancelButton: true,
					confirmButtonColor: '#5bc0de',
					confirmButtonText: "Да",
					cancelButtonText: "Нет"
				}, function(isConfirm){
					if (callback) {
						callback(isConfirm);
					}
				});
			},
			prompt: function(message, callback) {
				swal({
					title: message,
					type: "input",
					confirmButtonText: "Далее",
					cancelButtonText: "Отмена",
					showCancelButton: true
				}, function(inputValue){
					if (callback) {
						callback(inputValue !== false, inputValue);
					}
				});
			},
			log: function(message) {
				$.growl({ message: message });
			},
			error: function(message) {
				$.growl.error({ message: message });
			},
			info: function(message) {
				$.growl.info({ message: message });
			},
			warning: function(message) {
				$.growl.warning({ message: message });
			},
			success: function(message) {
				$.growl.notice({ message: message });
			}
		}
    } catch (e) {
        console.error(e.message)
    }
});

var currentCategory = "";
$.ui.autocomplete.prototype._renderItem = function( ul, item){
  var term = this.term.split(' ').join('|');
	var t = item.label;
	if (term) {
		term = term.replace(/\(/g, "\\(").replace(/\)/g, "\\)");
	  var re = new RegExp("(" + term + ")", "gi") ;
	  t = t.replace(re,"<b>$1</b>");
	}
	if (currentCategory && !item.category) {
		item.category = '--';
	}
	if (item.category && item.category != currentCategory) {
		ul.append("<li class='ui-autocomplete-category'>" + item.category + "</li>");
		currentCategory = item.category;
	}

  return $( "<li></li>" )
     .data( "item.autocomplete", item )
     .append( "<a>" + t + "</a>" )
     .appendTo( ul );
};