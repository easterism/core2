<div data-role="page" id="demo-page" data-url="demo-page">
	    <div data-role="header" data-theme="b">
		        <h1>Home</h1>
		        <a href="#left-panel" data-icon="carat-r" data-iconpos="notext" data-shadow="false" data-iconshadow="false" class="ui-nodisc-icon">Open left panel</a>
		    </div>
	    <div role="main" class="ui-content">
		    <div id="mainContainer">
				<div id="main_body"></div>
			</div>
		</div>
	    <div data-role="panel" id="left-panel" data-theme="b">
			<!--BEGIN modules -->
			<p id="module_[MODULE_ID]" onclick="changeRoot(this, '[MODULE_URL]')">[MODULE_NAME]</p>
			<!--END modules -->
	        <!-- BEGIN submodules -->

		<!-- END submodules -->
	        <a href="#" data-rel="close" class="ui-btn ui-corner-all ui-shadow ui-mini ui-btn-inline ui-icon-delete ui-btn-icon-left ui-btn-right">Close</a>
	    </div>
</div>
