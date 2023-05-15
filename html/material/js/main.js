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
     *
     */
    setAngles : function() {

        $('.menu-module, .menu-module-selected').each(function(){
            var module = $(this).attr('id').substr(7);
            if ($('li[id^=submodule-' + module + '-]')[0]) {

            	if ($(this).hasClass('menu-module-selected')) {
                    $(this).find('>a').append('<span class="nav-second-level-toggle fa"></span>');
				} else {
                    $(this).find('>a').append('<span class="nav-second-level-toggle fa fa-angle-right"></span>');
				}
            }
        });
    },

    /**
	 *
	 */
    setIconLetter : function() {

        $('.menu-module, .menu-module-selected').each(function(){
            var $i = $(this).find('i');
            if ( ! $i[0]) {
				var letter = $(this).text().trim().substr(0, 1);
                $('a', this).append('<span class="module-icon-letter">' + letter + '</span>');
            }
        });
    }
};


/**
 * @param obj
 * @param path
 */
function changeSub(obj, path) {
	if (!obj) return;

	var parent = obj.parentNode;
	for (var i = 0; i < parent.childNodes.length; i++) {
		if (parent.childNodes[i].nodeName === 'LI') {
			parent.childNodes[i].className = 'menu-submodule';
			if (parent.childNodes[i] === obj) {
				parent.childNodes[i].className = 'menu-submodule-selected';


                var module_name = parent.childNodes[i].id.split('-')[1];
                var href        = $(parent.childNodes[i]).find('>a').attr('href');

                if (module_name && href) {
                	$('#module-' + module_name).find('.module-submodules > a[href="' + href + '"]').addClass('module-submodules-selected');
				}

				if (path) load(path);
			}
		}
	}
}


/**
 * Переключение модуля
 * @param obj
 * @param to
 * @param actionSelect
 */
function changeRoot(obj, to, actionSelect) {

    $('#menu-wrapper .module-submodules').empty();
    $('#menu-submodules').hide();

	var parent = $('#menu-modules')[0];
	for (var i = 0; i < parent.childNodes.length; i++) {
		if (parent.childNodes[i].nodeName === 'LI') {
            parent.childNodes[i].className = 'menu-module';

            $(parent.childNodes[i]).find('.nav-second-level-toggle').addClass('fa-angle-right');

			if (parent.childNodes[i] === obj) {
                parent.childNodes[i].className = 'menu-module-selected';

                var module_id = parent.childNodes[i].id;
				if (module_id !== 'module-admin' || (module_id === 'module-admin' && actionSelect !== 'welcome')) {
					if ( ! actionSelect || actionSelect === 'index') {
						$(parent.childNodes[i]).find('>a').addClass('index-select');
					} else {
						$(parent.childNodes[i]).find('>a').removeClass('index-select');
					}
                    $('#home-button > a').removeClass('home-select');

                } else {
                    $('#home-button > a').addClass('home-select');
				}

                var issetSubmodules = false;
				var sub 			= document.getElementById('menu-submodules');

				for (var x = 0; x < sub.childNodes.length; x++) {
					if (sub.childNodes[x].nodeName === 'LI') {
						sub.childNodes[x].className = 'menu-submodule';
						sub.childNodes[x].style.display = 'none';
						if (sub.childNodes[x].id.indexOf(parent.childNodes[i].id + '-') !== -1) {
                            if (module_id !== 'module-admin' || (module_id === 'module-admin' && actionSelect !== 'welcome')) {
                                issetSubmodules = true;
                                $(parent.childNodes[i]).find('.module-submodules').append(
                                    $(sub.childNodes[x]).find('>a').clone()
                                );
                            }

							sub.childNodes[x].style.display = '';
						}
					}
				}

				if (issetSubmodules) {
                    $(parent.childNodes[i]).addClass('module-isset-submodules');
                    $(parent.childNodes[i]).find('.nav-second-level-toggle').removeClass('fa-angle-right');
                    if ($(window).width() < 768 || ! $('.s-toggle')[0]) {
                        $(parent.childNodes[i]).find('.module-submodules').show()
                    }
				} else {
					$(parent.childNodes[i]).removeClass('module-isset-submodules');
					$(parent.childNodes[i]).find('.module-submodules').hide();
				}
			}
		}
	}
	if (to) load(to);
}


