<div id="menu-container">
	<table width="100%">
		<tr id="menu-row-1">
			<td id="heme-button">
				<a href="index.php#module=admin&action=welcome"
                   onclick="if (event.button === 0 && ! event.ctrlKey) load('index.php#module=admin&action=welcome');"><!--SYSTEM_NAME--></a>
			</td>
			<td align="left" width="100%">
                <ul id="menu-modules">
                    <!-- BEGIN modules -->
                    <li id="module-[MODULE_ID]" class="menu-module">
                        <a href="index.php#module=[MODULE_ID]"
                           onclick="if (event.button === 0 && ! event.ctrlKey) load('index.php#module=[MODULE_ID]');">[MODULE_NAME]</a>
                    </li>
                    <!-- END modules -->
                </ul>
			</td>
			<td id="user-section">
                <div class="dropdown">
                    <span class="dropdown-toggle" id="user-menu" data-toggle="dropdown">
                        <i class="fa fa-user"></i>
                        <!--CURRENT_USER_LOGIN-->
                        <span class="caret"></span>
                    </span>
                    <ul class="dropdown-menu dropdown-menu-right">
                        <li class="dropdown-profile profile">
                            <a href="index.php#module=profile">
                                <i class="fa fa-cogs"></i>
                                Профиль
                            </a>
                        </li>
                        <li class="dropdown-profile messages">
                            <a href="index.php#module=profile&action=messages">
                                <i class="fa fa-envelope-o"></i>
                                Сообщения
                            </a>
                        </li>
                        <li class="dropdown-profile divider"></li>
                        <li>
                            <a href="javascript:logout()">
                                <i class="fa fa-power-off"></i>
                                Выход
                            </a>
                        </li>
                    </ul>
                </div>
			</td>
		</tr>
		<tr valign="middle" id="menu-row-2">
            <td></td>
			<td colspan="20" align="left">
                <ul id="menu-submodules">
                    <!-- BEGIN submodules -->
                    <li id="submodule-[MODULE_ID]-[SUBMODULE_ID]" class="menu-submodule" style="display:none" >
                        <a href="index.php#module=[MODULE_ID]&action=[SUBMODULE_ID]"
                           onclick="if (event.button === 0 && ! event.ctrlKey) load('index.php#module=[MODULE_ID]&action=[SUBMODULE_ID]');">[SUBMODULE_NAME]</a>
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
    <div id="main_body"></div>
</div>

