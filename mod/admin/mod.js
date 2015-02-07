var modules = {
    'repo': function (repo_url, repo_id) {
        $.get('index.php?module=admin&action=modules&repo',
            {'getModsListFromRepo': repo_url},
            function(data, textStatus){
                if(textStatus == 'success') {
                    $("#repo_" + repo_id).html(data);
                }
            },
            'html');
    },
    'spoiler': function (id) {
        $('.' + id).toggle();
    },
    'refreshFiles': function (mod, v, mod_id) {
        if (confirm('Обновить файлы модуля ' + mod + ' ' + v + '?')) {
            load('index.php' + document.location.hash, {"refreshFilesModule":mod_id, "v": v});
        } else return false;
    },
    'download': function (mod, v, modId) {
		if (confirm('Скачать архив модуля ' + mod + ' ' + v + '?')) {
			loadPDF('index.php?module=admin&action=modules&tab_mod=2&download_mod=' + modId);
		} else return false;
	},
    'requestToRepo': function (mod, v, m_id, repo, request) {
		if (request == 'install' && confirm('Установить модуль ' + mod + ' ' + v + '?')) {
			load('index.php?module=admin&action=modules&loc=core&tab_mod=2', {"install_from_repo":m_id, "repo": repo});
		} else return false;
	},
	newRule: function(container) {
		var id = new Date().valueOf();
		var x = document.createElement("input");
		x.type = "text";
		x.className = "input";
		x.name = "addRules[" + id + "]";
		document.getElementById(container).appendChild(x);
		x = document.createElement("input");
		x.type = "checkbox";
		x.name = "value_all[" + id + "]";
		x.id = "access_" + id + "_all"
		x.onclick = function () {
			checkToAll(this)
		};
		x.value = "all";
		document.getElementById(container).appendChild(x);
		x = document.createElement("label");
		x.innerHTML = "Все";
		document.getElementById(container).appendChild(x);
		x = document.createElement("input");
		x.type = "checkbox";
		x.name = "value_owner[" + id + "]";
		x.id = "access_" + id + "_owner"
		x.value = "owner";
		document.getElementById(container).appendChild(x);
		x = document.createElement("label");
		x.innerHTML = "Владелец";
		document.getElementById(container).appendChild(x);
		x = document.createElement('br');
		document.getElementById(container).appendChild(x);
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

	function installModule(mod, v, modInstall, page) {
		if (confirm('Установить модуль ' + mod + ' ' + v + '?')) {
            var url = 'index.php?module=admin&action=modules&loc=core&tab_mod=2';
            if (page >= 1) {
                url = url + '&_page_mod_available=' + page;
            }
			load(url, {"install":modInstall});
		} else return false;
	}

