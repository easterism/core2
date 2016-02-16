var monitoring = {
	search: function() {
		var url = 'index.php?module=admin&action=monitoring&loc=core&tab_e_monitoring=3';

        var form_data = '';
        $('#form-monitoring').serializeArray().map(function(x){
            form_data += '&' + x.name + '=' + encodeURIComponent(x.value);
        });

        load(url + form_data);
	},

    resize: function() {
        var height_body = $("body").height() - ($("#menu-container").height() || $("#menuContainer").height());
        var width_body  = $('#main_body').width();
        var elem_rect   = $('.monitoring-toolbar')[0].getBoundingClientRect();


        $('.monitoring-body').height(height_body - elem_rect.top - 30);
        $('.monitoring-body').width(width_body - 30);
    }
};

$(document).ready(monitoring.resize);
$(window).resize(monitoring.resize);