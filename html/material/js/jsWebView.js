

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
		};
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
	}
	else if (jqxhr.status == 500) {
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
	if (h != url && url.indexOf('&__') < 0) {
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
		locData['id'] = id;
		locData['data'] = data;
        locData['loc'] = 'index.php' + url;
		loc = 'index.php' + url; //DEPRECATED
        if (locData.data) {
			$(locData.id).load('index.php' + url, locData.data, callback);
		} else {
			$(locData.id).load('index.php' + url, callback);
		}
	}
};

$( document ).on( "pagecreate", "#demo-page", function() {
	$( document ).on( "swipeleft swiperight", "#demo-page", function( e ) {
		// We check if there is no open panel on the page because otherwise
		// a swipe to close the left panel would also open the right panel (and v.v.).
		// We do this by checking the data that the framework stores on the page element (panel: open).
		if ( $( ".ui-page-active" ).jqmData( "panel" ) !== "open" ) {
			if ( e.type === "swipeleft" ) {
				$( "#right-panel" ).panel( "open" );
			} else if ( e.type === "swiperight" ) {
				$( "#left-panel" ).panel( "open" );
			}
		}
	});
});
