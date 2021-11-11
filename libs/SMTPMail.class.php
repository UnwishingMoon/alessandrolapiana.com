<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class SMTPMail {
    public function sendGmail($client, $from, $to, $subject, $message, $attach = array(), $debug = false, &$output = array()): bool {
        if (empty($client)) {
            if ($debug) {
                $output[] = "Google client is empty";
            }

            return false;
        }

        // Creating the Email
        $service = new Google_Service_Gmail($client);
        if ($debug) {
            $output[] = "Created Gmail service";
        }

        $email = new Google_Service_Gmail_Message();
        if ($debug) {
            $output[] = "Created Gmail message";
        }

        $rawMsg = "";
        $bcc = array();
        $rawCharset = "utf-8";
        $boundary = uniqid(rand(), true);
        $result = false;

        if (is_array($from)) {
            $rawMsg = "From: {$from[1]}<{$from[0]}>\r\n";
        } else {
            $rawMsg = "From: $from<$from>\r\n";
        }
        if ($debug) {
            $output[] = "Added From email";
        }

        if (is_array($to)) {
            foreach ($to as $address) {
                $bcc[] = $address;
            }
        } else {
            $rawMsg .= "To: <{$to}>\r\n";
        }
        if ($debug) {
            $output[] = "Added To address";
        }

        $rawMsg .= 'Subject: =?' . $rawCharset . '?B?' . base64_encode($subject) . "?=\r\n";
        $rawMsg .= "MIME-Version: 1.0\r\n";
        if ($debug) {
            $output[] = "Added base64 encoded subject";
        }

        if (!empty($bcc)) {
            $bcc = implode(",", $bcc);
            $rawMsg .= "Bcc: $bcc\r\n";
            if ($debug) {
                $output[] = "Added all Bcc addresses";
            }
        }

        // Adding the body
        $rawMsg .= 'Content-type: Multipart/Mixed; boundary="' . $boundary . '"' . "\r\n";
        $rawMsg .= "\r\n--{$boundary}\r\n";
        $rawMsg .= 'Content-Type: text/html; charset=' . $rawCharset . "\r\n";
        $rawMsg .= "Content-Transfer-Encoding: base64" . "\r\n\r\n";
        $rawMsg .= base64_encode($message) . "\r\n";
        $rawMsg .= "--{$boundary}\r\n";
        if ($debug) {
            $output[] = "Added body message and boundary";
        }

        if (!empty($attach)) {
            foreach ($attach as $file) {
                $mimeType = mime_content_type($file);
                $fileName = basename($file);
                $fileData = base64_encode(file_get_contents($file));

                $rawMsg .= "\r\n--$boundary\r\n";
                $rawMsg .= "Content-Type: $mimeType; name='$fileName';\r\n";
                $rawMsg .= "Content-ID: <$to>\r\n";
                $rawMsg .= "Content-Description: $fileName;\r\n";
                $rawMsg .= "Content-Disposition: attachment; filename=$fileName; size=" . filesize($file) . ";\r\n";
                $rawMsg .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $rawMsg .= chunk_split($fileData, 76, "\n");
                $rawMsg .= "\r\n--$boundary\r\n";
            }
            if ($debug) {
                $output[] = "Attachments added to the email";
            }
        }

        try {
            $rawMsg = rtrim(strtr(base64_encode($rawMsg), array('+' => '-', '/' => '_')), '=');
            $email->setRaw($rawMsg);

            $result = $service->users_messages->send("me", $email);
            if ($debug) {
                $output[] = "Sending the email..";
            }
        } catch (\Exception $e) {
            // Print error
            if ($debug) {
                $output[] = "An error occurred while sending the email!";
                $output[] = $e->getMessage();
            }
            return false;
        }

        return $result;
    }

    /**
     * Send email using the sendinblue service
     *
     * @param mixed $from Can be an email string or an array. If array the first value must be the email address and the second the name visualized
     * @param mixed $to Can be a single email or an array of mails. If an array is provided each email is added as a BCC address (blind carbon copy)
     * @param mixed $subject The subject of the message
     * @param mixed $message The message body of the email
     * @param array $attach An array of attachments that can be added to the email
     * @param array $SMTP Custom SMTP infos. An associative array where the keys can be 'host', 'port', 'username' and 'password'
     * @param int $debug The level of debug that should be used. Possible values are (DEBUG_OFF, DEBUG_CLIENT, DEBUG_SERVER, DEBUG_CONNECTION, DEBUG_LOWLEVEL)
     * @param array $output Reference to an array which contains all the debug lines if the debug is active
     * @return bool True when the email is sent successfully, false otherwise
     * @throws Exception
     */
    public function sendSendinblue($from, $to, $subject, $message, $attach = array(), $SMTP = array(), $debug = "DEBUG_OFF", &$output = array()) {
        $mail = new PHPMailer();
        $result = false;

        $mail->isSMTP();
        $mail->Host = 'smtp-relay.sendinblue.com';
        $mail->Port = 587;
        $mail->SMTPAuth = true;
        $mail->Username = '';
        $mail->Password = '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPDebug = constant("SMTP::$debug");

        if ($debug != SMTP::DEBUG_OFF) {
            $output[] = "Finished setting up default SMTP";
        }

        // Custom SMTP
        if (!empty($SMTP)) {
            if ($debug != SMTP::DEBUG_OFF) {
                $output[] = "Custom SMTP found, setting up values..";
            }

            if (!empty($SMTP["host"])) {
                $mail->Host = $SMTP["host"];
            }

            if (!empty($SMTP["port"])) {
                $mail->Port = $SMTP["port"];
            }

            if (!empty($SMTP["username"])) {
                $mail->Username = $SMTP["username"];
            }

            if (!empty($SMTP["password"])) {
                $mail->Password = $SMTP["password"];
            }
        }

        // From address
        if (is_array($from)) {
            $mail->setFrom($from[0], $from[1]);
            $mail->addAddress($from[0]);

            if ($debug != SMTP::DEBUG_OFF) {
                $output[] = "Added from address: {$from[1]} ($from[0])";
            }
        } else {
            $mail->setFrom($from);
            $mail->addAddress($from);

            if ($debug != SMTP::DEBUG_OFF) {
                $output[] = "Added from address: {$from}";
            }
        }

        $mail->IsHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        // To address
        if (is_array($to)) {
            foreach ($to as $email) {
                $mail->AddBCC($email);

                if ($debug != SMTP::DEBUG_OFF) {
                    $output[] = "Added BBC address: {$email}";
                }
            }
        } else {
            $mail->addAddress($to);

            if ($debug != SMTP::DEBUG_OFF) {
                $output[] = "Added To address: {$to}";
            }
        }

        // Attachments
        if (!empty($attach)) {
            foreach ($attach as $file) {
                $mail->addAttachment($file);

                if ($debug != SMTP::DEBUG_OFF) {
                    $output[] = "Added attachment: {$file}";
                }
            }
        }

        try {
            $result = $mail->send(); // Ritorna false nel caso di errore

            if ($debug != SMTP::DEBUG_OFF) {
                $output[] = "(" . date("Y-m-d_H-i-s.u") . ") Tried sending email";
            }
        } catch (Exception $e) {
            // Some important error happened

            if ($debug != SMTP::DEBUG_OFF) {
                $output[] = "(" . date("Y-m-d_H-i-s.u") . ") Error found when sending email: " . $e->errorMessage();
            }
        }

        return $result;
    }
}
