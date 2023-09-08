CREATE TABLE IF NOT EXISTS `core_roles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `is_active_sw` enum('Y','N') NOT NULL DEFAULT 'Y',
  `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `description` varchar(255) DEFAULT NULL,
  `lastuser` int(11) unsigned DEFAULT NULL,
  `access` text,
  `date_added` datetime NOT NULL,
  `author` varchar(60) NOT NULL DEFAULT '',
  `position` int(11) NOT NULL,
  `access_add` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `core_roles_idx1` (`is_active_sw`),
  KEY `position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `core_users` (
  `u_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `u_login` varchar(120) NOT NULL DEFAULT '',
  `u_pass` varchar(36) DEFAULT NULL,
  `visible` enum('Y','N') NOT NULL DEFAULT 'N',
  `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `email` varchar(60) DEFAULT '',
  `lastuser` int(11) unsigned DEFAULT NULL,
  `role_id` int(11) unsigned DEFAULT NULL,
  `certificate` text,
  `is_admin_sw` enum('Y','N') NOT NULL DEFAULT 'N',
  `date_added` datetime NOT NULL,
  `is_email_wrong` enum('Y','N') NOT NULL DEFAULT 'N',
  `is_pass_changed` enum('Y','N') NOT NULL DEFAULT 'N',
  PRIMARY KEY (`u_id`),
  UNIQUE KEY `u_login` (`u_login`),
  UNIQUE KEY `email` (`email`),
  KEY `core_users_idx1` (`visible`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `core_users_fk1` FOREIGN KEY (`role_id`) REFERENCES `core_roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `core_users_profile` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `lastname` varchar(60) DEFAULT '',
  `firstname` varchar(60) NOT NULL DEFAULT '',
  `middlename` varchar(60) DEFAULT '',
  `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lastuser` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `core_users_profile_fk` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`u_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `core_users_profile_fk1` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`u_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `core_controls` (
  `tbl` varchar(60) NOT NULL,
  `keyfield` varchar(20) NOT NULL,
  `val` varchar(20) NOT NULL,
  `lastupdate` varchar(30) NOT NULL,
  `lastuser` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `core_enum` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL DEFAULT '',
  `parent_id` int(11) unsigned DEFAULT NULL,
  `is_default_sw` enum('Y','N') NOT NULL DEFAULT 'N',
  `lastuser` int(11) unsigned DEFAULT NULL,
  `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active_sw` enum('Y','N') NOT NULL DEFAULT 'Y',
  `seq` int(11) NOT NULL DEFAULT '0',
  `global_id` varchar(20) DEFAULT NULL,
  `custom_field` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parent_id` (`parent_id`,`name`),
  UNIQUE KEY `global_id` (`global_id`),
  KEY `parent_id_2` (`parent_id`),
  KEY `seq` (`seq`),
  CONSTRAINT `core_enum_fk` FOREIGN KEY (`parent_id`) REFERENCES `core_enum` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `core_log` (
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
  KEY `user_id` (`user_id`),
  KEY `session_id` (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `core_modules` (
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
  `uninstall` text,
  `files_hash` text,
  `isset_home_page` enum('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY (`m_id`),
  UNIQUE KEY `m_name` (`m_name`),
  UNIQUE KEY `module_id` (`module_id`),
  KEY `core_modules_idx1` (`visible`),
  KEY `seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `core_session` (
	`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`sid` VARCHAR(128) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci',
	`login_time` TIMESTAMP NULL DEFAULT NULL,
	`logout_time` DATETIME NULL DEFAULT NULL,
	`user_id` INT(11) UNSIGNED NOT NULL,
	`ip` VARCHAR(20) NOT NULL DEFAULT '' COLLATE 'utf8_general_ci',
	`is_expired_sw` ENUM('N','Y') NOT NULL DEFAULT 'N' COLLATE 'utf8_general_ci',
	`last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`crypto_sw` ENUM('N','Y') NOT NULL DEFAULT 'N' COLLATE 'utf8_general_ci',
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `user_id` (`user_id`) USING BTREE,
	INDEX `sid` (`sid`) USING BTREE
)
COLLATE='utf8_general_ci' ENGINE=MyISAM;


CREATE TABLE IF NOT EXISTS `core_settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `lastuser` int(11) unsigned DEFAULT NULL,
  `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `system_name` varchar(255) DEFAULT NULL,
  `value` text,
  `code` varchar(60) NOT NULL DEFAULT '',
  `visible` enum('Y','N') NOT NULL DEFAULT 'Y',
  `type` varchar(20) NOT NULL DEFAULT 'text',
  `is_custom_sw` enum('N','Y') NOT NULL DEFAULT 'N',
  `is_personal_sw` enum('N','Y') NOT NULL DEFAULT 'N',
  `seq` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `seq` (`seq`),
  KEY `visible` (`visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `core_submodules` (
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
  UNIQUE KEY `m_id` (`m_id`,`sm_key`),
  KEY `core_submodules_idx1` (`visible`),
  KEY `seq` (`seq`),
  CONSTRAINT `core_submodules_fk` FOREIGN KEY (`m_id`) REFERENCES `core_modules` (`m_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `core_users_roles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `role_id` int(11) unsigned NOT NULL,
  `lastuser` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `core_users_roles_fk` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`u_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `core_users_roles_fk1` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`u_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `core_users_roles_fk2` FOREIGN KEY (`role_id`) REFERENCES `core_roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


INSERT INTO `core_users` (`u_login`, `u_pass`, `visible`, `is_admin_sw`, `date_added`) VALUES ('admin', 'ad7123ebca969de21e49c12a7d69ce25', 'Y', 'Y', NOW());