
DROP TABLE IF EXISTS `mod_profile_messages_settings`;
DROP TABLE IF EXISTS `mod_profile_messages_files`;
DROP TABLE IF EXISTS `mod_profile_messages`;
DROP TABLE IF EXISTS `mod_profile_user_settings`;
DROP TABLE IF EXISTS `mod_profile_users_data`;


CREATE TABLE `mod_profile_messages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `from` varchar(255) NOT NULL,
  `to` varchar(255) NOT NULL,
  `message` longtext,
  `date_add` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `location` enum('inbox','outbox') NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `is_read` enum('Y','N') DEFAULT 'N',
  `content_type` varchar(255) DEFAULT NULL,
  `email_id` varchar(255) DEFAULT NULL,
  `method_of_getting` enum('core','email','service') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_email_id` (`email_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `mod_profile_messages_fk` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`u_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `mod_profile_messages_files` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `content` longblob,
  `refid` int(11) unsigned NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filesize` int(11) NOT NULL,
  `hash` varchar(128) NOT NULL,
  `type` varchar(20) DEFAULT NULL,
  `fieldid` varchar(255) DEFAULT NULL,
  `thumb` longblob,
  PRIMARY KEY (`id`),
  KEY `mod_profile_messages_files_fk` (`refid`),
  CONSTRAINT `mod_profile_messages_files_fk` FOREIGN KEY (`refid`) REFERENCES `mod_profile_messages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `mod_profile_messages_settings` (
  `name` varchar(255) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `user_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`name`),
  UNIQUE KEY `unq_idx_settings` (`user_id`,`name`),
  KEY `user_id_idx` (`user_id`),
  CONSTRAINT `mod_profile_messages_settings_fk` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`u_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `mod_profile_user_settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `value` text,
  `code` varchar(60) NOT NULL DEFAULT '',
  `user_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_new` (`code`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `mod_profile_user_settings_fk1` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`u_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- v1.6 --
CREATE TABLE `mod_profile_users_data` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `code` varchar(60) NOT NULL DEFAULT '',
  `user_id` int(11) unsigned DEFAULT NULL,
  `udata` text,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;