function kick(id) {
	if (confirm('Выкинуть пользователя из системы?')) {
		load('index.php?module=admin&action=monitoring&kick=' + id);
	} else return false;
}