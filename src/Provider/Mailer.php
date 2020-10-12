<?php
namespace Admin\Provider;

use Admin\Admin;
use Admin\IO\Request;
use Admin\Config\Config;
use Admin\Provider\Mustache;
use Admin\File\Path;
use Admin\Utils\Arrays;
use Admin\Utils\JSON;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\OAuth;
use League\OAuth2\Client\Provider\Google;

/**
 * The Mailer Provider
 */
class Mailer {
    
    private static $loaded   = false;
    private static $template = null;
    private static $url      = "";
    private static $name     = "";
    private static $smtp     = null;
    private static $google   = null;
    private static $emails   = [];
    
    
    /**
     * Loads the Mailer Config
     * @return void
     */
    public static function load(): void {
        if (!self::$loaded) {
            self::$loaded   = true;
            self::$template = Admin::loadFile(Admin::DataDir, "email.html");
            self::$url      = Config::get("url");
            self::$name     = Config::get("name");
            self::$smtp     = Config::get("smtp");
            self::$google   = Config::get("google");
            self::$emails   = Config::getArray("smtpActiveEmails");
        }
    }
    
    
    
    /**
     * Sends the Email
     * @param string  $to
     * @param string  $subject
     * @param string  $body
     * @param boolean $sendHtml   Optional.
     * @param string  $attachment Optional.
     * @return boolean
     */
    private static function send(string $to, string $subject, string $body, bool $sendHtml = true, string $attachment = ""): bool {
        if (self::$smtp->sendDisabled) {
            return false;
        }
        if (!empty(self::$emails) && !Arrays::contains(self::$emails, $to)) {
            return false;
        }

        $mail = new PHPMailer();
        
        $mail->isSMTP();
        $mail->isHTML($sendHtml);
        $mail->clearAllRecipients();
        $mail->clearReplyTos();

        $mail->Timeout     = 10;
        $mail->Host        = self::$smtp->host;
        $mail->Port        = self::$smtp->port;
        $mail->SMTPSecure  = self::$smtp->secure;
        $mail->SMTPAuth    = true;
        $mail->SMTPAutoTLS = false;
        
        if (self::$smtp->useOauth) {
            $mail->SMTPAuth = true;
            $mail->AuthType = "XOAUTH2";

            $provider = new Google([
                "clientId"     => self::$google->client,
                "clientSecret" => self::$google->secret,
            ]);
            $mail->setOAuth(new OAuth([
                "provider"     => $provider,
                "clientId"     => self::$google->client,
                "clientSecret" => self::$google->secret,
                "refreshToken" => self::$smtp->refreshToken,
                "userName"     => self::$smtp->email,
            ]));
        } else {
            $mail->Username = self::$smtp->email;
            $mail->Password = self::$smtp->password;
        }
        
        $mail->CharSet  = "UTF-8";
        $mail->From     = self::$smtp->email;
        $mail->FromName = self::$name;
        $mail->Subject  = $subject;
        $mail->Body     = $body;
        
        $mail->addAddress($to);
        if (!empty($attachment)) {
            $mail->AddAttachment($attachment);
        }
        if (self::$smtp->showErrors) {
            $mail->SMTPDebug = 3;
        }
        
        $result = $mail->send();
        if (self::$smtp->showErrors && !$result) {
            echo "Message could not be sent.";
            echo "Mailer Error: " . $mail->ErrorInfo;
        }
        return $result;
    }

    /**
     * Sends Emails in HTML
     * @param string|string[] $sendTo
     * @param string          $subject
     * @param string          $message
     * @return boolean
     */
    public static function sendTo(string $sendTo, string $subject, string $message): bool {
        self::load();
        $sendTo  = Arrays::toArray($sendTo);
        $subject = Mustache::render($subject, [
            "url"  => self::$url,
            "name" => self::$name,
        ]);
        $body    = Mustache::render(self::$template, [
            "url"     => self::$url,
            "name"    => self::$name,
            "files"   => Path::getUrl("email"),
            "logo"    => self::$smtp->logo ?: "",
            "message" => $message,
        ]);

        foreach ($sendTo as $email) {
            $success = self::send($email, $subject, $message);
        }
        return $success;
    }



    /**
     * Sends a Reset password email
     * @param string $sendTo
     * @param string $resetCode
     * @return boolean
     */
    public static function sendReset(string $sendTo, string $resetCode) {
        $url      = "{{url}}session/code?resetCode={$resetCode}";
        $subject  = "Resetear contranseña en {{name}}";
        $message  = "<p>Ha recibido este email porque ha solicitado recuperar su contraseña.<br/>";
        $message .= "Si ha sido usted, por favor diríjase a la siguiente dirección para realizar el cambio:<br/>";
        $message .= "$url</p>";
        $message .= "<p>Ó puede completar el formulario con el siguiente código:<br> <b>$resetCode</b></p>";
        $message .= "<p>Si no ha sido usted, puede simplemente eliminar este mail.</p>";
        
        return self::sendTo($sendTo, $subject, $message);
    }

    /**
     * Sends the Backup to the Backup account
     * @param string $sendTo
     * @param string $attachment
     * @return boolean
     */
    public static function sendBackup(string $sendTo, string $attachment): bool {
        $subject = Config::get("name") . ": Database Backup";
        $message = "Backup de la base de datos al dia: " . date("d M Y, H:i:s");
        
        return self::send($sendTo, $subject, $message, false, $attachment);
    }



    /**
     * Returns the Google Auth Url
     * @param string $redirectUri
     * @return string
     */
    public static function getAuthUrl(string $redirectUri): string {
        self::load();
        $options  = [ "scope" => [ "https://mail.google.com/" ]];
        $provider = new Google([
            "clientId"     => self::$google->client,
            "clientSecret" => self::$google->secret,
            "redirectUri"  => self::$url . $redirectUri,
            "accessType"   => "offline",
        ]);
        return $provider->getAuthorizationUrl($options);
    }

    /**
     * Returns the Google Refresh Token
     * @param string $redirectUri
     * @param string $code
     * @return string
     */
    public static function getAuthToken(string $redirectUri, string $code): string {
        self::load();
        $provider = new Google([
            "clientId"     => self::$google->client,
            "clientSecret" => self::$google->secret,
            "redirectUri"  => self::$url . $redirectUri,
            "accessType"   => "offline",
        ]);
        $token = $provider->getAccessToken("authorization_code", [ "code" => $code ]);
        return $token->getRefreshToken();
    }



    /**
     * Checks if the Recaptcha is Valid
     * @param Request $request
     * @return boolean
     */
    public static function isCaptchaValid(Request $request) {
        $secretKey = urlencode(Config::get("recaptchaKey"));
        $captcha   = urlencode($request->get("g-recaptcha-response"));
        $url       = "https://www.google.com/recaptcha/api/siteverify?secret=$secretKey&response=$captcha";
        $response  = JSON::readUrl($url, true);

        if (empty($response["success"])) {
            return false;
        }
        return true;
    }
}
