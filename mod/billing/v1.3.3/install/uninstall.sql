DROP TABLE IF EXISTS `mod_billing_operations`;
DELETE FROM core_settings WHERE `code` = 'billing_date_disable';
DELETE FROM core_settings WHERE `code` = 'billing_mail_success_coming';
DELETE FROM core_settings WHERE `code` = 'billing_mail_from';

DELETE FROM mod_cron_jobs WHERE `module_id` = 'billing' AND module_method = 'cronReminderPay';
DELETE FROM mod_cron_jobs WHERE `module_id` = 'billing' AND module_method = 'cronCheckOperations';
DELETE FROM mod_cron_jobs WHERE `module_id` = 'billing' AND module_method = 'cronLicenseRenewals';
