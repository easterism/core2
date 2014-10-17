<thead id="searchHead">
	<tr class="headerText">
		<td colspan="100" class="HeaderTextTD">
			<!-- BEGIN col -->
			<div class="columnFilterIcon" onclick="{CLICK_COL}" title="Колонки"></div>
			<!-- END col -->
			<div class="showFilterIcon" onclick="{CLICK_FILTER}" title="Поиск"></div>
			<!-- BEGIN clear -->
			<div class="clearFilterIcon" onclick="{CLICK_CLEAR}" title="Очистить"></div>
			<!-- END clear -->
		</td>
	</tr>
	
	<tr class="searchContainer" style="display:none" id="{filterID}">
		<td colspan="100">
			<div>
                <form onsubmit="{CLICK_START}">
                <table>
                    <!-- BEGIN fields -->
                    <tr>
                        <td align="right">{FIELD_CAPTION}:</td>
                        <td>{FIELD_CONTROL}</td>
                    </tr>
                    <!-- END fields -->
                    <tr>
                        <td></td><td></td>
                        <td><input type="submit" class="buttonSmall" value="Искать" style="position:absolute;margin-top:-25px"></td>
                    </tr>
                </table>
                </form>
            </div>
		</td>
	</tr>
	<!-- BEGIN filterColumnContainer -->
	<tr class="searchContainer" style="display:none" id="{filterColumnID}">
		<td colspan="100">
			<div>
				<form onsubmit="{COL_SUBMIT}">
						<!-- BEGIN filterColumn -->
					<label><input type="checkbox" value="{VAL}" {checked}/>{COL_CAPTION}</label><br>
						<!-- END filterColumn -->
					<input type="submit" class="buttonSmall" value="Применить" style="margin:5px"/>
				</form>
			</div>
		</td>
	</tr>
	<!-- END filterColumnContainer -->
</thead>