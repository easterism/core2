
#
# Structure for the `core_available_modules` table : 
#

CREATE TABLE `core_available_modules` (
  `id`           INTEGER(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(60)
                 COLLATE utf8_general_ci          DEFAULT NULL,
  `version`      VARCHAR(10)
                 COLLATE utf8_general_ci NOT NULL DEFAULT '1.0',
  `descr`        VARCHAR(128)
                 COLLATE utf8_general_ci          DEFAULT NULL,
  `data`         LONGBLOB,
  `lastuser`     INTEGER(11) UNSIGNED             DEFAULT NULL,
  `install_info` TEXT
                 COLLATE utf8_general_ci,
  `readme`       TEXT
                 COLLATE utf8_general_ci,
  PRIMARY KEY USING BTREE (`id`),
  INDEX `lastuser` USING BTREE (`lastuser`)
)
  ENGINE =InnoDB
  AUTO_INCREMENT =1
  CHARACTER SET 'utf8'
  COLLATE 'utf8_general_ci'
  COMMENT ='';

#
# Structure for the `core_controls` table : 
#

CREATE TABLE `core_controls` (
  `tbl`        VARCHAR(60)
               COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `keyfield`   VARCHAR(20)
               COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `val`        VARCHAR(20)
               COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `lastupdate` VARCHAR(30)
               COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `lastuser`   INTEGER(11)             NOT NULL
)
  ENGINE =InnoDB
  CHARACTER SET 'utf8'
  COLLATE 'utf8_general_ci'
  COMMENT ='';

#
# Structure for the `core_enum` table : 
#

CREATE TABLE `core_enum` (
  `id`            INTEGER(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(128)
                  COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `parent_id`     INTEGER(11) UNSIGNED             DEFAULT NULL,
  `is_default_sw` ENUM('Y', 'N')
                  COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `lastuser`      INTEGER(11) UNSIGNED             DEFAULT NULL,
  `lastupdate`    TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active_sw`  ENUM('Y', 'N')
                  COLLATE utf8_general_ci NOT NULL DEFAULT 'Y',
  `seq`           INTEGER(11)             NOT NULL DEFAULT 0,
  `global_id`     VARCHAR(20)
                  COLLATE utf8_general_ci          DEFAULT NULL,
  `custom_field`  TEXT
                  COLLATE utf8_general_ci,
  PRIMARY KEY USING BTREE (`id`),
  UNIQUE INDEX `parent_id` USING BTREE (`parent_id`, `name`),
  UNIQUE INDEX `global_id` USING BTREE (`global_id`),
  INDEX `parent_id_2` USING BTREE (`parent_id`),
  CONSTRAINT `core_enum_fk` FOREIGN KEY (`parent_id`) REFERENCES `core_enum` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
)
  ENGINE =InnoDB
  CHARACTER SET 'utf8'
  COLLATE 'utf8_general_ci'
  COMMENT ='';

#
# Structure for the `core_log` table : 
#

CREATE TABLE `core_log` (
  `id`             INTEGER(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`        INTEGER(11) UNSIGNED    NOT NULL,
  `sid`            VARCHAR(128)
                   COLLATE utf8_general_ci NOT NULL                             DEFAULT '',
  `action`         LONGTEXT
                   COLLATE utf8_general_ci,
  `lastupdate`     TIMESTAMP               NOT NULL ON UPDATE CURRENT_TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `query`          TEXT
                   COLLATE utf8_general_ci,
  `request_method` VARCHAR(20)
                   COLLATE utf8_general_ci                                      DEFAULT NULL,
  `remote_port`    MEDIUMINT(9)                                                 DEFAULT NULL,
  `ip`             VARCHAR(20)
                   COLLATE utf8_general_ci                                      DEFAULT NULL,
  PRIMARY KEY USING BTREE (`id`),
  INDEX `user_id` USING BTREE (`user_id`),
  INDEX `session_id` USING BTREE (`sid`)
)
  ENGINE =MyISAM
  CHARACTER SET 'utf8'
  COLLATE 'utf8_general_ci'
  COMMENT ='';

#
# Structure for the `core_modules` table : 
#

CREATE TABLE `core_modules` (
  `m_id`           INTEGER(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `m_name`         VARCHAR(60)
                   COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `module_id`      VARCHAR(60)
                   COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `visible`        ENUM('Y', 'N')
                   COLLATE utf8_general_ci NOT NULL DEFAULT 'Y',
  `lastuser`       INTEGER(11) UNSIGNED             DEFAULT NULL,
  `lastupdate`     TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_system`      ENUM('Y', 'N')
                   COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `is_public`      ENUM('Y', 'N')
                   COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `dependencies`   TEXT
                   COLLATE utf8_general_ci,
  `global_id`      VARCHAR(20)
                   COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `seq`            TINYINT(4)                       DEFAULT NULL,
  `access_default` TEXT
                   COLLATE utf8_general_ci,
  `access_add`     TEXT
                   COLLATE utf8_general_ci,
  `version`        VARCHAR(10)
                   COLLATE utf8_general_ci NOT NULL DEFAULT '1.0.0',
  PRIMARY KEY USING BTREE (`m_id`),
  UNIQUE INDEX `m_name` USING BTREE (`m_name`),
  UNIQUE INDEX `module_id` USING BTREE (`module_id`),
  UNIQUE INDEX `global_id` USING BTREE (`global_id`),
  INDEX `core_modules_idx1` USING BTREE (`visible`)
)
  ENGINE =InnoDB
  CHARACTER SET 'utf8'
  COLLATE 'utf8_general_ci'
  COMMENT ='';

#
# Structure for the `core_roles` table : 
#

CREATE TABLE `core_roles` (
  `id`           INTEGER(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(255)
                 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `is_active_sw` ENUM('Y', 'N')
                 COLLATE utf8_general_ci NOT NULL DEFAULT 'Y',
  `lastupdate`   TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `description`  VARCHAR(255)
                 COLLATE utf8_general_ci          DEFAULT NULL,
  `lastuser`     INTEGER(11) UNSIGNED             DEFAULT NULL,
  `access`       TEXT
                 COLLATE utf8_general_ci,
  `date_added`   DATETIME                NOT NULL,
  `author`       VARCHAR(60)
                 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `position`     INTEGER(11)             NOT NULL,
  `access_add`   TEXT
                 COLLATE utf8_general_ci,
  PRIMARY KEY USING BTREE (`id`),
  UNIQUE INDEX `name` USING BTREE (`name`),
  INDEX `core_roles_idx1` USING BTREE (`is_active_sw`)
)
  ENGINE =InnoDB
  CHARACTER SET 'utf8'
  COLLATE 'utf8_general_ci'
  COMMENT ='';

#
# Structure for the `core_session` table : 
#

CREATE TABLE `core_session` (
  `id`            INTEGER(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `sid`           VARCHAR(128)
                  COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `login_time`    TIMESTAMP               NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logout_time`   DATETIME                         DEFAULT NULL,
  `user_id`       INTEGER(11) UNSIGNED    NOT NULL,
  `ip`            VARCHAR(20)
                  COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `is_expired_sw` ENUM('N', 'Y')
                  COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `last_activity` TIMESTAMP               NOT NULL DEFAULT '0000-00-00 00:00:00',
  `crypto_sw`     ENUM('N', 'Y')
                  COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  PRIMARY KEY USING BTREE (`id`),
  INDEX `user_id` USING BTREE (`user_id`),
  INDEX `sid` USING BTREE (`sid`)
)
  ENGINE =MyISAM
  CHARACTER SET 'utf8'
  COLLATE 'utf8_general_ci'
  COMMENT ='';

#
# Structure for the `core_settings` table : 
#

CREATE TABLE `core_settings` (
  `id`             INTEGER(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `lastuser`       INTEGER(11) UNSIGNED             DEFAULT NULL,
  `lastupdate`     TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `system_name`    VARCHAR(255)
                   COLLATE utf8_general_ci          DEFAULT NULL,
  `value`          TEXT
                   COLLATE utf8_general_ci,
  `code`           VARCHAR(60)
                   COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `visible`        ENUM('Y', 'N')
                   COLLATE utf8_general_ci NOT NULL DEFAULT 'Y',
  `type`           VARCHAR(20)
                   COLLATE utf8_general_ci NOT NULL DEFAULT 'text',
  `is_custom_sw`   ENUM('N', 'Y')
                   COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `is_personal_sw` ENUM('N', 'Y')
                   COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  PRIMARY KEY USING BTREE (`id`),
  UNIQUE INDEX `code` USING BTREE (`code`)
)
  ENGINE =InnoDB
  CHARACTER SET 'utf8'
  COLLATE 'utf8_general_ci'
  COMMENT ='';

#
# Structure for the `core_submodules` table : 
#

CREATE TABLE `core_submodules` (
  `sm_id`          INTEGER(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `sm_name`        VARCHAR(128)
                   COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `visible`        ENUM('Y', 'N')
                   COLLATE utf8_general_ci NOT NULL DEFAULT 'Y',
  `m_id`           INTEGER(11) UNSIGNED    NOT NULL,
  `sm_path`        VARCHAR(255)
                   COLLATE utf8_general_ci          DEFAULT NULL,
  `lastuser`       INTEGER(11) UNSIGNED             DEFAULT NULL,
  `lastupdate`     TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sm_key`         VARCHAR(20)
                   COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `seq`            INTEGER(11)             NOT NULL,
  `access_default` TEXT
                   COLLATE utf8_general_ci,
  `access_add`     TEXT
                   COLLATE utf8_general_ci,
  PRIMARY KEY USING BTREE (`sm_id`),
  UNIQUE INDEX `m_id` USING BTREE (`m_id`, `sm_key`),
  INDEX `core_submodules_idx1` USING BTREE (`visible`),
  CONSTRAINT `core_submodules_fk` FOREIGN KEY (`m_id`) REFERENCES `core_modules` (`m_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
)
  ENGINE =InnoDB
  CHARACTER SET 'utf8'
  COLLATE 'utf8_general_ci'
  COMMENT ='';

#
# Structure for the `core_users` table : 
#

CREATE TABLE `core_users` (
  `u_id`            INTEGER(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `u_login`         VARCHAR(120)
                    COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `u_pass`          VARCHAR(36)
                    COLLATE utf8_general_ci          DEFAULT NULL,
  `visible`         ENUM('Y', 'N')
                    COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `lastupdate`      TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `email`           VARCHAR(60)
                    COLLATE utf8_general_ci          DEFAULT '',
  `lastuser`        INTEGER(11) UNSIGNED             DEFAULT NULL,
  `role_id`         INTEGER(11) UNSIGNED             DEFAULT NULL,
  `certificate`     TEXT
                    COLLATE utf8_general_ci,
  `is_admin_sw`     ENUM('Y', 'N')
                    COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `date_added`      DATETIME                NOT NULL,
  `is_email_wrong`  ENUM('Y', 'N')
                    COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  `is_pass_changed` ENUM('Y', 'N')
                    COLLATE utf8_general_ci NOT NULL DEFAULT 'N',
  PRIMARY KEY USING BTREE (`u_id`),
  UNIQUE INDEX `u_login` USING BTREE (`u_login`),
  UNIQUE INDEX `email` USING BTREE (`email`),
  INDEX `core_users_idx1` USING BTREE (`visible`),
  INDEX `role_id` USING BTREE (`role_id`),
  CONSTRAINT `core_users_fk1` FOREIGN KEY (`role_id`) REFERENCES `core_roles` (`id`)
)
  ENGINE =InnoDB
  CHARACTER SET 'utf8'
  COLLATE 'utf8_general_ci'
  COMMENT ='';

#
# Structure for the `core_users_profile` table : 
#

CREATE TABLE `core_users_profile` (
  `id`         INTEGER(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`    INTEGER(11) UNSIGNED    NOT NULL,
  `lastname`   VARCHAR(60)
               COLLATE utf8_general_ci          DEFAULT '',
  `firstname`  VARCHAR(60)
               COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `middlename` VARCHAR(60)
               COLLATE utf8_general_ci          DEFAULT '',
  `lastupdate` TIMESTAMP               NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastuser`   INTEGER(11) UNSIGNED             DEFAULT NULL,
  PRIMARY KEY USING BTREE (`id`),
  INDEX `user_id` USING BTREE (`user_id`),
  CONSTRAINT `core_users_profile_fk` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`u_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `core_users_profile_fk1` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`u_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
)
  ENGINE =InnoDB
  CHARACTER SET 'utf8'
  COLLATE 'utf8_general_ci'
  COMMENT ='';

#
# Structure for the `core_users_roles` table : 
#

CREATE TABLE `core_users_roles` (
  `id`      INTEGER(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INTEGER(11) UNSIGNED NOT NULL,
  `role_id` INTEGER(11) UNSIGNED NOT NULL,
  `lastuser` INTEGER(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY USING BTREE (`id`),
  INDEX `user_id` USING BTREE (`user_id`),
  INDEX `role_id` USING BTREE (`role_id`),
  CONSTRAINT `core_users_roles_fk` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`u_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `core_users_roles_fk1` FOREIGN KEY (`user_id`) REFERENCES `core_users` (`u_id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `core_users_roles_fk2` FOREIGN KEY (`role_id`) REFERENCES `core_roles` (`id`)
)
  ENGINE =InnoDB
  CHARACTER SET 'utf8'
  COLLATE 'utf8_general_ci'
  COMMENT ='';
