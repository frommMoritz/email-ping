<?php

namespace frommMoritz;

class pingEmail
{
    private $user;
    private $email;
    private $domain;
    private $socket;
    private $MXHost;
    private $mailFrom;
    private $hostname;
    private $hasMXRecord;
    private $isValidEMail;

    /**
     * @param string $email
     * @param string $hostname
     */
    public function __construct(string $email, string $hostname = 'mx0.dummymail.de', $mailFrom = 'emailtester@dummymail.de')
    {
        $this->setEmail($email);
        $this->setHostname($hostname);
        $this->setMailFrom($mailFrom);
    }

    /**
     * @return bool
     */
    private function openSocket() :bool
    {
        $this->socket = @fsockopen('ssl://' . $this->MXHost, 465, $errno, $errstr, 0.1);
        if (!$this->socket)
        {
            $this->socket = fsockopen($this->MXHost, 25, $errno, $errstr);
            if (!$this->socket) {
                return false;
            }
        }

        $line = trim(fgets($this->socket));
        return (bool) preg_match('/220 '. preg_quote($this->MXHost) . ' .*/', $line);
    }

    private function sendSocketMessage($message, $positiveRegEx = null)
    {
        // var_dump('sending "' . $message . '"...');
        fwrite($this->socket, $message . "\r\n");
        // var_dump('sent "' . $message . '"');
        $line = trim(fgets($this->socket));
        // var_dump($line);
        if ($positiveRegEx !== null)
        {
            return (bool) preg_match($positiveRegEx, $line);
        }
        return $line;
    }

    /**
     * @return boolean
     */
    private function sendHelo() :bool
    {
        return $line = $this->sendSocketMessage('HELO ' . $this->hostname, '/(220|250)/i');
    }

    /**
     * @return boolean
     */
    private function sendMailFrom() :bool
    {
        return $this->sendSocketMessage('MAIL FROM:<' . $this->mailFrom . '>', '/250/i');
    }

    /**
     * @return boolean
     */
    private function sendRcpTo() :bool
    {
        return $this->sendSocketMessage('RCPT TO:<' . $this->email . '>', '/250/i');
    }

    /**
     * @param string $email
     * @return self|bool
     */
    public function setEmail(string $email)
    {
        $this->email = $email;
        $this->isValidEMail = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$this->isValidEMail)
        {
            return false;
        }

        $email = explode('@', $email, 2);
        $this->user = $email[0];
        $this->setDomain($email[1]);

        return $this;
    }

    /**
     * @param string $domain
     */
    public function setDomain(string $domain)
    {
        $this->domain = $domain;

        if (!checkdnsrr($domain, 'MX'))
        {
            $this->hasMXRecord = false;
            return false;
        }

        $MXRecords = dns_get_record($domain, DNS_MX);
        usort($MXRecords, function ($a, $b)  {
            return $a['pri'] > $b['pri'];
        });

        $this->MXHost = $MXRecords[0]['target'];
        $this->hasMXRecord = true;
        return $this;
    }

    /**
     * @param string $hostname
     * @return self
     */
    public function setHostname(string $hostname) :self
    {
        $this->hostname = $hostname;
        return $this;
    }

    /**
     * @param string $hostname
     * @return self
     */
    public function setMailFrom(string $mailFrom) :self
    {
        $this->mailFrom = $mailFrom;
        return $this;
    }

    /**
     * @return bool
     */
    public function verifyEmailOnServer() :bool
    {
        $this->openSocket();
        if (!$this->sendHelo())
        {
            return false;
        }

        if (!$this->sendMailFrom())
        {
            return false;
        }

        if (!$this->sendRcpTo())
        {
            return false;
        }

        return true;
    }

    /**
     * @return boolean
     */
    public function checkIfEmailExists() :bool
    {
        if (!$this->hasMXRecord) {
            return false;
        }

        if (!$this->isValidEMail) {
            return false;
        }

        return $this->verifyEmailOnServer();
    }

    public function __destruct()
    {
        fclose($this->socket);
    }
}

