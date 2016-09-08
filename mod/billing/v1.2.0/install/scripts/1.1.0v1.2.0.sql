
INSERT INTO core_settings (lastupdate, system_name, value, code, visible, type, is_custom_sw, is_personal_sw, seq)
VALUES (NOW(), 'Email для уведомлений об успешной оплате', '', 'billing_mail_success_coming', 'Y', 'text', 'N', 'N', null);

INSERT INTO core_settings (lastupdate, system_name, value, code, visible, type, is_custom_sw, is_personal_sw, seq)
VALUES (NOW(), 'Email с которого происходят рассылки писем от биллинга', '', 'billing_mail_from', 'Y', 'text', 'N', 'N', null);