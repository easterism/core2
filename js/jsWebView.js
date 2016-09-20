

var locData = {};
var loc = ''; //DEPRECATED

function changeRoot(obj, to) {
	if (!obj) return;
	if (to) {
		load(to);
	}
}

function toAnchor(id) {
	if (id.indexOf('#') < 0) {
		id = "#" + id;
	}
	var ofy = $(id);
	if (ofy[0]) {
		$('html,body').animate({
			scrollTop : ofy.offset().top
		}, 'fast');
	}
}

var preloader = {
	extraLoad : {},
	oldHash : {},
	show : function() {
		$( "#left-panel" ).panel( "close" );
		$( "#right-panel" ).panel( "close" );
		$.mobile.loading( "show", {
			text: "foo",
			textVisible: false,
			theme: $.mobile.loader.prototype.options.theme,
			html: ""
		});
	},
	hide : function() {
		$.mobile.loading("hide");
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
							if (aUrl.hasOwnProperty(k)) {
								bUrl.push(encodeURIComponent(k) + '=' + encodeURIComponent(aUrl[k]));
							}
						}
						$('#' + el).load("index.php?" + bUrl.join('&'));
					}
				}
				preloader.extraLoad = {};
			}
			toAnchor(locData.id);
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
	normUrl: function () {

	}
};

var load = function (url, data, id, callback) {
	preloader.show();
	if (!id) id = '#main_body';
	else if (typeof id === 'string') {
		id = '#' + id;
	}
	if (url.indexOf("index.php") == 0) {
		url = url.substr(10);
	}
	url = preloader.prepare(url);

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

	}
	else {
		url = '?module=admin&action=welcome';
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
	Android.showToast(url);
	if (locData.data) {
		$(locData.id).load('index.php' + url, locData.data, callback);
	} else {
		$(locData.id).load('index.php' + url, callback);
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
