<div id="feedbackError" style="display:none"></div>
<form id="feedback-form" method="post" onsubmit="feedback.post();return false;">
    <table height="100%" width="100%" cellpadding="5">
        <tr>
            <td width="60" class="feedback-label">Модуль:</td>
            <td width="240" class="feedback-control">
    	        <select class="feedBackSelect" id="supportFormModule">
    	            <option value="" disabled="disabled" selected="selected">Выберите модуль</option>
                </select>
            </td>
        </tr>
        <tr valign="top">
            <td class="feedback-label">
                <br/>
                Собщение:
            </td>
        <td width="240" class="feedback-control">
            <br/>
            <textarea id="supportFormMessage" class="feedBackMessage"></textarea>
        </td>
    </tr>
    <tr>
        <td class="feedback-label">&nbsp;</td>
        <td class="feedback-control">
            <br/>
            <input type="submit" name="sendSupportForm" value="Отправить" class="button"/>
        </td>
    </tr>
    </table>
</form>