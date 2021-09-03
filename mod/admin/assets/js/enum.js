var en = {
	newEnumField : function() {
		$('#xxx').append($('#hid_custom').html());
	},
	selectEnumCustom : function(obj) {
		if (obj.value == 2 || obj.value == 3) {
			$(obj).next().show();
			$(obj).next().next().hide();
		} else if (obj.value == 6) {
            $(obj).next().hide();
            $(obj).next().next().show();
		} else {
			$(obj).next().hide();
			$(obj).next().next().hide();
		}
	}
}
