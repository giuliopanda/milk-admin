<?php
namespace App;

use PHPMailer\PHPMailer\PHPMailer;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Wrapper for PHPMailer class
 *
 * @example
 * ```php
 * $mail = Get::mail();
 * $mail->loadTemplate(__DIR__.'/mymodules/mails/email_template.php', ['name' => 'John Doe'])->to('aaa@example.com')->send();
 * ```
 *
 * @package     App
 */

class Mail
{
    private ?PHPMailer $mail = null;
    public string  $last_error = '';
    private bool  $new_email = false;

    /**
     * Configures the mailer with SMTP settings or PHP's mail() function
     * 
     * @param bool $is_smtp Whether to use SMTP (true) or PHP's mail() function (false)
     * @param string $username SMTP username
     * @param string $password SMTP password
     * @param string $host SMTP server hostname
     * @param int $port SMTP port number (default: 465 for SSL)
     * @param string $smtpSecure Encryption type (default: PHPMailer::ENCRYPTION_SMTPS)
     * @return void
     */
    public function config(bool $is_smtp, string $username = '', string $password = '', string $host = '', int $port = 465, string $smtpSecure = PHPMailer::ENCRYPTION_SMTPS): void
    {
        $this->last_error = '';
        $this->mail = new PHPMailer(true);
        //$this->mail->SMTPDebug = \SMTP::DEBUG_SERVER;                      //Enable verbose debug output
        if ($is_smtp) {
            $this->mail->isSMTP();                                            //Send using SMTP
            $this->mail->Host       = $host;                                 //Set the SMTP server to send through
            $this->mail->SMTPAuth   = true;                                   //Enable SMTP authentication
            $this->mail->Username   = $username;                              //SMTP username
            $this->mail->Password   = $password;                              //SMTP password
            $this->mail->SMTPSecure = $smtpSecure;                           //Enable implicit TLS encryption
            $this->mail->Port       = $port;                                  //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
        
            $this->mail->isHTML(true);    
        } else {
            $this->mail->isMail();
        }
        $this->mail->CharSet = 'UTF-8';
        $this->mail->Subject = "MilkAdmin email";
    }

    public function charset(string $charset = 'UTF-8'): self {
        $this->mail->CharSet = $charset;
        return $this;
    }
   
    /**
     * To accepts multiple email addresses and names separated by commas
     */
    public function to(string $address, string $name = ''): self {
        $this->isNewEamil();
        $address = str_replace(';', ',', $address);
        $name = str_replace(';', ',', $name);
        if (strpos($address, ',') !== false) {
            $addresses = explode(',', $address);
            $names = explode(',', $name);
            foreach ($addresses as $key => $address) {
                $address = trim($address);
                $name = isset($names[$key]) ? $names[$key] : '';
                try {
                    $this->mail->addAddress($address, $name);     //Add a recipient
                } catch (\Exception $e) {
                    $this->last_error = $this->mail->ErrorInfo;
                }
            }
        } else {
            try {
                $this->mail->addAddress($address, $name);     //Add a recipient
            } catch (\Exception $e) {
                $this->last_error = $this->mail->ErrorInfo;
            }
        }
        return $this;
    }

    /**
     * Adds CC (carbon copy) recipient(s) to the email
     * 
     * @param string $address Single email or comma-separated list of email addresses
     * @param string $name Optional name(s) corresponding to the email address(es)
     * @return $this Returns self for method chaining
     */
    public function cc(string $address, string $name = ''): self {
        $this->isNewEamil();
        $address = str_replace(';', ',', $address);
        $name = str_replace(';', ',', $name);
        if (strpos($address, ',') !== false) {
            $addresses = explode(',', $address);
            $names = explode(',', $name);
            foreach ($addresses as $key => $address) {
                $address = trim($address);
                $name = isset($names[$key]) ? $names[$key] : '';
                try {
                    $this->mail->addCC($address, $name);     //Add a recipient
                } catch (\Exception $e) {
                    $this->last_error = $this->mail->ErrorInfo;
                }
            }
        } else {
            try {
                $this->mail->addCC($address, $name);
            } catch (\Exception $e) {
                $this->last_error = $this->mail->ErrorInfo;
            }
        }
        return $this;
    }

    /**
     * Adds BCC (blind carbon copy) recipient(s) to the email
     * 
     * @param string $address Single email or comma-separated list of email addresses
     * @param string $name Optional name(s) corresponding to the email address(es)
     * @return $this Returns self for method chaining
     */
    public function bcc(string $address, string $name = ''): self {
        $this->isNewEamil();
        $address = str_replace(';', ',', $address);
        $name = str_replace(';', ',', $name);
        if (strpos($address, ',') !== false) {
            $addresses = explode(',', $address);
            $names = explode(',', $name);
            foreach ($addresses as $key => $address) {
                $address = trim($address);
                $name = isset($names[$key]) ? $names[$key] : '';
                try {
                    $this->mail->addBCC($address, $name);     //Add a recipient
                } catch (\Exception $e) {
                    $this->last_error = $this->mail->ErrorInfo;
                }
            }
        } else {
            try {
                $this->mail->addBCC($address, $name);
            } catch (\Exception $e) {
                $this->last_error = $this->mail->ErrorInfo;
            }
        }
        return $this;
    }
    
