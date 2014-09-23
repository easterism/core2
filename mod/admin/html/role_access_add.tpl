<div class="modRoles">
    <div class="access left">
        NAME_ACTION:
    </div>
    <div class="access2 left">
        <input onclick="ro.checkToAll(this)" type="checkbox" name="access[TYPE_ID_all][MODULE_ID]" id="TYPE_ID_all_MODULE_ID" />
        <label for="TYPE_ID_all_MODULE_ID">
            Все
        </label>
        <input type="checkbox" name="access[TYPE_ID_owner][MODULE_ID]" id="TYPE_ID_owner_MODULE_ID" />
        <label for="TYPE_ID_owner_MODULE_ID">
            Владелец
        </label>
    </div>
    <div class="left">
        <input type="checkbox" id="TYPE_ID_default_MODULE_ID" name="access[TYPE_ID_default][MODULE_ID]" onclick="ro.checkToDefault(this)"/>
        <label for="TYPE_ID_default_MODULE_ID">
            По умолчанию
        </label>
    </div>
</div>