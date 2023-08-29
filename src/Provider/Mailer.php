<?php
namespace Admin\Provider;

use Admin\Admin;
use Admin\IO\Request;
use Admin\Config\Config;
use Admin\Provider\Mustache;
use Admin\Utils\Arrays;
use Admin\Utils\JSON;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * The Mailer Provider
 */
class Mailer {

    private static bool   $loaded   = false;
    private static string $template = "";
    private static string $url      = "";
    private static string $name     = "";
    private static mixed  $smtp     = null;


    /**
     * Loads the Mailer Config
     * @return boolean
     */
    private static function load(): bool {
        if (self::$loaded) {
            return false;
        }

        self::$loaded   = true;
        self::$template = Admin::loadFile(Admin::DataDir, "email.html");
        self::$url      = Config::get("url");
        self::$name     = Config::get("name");
        self::$smtp     = Config::get("smtp");
        return true;
    }



    /**
     * Sends the Email
     * @param string  $to
     * @param string  $subject
     * @param string  $body
     * @param string  $attachment Optional.
     * @param boolean $sendHtml   Optional.
     * @return boolean
     */
    private static function send(string $to, string $subject, string $body, string $attachment = "", bool $sendHtml = true): bool {
        if (self::$smtp->sendDisabled) {
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

        $mail->Username    = self::$smtp->email;
        $mail->Password    = self::$smtp->password;

        $mail->CharSet     = "UTF-8";
        $mail->From        = self::$smtp->email;
        $mail->FromName    = self::$name;
        $mail->Subject     = $subject;
        $mail->Body        = $body;

        $mail->addAddress($to);
        if (!empty($attachment)) {
            $mail->AddAttachment($attachment);
        }
        if (!empty(self::$smtp->showErrors)) {
            $mail->SMTPDebug = 3;
        }

        $result = $mail->send();
        if (!empty(self::$smtp->showErrors) && !$result) {
            echo "Message could not be sent.";
            echo "Mailer Error: " . $mail->ErrorInfo;
        }
        return $result;
    }

    /**
     * Sends Emails in HTML
     * @param string[]|string $sendTo
     * @param string          $subject
     * @param string          $message
     * @param string          $attachment Optional.
     * @return boolean
     */
    public static function sendTo(array|string $sendTo, string $subject, string $message, string $attachment = ""): bool {
        self::load();

        $sendTo  = Arrays::toArray($sendTo);
        $subject = Mustache::render($subject, [
            "url"  => self::$url,
            "name" => self::$name,
        ]);
        $body    = Mustache::render(self::$template, [
            "url"     => self::$url,
            "name"    => self::$name,
            "logo"    => self::$smtp->logo ?: "",
            "message" => $message,
        ]);

        foreach ($sendTo as $email) {
            $success = self::send($email, $subject, $body, $attachment);
        }
        return $success;
    }



    /**
     * Sends a Contact email
     * @param string $subject
     * @param string $message
     * @param string $attachment Optional.
     * @return boolean
     */
    public static function sendContact(string $subject, string $message, string $attachment = ""): bool {
        $sendTo = Config::get("smtpSendTo");
        return self::sendTo($sendTo, $subject, $message, $attachment);
    }

    /**
     * Sends a Reset password email
     * @param string $sendTo
     * @param string $resetCode
     * @return boolean
     */
    public static function sendReset(string $sendTo, string $resetCode): bool {
        $url      = "{{url}}session/code?resetCode={$resetCode}";
        $subject  = "Resetear contraseña en {{name}}";
        $message  = "<p>Ha recibido este email porque ha solicitado recuperar su contraseña.<br/>";
        $message .= "Si ha sido usted, por favor diríjase a la siguiente dirección para realizar el cambio:<br/>";
        $message .= "$url</p>";
        $message .= "<p>Ó puede completar el formulario con el siguiente código:<br> <b>$resetCode</b></p>";
        $message .= "<p>Si no ha sido usted, puede simplemente eliminar este mail.</p>";

        return self::sendTo($sendTo, $subject, $message);
    }



    /**
     * Checks if the Recaptcha is Valid
     * @param Request $request
     * @param boolean $withScore Optional.
     * @return boolean
     */
    public static function isCaptchaValid(Request $request, bool $withScore = false): bool {
        $recaptchaSecret = Config::get("recaptchaSecret");
        if (!$request->has("g-recaptcha-response") || empty($recaptchaSecret)) {
            return false;
        }
        $secretKey = urlencode($recaptchaSecret);
        $captcha   = urlencode($request->get("g-recaptcha-response"));
        $url       = "https://www.google.com/recaptcha/api/siteverify?secret=$secretKey&response=$captcha";
        $response  = JSON::readUrl($url, true);

        if (empty($response["success"])) {
            return false;
        }
        if ($withScore && $response["score"] <= 0.5) {
            return false;
        }
        return true;
    }
}
