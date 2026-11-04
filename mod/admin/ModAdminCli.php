<?php

require_once __DIR__ . '/../../inc/classes/Common.php';
require_once __DIR__ . '/../../inc/classes/SessionStore.php';


/**
 * Class ModAdminCli
 */
class ModAdminCli extends Common {

    /**
     * Сброс накопленных в memcached данных о сессиях пользователей в core_session.
     *
     * @return string
     */
    public function syncSessions() {

        $count = (new \Core2\SessionStore())->flush();

        return "Session sync: {$count}";
    }
}
