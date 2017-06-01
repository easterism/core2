
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
			$.ajax({
				url: "?module=admin&action=welcome",
				data: {sendSupportForm: 1, supportFormModule: m.val(), supportFormMessage: me.val()},
				dataType: "json",
				method: "POST"
			})
			.done(function(data, status) {
				$('#feedbackError')[0].className = 'completed';
				$('#feedbackError').html('<div>Сообщение успешно отправлено</div>');
				$('#feedbackError').show('fast');
                me.val('');
			})
			.fail(function(){
				err.push('Ошибка отправки сообщения :(');
				feedback.feedbackError(err);
			});
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