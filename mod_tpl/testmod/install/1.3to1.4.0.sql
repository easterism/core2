CREATE TABLE IF NOT EXISTS `#___prod_gr_norma` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(11) unsigned NOT NULL,
  `norma` float(9,2) unsigned DEFAULT NULL,
  `date_added` date DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `group_id` (`group_id`) USING BTREE,
  CONSTRAINT `#___prod_gr_norma_fk` FOREIGN KEY (`group_id`) REFERENCES `mod_kitchen_prod_gr` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `#___day`;
DROP TABLE IF EXISTS `#___menu_extra`;
DROP TABLE IF EXISTS `#___dish_prod`;