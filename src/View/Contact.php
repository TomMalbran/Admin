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
use Admin\Schema\Field;
use Admin\Schema\Query;
use Admin\Log\ActionLog;
use Admin\Utils\Arrays;
use Admin\Provider\Mailer;

/**
 * The Contact View
 */
class Contact {

    /**
     * Returns the Contacts Data
     * @return mixed
     */
    private static function getData(): mixed {
        return Admin::loadData(Admin::ContactData, "admin", false);
    }

    /**
     * Returns the Contacts Schema Fields
     * @param array{} $fields
     * @return array{}
     */
    public static function getFields(array $fields): array {
        $data = Contact::getData();
        foreach ($data->fields as $key => $field) {
            $fields[$key] = [
                "type" => !empty($field->type) ? $field->type : Field::String,
            ];
        }
        return $fields;
    }



    /**
     * Returns the Contacts Schema
     * @return Schema
     */
    private static function schema(): Schema {
        return Factory::getSchema("contacts");
    }

    /**
     * Creates and returns the View
     * @return View
     */
    private static function getView(): View {
        return new View("contacts", "contacts", "contacts");
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

        $list  = self::schema()->getAll($query);
        $total = self::schema()->getTotal();
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
        $data    = self::getData();
        $contact = self::schema()->getOne($contactID);
        $fields  = [];

        foreach ($data->fields as $key => $field) {
            $fields[] = [
                "name"  => $field->name,
                "value" => $contact->get($key),
            ];
        }
        return self::getView()->create("view", $request, [
            "fields" => $fields,
        ], $contact);
    }



    /**
     * Creates a Contact
     * @param Request $request
     * @return Response
     */
    public static function create(Request $request): Response {
        $data   = self::getData();
        $errors = new Errors();

        if (!$request->has("name")) {
            $errors->add("name");
        }
        if (!$request->has("email") || !$request->isValidEmail("email")) {
            $errors->add("email");
        }

        foreach ($data->fields as $key => $field) {
            if ($field->isRequired && !$request->has($key)) {
                $errors->add($key);
            } elseif (!empty($field->options) && !Arrays::contains($field->options, $request->get($key))) {
                $errors->add($key);
            }
        }

        if ($data->hasCaptcha && !Mailer::isCaptchaValid($request)) {
            $errors->add("recaptcha");
        }

        if ($errors->has()) {
            return Response::errors($errors);
        }

        if (Admin::hasDatabase()) {
            self::schema()->create($request);
        }
        self::send($request);
        return Response::success("contact");
    }

    /**
     * Sends a Contact email
     * @param Request $request
     * @return boolean
     */
    private static function send(Request $request): bool {
        $data     = self::getData();

        $subject  = "Contacto en {{name}}";
        $message  = "<p>Han enviado un mensaje con los datos:</p>";
        $message .= "<p>Nombre y Apellido: {$request->name}</p>";
        $message .= "<p>Email: {$request->email}</p>";

        foreach ($data->fields as $key => $field) {
            if ($request->has($key)) {
                $value    = $request->toHtml($key);
                $message .= "<p>{$field->name}: {$value}</p>";
            }
        }

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
        if ($request->has("confirmed") && self::schema()->delete($contactID)) {
            ActionLog::add("Contact", "Delete", $contactID);
            $success = true;
        }
        return self::getView()->delete($request, $success, $contactID);
    }
}
