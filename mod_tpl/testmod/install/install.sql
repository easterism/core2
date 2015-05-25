CREATE TABLE IF NOT EXISTS `mod_%MODULE_ID%` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `date_added` date NOT NULL,
  `kids` tinyint(4) unsigned NOT NULL,
  `lastuser` int(11) unsigned DEFAULT NULL,
  `author` varchar(20) NOT NULL DEFAULT '',
  `cost` int(11) unsigned NOT NULL,
  `closed` enum('N','Y') NOT NULL DEFAULT 'N',
  `add_kids` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `date_added` (`date_added`) USING BTREE,
  KEY `closed` (`closed`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `#___prod_gr_norma` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(11) unsigned NOT NULL,
  `norma` float(9,2) unsigned DEFAULT NULL,
  `date_added` date DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `group_id` (`group_id`) USING BTREE,
  CONSTRAINT `#___prod_gr_norma_fk` FOREIGN KEY (`group_id`) REFERENCES `mod_kitchen_prod_gr` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `#___prod_gr_norma` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(11) unsigned NOT NULL,
  `norma` float(9,2) unsigned DEFAULT NULL,
  `date_added` date DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `group_id` (`group_id`) USING BTREE,
  CONSTRAINT `#___prod_gr_norma_fk` FOREIGN KEY (`group_id`) REFERENCES `mod_kitchen_prod_gr` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;