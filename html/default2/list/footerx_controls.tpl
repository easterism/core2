<tfoot>
	<tr>
		<td colspan="100" style="padding:0">
			<table class="paginTable" width="100%" id="[IDD]">
				<tr>
					<!-- BEGIN pages -->
						<td align="left">
							<input type="text" class="input" size="2"/>
						</td>
						<td align="left">
							<button class="paginButton" type="button" onclick="{GO_TO_PAGE}"><span>»</span></button>
						</td>
					<!-- END pages -->

					<td align="center" width="100%">
						<!-- BEGIN pages2 -->
						<button onclick="{GO_TO}" title="{BACK}" class="paginButton"><span>«</span></button>
						<!-- END pages2 -->
						{CURR_PAGE}
						<!-- BEGIN pages3 -->
						<button onclick="{GO_TO}" title="{FORW}" class="paginButton"><span>»</span></button>
						<!-- END pages3 -->
					</td>

					<!-- BEGIN recordsPerPage -->
						<td align="right" width="10">
							<select id="footerSelectCount" class="controlElements" onchange="{SWITCH_CO}"></select>
						</td>
					<!-- END recordsPerPage -->
				</tr>
			</table>
		</td>
	</tr>
</tfoot>