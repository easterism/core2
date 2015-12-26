    <tr class="headerText">
        <td width="1%" rowspan="{ROWSPAN}">№</td>
    <!-- BEGIN extrahead -->
        <!-- BEGIN extracell -->
        <td colspan="{COLSPAN}" rowspan="{ROWSPAN2}">{CAPTION}</td>
        <!-- END extracell -->
    </tr><tr>
    <!-- END extrahead -->

    <!-- BEGIN cell -->
        <td width="{WIDTH}" onclick="listx.doOrder('{resource}', '{ORDER_VALUE}', isAjax)" style="cursor:pointer;">
            <div>{CAPTION}<span style="margin-left:8px">{ORDER_TYPE}</span></div>
        </td>
    <!-- END cell -->

    <!-- BEGIN cellnosort -->
        <td width="{WIDTH}">
            {CAPTION}
        </td>
    <!-- END cellnosort -->

    <!-- BEGIN checkboxes -->
        <td width="1%"><input class="input" type="checkbox" onclick="listx.checkAll(this, '{resource}')"/></td>
    <!-- END checkboxes -->
    </tr>