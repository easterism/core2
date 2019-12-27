
<form id="form-monitoring" action="" onsubmit="monitoring.search(); return false;">
    <div class="monitoring-toolbar">
        <div class="pull-left monitoring-total">
            Всего строк: <b>[COUNT_LINES]</b>
        </div>
        <div class="pull-right">

            <label>
                Отобразить:
                <input type="text" class="input" size="3" name="lines" value="[VIEW_LINES]">
            </label>

            <label>
                Поиск:
                <input type="text" class="input" name="search" value="[SEARCH]">
            </label>

            <input type="submit" class="button" value="Показать"/>
            <a id="download-log" title="Скачать"
               href="index.php?module=admin&action=monitoring&loc=core&tab_admin_monitoring=3&download=1"
               ><img src="core2/mod/admin/img/download.png"></a>
        </div>
        <div class="clearfix"></div>
    </div>
</form>

<pre class="monitoring-body">[BODY]</pre>