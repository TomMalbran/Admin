<?php
namespace Admin\IO;

use Admin\IO\Request;
use Admin\IO\Response;
use Admin\IO\Navigation;
use Admin\IO\Errors;
use Admin\Schema\Model;

/**
 * The View wrapper
 */
class View {

    private $path;
    private $url;
    private $mainMenu;
    private $subMenu;


    /**
     * Creates a new View instance
     * @param string $path
     * @param string $url
     * @param string $mainMenu Optional.
     * @param string $subMenu  Optional.
     */
    public function __construct(string $path, string $url, string $mainMenu = "", string $subMenu = "") {
        $this->path     = $path;
        $this->url      = $url;
        $this->mainMenu = $mainMenu;
        $this->subMenu  = $subMenu;
    }



    /**
     * Sets the Url
     * @param string $url
     * @return View
     */
    public function setUrl(string $url): View {
        $this->url = $url;
        return $this;
    }
    
    /**
     * Sets the SubMenu
     * @param string $subMenu
     * @return View
     */
    public function setSub(string $subMenu): View {
        $this->subMenu = $subMenu;
        return $this;
    }
    
    /**
     * Generates a View Response
     * @param string  $template
     * @param Request $request
     * @param array   $result   Optional.
     * @param Model   $model    Optional.
     * @param Errors  $errors   Optional.
     * @return Response
     */
    public function create(
        string  $template,
        Request $request,
        array   $result = [],
        Model   $model = null,
        Errors  $errors = null
    ): Response {
        if ($model != null && $model->isEmpty()) {
            return Response::redirect($this->url, $request, "exists");
        }

        $temp = $this->path ."/" . $template;
        $data = $result + ($model != null ? $model->toObject() : []);
        if (empty($data["query"])) {
            $data["query"] = $request->getQuery()->toString();
        }
        return Response::view($temp, $this->mainMenu, $this->subMenu, $data, $request, $errors);
    }

    /**
     * Creates a Navigation
     * @param string     $template
     * @param Navigation $navigation
     * @param array      $data       Optional.
     * @return Response
     */
    public function navigation(string $template, Navigation $navigation, array $data = []): Response {
        $result = $navigation->create() + $data + [
            "navUrl" => $this->url,
        ];
        return self::create($template, $navigation->request, $result, null, $navigation->errors);
    }



    /**
     * Generates an Edit Response
     * @param Request $request
     * @param boolean $isEdit
     * @param integer $id
     * @return Response
     */
    public function edit(Request $request, bool $isEdit, int $id): Response {
        return $this->redirect($request, $isEdit ? "edit" : "create", true, $id);
    }

    /**
     * Generates a Delete Response
     * @param Request $request
     * @param boolean $success
     * @param integer $id
     * @return Response
     */
    public function delete(Request $request, bool $success, int $id): Response {
        return $this->redirect($request, "delete", $success, $success ? 0 : $id);
    }

    /**
     * Generates a Redirect Success Response
     * @param Request $request
     * @param string  $message
     * @param integer $id      Optional.
     * @return Response
     */
    public function success(Request $request, string $message, int $id = 0): Response {
        return $this->redirect($request, $message, true, $id);
    }

    /**
     * Generates a Redirect Error Response
     * @param Request $request
     * @param string  $message
     * @param integer $id      Optional.
     * @return Response
     */
    public function error(Request $request, string $message, int $id = 0): Response {
        return $this->redirect($request, $message, false, $id);
    }

    /**
     * Generates a Redirect Response
     * @param Request $request
     * @param string  $message
     * @param boolean $success
     * @param integer $id      Optional.
     * @return Response
     */
    public function redirect(Request $request, string $message, bool $success, int $id = 0): Response {
        $url = $this->url . (!empty($id) ? "/$id" : "");
        return Response::redirect($url, $request)->withMessage($message, $success);
    }
}
