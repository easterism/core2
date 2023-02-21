<?php

require_once __DIR__ . '/../inc/classes/Zend_Registry.php';
require_once __DIR__ . '/../inc/classes/Error.php';

use Laminas\Mail;
use Laminas\Mail\Transport;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Mime;
use Laminas\Mime\Part as MimePart;

class Mailer
{
    public function run($job, &$log) {

        $workload = json_decode($job->workload());
        $config = unserialize($workload->config);
        \Zend_Registry::set('config', $config);
        \Zend_Registry::set('core_config', new Zend_Config_Ini(__DIR__ . "/../conf.ini", 'production'));
        $_SERVER = get_object_vars($workload->server);

        $from   = $workload->payload->from;
        $to     = $workload->payload->to;
        $reply  = $workload->payload->reply;
        $subj   = $workload->payload->subj;
        $body   = $workload->payload->body;
        $bcc    = $workload->payload->bcc;
        $cc     = $workload->payload->cc;
        $files = unserialize($workload->payload->files);

        $message = new Mail\Message();
        $message->setEncoding('UTF-8');

        // DEPRECATED
        if (is_array($from)) {
            $from = "{$from[1]} <{$from[0]}>";
        }
        $from = trim($from);

        // DEPRECATED
        if (is_array($to)) {
            $to = "{$to[1]} <{$to[0]}>";
        }
        $to = trim($to);

        if ($reply) {
            $reply_email   = trim($reply);
            $reply_name    = '';
            $reply_explode = explode('<', $reply);

            if ( ! empty($reply_explode[1])) {
                $reply_email = trim($reply_explode[1], '> ');
                $reply_name  = trim($reply_explode[0]);
            }

            $message->setReplyTo($reply_email, $reply_name);
        }

        $force_from = ! empty($config->mail->force_from) ? $config->mail->force_from : $from;

        if ($config->mail && $force_from) {
            $reply_from            = $from;
            $reply_email           = $reply_from;
            $reply_name            = '';
            $reply_address_explode = explode('<', $reply_from);

            if ( ! empty($reply_address_explode[1])) {
                $reply_email = trim($reply_address_explode[1], '> ');
                $reply_name  = trim($reply_address_explode[0]);
            }

            if ( ! $reply) {
                $message->setReplyTo($reply_email, $reply_name);
            }

            $from = $force_from;
        }

        $from_email           = trim($from);
        $from_name            = '';
        $from_address_explode = explode('<', $from);

        if ( ! empty($from_address_explode[1])) {
            $from_email = trim($from_address_explode[1], '> ');
            $from_name  = trim($from_address_explode[0]);
        }


        // TO
        $to_addresses_explode = explode(',', $to);
        foreach ($to_addresses_explode as $to_address) {
            if (empty(trim($to_address))) {
                continue;
            }

            $to_email           = trim($to_address);
            $to_name            = '';
            $to_address_explode = explode('<', $to_address);

            if ( ! empty($to_address_explode[1])) {
                $to_email = trim($to_address_explode[1], '> ');
                $to_name  = trim($to_address_explode[0]);
            }

            $message->addTo($to_email, $to_name);
        }

        // CC
        if ( ! empty($cc)) {
            $cc_addresses_explode = explode(',', $cc);
            foreach ($cc_addresses_explode as $cc_address) {
                if (empty(trim($cc_address))) {
                    continue;
                }

                $cc_email           = trim($cc_address);
                $cc_name            = '';
                $cc_address_explode = explode('<', $cc_address);

                if ( ! empty($cc_address_explode[1])) {
                    $cc_email = trim($cc_address_explode[1], '> ');
                    $cc_name  = trim($cc_address_explode[0]);
                }

                $message->addCc($cc_email, $cc_name);
            }
        }


        // BCC
        if ( ! empty($bcc)) {
            $bcc_addresses_explode = explode(',', $bcc);
            foreach ($bcc_addresses_explode as $bcc_address) {
                if (empty(trim($bcc_address))) {
                    continue;
                }

                $bcc_email           = trim($bcc_address);
                $bcc_name            = '';
                $bcc_address_explode = explode('<', $bcc_address);

                if ( ! empty($bcc_address_explode[1])) {
                    $bcc_email = trim($bcc_address_explode[1], '> ');
                    $bcc_name  = trim($bcc_address_explode[0]);
                }

                $message->addBcc($bcc_email, $bcc_name);
            }
        }

        $message->setSubject($subj);

        $parts = [];

        $html = new MimePart($body);
        $html->type     = Mime::TYPE_HTML;
        $html->charset  = 'utf-8';
        $html->encoding = Mime::ENCODING_QUOTEDPRINTABLE;

        $parts[] = $html;

        if ( ! empty($files)) {
            foreach ($files as $file) {

                $attach_file              = new MimePart($file['content']);
                $attach_file->type        = $file['mimetype'];
                $attach_file->filename    = $file['name'];
                $attach_file->disposition = Mime::DISPOSITION_ATTACHMENT;
                $attach_file->encoding    = Mime::ENCODING_BASE64;

                $parts[] = $attach_file;
            }
        }

        $body = new MimeMessage();
        $body->setParts($parts);

        $message->setBody($body);

        $transport = new Transport\Sendmail();

        if ( ! empty($config->mail->server)) {
            $config_smtp = [
                'host' => $config->mail->server
            ];

            if ( ! empty($config->mail->port)) {
                $config_smtp['port'] = $config->mail->port;
            }

            if ( ! empty($config->mail->auth)) {
                $config_smtp['connection_class'] = $config->mail->auth;

                if ( ! empty($config->mail->username)) {
                    $config_smtp['connection_config']['username'] = $config->mail->username;
                    $from_email = $config->mail->username;
                    $from_name = '';
                }
                if ( ! empty($config->mail->password)) {
                    $config_smtp['connection_config']['password'] = $config->mail->password;
                }
                if ( ! empty($config->mail->ssl)) {
                    $config_smtp['connection_config']['ssl'] = $config->mail->ssl;
                }
            }

            $options   = new Transport\SmtpOptions($config_smtp);
            $transport = new Transport\Smtp();
            $transport->setOptions($options);
        }
        $message->setFrom($from_email, $from_name);
        $transport->send($message);

    }
}