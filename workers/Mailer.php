<?php
namespace Core2;

require_once __DIR__ . '/../inc/classes/Error.php';
require_once __DIR__ . '/../inc/classes/Db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{

    private $_config;

    public function __construct()
    {
        $this->_config = Registry::get('config');
    }


    /**
     * @param \GearmanJob|Job $job
     * @param array       $log
     */
    public function run(\GearmanJob|Job $job, array &$log) {

        $workload = json_decode($job->workload());

        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }
        $_SERVER = get_object_vars($workload->server);

        $from   = $workload->payload->from;
        $to     = $workload->payload->to;
        $reply  = $workload->payload->reply;
        $subj   = $workload->payload->subj;
        $body   = $workload->payload->body;
        $bcc    = $workload->payload->bcc;
        $cc     = $workload->payload->cc;
        $files  = $workload->payload->files; //StdObject

        $mail   = new PHPMailer();
        $mail->CharSet  = "UTF-8";
        $mail->Encoding = 'base64';
        //$mail->SMTPDebug = SMTP::DEBUG_SERVER;


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

            $mail->addReplyTo($reply_email, $reply_name);
        }

        $force_from = ! empty($this->_config->mail->force_from) ? $this->_config->mail->force_from : $from;

        if ($this->_config->mail && $force_from) {
            $reply_from            = $from;
            $reply_email           = $reply_from;
            $reply_name            = '';
            $reply_address_explode = explode('<', $reply_from);

            if ( ! empty($reply_address_explode[1])) {
                $reply_email = trim($reply_address_explode[1], '> ');
                $reply_name  = trim($reply_address_explode[0]);
            }

            if ( ! $reply) {
                $mail->addReplyTo($reply_email, $reply_name);
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

            $mail->addAddress($to_email, $to_name);
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

                $mail->addCC($cc_email, $cc_name);
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

                $mail->addBCC($bcc_email, $bcc_name);
            }
        }

        $mail->Subject = $subj;

        if ( ! empty($files)) {
            foreach ($files as $file) {

                $mail->addAttachment($file->file, $file->name);
            }
        }

        $mail->isHTML(true);
        $mail->Body = $body;
        //Read an HTML message body from an external file, convert referenced images to embedded,
        //convert HTML into a basic plain-text alternative body
        //$mail->msgHTML(file_get_contents('contents.html'), __DIR__);

        if ( ! empty($this->_config->mail->server)) {
            $mail->isSMTP();
            $mail->Host = $this->_config->mail->server;

            if ( ! empty($this->_config->mail->port)) {
                $mail->Port = $this->_config->mail->port;
            }

            if ( ! empty($this->_config->mail->auth)) {
                $mail->SMTPAuth   = true;

                if ( ! empty($this->_config->mail->username)) {
                    $mail->Username = $this->_config->mail->username;
                    $from_email = $this->_config->mail->username;
                    $from_name = '';
                }
                if ( ! empty($this->_config->mail->password)) {
                    $mail->Password = $this->_config->mail->password;
                }
                if ( ! empty($this->_config->mail->ssl)) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    //Custom connection options
                    //Note that these settings are INSECURE
                    /*$mail->SMTPOptions = array(
                        'ssl' => [
                            'verify_peer' => true,
                            'verify_depth' => 3,
                            'allow_self_signed' => true,
                            'peer_name' => 'smtp.example.com',
                            'cafile' => '/etc/ssl/ca_cert.pem',
                        ],
                    );*/
                }
            }

        }
        $mail->setFrom($from_email, $from_name);
        $isSent = true;
        if (!$mail->send()) {
            //echo 'Mailer Error: ' . $mail->ErrorInfo;
            $isSent = false;
        }
        try {

            if ( ! empty($workload->payload->queue_id) && $isSent) {
                $db = (new Db())->db;

                $where = $db->quoteInto('id IN(?)', $workload->payload->queue_id);
                $db->update('mod_queue_mails', [
                    'date_send'  => date('Y-m-d H:i:s'),
                    'is_error'   => 'N',
                    'last_error' => '',
                ], $where);

                $db->closeConnection();
            }
            if (!$isSent) throw new \Exception($mail->ErrorInfo);

        }
        catch (\Exception $e) {
            if ( ! empty($workload->payload->queue_id)) {
                $db = (new Db())->db;

                $where = $db->quoteInto('id IN(?)', $workload->payload->queue_id);
                $db->update('mod_queue_mails', [
                    'is_error'   => 'Y',
                    'importance' => 'LOW',
                    'last_error' => $e->getMessage(),
                ], $where);

                $db->closeConnection();
            }
        }
        return $isSent;
    }
}