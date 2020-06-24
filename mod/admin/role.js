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
		let  this_id 			= $(this).parent('.roleModules').attr('id');
		let  access_role 		 = '#access_' 	 		+ this_id;
		let  default_role 		 = '#default_' 	 		+ this_id;
		let  list_all_role 		 = '#list_all_' 	 	+ this_id;
		let  list_owner_role 	 = '#list_owner_' 		+ this_id;
		let  list_default_role   = '#list_default_' 	+ this_id;
		let  read_all_role 		 = '#read_all_' 		+ this_id;
		let  read_owner_role 	 = '#read_owner_' 		+ this_id;
		let  read_default_role 	 = '#read_default_' 	+ this_id;
		let  edit_all_role 		 = '#edit_all_' 		+ this_id;
		let  edit_owner_role 	 = '#edit_owner_' 		+ this_id;
		let  edit_default_role 	 = '#edit_default_' 	+ this_id;
		let  delete_all_role 	 = '#delete_all_' 		+ this_id;
		let  delete_owner_role 	 = '#delete_owner_' 	+ this_id;
		let  delete_default_role = '#delete_default_' 	+ this_id;

		if (module_name !== this_id){
			clicks = 1;
		}
		module_name = this_id;

		if ( clicks === 1 ){
			$(access_role).prop('checked', true);
			$(access_role).prop("disabled",false);
			$(list_all_role).prop("disabled",false);
			$(read_all_role).prop("disabled",false);
			$(edit_all_role).prop("disabled",false);
			$(delete_all_role).prop("disabled",false);

			$(default_role).prop('checked', false);
			$(list_default_role).prop('checked', false);
			$(read_default_role).prop('checked', false);
			$(edit_default_role).prop('checked', false);
			$(delete_default_role).prop('checked', false);

			$(list_all_role).prop('checked', true);
			$(read_all_role).prop('checked', true);
			$(edit_all_role).prop('checked', true);
			$(delete_all_role).prop('checked', true);

			$(list_owner_role).prop("disabled",true);
			$(list_owner_role).prop("checked",true);

			$(read_owner_role).prop("checked",true);
			$(read_owner_role).prop("disabled",true);

			$(edit_owner_role).prop("checked",true);
			$(edit_owner_role).prop("disabled",true);

			$(delete_owner_role).prop("checked",true);
			$(delete_owner_role).prop("disabled",true);
		} else if (clicks === 2){
			$(list_all_role).prop('checked', false);
			$(read_all_role).prop('checked', false);
			$(edit_all_role).prop('checked', false);
			$(delete_all_role).prop('checked', false);

			$(list_owner_role).prop('checked', true);
			$(read_owner_role).prop('checked', true);
			$(edit_owner_role).prop('checked', true);
			$(delete_owner_role).prop('checked', true);

			$(list_owner_role).prop("disabled",false);
			$(read_owner_role).prop("disabled",false);
			$(edit_owner_role).prop("disabled",false);
			$(delete_owner_role).prop("disabled",false);

		} else if (clicks === 3){
			$(access_role).prop('checked', false);
			$(default_role).prop('checked', true);

			$(list_owner_role).prop('checked', false);
			$(read_owner_role).prop('checked', false);
			$(edit_owner_role).prop('checked', false);
			$(delete_owner_role).prop('checked', false);

			$(list_default_role).prop('checked', true);
			$(read_default_role).prop('checked', true);
			$(edit_default_role).prop('checked', true);
			$(delete_default_role).prop('checked', true);

		} else if (clicks === 4){
			$(default_role).prop('checked', false);
			$(list_default_role).prop('checked', false);
			$(read_default_role).prop('checked', false);
			$(edit_default_role).prop('checked', false);
			$(delete_default_role).prop('checked', false);
		}

			if (clicks === 4 ){
				clicks = 0;
			}

	});


});
