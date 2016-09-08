
CREATE TABLE `mod_billing_operations` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `operation_num` varchar(255) NOT NULL,
    `price` decimal(11,2) NOT NULL,
    `note` text NOT NULL,
    `type_operation` enum('coming','expense') NOT NULL,
    `transaction_id` varchar(255) DEFAULT NULL,
    `status_transaction` enum('pending','canceled','completed') NOT NULL DEFAULT 'pending',
    `system_name` varchar(255) NOT NULL,
    `paid_operation` varchar(255) DEFAULT NULL,
    `balance_before` decimal(11,2) DEFAULT NULL,
    `date_created` timestamp NULL DEFAULT NULL,
    `lastupdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `lastuser` int(11) unsigned DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx1_mod_billing_operations` (`operation_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO core_settings (lastupdate, system_name, value, code, visible, type, is_custom_sw, is_personal_sw, seq)
VALUES (NOW(), 'Дата отключения системы', NOW() + INTERVAL 1 MONTH, 'billing_date_disable', 'Y', 'text', 'N', 'N', null);
