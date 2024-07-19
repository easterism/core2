/*
 * jQuery hashchange event - v1.3 - 7/21/2010
 * http://benalman.com/projects/jquery-hashchange-plugin/
 *
 * Copyright (c) 2010 "Cowboy" Ben Alman
 * Dual licensed under the MIT and GPL licenses.
 * http://benalman.com/about/license/
 */
(function($,e,b){var c="hashchange",h=document,f,g=$.event.special,i=h.documentMode,d="on"+c in e&&(i===b||i>7);function a(j){j=j||location.href;return"#"+j.replace(/^[^#]*#?(.*)$/,"$1")}$.fn[c]=function(j){return j?this.bind(c,j):this.trigger(c)};$.fn[c].delay=50;g[c]=$.extend(g[c],{setup:function(){if(d){return false}$(f.start)},teardown:function(){if(d){return false}$(f.stop)}});f=(function(){var j={},p,m=a(),k=function(q){return q},l=k,o=k;j.start=function(){p||n()};j.stop=function(){p&&clearTimeout(p);p=b};function n(){var r=a(),q=o(m);if(r!==m){l(m=r,q);$(e).trigger(c)}else{if(q!==m){location.href=location.href.replace(/#.*/,"")+q}}p=setTimeout(n,$.fn[c].delay)}$.browser.msie&&!d&&(function(){var q,r;j.start=function(){if(!q){r=$.fn[c].src;r=r&&r+a();q=$('<iframe tabindex="-1" title="empty"/>').hide().one("load",function(){r||l(a());n()}).attr("src",r||"javascript:0").insertAfter("body")[0].contentWindow;h.onpropertychange=function(){try{if(event.propertyName==="title"){q.document.title=h.title}}catch(s){}}}};j.stop=k;o=function(){return a(q.location.href)};l=function(v,s){var u=q.document,t=$.fn[c].domain;if(v!==s){u.title=h.title;u.open();t&&u.write('<script>document.domain="'+t+'"<\/script>');u.close();q.location.hash=v}}})();return j})()})(jQuery,this);

function changeSub(obj, path) {
	if (!obj) return;
	var parent = obj.parentNode;
	for (var i = 0; i < parent.childNodes.length; i++) {
		if (parent.childNodes[i].nodeName == 'TD') {
			parent.childNodes[i].className = 'submenu_items';
			if (parent.childNodes[i] == obj) {
				parent.childNodes[i].className = 'submenu_items_selected';
				locData.title['smodule'] = $(parent.childNodes[i]).text();
				if (path) load(path);
				break;
			}
		}
	}
}

function changeRoot(obj, to) {
	if (!obj) return;
	var parent = obj.parentNode;
	for (var i = 0; i < parent.childNodes.length; i++) {
		if (parent.childNodes[i].nodeName == 'DIV') {
			parent.childNodes[i].className = 'menu_items';
			if (parent.childNodes[i] == obj) {
				parent.childNodes[i].className = 'menu_items_selected';
				locData.title['module'] = $(parent.childNodes[i]).text();
				locData.title['smodule'] = '';
				var sub = document.getElementById('table_submenu').rows[0];
				for (var x = 0; x < sub.childNodes.length; x++) {
					if (sub.childNodes[x].nodeName == 'TD') {
						sub.childNodes[x].className = 'submenu_items';
						sub.childNodes[x].style.display = 'none';
						if (sub.childNodes[x].id.indexOf(parent.childNodes[i].id + '_') != -1) {
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
	var av = new Array(8, 9, 35, 36, 37, 38, 39, 40, 45, 46, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57);
	for (var i = 0; i < av.length; i++) {
		if (av[i] == keycode) return true;
	}
	return false;
}

function goHome() {
	$('.menu_items_selected').addClass('menu_items');
	$('.menu_items_selected').removeClass('menu_items_selected');
	load('index.php?module=admin&action=welcome');
}

function logout() {
	if (confirm('Вы уверены, что хотите выйти?')) {
        preloader.show();

		$.ajax({url:'index.php?module=admin', data:{"exit":1}, method:'PUT'})
			.done(function (n) {
                preloader.hide();
				window.location = 'index.php';

			}).fail(function (a,b,t){
				preloader.hide();
				alert("Произошла ошибка: " + a.statusText);
			});
	}
}

function jsToHead(src) {
	var s = $('head').children();
	var h = '';
	var fromRoot = false;
	if (src.substr(0, 1) == '/') var fromRoot = true;
	for (var i = 0; i < s.length; i++) {
		if (s[i].src) {
			if (!h) {
				var temp = s[i].src.split('core2');
				if (temp[1]) {
					h = temp[0];
				}
			}
			if (h && fromRoot) {
				var temp = h.split('://');
				var pos = temp[1].indexOf('/');
				h = temp[0] + '://' + temp[1].substr(0, pos) + '/';
				fromRoot = false;
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

var glob = {'toAnchor':false};




/**
 * Анимация для указанного элемента
 * @param {string} elementId
 * @param {string} effect
 */
function animatedElement(elementId, effect) {

	var element = $('#' + elementId);
	if ( ! element[0]) {
		return;
	}


	element.removeClass('animated ' + effect);

	setTimeout(function() {
		element.addClass('animated ' + effect);
	}, 0);
}

function toAnchor(id) {
	if (!id) return;
	if (typeof id != 'string') return;
	if (id.substr(-9) == '_mainform') {
		glob['toAnchor'] = true;
	}
	if (id.indexOf('#') < 0) {
		id = "#" + id;
	}
	var ofy = $(id);
	if (ofy[0]) {
		if (glob['toAnchor'] == false) {
			$('html,body').animate({
				scrollTop: ofy.offset().top - $("#menuContainer").height()
			}, 'fast');
		}
		glob['toAnchor'] = false;
	}
}

var locData = {"title":{"main":document.title}};
var loc = ''; //DEPRECATED
var xhrs = {};

var preloader = {
	extraLoad : {},
	oldHash : {},
	show : function() {
		$("#preloader").css('margin-top', ($("#menuContainer").height()));
		$("#preloader div").addClass('image');
		$("#preloader").show();
	},
	hide : function() {
		$("#preloader div").html("");
		$("#preloader").hide();
	},
	callback : function () {
		if (preloader.extraLoad) {
			for (var el in preloader.extraLoad) {
				var aUrl = preloader.extraLoad[el];
				if (aUrl) {
					aUrl = JSON.parse(aUrl);
					var bUrl = [];
					for (var k in aUrl) {
						if (aUrl.hasOwnProperty(k)) {
							bUrl.push(encodeURIComponent(k) + '=' + encodeURIComponent(aUrl[k]));
						}
					}
					$('#' + el).load("index.php?" + bUrl.join('&'));
				}
			}
			preloader.extraLoad = {};
		}
		preloader.hide();
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
			if (url.hasOwnProperty(key)) {
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
			if (url.hasOwnProperty(key)) {
				var pu = '"' + encodeURIComponent(key) + '":"' + encodeURIComponent(url[key]) + '"';
				pairs.push(pu);
			}
		return '{' + pairs.join(',') + '}';
	},
	setText: function(text) {
		this.show();
		$("#preloader div").removeClass('image');
		$("#preloader div").html("<span style=\"margin-top:30px;display:block;\">" + text + "</span>");
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
	}
	else if (jqxhr.status == 403) {
		alert("Время жизни вашей сессии истекло. Чтобы войти в систему заново, обновите страницу.");
	}
	else if (jqxhr.status == 500) {
		alert("Ой! Что-то сломалось, подождите пока мы починим.");
	}
	else if (jqxhr.status == 503) {
		alert("Сервис не доступен.");
	} else {
		if (exception != 'abort') {
			alert("Произошла ошибка: " + jqxhr.status + ' ' + exception);
		}
	}
});
$(document).ajaxSuccess(function (event, xhr, settings) {
	if (xhr.status == 203) {
		top.document.location = settings.url;
	};
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

    $("body").css("overflow", "");

	if (!data && h != url && url.indexOf('&__') < 0) {
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
							if (aUrl.hasOwnProperty(k)) {
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
				changeRoot($('#module_' + qs['module'])[0]);
				if (qs['action']) {
					changeSub($('#smodule_' + qs['module'] + '_' + qs['action'])[0])
				}
				var t = locData.title.main;
				if (locData.title.module) {
					t = locData.title.module + ' - ' + t;
					if (locData.title.smodule) t = locData.title.smodule + ' - ' + t;
				}
				document.title = t;
			}
		}
		else {
			url = '?module=admin&action=welcome';
		}
		if (url == '?module=admin&action=welcome') {
			$('#moduleContainer div').removeClass("menu_items_selected").addClass('menu_items');
			$('.submenu_items').hide();
		}

		if (!callback) {
			if (ax) {
				for (var key in ax) {
					preloader.extraLoad[key.substr(2)] = ax[key];
				}
			}
			callback = preloader.callback;
		}
		locData['id'] = id;
		locData['data'] = data;
        locData['loc'] = 'index.php' + url;
		loc = 'index.php' + url; //DEPRECATED
		$("#pdfiframe").remove();
		if (xhrs[locData.id]) xhrs[locData.id].abort();
		if (locData.data) {
			//$(locData.id).load('index.php' + url, callback);
			$(locData.id).load('index.php' + url, locData.data, callback);
			//xhrs[locData.id] = $.ajax({url:'index.php' + url, global:false})
		} else {
			xhrs[locData.id] = $.ajax({url:'index.php' + url, global:false})
				.done(function (n) {
					$(locData.id).html(n);
					toAnchor(locData.id);
					callback();
				})
				.fail(function (a,b,t) {
					if (a.statusText != 'abort') {
						if (!a.status) alert("Превышено время ожидания ответа. Проверьте соединение с Интернет.");
						else if (a.status == 500) alert("Ой! Что-то сломалось, подождите пока мы починим.");
						else if (a.status == 404) alert("Запрашиваемый ресурс не найден.");
						else if (a.status == 403) document.location.reload();
						else alert("Произошла ошибка: " + a.statusText);
					}
					preloader.hide();
				});
		}
	}
};

var loadPDF = function (url) {
	preloader.show();
    $("#main_body").prepend(
        '<div class="pdf-panel hide">' +
        '<div class="pdf-tool-panel"><button class="btn buttonSmall" onclick="removePDF();">Закрыть</button></div>' +
        '<div class="pdf-main-panel"><iframe id="pdfiframe" style="background-color: white;" frameborder="0" width="100%" height="100%" src="' + url + '"></iframe></div>' +
		'</div>'
    );

	$("#pdfiframe").load( function() {
        $("body").css("overflow", "hidden");

        $("#main_body .pdf-main-panel").css({
            'height'      : ($("body").height() - ($("#menuContainer").height()) - 41),
        });

		preloader.hide();
        $('.pdf-panel').removeClass('hide');
        $(window).hashchange( function() {
            $("body").css("overflow", "");
        });
	});
}

function removePDF() {
    $('.pdf-panel').remove();
    $('body').css('overflow', '');
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
		$("body").css("overflow", "hidden");

		$("#main_body .ext-main-panel").css({
			'height': $("body").height() - $("#menuContainer").height() + 5
		});

		preloader.hide();
		$('.ext-panel').removeClass('hidden');
		$(window).hashchange( function() {
			$("body").removeClass("ext-open");
		});
	});
};


function resize() {
	$("#mainContainer").css('padding-top', $("#menuContainer").height() + 5);
	$("#main_body").height($("#rootContainer").height() - ($("#menuContainer").height() + 15));

	$("#main_body > .pdf-panel").css({
        'margin-top'  : $(document).scrollTop() - 5
    });

    $("#main_body .pdf-main-panel").css({
        'height'      : ($("body").height() - ($("#menuContainer").height()) - 41),
    });
	$("#main_body .ext-main-panel").css({
		'height': $("body").height() - $("#menuContainer").height()
	});
}

$(function(){
	$(window).hashchange( function() {
		var hash = location.hash;
		var url = preloader.prepare(hash.substr(1));
		load(url);
	})
	// Since the event is only triggered when the hash changes, we need to trigger
	// the event now, to handle the hash the page may have loaded with.
	$(window).hashchange();
});

//$(window).resize(resize);

document.addEventListener("DOMContentLoaded",
	(e) => {
	xajax.callback.global.onRequest = function () {
		preloader.show();
	}
	xajax.callback.global.onFailure = function (a) {
		preloader.hide();
		if (a.request.statusText != 'abort' && a.request.status == '0') {
			alert("Превышено время ожидания ответа. Проверьте соединение с Интернет.");
		}
		else if (a.request.status == 500) {
			alert("Ой! Что-то сломалось, подождите пока мы починим.");
		}else if (a.request.status == 403) {
			alert("Время жизни вашей сессии истекло. Чтобы войти в систему заново, обновите страницу.");
		} else {
			alert("Произошла ошибка: " + a.request.status + ' ' + a.request.statusText);
		}
	}
	xajax.callback.global.onResponseDelay = function () {
		//alert("Отсутствует соединение с Интернет.");
	}
	xajax.callback.global.onExpiration = function () {
		//alert("Отсутствует соединение с Интернет.");
	}
	xajax.callback.global.onComplete = function () {
		preloader.hide();
	}
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

	const targetNode = document.getElementById("mainContainer");
	const config = {childList: true, subtree: true};
	// Callback function to execute when mutations are observed
	const callback = (mutationList, observer) => {
		for (const mutation of mutationList) {
			if (mutation.type === "childList" && mutation.addedNodes.length) {
				$('a, button').each(function (){
					if ($(this).data('hotkey')) {
						if ($(this)[0].getAttribute('listener') !== 'true') {
							const hotkey = $(this).data('hotkey')
							keymaps[hotkey] = $(this)[0];
							$(this)[0].setAttribute('listener', 'true');
						}
					}
				});
			} else if (mutation.type === "attributes") {
				//console.log(`The ${mutation.attributeName} attribute was modified.`);
			}
		}
	};
	let keymaps= {};

	addEventListener("keydown", (event) => {});
	onkeydown = (e) => {
		// console.log(e)
		let key = "";
		if (e.ctrlKey) key += "Ctrl+";
		if (e.altKey) key += "Alt+";
		key += e.code;
		if (keymaps[key]) {
			if (document.body.contains(keymaps[key])) {
				if ($(keymaps[key])[0].nodeName == 'A') document.location = $(keymaps[key]).attr('href');
				if ($(keymaps[key])[0].nodeName == 'BUTTON') $(keymaps[key]).click();
			}
		}
	};

	// Create an observer instance linked to the callback function
	const observer = new MutationObserver(callback);

	// Start observing the target node for configured mutations
	observer.observe(targetNode, config);

	// Later, you can stop observing
	//observer.disconnect();

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

//------------Core2 worker-------------
if (window.hasOwnProperty('SharedWorker') && typeof window.SharedWorker === 'function') {
	var worker = new SharedWorker("core2/js/worker.js");
	worker.port.addEventListener(
		"message",
		function(e) {
			const evt = e.data.event;
			switch (e.data.type) {
				case 'modules':
					for (i in evt) {
						document.dispatchEvent(new CustomEvent(i, {'detail': evt[i]}));
					}
					break;
				case 'Core2':
					for (i in evt) {
						document.dispatchEvent(new CustomEvent("Core2", {'detail': evt[i]}));
					}
					break;

				default:
					console.log(e.data);
					break;
			}
		},
		false,
	);
	worker.onerror = function(event) {
		console.error("There is an error with your worker!");
	};
	worker.port.start();
	worker.port.postMessage("start");
	worker.port.postMessage("sse-open");

	document.addEventListener(
		"Core2-Fact",
		(e) => {
			e.detail.forEach(function (data){
				const e = JSON.parse(data);
				console.log(e)
				if (e.element) {
					$("#" + e.element.selector).text(e.element.text);
				}
			})
		},
		false,
	);
}