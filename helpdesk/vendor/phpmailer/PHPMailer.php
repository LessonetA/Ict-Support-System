<?php
/**
 * PHPMailer-compatible SMTP Email Class
 * Drop-in compatible with PHPMailer API
 * For ICT Helpdesk System
 */
namespace PHPMailer\PHPMailer;

class PHPMailer {
    public $SMTPDebug = 0;
    public $isSMTP_flag = false;
    public $Host = '';
    public $SMTPAuth = true;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = 'tls';
    public $Port = 587;
    public $CharSet = 'UTF-8';
    public $isHTML_flag = false;
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $From = '';
    public $FromName = '';
    private $to = [];
    private $cc = [];
    private $bcc = [];
    public $ErrorInfo = '';
    private $exceptions;

    public function __construct($exceptions = false) {
        $this->exceptions = $exceptions;
    }

    public function isSMTP() { $this->isSMTP_flag = true; }
    public function isHTML($bool = true) { $this->isHTML_flag = $bool; }
    public function addAddress($email, $name = '') { $this->to[] = ['email'=>$email,'name'=>$name]; }
    public function addCC($email, $name = '')  { $this->cc[]  = ['email'=>$email,'name'=>$name]; }
    public function addBCC($email, $name = '') { $this->bcc[] = ['email'=>$email,'name'=>$name]; }
    public function clearAddresses() { $this->to=[]; $this->cc=[]; $this->bcc=[]; }

    public function send() {
        if (empty($this->to)) { $this->ErrorInfo = 'No recipients'; return false; }
        try {
            return $this->sendViaSMTP();
        } catch (\Exception $e) {
            $this->ErrorInfo = $e->getMessage();
            if ($this->exceptions) throw new Exception($e->getMessage());
            return false;
        }
    }

    private function sendViaSMTP() {
        $host   = $this->Host;
        $port   = $this->Port;
        $secure = strtolower($this->SMTPSecure);

        // Build socket connection
        $errno = $errstr = null;
        $timeout = 20;

        if ($secure === 'ssl') {
            $socket = fsockopen("ssl://{$host}", $port, $errno, $errstr, $timeout);
        } else {
            $socket = fsockopen($host, $port, $errno, $errstr, $timeout);
        }

        if (!$socket) {
            throw new \Exception("SMTP connect failed: [{$errno}] {$errstr}");
        }

        stream_set_timeout($socket, $timeout);

        $this->smtpRead($socket); // greeting

        // EHLO
        $this->smtpSend($socket, "EHLO " . gethostname());
        $ehloResp = $this->smtpRead($socket);

        // STARTTLS if needed
        if ($secure === 'tls') {
            $this->smtpSend($socket, "STARTTLS");
            $this->smtpRead($socket);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->smtpSend($socket, "EHLO " . gethostname());
            $this->smtpRead($socket);
        }

        // AUTH
        if ($this->SMTPAuth) {
            $this->smtpSend($socket, "AUTH LOGIN");
            $this->smtpRead($socket);
            $this->smtpSend($socket, base64_encode($this->Username));
            $this->smtpRead($socket);
            $this->smtpSend($socket, base64_encode($this->Password));
            $resp = $this->smtpRead($socket);
            if (substr($resp, 0, 3) !== '235') {
                fclose($socket);
                throw new \Exception("SMTP Auth failed: {$resp}");
            }
        }

        // MAIL FROM
        $this->smtpSend($socket, "MAIL FROM:<{$this->From}>");
        $this->smtpRead($socket);

        // RCPT TO
        foreach ($this->to  as $r) { $this->smtpSend($socket, "RCPT TO:<{$r['email']}>"); $this->smtpRead($socket); }
        foreach ($this->cc  as $r) { $this->smtpSend($socket, "RCPT TO:<{$r['email']}>"); $this->smtpRead($socket); }
        foreach ($this->bcc as $r) { $this->smtpSend($socket, "RCPT TO:<{$r['email']}>"); $this->smtpRead($socket); }

        // DATA
        $this->smtpSend($socket, "DATA");
        $this->smtpRead($socket);

        // Headers + body
        $msg = $this->buildMessage();
        $this->smtpSend($socket, $msg . "\r\n.");
        $resp = $this->smtpRead($socket);

        $this->smtpSend($socket, "QUIT");
        fclose($socket);

        if (substr($resp, 0, 3) !== '250') {
            throw new \Exception("Message rejected: {$resp}");
        }
        return true;
    }

    private function buildMessage() {
        $toList = implode(', ', array_map(fn($r)=>$r['name']?"\"".addslashes($r['name'])."\" <{$r['email']}>"  :$r['email'], $this->to));
        $date   = date('r');
        $boundary = md5(uniqid('', true));
        $body = $this->isHTML_flag ? $this->Body : $this->AltBody ?: $this->Body;
        $alt  = $this->AltBody ?: strip_tags($this->Body);
        $subj = $this->encodeHeader($this->Subject);

        $headers  = "Date: {$date}\r\n";
        $headers .= "From: {$this->encodeHeader($this->FromName)} <{$this->From}>\r\n";
        $headers .= "To: {$toList}\r\n";
        $headers .= "Subject: {$subj}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        if ($this->isHTML_flag) {
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $headers .= "\r\n";
            $headers .= "--{$boundary}\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $headers .= chunk_split(base64_encode($alt),76,"\r\n") . "\r\n";
            $headers .= "--{$boundary}\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $headers .= chunk_split(base64_encode($body),76,"\r\n") . "\r\n";
            $headers .= "--{$boundary}--";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";
            $headers .= "\r\n";
            $headers .= chunk_split(base64_encode($body),76,"\r\n");
        }
        return $headers;
    }

    private function encodeHeader($str) {
        if (preg_match('/[^\x20-\x7E]/', $str)) {
            return '=?UTF-8?B?' . base64_encode($str) . '?=';
        }
        return $str;
    }

    private function smtpSend($socket, $cmd) {
        if ($this->SMTPDebug > 0) error_log("[SMTP] >> {$cmd}");
        fwrite($socket, $cmd . "\r\n");
    }

    private function smtpRead($socket) {
        $resp = '';
        while ($line = fgets($socket, 512)) {
            $resp .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        if ($this->SMTPDebug > 0) error_log("[SMTP] << {$resp}");
        return $resp;
    }
}

class Exception extends \Exception {}