/**
 * @returns {{width: *, height: *}}
 */
function viewport() {
    var e = window,
		a = 'inner';
    if ( ! ('innerWidth' in window)) {
        a = 'client';
        e = document.documentElement || document.body;
    }
    return { width : e[ a+'Width' ] , height : e[ a+'Height' ] }
}


/**
 * @param evt
 * @returns {boolean}
 */
function checkInt(evt) {
	var keycode;
	if (evt.keyCode) keycode = evt.keyCode;
	else if(evt.which) keycode = evt.which;
	var av = [8, 9, 35, 36, 37, 38, 39, 40, 45, 46, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57];
	for (var i = 0; i < av.length; i++) {
		if (av[i] === keycode) return true;
	}
	return false;
}


/**
 *
 */
function goHome() {
	$('.menu-module-selected').addClass('menu-module');
	$('.menu-module_selected').removeClass('menu-module-selected');
	load('index.php?module=admin&action=welcome');
}


/**
 *
 */
function logout() {
    swal({
        title: 'Вы уверены, что хотите выйти?',
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: '#f0ad4e',
        confirmButtonText: "Да",
        cancelButtonText: "Нет"
    }).then(
        function(result) {
        	preloader.show();

            $.ajax({url:'index.php?module=admin', data:{"exit":1}, method:'PUT'})
				.done(function (n) {
                    preloader.hide();
					window.location = 'index.php';

				}).fail(function (a,b,t){
                	preloader.hide();
					alert("Произошла ошибка: " + a.statusText);
				});
        }, function(dismiss) {}
    );
}


