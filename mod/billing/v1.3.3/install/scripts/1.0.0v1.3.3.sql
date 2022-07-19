
START TRANSACTION;
ALTER TABLE mod_billing_operations ADD COLUMN `discount_name` varchar(255) DEFAULT NULL AFTER `paid_operation`;
ALTER TABLE mod_billing_operations ADD COLUMN `discount_price` decimal(11,2) DEFAULT NULL AFTER `discount_name`;
ALTER TABLE mod_billing_operations ADD COLUMN `shipping_name` varchar(255) DEFAULT NULL AFTER `discount_price`;
ALTER TABLE mod_billing_operations ADD COLUMN `shipping_price` decimal(11,2) DEFAULT NULL AFTER `shipping_name`;
ALTER TABLE mod_billing_operations ADD COLUMN `tax` decimal(11,2) DEFAULT NULL AFTER `shipping_price`;
ALTER TABLE mod_billing_operations ADD COLUMN `currency` VARCHAR(20) DEFAULT NULL AFTER `tax`;

INSERT INTO core_settings (lastupdate, system_name, value, code, visible, type, is_custom_sw, is_personal_sw, seq)
VALUES (NOW(), 'Email для уведомлений об успешной оплате', '', 'billing_mail_success_coming', 'Y', 'text', 'N', 'N', null);

INSERT INTO core_settings (lastupdate, system_name, value, code, visible, type, is_custom_sw, is_personal_sw, seq)
VALUES (NOW(), 'Email с которого происходят рассылки писем от биллинга', '', 'billing_mail_from', 'Y', 'text', 'N', 'N', null);


INSERT INTO mod_webservice_regapikeys (title, type_sw, transfer_sw, apikey, max_apikeys, is_active_sw, lastuser)
VALUES ('Для биллинга', 'api', 'address', SHA1(CONCAT_WS('', NOW(), RAND())), 0, 'Y', null);

INSERT INTO mod_webservice_apikeys (parent_id, apikey, name, is_active_sw, lastuser, date_add, number_requests)
VALUES (LAST_INSERT_ID(), SHA1(CONCAT_WS('', NOW(), RAND())), 'billing_key', 'Y', null, NOW(), 0);


INSERT INTO mod_cron_jobs (title, description, module_id, module_method, is_active_sw, schedule_type, schedule_simple, schedule_advanced_minutes, schedule_advanced_hours, schedule_advanced_days, schedule_advanced_month, schedule_advanced_weekdays, execute_type, execute_from, execute_to, is_send_email_sw, date_laststart, lastupdate)
VALUES ('Напоминание об оплате', null, 'billing', 'cronReminderPay', 'Y', 'simple', 'daily', null, null, null, null, null, 'no_limited', null, null, 'Y', null, NOW());

INSERT INTO mod_cron_jobs (title, description, module_id, module_method, is_active_sw, schedule_type, schedule_simple, schedule_advanced_minutes, schedule_advanced_hours, schedule_advanced_days, schedule_advanced_month, schedule_advanced_weekdays, execute_type, execute_from, execute_to, is_send_email_sw, date_laststart, lastupdate)
VALUES ('Проверка оплат пополнений', null, 'billing', 'cronCheckOperations', 'Y', 'advanced', 'hourly', '0,10,20,30,40,50', 'all', 'all', 'all', 'all', 'no_limited', null, null, 'Y', null, NOW());

INSERT INTO mod_cron_jobs (title, description, module_id, module_method, is_active_sw, schedule_type, schedule_simple, schedule_advanced_minutes, schedule_advanced_hours, schedule_advanced_days, schedule_advanced_month, schedule_advanced_weekdays, execute_type, execute_from, execute_to, is_send_email_sw, date_laststart, lastupdate)
VALUES ('Автоматическое продление лицензий', null, 'billing', 'cronLicenseRenewals', 'Y', 'advanced', 'hourly', '0', '0', 'all', 'all', 'all', 'no_limited', null, null, 'Y', null, NOW());
COMMIT;