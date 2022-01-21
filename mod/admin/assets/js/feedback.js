
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
			stopRecordingCallback();
			var serializedFormData = new FormData(document.getElementById('feedback-form'));
			serializedFormData.append('sendSupportForm', 1);
			if (recorder) {
				serializedFormData.append('video-filename', "feedback.webm");
				serializedFormData.append('video-blob', recorder.getBlob());
			}
			var request = new XMLHttpRequest();
			request.onreadystatechange = function () {
				if (request.readyState == 4) {
					if (request.status == 200) {
						$('#feedbackError')[0].className = 'completed';
						$('#feedbackError').html('<div>Сообщение успешно отправлено</div>');
						$('#feedbackError').show('fast');
						me.val('');
						if (recorder) {
							recorder.destroy();
							recorder = null;
						}
					}
					else {
						err.push('Ошибка отправки сообщения :(');
						feedback.feedbackError(err);
					}
				}
			};
			request.open('POST', "index.php?module=admin&action=welcome");
			request.send(serializedFormData);
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