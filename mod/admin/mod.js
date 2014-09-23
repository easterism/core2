var modules = {
    'repo': function (url, repo_id) {
        $.get('index.php?module=admin&action=modules&repo',
            {'url': url, 'repo_id': repo_id},
            function(data, textStatus){
                if(textStatus == 'success') {
                    $("#repo_" + repo_id).html(data);
                }
            },
            'html');
    },
    'spoiler': function (id) {
        $('.' + id).toggle();
    }
};

	function checkToAll(obj) {
		var i = obj.id.split("_");
		var obj2 = document.getElementById("access_"+i[1]+"_owner");
		if (obj.checked==true) {obj2.checked=true;obj2.disabled=true;}
		else {obj2.checked=false;obj2.disabled=false;}
	}
	function subcheckToAll(obj) {
		var i = obj.id.split("_");
		var obj2 = document.getElementById("subaccess_"+i[1]+"_owner");
		if (obj.checked==true) {obj2.checked=true;obj2.disabled=true;}
		else {obj2.checked=false;obj2.disabled=false;}
	}
	function newRule(container) {
		var id = new Date().valueOf();
		var x = document.createElement("input");
		x.type = "text";
		x.className = "input";
		x.name = "addRules["+id+"]";
		document.getElementById(container).appendChild(x);
		x = document.createElement("input");
		x.type = "checkbox";
		x.name = "value_all["+id+"]";
		x.id = "access_" + id + "_all"
		x.onclick = function() {checkToAll(this)};
		x.value = "all";
		document.getElementById(container).appendChild(x);
		x = document.createElement("label");
		x.innerHTML = "Все";
		document.getElementById(container).appendChild(x);
		x = document.createElement("input");
		x.type = "checkbox";
		x.name = "value_owner["+id+"]";
		x.id = "access_" + id + "_owner"
		x.value = "owner";
		document.getElementById(container).appendChild(x);
		x = document.createElement("label");
		x.innerHTML = "Владелец";
		document.getElementById(container).appendChild(x);
		x = document.createElement('br');
		document.getElementById(container).appendChild(x);
	}
	function newField(container) {
		var id = new Date().valueOf();
		var x = document.createElement("input");
		x.type = "text";
		x.className = "input";
		x.name = "customField["+id+"]";
		document.getElementById(container).appendChild(x);
		x = document.createElement('br');
		document.getElementById(container).appendChild(x);
	}

	function uninstallModule(mod, v, modUninstall) {
		if (confirm('Разинсталировать модуль ' + mod + ' ' + v + '?')) {
			load('index.php' + document.location.hash, {"uninstall":modUninstall});
		} else return false;
	}

	function installModule(mod, v, modInstall) {
		if (confirm('Установить модуль ' + mod + ' ' + v + '?')) {
			load('index.php?module=admin&action=modules&loc=core&tab_mod=2', {"install":modInstall});
		} else return false;
	}

	function installModuleFromRepo(mod, v, modInstall, repo) {
		if (confirm('Установить модуль ' + mod + ' ' + v + '?')) {
            load('index.php?module=admin&action=modules&loc=core&tab_mod=2', {"install_from_repo":modInstall, "repo": repo});
		} else return false;
	}
