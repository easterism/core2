CREATE TABLE `core_available_modules` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `module_id` varchar(60) NOT NULL,
    `module_group` varchar(60) DEFAULT NULL,
    `name` varchar(60) DEFAULT NULL,
    `version` varchar(10) NOT NULL DEFAULT '1.0.0',
    `descr` varchar(128) DEFAULT NULL,
    `data` longblob,
    `lastuser` int(11) unsigned DEFAULT NULL,
    `install_info` text,
    `readme` text,
    `files_hash` text,
    PRIMARY KEY (`id`),
    KEY `idx1_core_available_modules` (`lastuser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `core_controls` (
    `tbl` varchar(60) NOT NULL,
    `keyfield` varchar(20) NOT NULL,
    `val` varchar(20) NOT NULL,
    `lastupdate` varchar(30) NOT NULL,
    `lastuser` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE `core_enum` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(128) NOT NULL DEFAULT '',
    `parent_id` int(11) unsigned,
    `is_default_sw` enum('Y','N') NOT NULL DEFAULT 'N',
    `lastuser` int(11) unsigned DEFAULT NULL,
    `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_active_sw` enum('Y','N') NOT NULL DEFAULT 'Y',
    `seq` int(11) NOT NULL DEFAULT '0',
    `global_id` varchar(20) DEFAULT NULL,
    `custom_field` text,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx1_core_enum` (`parent_id`,`name`),
    UNIQUE KEY `idx2_core_enum` (`global_id`),
    CONSTRAINT `fk1_core_enum` FOREIGN KEY (`parent_id`) REFERENCES `core_enum` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `core_log` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int(11) unsigned NOT NULL,
    `sid` varchar(128) NOT NULL DEFAULT '',
    `action` longtext,
    `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `query` text,
    `request_method` varchar(20) DEFAULT NULL,
    `remote_port` mediumint(9) DEFAULT NULL,
    `ip` varchar(20) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx1_core_log` (`user_id`),
    KEY `idx2_core_log` (`sid`),
    KEY `idx3_core_log` (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `core_modules` (
    `m_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `m_name` varchar(60) NOT NULL DEFAULT '',
    `module_id` varchar(60) NOT NULL DEFAULT '',
    `visible` enum('Y','N') NOT NULL DEFAULT 'Y',
    `lastuser` int(11) unsigned DEFAULT NULL,
    `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_system` enum('Y','N') NOT NULL DEFAULT 'N',
    `is_public` enum('Y','N') NOT NULL DEFAULT 'N',
    `dependencies` text,
    `seq` int(11) DEFAULT NULL,
    `access_default` text,
    `access_add` text,
    `version` varchar(10) NOT NULL DEFAULT '1.0.0',
    `isset_home_page` enum('Y','N') NOT NULL DEFAULT 'Y',
    `uninstall` text,
    `files_hash` longtext,
    PRIMARY KEY (`m_id`),
    UNIQUE KEY `idx1_core_modules` (`m_name`),
    UNIQUE KEY `idx2_core_modules` (`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `core_roles` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL DEFAULT '',
    `is_active_sw` enum('Y','N') NOT NULL DEFAULT 'Y',
    `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `description` varchar(255) DEFAULT NULL,
    `lastuser` int(11) unsigned DEFAULT NULL,
    `access` text,
    `date_added` datetime NOT NULL,
    `position` int(11) NOT NULL,
    `access_add` text,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx1_core_roles` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `core_session` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `sid` varchar(128) NOT NULL DEFAULT '',
    `login_time` timestamp NULL DEFAULT NULL,
    `logout_time` datetime NULL DEFAULT NULL,
    `user_id` int(11) unsigned NOT NULL,
    `ip` varchar(20) NOT NULL DEFAULT '',
    `is_expired_sw` enum('N','Y') NOT NULL DEFAULT 'N',
    `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `crypto_sw` enum('N','Y') NOT NULL DEFAULT 'N',
    `is_kicked_sw` enum('N','Y') NOT NULL DEFAULT 'N',
    PRIMARY KEY (`id`),
    KEY `idx1_core_session` (`user_id`),
    KEY `idx2_core_session` (`sid`),
    KEY `idx3_core_session` (`is_expired_sw`),
    KEY `idx4_core_session` (`is_kicked_sw`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `core_settings` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `lastuser` int(11) unsigned DEFAULT NULL,
    `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `system_name` varchar(255) DEFAULT '',
    `value` text,
    `code` varchar(60) NOT NULL DEFAULT '',
    `visible` enum('Y','N') NOT NULL DEFAULT 'Y',
    `is_custom_sw` enum('N','Y') NOT NULL DEFAULT 'N',
    `is_personal_sw` enum('N','Y') NOT NULL DEFAULT 'N',
    `type` varchar(20) NOT NULL DEFAULT 'text',
    `seq` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx1_core_settings` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `core_submodules` (
    `sm_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `sm_name` varchar(128) NOT NULL DEFAULT '',
    `visible` enum('Y','N') NOT NULL DEFAULT 'Y',
    `m_id` int(11) unsigned NOT NULL,
    `sm_path` varchar(255) DEFAULT NULL,
    `lastuser` int(11) unsigned DEFAULT NULL,
    `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `sm_key` varchar(20) NOT NULL DEFAULT '',
    `seq` int(11) NOT NULL,
    `access_default` text,
    `access_add` text,
    PRIMARY KEY (`sm_id`),
    UNIQUE KEY `idx1_core_submodules` (`m_id`,`sm_key`),
    KEY `idx2_core_submodules` (`m_id`),
    CONSTRAINT `fk1_core_submodules` FOREIGN KEY (`m_id`) REFERENCES `core_modules` (`m_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `core_users` (
    `u_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `u_login` varchar(120) NOT NULL DEFAULT '',
    `u_pass` varchar(36) DEFAULT '',
    `visible` enum('Y','N') NOT NULL DEFAULT 'N',
    `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `email` varchar(60) DEFAULT '',
    `lastuser` int(11) unsigned DEFAULT NULL,
    `is_admin_sw` enum('Y','N') NOT NULL DEFAULT 'N',
    `certificate` text,
    `role_id` int(11) unsigned DEFAULT NULL,
    `date_added` datetime NOT NULL,
    `is_email_wrong` enum('Y','N') NOT NULL DEFAULT 'N',
    `is_pass_changed` enum('Y','N') NOT NULL DEFAULT 'N',
    PRIMARY KEY (`u_id`),
    UNIQUE KEY `idx1_core_users` (`u_login`),
    UNIQUE KEY `idx2_core_users` (`email`),
    KEY `idx3_core_users` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `core_users_profile` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int(11) unsigned NOT NULL,
    `lastname` varchar(60) DEFAULT '',
    `firstname` varchar(60) NOT NULL DEFAULT '',
    `middlename` varchar(60) DEFAULT '',
    `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `lastuser` int(11) unsigned DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx1_core_users_profile` (`user_id`),
    CONSTRAINT `fk1_core_users_profile` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`u_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `core_users_roles` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int(11) unsigned NOT NULL,
    `role_id` int(11) unsigned NOT NULL,
    `lastuser` int(11) unsigned DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx1_core_users_roles` (`user_id`),
    KEY `idx2_core_users_roles` (`role_id`),
    CONSTRAINT `fk1_core_users_roles` FOREIGN KEY (`role_id`) REFERENCES `core_roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;