/**
 * @param src
 */
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
			if (s[i].src === src || s[i].src === h + src.replace(/^\//, '')) {
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
    setTimeout(function() {
		if (typeof id == 'string' && id.indexOf('#') < 0) {
			id = "#" + id;
		}

		var element = $(id);

        if (element && element[0]) {
            $('html,body').animate({
                scrollTop : element.offset().top - $("#navbar-top").height() - 115
            }, 'fast');
        }
    }, 0);
}

var locData = {};
var loc = ''; //DEPRECATED
var xhrs = {};

var preloader = {
	extraLoad : {},
	oldHash : {},
	show : function() {
        $("#preloader .spinner-text").text('Загрузка...');
        $("#preloader").show();
	},
	hide : function() {
		$("#preloader").hide();
	},
	callback : function (response, status, xhr) {
		if (status === "error") {

		} else {
			if (preloader.extraLoad) {
				for (var el in preloader.extraLoad) {
					var aUrl = preloader.extraLoad[el];
					if (aUrl) {
						aUrl = JSON.parse(aUrl);
						var bUrl = [];
						for (var k in aUrl) {
							if (typeof aUrl.hasOwnProperty === 'function' && aUrl.hasOwnProperty(k)) {
								bUrl.push(encodeURIComponent(k) + '=' + encodeURIComponent(aUrl[k]));
							}
						}
						$('#' + el).load("index.php?" + bUrl.join('&'));
					}
				}
				preloader.extraLoad = {};
			}

			if (locData['loc'] &&
				locData['id'] &&
				locData['loc'].indexOf('&__') >= 0
			) {
				toAnchor(locData['id']);

			} else {
				$('html').animate({
					scrollTop: 0
				});
			}
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
			if (typeof url.hasOwnProperty === 'function' && url.hasOwnProperty(key)) {
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
			if (typeof url.hasOwnProperty === 'function' && url.hasOwnProperty(key)) {
				var pu = '"' + encodeURIComponent(key) + '":"' + encodeURIComponent(url[key]) + '"';
				pairs.push(pu);
			}
		return '{' + pairs.join(',') + '}';
	},
    setText: function(text) {
        this.show();
        $("#preloader .spinner-text").text(text);
    },
	normUrl: function () {

	}
};

$(document).ajaxError(function (event, jqxhr, settings, exception) {
    preloader.hide();
    if (jqxhr.status === '0') {
        //alert("Соединение прервано.");
    } else if (jqxhr.statusText === 'error') {
        swal("Отсутствует соединение с Интернет.", '', 'error').catch(swal.noop);
    } else if (jqxhr.status === 403) {
        swal("Время жизни вашей сессии истекло", 'Чтобы войти в систему заново, обновите страницу (F5)', 'error').catch(swal.noop);
    } else if (jqxhr.status === 500) {
        swal("Ой, извините!", "Во время обработки вашего запроса произошла ошибка.", 'error').catch(swal.noop);
    } else if (exception !== 'abort') {
        swal("Произошла ошибка", jqxhr.status + ' ' + exception, 'error').catch(swal.noop);
    }
});
$(document).ajaxSuccess(function (event, xhr, settings) {
	if (xhr.status === 203) {
		top.document.location = settings.url;
	}
});


/**
 * @param url
 * @param data
 * @param id
 * @param callback
 */
var load = function (url, data, id, callback) {
	preloader.show();

	if (!id) id = '#main_body';
	else if (typeof id === 'string') {
		id = '#' + id;
	}
	if (url.indexOf("index.php") === 0) {
		url = url.substr(10);
	}

	var h = preloader.prepare(location.hash.substr(1));
	url = preloader.prepare(url);

    $("body").removeClass("pdf-open")
		.removeClass("ext-open");

    if ($(window).width() < 768) {
        $('#main').removeClass('s-toggle');
        $('#menu-wrapper .module-submodules').hide();
    }

	if ( ! data && h !== url && url.indexOf('&__') < 0) {
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
				if (key.indexOf('--') !== 0) {
					r.push(key + '=' + qs[key]);
				} else {
					ax[key] = qs[key];
				}
			}
			r = r.join('&');
			if (r === preloader.oldHash['--root']) {
				var gotIt = false;
				for (var key in ax) {
					if (preloader.oldHash[key] !== ax[key]) {
						gotIt = true;
						preloader.oldHash[key] = ax[key];
						var aUrl = JSON.parse(ax[key]);
						var bUrl = [];
						for (var k in aUrl) {
							if (typeof aUrl.hasOwnProperty === 'function' && aUrl.hasOwnProperty(k)) {
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
			if (qs['module'] && url.indexOf('&__') < 0) {
				changeRoot($('#module-' + qs['module'])[0], false, qs['action']);
				if (qs['action']) {
					changeSub($('#submodule-' + qs['module'] + '-' + qs['action'])[0])
				}
			}
		}
		else {
			url = '?module=admin&action=welcome';
		}
		if (url === '?module=admin&action=welcome') {
            $('#home-button > a').addClass('home-select');
			$('#menu-modules li').removeClass("menu-module-selected").addClass('menu-module');
			$('#menu-submodules .menu-submodule-selected, #menu-submodules .menu-submodule').hide();
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

		var mod_title    = $('#module-' + load_module + ' > a .module-title').text();
		var action_title = $('#submodule-' + load_module + '-' + load_action + ' > a').text();

        if (load_module === 'admin' && load_action === 'welcome') {
            mod_title = '';
        }

        var css_mod_title = action_title === ''
            ? {'fontSize': '18px', 'paddingTop': '15px','lineHeight': '20px'}
            : {'fontSize': '',     'paddingTop': '',    'lineHeight': ''};

        $('#navbar-top .module-title').css(css_mod_title).text(mod_title);
        $('#navbar-top .module-action').text(action_title);

		var siteName = $.trim($('.site-name').text());
		var title    = siteName + ' - ' + mod_title + (action_title ? (' - ' + action_title) : '');
		$('html > head > title').text(title);

        if (xhrs[id]) {
        	xhrs[id].abort();
        }

        if (locData.data) {
            $(id).load('index.php' + url, locData.data, callback);

        } else {
			xhrs[id] = $.ajax({
				url: 'index.php' + url,
				data: data,
				global: false,
				async: true,
				method: 'GET'
			}).done(function (result) {
				$(id).html(result);

				if (current_module !== load_module || current_action !== load_action ||
					document.location.hash.match(/^#module=([a-zA-Z0-9_]+)$/) ||
					document.location.hash.match(/^#module=([a-zA-Z0-9_]+)&action=([a-zA-Z0-9_]+)$/)
				) {
					$(locData.id).hide();
					$(locData.id).fadeIn('fast');
				} else {
					$(locData.id).hide();
					$(locData.id).fadeIn(50);
				}
				if (typeof locData.callback === 'function') {
					locData.callback();
					locData.callback = null;
				}
				callback();

			}).fail(function (a,b,t){
				preloader.hide();
				if (a.statusText !== 'abort') {
					if (!a.status) swal("Превышено время ожидания ответа. Проверьте соединение с Интернет.", '', 'error').catch(swal.noop);
					else if (a.status === 500) swal("Во время обработки вашего запроса произошла ошибка.", '', 'error').catch(swal.noop);
					else if (a.status === 404) swal("Запрашиваемый ресурс не найден.", '', 'error').catch(swal.noop);
					else if (a.status === 403) {
                        if (current_module !== load_module || current_action !== load_action) {
							location.reload();
						} else {
                            swal("Время жизни вашей сессии истекло", 'Чтобы войти в систему заново, обновите страницу (F5)', 'error').catch(swal.noop);
						}
                    }
					else swal("Произошла ошибка: " + a.statusText, '', 'error').catch(swal.noop);
				}
			});
        }
	}
};


/**
 * @param url
 */
var loadPDF = function (url) {
	preloader.show();
	$("#main_body").prepend(
	    '<div class="pdf-panel hidden">' +
		'<div class="pdf-tool-panel"><button class="btn btn-sm btn-default" onclick="removePDF();">Закрыть</button></div>' +
		'<div class="pdf-main-panel"><iframe id="core-iframe" frameborder="0" width="100%" height="100%" src="' + url + '"></iframe></div>' +
        '</div>'

	);

	$("#core-iframe").load( function() {
        $("body").addClass("pdf-open");

        $("#main_body .pdf-main-panel").css({
            'height': ($("body").height() - ($("#navbar-top").height()) - 40)
        });

		preloader.hide();
		$('.pdf-panel').removeClass('hidden');
        $(window).hashchange( function() {
            $("body").removeClass("pdf-open");
        });
	});
};


/**
 *
 */
function removePDF() {
	$('.pdf-panel').remove();
	$('body').removeClass('pdf-open');
}


/**
 * @param url
 */
var loadScreen = function (url) {
	preloader.show();

	$("#main_body").prepend(
		'<div class="screen-panel hidden">' +
			'<div class="screen-tool">' +
				'<button class="btn btn-sm btn-default" onclick="removeScreen();">Закрыть</button>' +
			'</div>' +
			'<div class="screen-content"></div>' +
		'</div>'

	);

	$(".screen-panel .screen-content").load(url, function() {
		$("body").addClass("screen-open");

		$("#main_body .screen-content").css({
			'height': ($("body").height() - ($("#navbar-top").height()) - 40)
		});

		preloader.hide();
		$('.screen-panel').removeClass('hidden');
		$(window).hashchange( function() {
			$("body").removeClass("screen-open");
		});
	});
};


/**
 *
 */
function removeScreen() {
	$('#main_body > .screen-panel').remove();
	$('body').removeClass('screen-open');
}



/**
 * @param url
 */
var loadExt = function (url) {
	preloader.show();
	$("#main_body").prepend(
	    '<div class="ext-panel hidden">' +
			'<div class="ext-main-panel"><iframe id="core-iframe" frameborder="0" width="100%" height="100%" src="' + url + '"></iframe></div>' +
        '</div>'

	);

	$("#core-iframe").load( function() {
        $("body").addClass("ext-open");

        $("#main_body .ext-main-panel").css({
            'height': $("body").height() - $("#navbar-top").height()
        });

		preloader.hide();
		$('.ext-panel').removeClass('hidden');
        $(window).hashchange( function() {
            $("body").removeClass("ext-open");
        });
	});
};


/**
 *
 */
function resize() {
    $("#main_body .pdf-main-panel").css({
        'height': ($("body").height() - ($("#navbar-top").height()) - 40)
    });
    $("#main_body .ext-main-panel").css({
        'height': $("body").height() - $("#navbar-top").height()
    });
}

$(function(){
	$(window).hashchange( function() {
		var hash = location.hash;
		var url = preloader.prepare(hash.substr(1));
		load(url);

		$('body > .modal-backdrop').fadeOut(function () {
			$('body').removeClass('modal-open');
			$(this).remove();
		});
	});
	// Since the event is only triggered when the hash changes, we need to trigger
	// the event now, to handle the hash the page may have loaded with.
	$(window).hashchange();
});

$(window).resize(resize);

$(document).ready(function() {

    jQuery(document).ready(function() {
        if ( ! jQuery.support.leadingWhitespace || (document.all && ! document.querySelector)) {
            $("#mainContainer").prepend(
                "<h2>" +
					"<span style=\"color:red\">Внимание!</span> " +
					"Вы пользуетесь устаревшей версией браузера. " +
					"Во избежание проблем с работой, рекомендуется обновить текущий или установить другой, более современный браузер." +
                "</h2>"
            );
        }
    });

    main_menu.setAngles();
    main_menu.setIconLetter();

    $("#menu-modules > .menu-module, #menu-modules > .menu-module-selected").mouseenter(function() {
        if ($(window).width() >= 768 && ($(this).hasClass('menu-module') || $('.s-toggle')[0])) {
			$('.menu-submodule, .menu-submodule-selected').hide();

			var submodulesContainer = $('#menu-submodules').hide();
            var module              = $(this).attr('id').substr(7);
            var submodules          = $('li[id^=submodule-' + module + '-]').show();

			if ($('.s-toggle')[0] || submodules[0]) {
                $('#menu-submodules').find('.submenu-module-title').remove();

				if ($('.s-toggle')[0]) {
                    var issetSelectSubmodule = $('li[id^=submodule-' + module + '-].menu-submodule-selected')[0];
					var selectedClass        = ! issetSelectSubmodule && $(this).hasClass('menu-module-selected') ? 'selected' : '';
                    var $moduleElement       = $(this).find('>a');
                    var moduleHref 	         = $moduleElement.attr('href');
                    var moduleOnClick        = $moduleElement.attr('onclick');
                    var moduleTitle          = $moduleElement.find('>.module-title').text().trim();

                    $('#menu-submodules').prepend(
                        '<li class="submenu-module-title ' + selectedClass + '">' +
                        	'<a href="' + moduleHref + '" onclick="' + moduleOnClick + '">' + moduleTitle + '</a>' +
                        '</li>'
                    );
                }

                submodulesContainer.show();

                var offsets = this.getBoundingClientRect();

                if (($(window).height() - $(this)[0].offsetTop) < submodulesContainer.height()) {
                    submodulesContainer.css('top', (offsets.top - submodulesContainer.height() + 27) + 'px');
                } else {
                    submodulesContainer.css('top', (offsets.top + 1) + 'px');
                }
			}
        }
    });
    $("#menu-modules > .menu-module, #menu-modules > .menu-module-selected").mouseleave(function(e) {
        if ($(window).width() >= 768 && ($(this).hasClass('menu-module') || $('.s-toggle')[0])) {
			var target = e.toElement || e.relatedTarget || e.target;

			if (target) {
				if ($(target).parent().parent().attr('id') === 'menu-submodules') {
					$(this).addClass('module-hover');
				} else {
					$('#menu-submodules').hide();
				}

			} else {
				$('#menu-submodules').hide();
			}
        }
    });
    $("#menu-submodules").mouseleave(function() {
        $(this).hide();
        $("#menu-modules > .menu-module, #menu-modules > .menu-module-selected").removeClass('module-hover');
    });

    if (localStorage.getItem('sidebar_collapse')) {
        $('#main-content, #menu-container, #menu-wrapper, #navbar-top').css('transition', "none");
        if ($(window).width() >= 768) {
            $('#main').toggleClass('s-toggle');
        	$('#menu-wrapper .module-submodules').hide();
		}
    }

    $("#sidebar-toggle").click(function() {
        $('#main-content, #menu-container, #menu-wrapper, #navbar-top').css('transition', "");
        $('#main').toggleClass('s-toggle');


        if ($(window).width() >= 768) {
			if ($('#main').hasClass('s-toggle')) {
				$('#menu-wrapper .module-submodules').hide();
			} else {
				$('#menu-wrapper .module-submodules').show();
			}
		}

		if (localStorage.getItem('sidebar_collapse')) {
			localStorage.setItem('sidebar_collapse', '');
		} else {
			localStorage.setItem('sidebar_collapse', 1);
		}
    });

    $(".swipe-area").swipe({
        swipeStatus: function(event, phase, direction, distance, duration, fingers) {
            var width = $(window).width();
            if (phase === "move" && ((width < 768 && direction === "right") || (width >= 768 && direction === "left"))) {
                $("#main").addClass("s-toggle");
                if (width >= 768) {
                    $('#menu-wrapper .module-submodules').hide();
                }
				localStorage.setItem('sidebar_collapse', 1);
                return false;
            }
            if (phase === "move" && ((width < 768 && direction === "left") || (width >= 768 && direction === "right"))) {
                $("#main").removeClass("s-toggle");
                if (width >= 768) {
                    $('#menu-wrapper .module-submodules').show();
                }
				localStorage.setItem('sidebar_collapse', '');
                return false;
            }
        }
    });

	xajax.callback.global.onRequest = function () {
		preloader.show();
	};
	xajax.callback.global.onFailure = function (a) {
        preloader.hide();
        if (a.request.status === '0') {
            swal("Превышено время ожидания ответа", 'Проверьте соединение с Интернет', 'error').catch(swal.noop);
        } else if (a.request.status === 500) {
            swal("Ой, извините!", 'Во время обработки вашего запроса произошла ошибка.', 'error').catch(swal.noop);
        } else if (a.request.status === 203) {
            swal("Время жизни вашей сессии истекло", 'Чтобы войти в систему заново, обновите страницу (F5)', 'error').catch(swal.noop);
        } else {
            swal("Произошла ошибка", a.request.status + ' ' + a.request.statusText, 'error').catch(swal.noop);
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
	resize();

    $.datepicker.setDefaults($.datepicker.regional[ "ru" ]);
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
            swal(title, message).catch(swal.noop);
        };
        // !!!!!!!!! DEPRECATED !!!!!!!!!
        alertify = {
            alert: function(title) {
                swal(title).catch(swal.noop);
            },
            confirm: function(question, callback) {
                swal({
                    title: question,
                    type: "question",
                    showCancelButton: true,
                    confirmButtonColor: '#5bc0de',
                    confirmButtonText: "Да",
                    cancelButtonText: "Нет"
                }).then(
                    function(result) {
                        if (callback) {
                            callback(true);
                        }
                    }, function(dismiss) {
                        if (callback) {
                            callback(false);
                        }
                    }
                );
            },
            prompt: function(message, callback) {
                swal({
                    title: message,
                    input: 'text',
                    confirmButtonText: "Далее",
                    cancelButtonText: "Отмена",
                    showCancelButton: true
                }).then(
                    function(result) {
                        if (callback) {
                            callback(true, result);
                        }
                    }, function(dismiss) {
                        if (callback) {
                            callback(false, '');
                        }
                    }
                );
            },
            log: function(message) {
                var d = new Date();
                Snarl.addNotification({
					title: d.getHours()  + ':' + d.getMinutes() + ':' + d.getSeconds(),
					icon: '<i class="fa fa-exclamation-circle"></i>',
					text: message
                });
            },
            error: function(message) {
                Snarl.addNotification({title: "Ошибка", icon: '<i class="fa fa-times"></i>', text: message });
            },
            info: function(message) {
                Snarl.addNotification({title: "Уведомление", icon: '<i class="fa fa-info"></i>', text: message });
            },
            warning: function(message) {
                Snarl.addNotification({title: "Внимание", icon: '<i class="fa fa-warning"></i>',  text: message });
            },
            success: function(message) {
                Snarl.addNotification({title: "Успех", icon: '<i class="fa fa-check"></i>',  text: message });
            },
            message: function(message) {
                Snarl.addNotification({title: "Сообщение", icon: '<i class="fa fa-comment-o"></i>', text: message });
            }
        }
    } catch (e) {
        console.error(e.message)
    }
});

var currentCategory = "";
$.ui.autocomplete.prototype._renderItem = function( ul, item){
	var term = this.term.split(' ').join('|');
	var t 	 = item.label;

	if (term) {
		term = term.replace(new RegExp('[.\\\\+*?\\[\\^\\]$(){}=!<>|:\\' + '' + '-]', 'g'), '\\$&');

		var re = new RegExp("(" + term + ")", "gi");
		t = t.replace(re, "<b>$1</b>");
	}

	if (currentCategory && ! item.category) {
		item.category = '--';
	}

	if (item.category && item.category !== currentCategory) {
		ul.append("<li class='ui-autocomplete-category'>" + item.category + "</li>");
		currentCategory = item.category;
	}

	return $("<li></li>")
		.data("item.autocomplete", item)
		.append("<a>" + t + "</a>")
		.appendTo(ul);
};