var monitoring = {
	search: function() {
		var url = 'index.php?module=admin&action=monitoring&loc=core&tab_monitoring=3';

        var form_data = '';
        $('#form-monitoring').serializeArray().map(function(x){
            form_data += '&' + x.name + '=' + encodeURIComponent(x.value);
        });

        load(url + form_data);
	}
};