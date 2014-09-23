var ro = {
	checkToAll : function (obj) {
		var i = obj.id.split("_");
		var obj2 = document.getElementById(i[0] + "_owner_" + i[2]);
		if (obj.checked == true) {
			obj2.checked = true;
			obj2.disabled = true;
		}
		else {obj2.checked=false;obj2.disabled=false;}
	},
	checkToDefault : function (obj) {
		var i = obj.id.split("_");
		var obj2 = document.getElementById(i[0] + "_all_" + i[2]);
		var obj3 = document.getElementById(i[0] + "_owner_" + i[2]);
		if (obj.checked==true) {
			obj2.disabled=true;
			obj3.disabled=true;
		}
		else {obj2.disabled=false;obj3.disabled=false;}
	},
	checkToDefault2 : function (obj) {
		var i = obj.id.split("_");
		var obj2 = document.getElementById("access_" + i[1]);
		if (obj2) {
			if (obj.checked == true) {
				obj2.disabled = true;
			}
			else {
				obj2.disabled = false;
			}
		}
	},
	setDefault : function (obj) {
		for (rule in obj) {
			for (module in obj[rule]) {
				var el = document.getElementById(rule + '_' + module);
				if (el) {
					el.checked = true;
					if (el.getAttribute('onclick')) el.onclick();
					/*
					if (document.all) {
											for (i in el) {
												if (i == 'onchange' && null != el[i]) {
													//alert(el[i])
												}
											}
										} else {
											if (el.getAttribute('onclick')) el.onclick();
										}
					*/
				}
			}
		}
	},
	setDefaultNew : function () {
		var objs = document.getElementsByTagName('input');
	    for(var i = 0; i < objs.length; i++){
	        obj = objs[i];
	        if ((typeof obj.type === 'string') && (obj.type === 'checkbox') && (obj.id.indexOf('default_') > -1)){
	        	obj.checked = true;
	        } else if (obj.name.indexOf('access') > -1) {
	        	obj.disabled = true;
			}
	    }
	}
}
