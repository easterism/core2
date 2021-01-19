<div class="ctrl-dpr" id="[ID]-ctrl-dpr">
    <div class="ctrl-dpr-controls">
        <input type="hidden" id="[ID]-from-value" class="ctrl-dpr-from-value" name="[NAME]" value="[VALUE_FROM]"/>
        <input type="hidden" id="[ID]-to-value" class="ctrl-dpr-to-value" name="[NAME]" value="[VALUE_TO]"/>

        <input class="ctrl-dpr-from-day first input" id="[ID]-from-day" min="1" max="31"
               autocomplete="off" placeholder="дд" type="text"/>
        <input class="ctrl-dpr-from-month middle input" id="[ID]-from-month" min="1" max="12"
               autocomplete="off" placeholder="мм" type="text"/>
        <input class="ctrl-dpr-from-year middle input" id="[ID]-from-year" max="9999"
               autocomplete="off" placeholder="гггг" type="number"/>

        <span class="ctrl-add-on"> - </span>

        <input class="ctrl-dpr-to-day middle input" id="[ID]-to-day" min="1" max="31"
               autocomplete="off" placeholder="дд" type="text"/>
        <input class="ctrl-dpr-to-month middle input" id="[ID]-to-month" min="1" max="12"
               autocomplete="off" placeholder="мм" type="text"/>
        <input class="ctrl-dpr-to-year middle input" id="[ID]-to-year" max="9999"
               autocomplete="off" placeholder="гггг" type="number"/>

        <span class="ctrl-dpr-clear middle">
            <img src="core2/html/material/img/clear.png" alt="_tr(Очистить)" title="_tr(Очистить)">
        </span>
        <span class="ctrl-dpr-trigger last">
            <img src="core2/html/material/img/calendar.png" alt="..." title="...">
        </span>
    </div>
    <div class="clearfix"></div>
    <div class="ctrl-dpr-container" style="display: none"></div>
</div>
<div class="searchOutHtml">[OUT]</div>
<script>
    control_datepicker_range.create($('#[ID]-ctrl-dpr')[0]);
</script>