	<tr class="searchPanel">
		<th colspan="100">
			<div class="showFilterIcon" onclick="{CLICK_FILTER}" title="_tr(Поиск)">_tr(Поиск)</div>
			<!-- BEGIN clear -->
			<div class="clearFilterIcon" onclick="{CLICK_CLEAR}" title="_tr(Очистить)"></div>
			<!-- END clear -->
			<!-- BEGIN col -->
			<div class="columnFilterIcon" onclick="{CLICK_COL}" title="_tr(Колонки)">_tr(Колонки)</div>
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
                        <div style="padding: 5px 0"><input type="submit" class="buttonSmall" value="_tr(Искать)"></div>
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
					<input type="submit" class="buttonSmall btn btn-primary" value="_tr(Применить)"/>
				</form>
			</div>
		</td>
	</tr>
	<!-- END filterColumnContainer -->