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
};
$(document).ready(function() {
	let clicks = 0;
	let module_name = '';
	$('.roleModulesClick').click(function(){
		clicks += 1;
		let this_id 		= $(this).parent('.roleModules').attr('id');
		let access_role 	= $('#access_'+ this_id);
		let all_role 		= $("input[id$='all_"+ this_id +"']");
		let owner_role 		= $("input[id$='owner_"+ this_id +"']");
		let default_role 	= $("input[id$='default_"+ this_id +"']");

		if (module_name !== this_id){
			clicks = 1;
		}
		module_name = this_id;

		if ( clicks === 1 ){
			access_role.prop('checked', true);
			access_role.prop("disabled",false);
			all_role.prop('checked', true);
			owner_role.prop('disabled', true);
			owner_role.prop('checked', true);
		} else if ( clicks === 2 ){
			all_role.prop('checked', false);
			owner_role.prop('checked', true);
			owner_role.prop('disabled', false);
		} else if ( clicks === 3 ){
			access_role.prop('checked', false);
			owner_role.prop('checked', false);
			default_role.prop('checked', true);
		} else if ( clicks === 4 ){
			default_role.prop('checked', false);
		}
		if (clicks === 4 ){
			clicks = 0;
		}

	});

});
