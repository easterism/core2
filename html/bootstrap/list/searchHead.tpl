	<tr class="searchPanel">
		<th colspan="100">
			<div class="showFilterIcon" onclick="{CLICK_FILTER}" title="Поиск">Поиск</div>
			<!-- BEGIN clear -->
			<div class="clearFilterIcon" onclick="{CLICK_CLEAR}" title="Очистить"></div>
			<!-- END clear -->
			<!-- BEGIN col -->
			<div class="columnFilterIcon" onclick="{CLICK_COL}" title="Колонки">Колонки</div>
			<!-- END col -->
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
			<div class="list-filter-container">
				<form onsubmit="{COL_SUBMIT}">
					<!-- BEGIN filterColumn -->
					<div class="checkbox">
						<label><input type="checkbox" value="{VAL}" {checked}/>{COL_CAPTION}</label>
					</div>
					<!-- END filterColumn -->
					<input type="submit" class="buttonSmall btn btn-primary" value="Применить"/>
					<input type="button" class="buttonSmall hide" value="Очистить"/>
				</form>
			</div>
		</td>
	</tr>
	<!-- END filterColumnContainer -->