<table style="width: 100%;white-space: nowrap;">
    <tr>
        <td style="min-width: 180px">Доступен</td>
        <td style="min-width: 150px"><input type="checkbox" name="access[access][MODULE_ID]" id="access_MODULE_ID"/></td>
        <td>
            <input type="checkbox" id="default_MODULE_ID" name="access[default][MODULE_ID]" onclick="ro.checkToDefault2(this)" />
            <label for="default_MODULE_ID">
                По умолчанию
            </label>
        </td>
    </tr>
    <tr>
        <td>Просмотр списка</td>
        <td>
            <input onclick="ro.checkToAll(this)" type="checkbox" name="access[list_all][MODULE_ID]" id="list_all_MODULE_ID" />
            <label for="list_all_MODULE_ID">
                Все
            </label>
            <input type="checkbox" name="access[list_owner][MODULE_ID]" id="list_owner_MODULE_ID" />
            <label for="list_owner_MODULE_ID">
                Владелец
            </label>
        </td>
        <td>
            <input type="checkbox" id="list_default_MODULE_ID" name="access[list_default][MODULE_ID]" onclick="ro.checkToDefault(this)"/>
            <label for="list_default_MODULE_ID">
                По умолчанию
            </label>
        </td>
    </tr>
    <tr>
        <td>Чтение</td>
        <td>
            <input onclick="ro.checkToAll(this)" type="checkbox" name="access[read_all][MODULE_ID]" id="read_all_MODULE_ID" />
            <label for="read_all_MODULE_ID">
                Все
            </label>
            <input type="checkbox" name="access[read_owner][MODULE_ID]" id="read_owner_MODULE_ID" />
            <label for="read_owner_MODULE_ID">
                Владелец
            </label>
        </td>
        <td>
            <input type="checkbox" id="read_default_MODULE_ID" name="access[read_default][MODULE_ID]" onclick="ro.checkToDefault(this)" />
            <label for="read_default_MODULE_ID">
                По умолчанию
            </label>
        </td>
    </tr>
    <tr>
        <td>Редактирование</td>
        <td>
            <input onclick="ro.checkToAll(this)" type="checkbox" name="access[edit_all][MODULE_ID]" id="edit_all_MODULE_ID" />
            <label for="edit_all_MODULE_ID">
                Все
            </label>
            <input type="checkbox" name="access[edit_owner][MODULE_ID]" id="edit_owner_MODULE_ID" />
            <label for="edit_owner_MODULE_ID">
                Владелец
            </label>
        </td>
        <td>
            <input type="checkbox" id="edit_default_MODULE_ID" name="access[edit_default][MODULE_ID]" onclick="ro.checkToDefault(this)" />
            <label for="edit_default_MODULE_ID">
                По умолчанию
            </label>
        </td>
    </tr>
    <tr>
        <td>Удаление</td>
        <td>
            <input onclick="ro.checkToAll(this)" type="checkbox" name="access[delete_all][MODULE_ID]" id="delete_all_MODULE_ID" />
            <label for="delete_all_MODULE_ID">
                Все
            </label>
            <input type="checkbox" name="access[delete_owner][MODULE_ID]" id="delete_owner_MODULE_ID" />
            <label for="delete_owner_MODULE_ID">
                Владелец
            </label>
        </td>
        <td>
            <input type="checkbox" id="delete_default_MODULE_ID" name="access[delete_default][MODULE_ID]" onclick="ro.checkToDefault(this)" />
            <label for="delete_default_MODULE_ID">
                По умолчанию
            </label>
        </td>
    </tr>
    <!-- BEGIN custom -->
    <tr>
        <td>NAME_ACTION</td>
        <td>
            <input onclick="ro.checkToAll(this)" type="checkbox" name="access[TYPE_ID_all][MODULE_ID]" id="TYPE_ID_all_MODULE_ID" />
            <label for="TYPE_ID_all_MODULE_ID">
                Все
            </label>
            <input type="checkbox" name="access[TYPE_ID_owner][MODULE_ID]" id="TYPE_ID_owner_MODULE_ID" />
            <label for="TYPE_ID_owner_MODULE_ID">
                Владелец
            </label>
        </td>
        <td>
            <input type="checkbox" id="TYPE_ID_default_MODULE_ID" name="access[TYPE_ID_default][MODULE_ID]" onclick="ro.checkToDefault(this)"/>
            <label for="TYPE_ID_default_MODULE_ID">
                По умолчанию
            </label>
        </td>
    </tr>
    <!-- END custom -->
</table>
