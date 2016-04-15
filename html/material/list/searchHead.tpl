	<tr class="searchPanel">
		<th colspan="100">
			<!-- BEGIN col -->
			<div class="columnFilterIcon" onclick="{CLICK_COL}" title="Колонки"></div>
			<!-- END col -->
			<div class="showFilterIcon" onclick="{CLICK_FILTER}" title="Поиск"></div>
			<!-- BEGIN clear -->
			<div class="clearFilterIcon" onclick="{CLICK_CLEAR}" title="Очистить"></div>
			<!-- END clear -->
		</th>
	</tr>
	
	<tr class="searchContainer hide" id="{filterID}">
		<td colspan="100">
            <form onsubmit="{CLICK_START}">
                <!-- BEGIN fields -->
                <div class="searchRow">
                    <div>{FIELD_CAPTION}</div>
                    <div>
                        {FIELD_CONTROL}
                        <!-- BEGIN submit -->
                        <div style="padding: 5px 0"><input type="submit" class="buttonSmall" value="Искать"></div>
                        <!-- END submit -->
                    </div>
                </div>
                <!-- END fields -->
            </form>
		</td>
	</tr>
	<!-- BEGIN filterColumnContainer -->
	<tr class="searchContainer hide" id="{filterColumnID}">
		<td colspan="100">
			<div>
				<form onsubmit="{COL_SUBMIT}">
					<!-- BEGIN filterColumn -->
					<label><input type="checkbox" value="{VAL}" {checked}/>{COL_CAPTION}</label><br>
					<!-- END filterColumn -->
					<input type="submit" class="buttonSmall" value="Применить" style="margin:5px"/>
					<input type="button" class="buttonSmall" value="Очистить" style="margin:5px"/>
				</form>
			</div>
		</td>
	</tr>
	<!-- END filterColumnContainer -->