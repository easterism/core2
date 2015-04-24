<div id="menu-container">
	<table width="100%">
		<tr id="menu-row-1">
			<td id="heme-button">
				<a href="javascript:goHome()"><!--SYSTEM_NAME--></a>
			</td>
			<td align="left" width="100%" height="37">
                <ul id="menu-modules">
                    <!-- BEGIN modules -->
                    <li id="module-[MODULE_ID]" class="menu-module"
                        onclick="changeRoot(this, '[MODULE_URL]')">
                        [MODULE_NAME]
                    </li>
                    <!-- END modules -->
                </ul>
			</td>
			<td id="user-section">
                <span class="user-login"><!--CURRENT_USER_LOGIN--></span>
                <div class="">
                    <a href="javascript:logout()" title="Надоело! Хочу домой!!!"> Выход</a>
                </div>
			</td>
		</tr>
		<tr valign="middle" id="menu-row-2">
            <td></td>
			<td colspan="20" align="left">
                <ul id="menu-submodules">
                    <!-- BEGIN submodules -->
                    <li id="submodule-[MODULE_ID]-[SUBMODULE_ID]" class="menu-submodule"
                        style="display:none" onclick="changeSub(this, '[SUBMODULE_URL]')">
                        [SUBMODULE_NAME]
                    </li>
                    <!-- END submodules -->
                </ul>
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

