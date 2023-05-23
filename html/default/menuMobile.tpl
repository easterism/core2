<div id="menuContainer">
	<table class="menu_back">
		<tr>
			<td class="font homeButton">
				<img src="core2/html/default/img/home.gif" alt="На главную" title="_tr('Уйти на главную страницу')" onclick="goHome()"/>
			</td>
			<td align="center" width="100%" height="37">
				<table style="margin-bottom:-2px;">
					<tr valign="bottom">
						<td height="37" id="moduleContainer">
							<!-- BEGIN modules -->
			                <div id="module_[MODULE_ID]" class="menu_items" onclick="changeRoot(this, '[MODULE_URL]')"><span class="menu_block"><span>[MODULE_NAME]</span></span><span class="iefix">&nbsp;</span></div>
			                <!-- END modules -->
		                </td>
		            </tr>
		        </table>
			</td>
			<td class="font closeButton">
				<img src="core2/html/default/img/exit.gif" alt="Выход" title="_tr('Надоело! Хочу домой!!!')" onclick="logout()"/>
			</td>
		</tr>
		<tr valign="middle">
			<td colspan="20" class="menu_items2" align="center">
				<table id="table_submenu" height="100%">
					<tr valign="middle">
						<!-- BEGIN submodules -->
			            <td id="smodule_[MODULE_ID]_[SUBMODULE_ID]" class="submenu_items" style="display:none" onclick="changeSub(this, '[SUBMODULE_URL]')">[SUBMODULE_NAME]</td>
			            <!-- END submodules -->
		            </tr>
		        </table>
		    </td>
		</tr>
	</table>
</div>
<div id="preloader">
    <div></div>
</div>
<div id="mainContainer">
    <div id="progressbar"><table><tr><td><span id="container"></span></td></tr></table></div>
    <div id="main_body"></div>
</div>

