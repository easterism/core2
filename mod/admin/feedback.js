
var feedback = {
	post : function () {
		var m = $('#supportFormModule');
		var me = $('#supportFormMessage');
		$('#feedbackError').hide('fast');
		var err = [];
		if (!m.val()) {
			err.push('Выберите модуль');
			this.feedbackError(err);
		}
		if (!me.val()) {
			err.push('Введите текст сообщения');
			this.feedbackError(err);
		}
		if (!err.length) {
			$.post("?module=admin&action=welcome",
				{sendSupportForm: 1, supportFormModule: m.val(), supportFormMessage: me.val()},
				function(data, status) {
					if (status != 'success') {
						var data = {error : [status]}
					}
					if (data && data.error) {
						feedback.feedbackError(data.error);
					} else {
						$('#feedbackError')[0].className = 'completed';
						$('#feedbackError').html('<div>Сообщение успешно отправлено</div>');
						$('#feedbackError').show('fast');
					}
				},
				'json'
			);
		}
	},
	feedbackError : function(err) {
		$('#feedbackError')[0].className = 'error';
		var v = '';
		for (var k in err) {
			v += '<div>' + err[k] + '</div>';
		}
		$('#feedbackError').html(v);
		$('#feedbackError').show('fast');
	}
}