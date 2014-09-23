<thead id="searchHead">
	<tr class="headerText">
		<td colspan="100" class="HeaderTextTD">
			<table class="paginTable" width="100%">
				<tr>
					<td align="right" nowrap="nowrap" class="showFilterTD" onclick="{CLICK_FILTER}">
						<img src="core2/html/default/list/img/view.png" border="0" alt="Search" title="Поиск" />
					</td>
					<!-- BEGIN clear -->
					<td align="right" nowrap="nowrap" style="cursor:pointer;" onclick="{CLICK_CLEAR}">
						<img src="core2/html/default/list/img/clearview.png" border="0" alt="Clear" title="Очистить" />
					</td>
					<!-- END clear -->
					<td width="100%"></td>
				</tr>
			</table>
		</td>
	</tr>
	
	<tr class="searchContainer" style="display:none" id="{filterID}">
		<td colspan="100">
			<div>
                <form onsubmit="{CLICK_START}">
                <table>
                    <!-- BEGIN fields -->
                    <tr>
                        <td width="120" align="right">{FIELD_CAPTION}:</td>
                        <td>{FIELD_CONTROL}</td>
                    </tr>
                    <!-- END fields -->
                    <tr>
                        <td></td><td></td>
                        <td><input type="submit" class="button" value="Искать" style="position:absolute;margin-top:-30px"></td>
                    </tr>
                </table>
                </form>
            </div>
		</td>
	</tr>
</thead>