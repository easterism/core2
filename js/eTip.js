/**
 * Javascript class for tips
 * created by Easter
 */

var eTip = {
	exists: new Array(),
	waiting: new Array(),
	waitingIn: new Array(),
	delayIn: 0,
	delayOut: 800,
	preloadHTML: "<font color=\"silver\">Loading...</font>",
	request: function() {},
	show: function(id, dIn, dOut, text) {
		var request = eTip.request;
		var d = document.getElementById(id);
		var hide = function() {
			//$('.etip').fadeOut(100,function(){
				$('.etip').remove();
			//});
			if (eTip.waiting[id] == 0) {
				if (document.getElementById(id + "_tip")) {
					var el = document.getElementById(id + "_tip");
					if (el.removeNode) el.removeNode()
					else el.remove();
					//document.getElementById(id + "_tip").style.display = 'none';
				}
			}
		};
		
		var timeout = function() {
			eTip.waitingIn[id] = 0;
			eTip.waiting[id] = 0;
			setTimeout(hide, delayOut);
		};
		
		eTip.waitingIn[id] = 1;
		
		d.onblur = timeout;
		if (text) {
			if (typeof text == "function") request = text;
			else request = text;
		}
		
		var delayIn = eTip.delayIn;
		if (dIn) delayIn = dIn;
		var delayOut = eTip.delayOut;
		if (dOut) delayOut = dOut;
		
		var realShow = function() {
			
			if (!id || eTip.waitingIn[id] != 1) {
				return;
			}
			eTip.waiting[id] = 1;
			
			//d.style.position = "relative";
			
			if (document.getElementById(id + "_tip")) {	
				var el = document.getElementById(id + "_tip");
			} else {
				var el = document.createElement('DIV');
				$('#'+id).after("<div class='etip' style=''><div>"+text+"</div></div>");
				//$('.etip').show(400);
				
			}
			
			
			el.className = "eTip";
			el.style.position = "absolute";
			el.style.left = d.offsetLeft + d.offsetWidth + 3 + "px";
			el.style.top = d.offsetTop + d.offsetHeight + 3 + "px";
			//el.style.width = "100px";
			//el.style.height = "100px";
			el.id = id + "_tip";
			el.innerHTML = eTip.preloadHTML;
			//if (!document.getElementById(id + "_tip")) document.body.appendChild(el);
			if (typeof request == "function") {
				if (eTip.exists[id]) {
					request = eTip.exists[id];
				} else {
					request = request();
				}
			}
			if (request) {
				el.innerHTML = request;
				eTip.exists[id] = request;
			}
		};
		
		if (delayIn > 0) setTimeout(realShow, delayIn);
		else realShow();
	}
}

function showHideTipHelp(tab) {
	var containerTipHelp = document.getElementById('containerTipHelp_' + tab);
	if (containerTipHelp != null) {
		if (containerTipHelp.style.display != 'block') {
			containerTipHelp.style.display = 'block';
		} else {
			containerTipHelp.style.display = 'none';
		}
	}
}