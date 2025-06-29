<?php
namespace MilkCore;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

!defined('MILK_DIR') && die(); // Avoid direct access
require MILK_DIR.'/external-library/phpmailer/src/Exception.php';
require MILK_DIR.'/external-library/phpmailer/src/PHPMailer.php';
require MILK_DIR.'/external-library/phpmailer/src/SMTP.php';

/**
 * Wrapper for PHPMailer class
 *
 * @example
 * ```php
 * $mail = Get::mail();
 * $mail->load_template(__DIR__.'/mymodules/mails/email_template.php', ['name' => 'John Doe'])->to('aaa@example.com')->send();
 * ```
 *
 * @package     MilkCore
 */

class Mail
{
    var $mail = null;
    var $last_error = '';
    var $new_email = false;

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
    function config($is_smtp, $username = '', $password = '', $host = '',  $port = 465, $smtpSecure = PHPMailer::ENCRYPTION_SMTPS)
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

    public function charset($charset = 'UTF-8') {
        $this->mail->CharSet = $charset;
    }
   
    /**
     * To accepts multiple email addresses and names separated by commas
     */
    public function to(string $address, string $name = '') {
        $this->is_new_eamil();
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
                } catch (Exception $e) {
                    $this->last_error = $this->mail->ErrorInfo;
                }
            }
        } else {
            try {
                $this->mail->addAddress($address, $name);     //Add a recipient
            } catch (Exception $e) {
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
    public function cc(string $address, string $name = '') {
        $this->is_new_eamil();
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
                } catch (Exception $e) {
                    $this->last_error = $this->mail->ErrorInfo;
                }
            }
        } else {
            try {
                $this->mail->addCC($address, $name);
            } catch (Exception $e) {
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
    public function bcc(string $address, string $name = '') {
        $this->is_new_eamil();
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
                } catch (Exception $e) {
                    $this->last_error = $this->mail->ErrorInfo;
                }
            }
        } else {
            try {
                $this->mail->addBCC($address, $name);
            } catch (Exception $e) {
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
    public function from($from, $from_name = '') {
        $this->is_new_eamil();
        try {
            $this->mail->setFrom($from, $from_name);
        } catch (Exception $e) {
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
    public function replyTo($reply_to, $reply_to_name = '') {
        $this->is_new_eamil();
        try {
            $this->mail->addReplyTo($reply_to, $reply_to_name);
        } catch (Exception $e) {
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
    public function subject($subject) {
        $this->is_new_eamil();
        try {
            $this->mail->Subject = $subject;
        } catch (Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
        }
        return $this;
    }

    /**
     * Finds and loads the email template
     */
    public function load_template($path, $args_data = []) {
        $this->is_new_eamil();
        extract($args_data);
        $page = Get::dir_path($path);
        ob_start();
        if (is_file ( $page )) {
            require $page;
        } else {
            Logs::set('mail', 'ERROR', 'Template not found: '.$page);
            die ("Template not found: ". $page);
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
    public function message($message) {
        $this->is_new_eamil();
        try {
            $this->mail->Body    = $message;
            $this->mail->AltBody = strip_tags($message);
        } catch (Exception $e) {
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
    public function isHTML($is_html = false) {
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
    public function addAttachment($path, $name = '')
    {
        $this->is_new_eamil();
        try {
            if ($name == '') {
                $name = basename($path);
            }
            $this->mail->addAttachment($path, $name);
        } catch (Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
        }
        return $this;
    }

    /**
     * Sends the email
     */
    public function send() {
        $this->new_email = false;
        $this->last_error = '';
        try {
            $result = $this->mail->send();
            if (!$result) {
                $this->last_error = $this->mail->ErrorInfo;
            }
        } catch (Exception $e) {
            $this->last_error = $this->mail->ErrorInfo;
            $result = false;
        }
        $this->clear();
        return $result;
    }

    /**
     * Checks if there was an error in the last operation
     * 
     * @return bool True if there was an error, false otherwise
     */
    public function has_error() {
        return $this->last_error != '';
    }

    /**
     * Gets the last error message
     * 
     * @return string The last error message, or an empty string if no error occurred
     */
    public function get_error() {
        return $this->last_error;
    }

    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Clears all recipients, attachments, and resets the message state
     * 
     * @return void
     */
    private function clear() {
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
    private function is_new_eamil() {
        if (!$this->new_email) {
            $this->clear();
            $this->last_error = '';
            $this->new_email = true;
        }
    }

}
