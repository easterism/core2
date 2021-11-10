<tr class="searchPanel">
	<th colspan="100">
		<div class="showFilterIcon" onclick="listx.showFilter('[RESOURCE]')" title="_tr(Поиск)">_tr(Поиск)</div>
		<!-- BEGIN clear -->
		<div class="clearFilterIcon" onclick="listx.clearFilter('[RESOURCE]', [AJAX])" title="_tr(Очистить)"></div>
		<!-- END clear -->
		<!-- BEGIN col -->
		<div class="columnFilterIcon" onclick="listx.columnFilter('[RESOURCE]')" title="_tr(Колонки)">_tr(Колонки)</div>
		<!-- END col -->
		<!-- BEGIN templates_list -->
		<div class="columnTemplatesIcon" onclick="listx.showTemplates('[RESOURCE]')" title="_tr(Шаблоны)">
			<i class="icon-template"></i> _tr(Шаблоны)
		</div>
		<!-- END templates_list -->
	</th>
</tr>

<tr class="searchContainer hide" id="filter[RESOURCE]">
	<td colspan="100">
		<form onsubmit="listx.startSearch('[RESOURCE]', [AJAX]); return false;">
			<!-- BEGIN fields -->
			<div class="searchRow">
				<div>{FIELD_CAPTION}</div>
				<div>
					{FIELD_CONTROL}
					<div style="padding: 5px 0">
						<!-- BEGIN search_btn -->
						<input type="submit" class="buttonSmall" value="_tr(Искать)">
						<!-- END search_btn -->

						<!-- BEGIN search_btn_template -->
						<div class="btn-group">
							<button type="submit" class="btn btn-sm btn-primary">_tr(Искать)</button>
							<button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown">
								<span class="caret"></span>
							</button>
							<ul class="dropdown-menu dropdown-menu-left">
								<li><a href="#" onclick="listx.template.create('[RESOURCE]', [AJAX]);return false;">_tr(Сохранить шаблон)</a></li>
							</ul>
						</div>
						<!-- END search_btn_template -->
					</div>
				</div>
			</div>
			<!-- END fields -->
		</form>
	</td>
</tr>

<!-- BEGIN filterColumnContainer -->
<tr class="searchContainer hide" id="filterColumn[RESOURCE]">
	<td colspan="100">
		<div class="list-filter-container">
			<form onsubmit="listx.columnFilterStart('[RESOURCE]', [AJAX]); return false;">
				<div class="checkbox checkbox-all">
					<label><input type="checkbox" onclick="listx.toggleAllColumns('[RESOURCE]')" checked="checked">Все</label>
				</div>

				<!-- BEGIN filterColumn -->
				<div class="checkbox">
					<label><input type="checkbox" value="{VAL}" {checked}/>{COL_CAPTION}</label>
				</div>
				<!-- END filterColumn -->

				<!-- BEGIN column_btn -->
				<input type="submit" class="btn btn-sm btn-primary" value="_tr(Применить)">
				<!-- END column_btn -->

				<!-- BEGIN column_btn_template -->
				<div class="btn-group">
					<button type="submit" class="btn btn-sm btn-primary">_tr(Применить)</button>
					<button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown">
						<span class="caret"></span>
					</button>
					<ul class="dropdown-menu dropdown-menu-left">
						<li><a href="#" onclick="listx.template.create('[RESOURCE]', [AJAX]);return false;">_tr(Сохранить шаблон)</a></li>
					</ul>
				</div>
				<!-- END column_btn_template -->
			</form>
		</div>
	</td>
</tr>
<!-- END filterColumnContainer -->


<!-- BEGIN templates_container -->
<tr class="searchContainer hide" id="templates-row-[RESOURCE]">
	<td colspan="100">
		<div class="listx-template-items">
			<!-- BEGIN template_item -->
			<div class="listx-template-item">
				<button type="button" class="btn btn-sm btn-default listx-template-item-select"
						onclick="listx.template.select('[RESOURCE]', '[ID]', [AJAX])">[TITLE]</button>
				<button type="button" class="btn btn-sm btn-default listx-template-item-remove"
						onclick="listx.template.remove('[RESOURCE]', '[ID]', [AJAX])"><i class="fa fa-times"></i></button>
				<div class="clearfix"></div>
			</div>
			<!-- END template_item -->
		</div>
	</td>
</tr>
<!-- END templates_container -->