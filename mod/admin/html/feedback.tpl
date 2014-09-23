<div id="feedbackError" style="display:none"></div>
<form method="post" onsubmit="feedback.post();return false;">
    <table height="100%" cellpadding="5">
    <tr><td width="60">Модуль:</td><td width="240">
    	<select class="feedBackSelect" id="supportFormModule">
    	<option value="" disabled="disabled" selected="selected">Выберите модуль</option>
        </select>
    </td></tr>
    <tr valign="top"><td><br/>Собщение:</td><td><br/><textarea id="supportFormMessage" class="feedBackMessage"></textarea></td></tr>
    <tr><td>&nbsp;</td><td><br/><input type="submit" name="sendSupportForm" value="Отправить" class="button"/></td></tr>
    </table>
</form>