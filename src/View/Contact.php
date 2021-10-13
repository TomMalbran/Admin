<?php
namespace Admin\View;

use Admin\Admin;
use Admin\IO\View;
use Admin\IO\Request;
use Admin\IO\Response;
use Admin\IO\Errors;
use Admin\IO\Navigation;
use Admin\Schema\Factory;
use Admin\Schema\Schema;
use Admin\Schema\Query;
use Admin\Log\ActionLog;
use Admin\Utils\Strings;
use Admin\Provider\Mailer;

/**
 * The Contact View
 */
class Contact {

    private static $loaded  = false;
    private static $schema  = null;
    private static $options = null;


    /**
     * Loads the Contacts Schema
     * @return Schema
     */
    public static function getSchema(): Schema {
        if (!self::$loaded) {
            self::$loaded = false;
            self::$schema = Factory::getSchema("contacts");
        }
        return self::$schema;
    }

    /**
     * Creates and returns the View
     * @return View
     */
    private static function getView(): View {
        return new View("contacts", "contacts", "contacts");
    }

    /**
     * Returns the Contact Options
     * @param boolean $asArray Optional.
     * @return mixed
     */
    private static function getOptions(bool $asArray = true) {
        $data = Admin::loadData(Admin::ContactData, "admin", $asArray);
        return $asArray ? $data["options"] : $data->options;
    }



    /**
     * Returns the Contacts list view
     * @param Request $request
     * @return Response
     */
    public static function getAll(Request $request): Response {
        $navigation = new Navigation($request);
        $query      = Query::createOrderBy("createdTime", false);
        $query->limit($navigation->from, $navigation->to);

        $schema = self::getSchema();
        $list   = $schema->getAll($query);
        $total  = $schema->getTotal();
        $navigation->setData($list, $total);

        return self::getView()->navigation("main", $navigation);
    }

    /**
     * Returns the view for a single Contact
     * @param integer $contactID
     * @param Request $request
     * @return Response
     */
    public static function getOne(int $contactID, Request $request): Response {
        $contact = self::getSchema()->getOne($contactID);
        return self::getView()->create("view", $request, [], $contact);
    }



    /**
     * Creates a Contact
     * @param Request $request
     * @return Response
     */
    public static function create(Request $request): Response {
        $options = self::getOptions(false);
        $schema  = self::getSchema();
        $errors  = new Errors();

        if (!$request->has("name")) {
            $errors->add("name");
        }
        if (!$request->has("email") || !$request->isValidEmail("email")) {
            $errors->add("email");
        }
        if (!$request->has("message")) {
            $errors->add("message");
        }

        if ($options->hasPhone && $options->reqPhone && !$request->has("phone")) {
            $errors->add("phone");
        }
        if ($options->hasCompany && $options->reqCompany && !$request->has("company")) {
            $errors->add("company");
        }
        if (!Mailer::isCaptchaValid($request)) {
            $errors->add("recaptcha");
        }

        if ($errors->has()) {
            return Response::errors($errors);
        }

        $schema->create($request);
        self::send($request);
        return Response::success("contact");
    }

    /**
     * Sends a Contact email
     * @param Request $request
     * @return boolean
     */
    private static function send(Request $request) {
        $subject  = "Contacto en {{name}}";
        $message  = "<p>Han enviado un mensaje con los datos:</p>";
        $message .= "<p>Nombre y Apellido: {$request->name}</p>";
        $message .= $request->has("company") ? "<p>Empresa: {$request->company}</p>" : "";
        $message .= "<p>Email: {$request->email}.</p>";
        $message .= $request->has("phone") ? "<p>Teléfono: {$request->phone}</p>" : "";
        $message .= "<p>Mensaje:<br/>" . Strings::toHtml($request->message) . "</p>";

        return Mailer::sendContact($subject, $message);
    }

    /**
     * Deletes the given Contact
     * @param integer $contactID
     * @param Request $request
     * @return Response
     */
    public static function delete(int $contactID, Request $request): Response {
        $success = false;
        if ($request->has("confirmed") && self::getSchema()->delete($contactID)) {
            ActionLog::add("Contact", "Delete", $contactID);
            $success = true;
        }
        return self::getView()->delete($request, $success, $contactID);
    }
}