    /**
     * Sets the sender's email address and name
     * 
     * @param string $from Sender's email address
     * @param string $from_name Optional sender's name
     * @return $this Returns self for method chaining
     */
    public function from(string $from, string $from_name = ''): self {
        $this->isNewEamil();
        try {
            $this->mail->setFrom($from, $from_name);
        } catch (\Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
        }
        return $this;
    }

    /**
     * Sets the Reply-To address and name
     * 
     * @param string $reply_to Reply-to email address
     * @param string $reply_to_name Optional reply-to name
     * @return $this Returns self for method chaining
     */
    public function replyTo(string $reply_to, string $reply_to_name = ''): self {
        $this->isNewEamil();
        try {
            $this->mail->addReplyTo($reply_to, $reply_to_name);
        } catch (\Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
        }
        return $this;
    }
    
    /**
     * Sets the email subject
     * 
     * @param string $subject The email subject line
     * @return $this Returns self for method chaining
     */
    public function subject(string $subject): self {
        $this->isNewEamil();
        try {
            $this->mail->Subject = $subject;
        } catch (\Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
        }
        return $this;
    }

    /**
     * Finds and loads the email template
     */
    public function loadTemplate(string $path, array $args_data = []): self {
        $this->isNewEamil();
        $path = Get::dirPath($path);
        extract($args_data, EXTR_SKIP);
        $page = Get::dirPath($path);
        ob_start();
        if (is_file ( $page )) {
            require $page;
        } else {
            Logs::set('MAIL',  'Template not found: '.$page, 'ERROR');
            $this->last_error = 'Email template not found';
        }
        ob_end_clean();
        return $this;
    }

    /**
     * Sets the email message body
     * 
     * @param string $message The email message body (HTML or plain text)
     * @return $this Returns self for method chaining
     */
    public function message(string $message): self {
        $this->isNewEamil();
        try {
            $this->mail->Body    = $message;
            $this->mail->AltBody = strip_tags($message);
        } catch (\Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
        }
        return $this;
    }

    /**
     * Sets whether the email is in HTML format
     * 
     * @param bool $is_html Whether the email is in HTML format (default: false)
     * @return $this Returns self for method chaining
     */
    public function isHTML(bool $is_html = false): self {
        $this->mail->isHTML($is_html);                                  //Set email format to HTML
        return $this;
    }

    /**
     * Adds an attachment to the email
     * 
     * @param string $path Full path to the file to attach
     * @param string $name Optional custom filename for the attachment
     * @return $this Returns self for method chaining
     */
    public function addAttachment(string $path, string $name = ''): self
    {
        $path = Get::dirPath($path);
        $this->isNewEamil();
        try {
            if ($name == '') {
                $name = basename($path);
            }
            $this->mail->addAttachment($path, $name);
        } catch (\Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
        }
        return $this;
    }

    /**
     * Sends the email
     *
     * Runs the 'mail_before_send' hook before sending.
     * Hook receives the PHPMailer instance and can:
     * - modify it and return it to proceed
     * - return false to cancel the send
     *
     * @example
     * ```php
     * Hooks::set('mail_before_send', function(PHPMailer $mail) {
     *     $mail->addBCC('archive@example.com'); // always BCC archive
     *     return $mail;
     * });
     *
     * Hooks::set('mail_before_send', function(PHPMailer $mail) {
     *     if (str_contains($mail->Subject, 'BLOCKED')) {
     *         return false; // cancel send
     *     }
     *     return $mail;
     * });
     * ```
     */
    public function send(): bool {
        $this->new_email = false;
        // Hook: mail_before_send — allows modification or cancellation
        $hookResult = Hooks::run('mail_before_send', $this->mail, $this->last_error);


        if ($hookResult === false) {
            $this->last_error = 'Send cancelled by hook';
            Hooks::run('mail_after_send', $this->mail, false, $this->last_error);
            $this->clear();
            return false;
        }

        if ($this->last_error != '') {
            Hooks::run('mail_after_send', $this->mail, false, $this->last_error);
            return false;
        }

        $this->last_error = '';
        try {
            $result = $this->mail->send();
            if (!$result) {
                $this->last_error = $this->mail->ErrorInfo;
            }
        } catch (\Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
            $result = false;
        }
       
        Hooks::run('mail_after_send', $this->mail, $result, $this->last_error);
        $this->clear();
        return $result;
    }

    /**
     * Checks if there was an error in the last operation
     * 
     * @return bool True if there was an error, false otherwise
     */
    public function hasError(): bool {
        return $this->last_error != '';
    }

    /**
     * Gets the last error message
     * 
     * @return string The last error message, or an empty string if no error occurred
     */
    public function getError(): string {
        return $this->last_error;
    }

    public function getLastError(): string {
        return $this->last_error;
    }

    /**
     * Clears all recipients, attachments, and resets the message state
     * 
     * @return void
     */
    private function clear(): void {
        if ( $this->mail ) {
            $this->mail->clearAddresses();
            $this->mail->clearAllRecipients();
            $this->mail->clearAttachments();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            $this->mail->clearReplyTos();
            $this->mail->clearCustomHeaders();
            $this->mail->isHTML(true);  
        }
    }

    /**
     * Initializes a new email by clearing any existing data
     * 
     * @return void
     */
    private function isNewEamil(): void {
        if (!$this->new_email) {
            $this->clear();
            $this->last_error = '';
            $this->new_email = true;
        }
    }

}
