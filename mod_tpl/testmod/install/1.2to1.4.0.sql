
CREATE TABLE IF NOT EXISTS `#___menu_extra` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) unsigned NOT NULL,
  `prod_id` int(11) unsigned NOT NULL,
  `amount` FLOAT(9,4) DEFAULT NULL,
  `add` FLOAT(9,4) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `menu_id` (`menu_id`) USING BTREE,
  KEY `prod_id` (`prod_id`) USING BTREE,
  CONSTRAINT `#___menu_extra_fk1` FOREIGN KEY (`menu_id`) REFERENCES `#___menu` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `#___menu_extra_fk2` FOREIGN KEY (`prod_id`) REFERENCES `mod_kitchen_prod` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

