var modules = {
    'repo': function (repo_id) {
        $.ajax({url:'index.php?module=admin&action=modules&getModsListFromRepo=' + repo_id})
		.done(function(data, textStatus){
			if(textStatus == 'success') {
				$("#repo_" + repo_id).html(data);
			}
		})
		.fail(function (a,b,t){
			$("#repo_" + repo_id).html("Фатальная ошибка!");
		});
    },


	/**
	 * Очистка кэша в списке модулей полученных из gitlab
	 * @param theme
	 */
	updateTable: function (theme) {

        $.ajax({
			url:'index.php?module=admin&action=modules&data=cache_clean',
			dataType: "json",
			type: "POST",
		})
		.done(function(data) {
			if (data.hasOwnProperty('status') && data.status === 'success') {
				$(".modal .modal-body").html('<div style="text-align:center"><img src="core2/html/' + theme + '/img/load.gif"> Обновление списка</div>');
				$(".modal .modal-body").load('index.php?module=admin&action=modules&page=table_gitlab');

			} else {
				alert("Ошибка обновления списка");
			}
		})
		.fail(function () {
			alert("Не удалось обновить список");
		});
    },

    'spoiler': function (id) {
        $('.' + id).toggle();
    },
    'refreshFiles': function (mod, v, mod_id) {
		if (alertify) {
			alertify.confirm('Обновить файлы модуля <b>' + mod + '</b> ' + v + '?', function(e){
				if (e) {
					load('index.php' + document.location.hash, {"refreshFilesModule": mod_id});
				} else return false;
			});
		} else {
			if (confirm('Обновить файлы модуля ' + mod + ' ' + v + '?')) {
				load('index.php' + document.location.hash, {"refreshFilesModule": mod_id});
			} else return false;
		}
    },
    'download': function (mod, v, modId) {
        if (alertify) {
            alertify.confirm('Скачать архив модуля <b>' + mod + '</b> ' + v + '?', function(e) {
                if (e) {
                    loadPDF('index.php?module=admin&action=modules&tab=available&download_mod=' + modId);
                } else return false;
            });
        } else {
            if (confirm('Скачать архив модуля ' + mod + ' ' + v + '?')) {
                loadPDF('index.php?module=admin&action=modules&tab=available&download_mod=' + modId);
            } else return false;
        }
	},
    'requestToRepo': function (mod, v, m_id, repo, request) {
        if (alertify) {
            alertify.confirm('Установить модуль <b>' + mod + '</b> ' + v + '?', function(e) {
                if (e) {
                    load('index.php' + document.location.hash, {"install_from_repo":m_id, "repo": repo});
                } else return false;
            });
        } else {
            if (confirm('Установить модуль ' + mod + ' ' + v + '?')) {
                load('index.php' + document.location.hash, {"install_from_repo":m_id, "repo": repo});
            } else return false;
        }
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
	},
	updateModule: function (mod, v, module_id) {
        if (alertify) {
            alertify.confirm('Обновить модуль <b>' + mod + '</b> до версии <b>' + v + '</b>?', function(e) {
                if (e) {
                    var url = 'index.php' + document.location.hash.replace('#', '?');
                    load(url, {"updateModule": module_id});
                } else return false;
            });
        } else {
            if (confirm('Обновить модуль ' + mod + ' до версии ' + v + '?')) {
                var url = 'index.php' + document.location.hash.replace('#', '?');
                load(url, {"updateModule": module_id});
            } else return false;
        }
	},
	checkModsUpdates: function (mods, theme) {
        $.ajax({
            url: "index.php?module=admin&action=modules",
            data: {"checkModsUpdates": mods},
            method: 'PUT',
            success: function(data, textStatus) {
                if (textStatus == 'success') {
                    data.forEach(function(item, i, arr) {
                        var obj = $('td[title=' + item.m_id + ']');
                        var obj_ver = obj.next().next().next();
                        obj_ver.html(obj_ver.html() + ' <b style="color: #008000;"> Доступно обновление до v' + item.version + '</b>');
                        var obj_do = obj.next().next().next().next().next().next();
                        obj_do.html(obj_do.html() + '<div style="display: inline-block;" onclick="modules.updateModule(\'' + item.m_name + '\', \'' + item.version + '\', \'' + item.module_id + '\');"><img src="core2/html/' + theme + '/img/box_refresh.png" border="0" title="Обновить модуль" /></div>');
                    });
                }
            }

        }).fail(function (jqXHR, textStatus, errorThrown) {
            alertify.error(textStatus);
        });
	},
    /**
     * Разинсталирование модуля
     * @param mod
     * @param v
     * @param modUninstall
     * @returns {boolean}
     */
    uninstallModule: function(mod, v, modUninstall) {
		if (alertify) {
			alertify.confirm('Разинсталировать модуль <b>' + mod + '<b> версии <b>' + v + '</b>?', function(e) {
				if (e) {
					load('index.php' + document.location.hash, {"uninstall":modUninstall});
				} else return false;
			});
		} else {
			if (confirm('Разинсталировать модуль ' + mod + ' версии ' + v + '?')) {
				load('index.php' + document.location.hash, {"uninstall":modUninstall});
			} else return false;
		}
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




    /**
     * Установка модуля
     * @param mod
     * @param v
     * @param modInstall
     * @param page
     * @returns {boolean}
     */
	function installModule(mod, v, modInstall, page) {
        if (alertify) {
            alertify.confirm('Установить модуль <b>' + mod + '</b> ' + v + '?', function(e) {
                if (e) {
                    var url = 'index.php?module=admin&action=modules&loc=core&tab_mod=2';
                    if (page >= 1) {
                        url = url + '&_page_mod_available=' + page;
                    }
                    load(url, {"install":modInstall});
                } else return false;
            });
        } else {
            if (confirm('Установить модуль ' + mod + ' ' + v + '?')) {
                var url = 'index.php?module=admin&action=modules&loc=core&tab_mod=2';
                if (page >= 1) {
                    url = url + '&_page_mod_available=' + page;
                }
                load(url, {"install":modInstall});
            } else return false;
        }
	}

