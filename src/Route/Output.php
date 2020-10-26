<?php
namespace Admin\Route;

use Admin\IO\Response;
use Admin\Auth\Auth;
use Admin\Config\Config;
use Admin\Provider\Mustache;
use Admin\Utils\JSON;

/**
 * The Output Service
 */
class Output {
    
    /**
     * Prints the Response
     * @param Response $response
     * @param boolean  $isAjax
     * @param boolean  $isReload
     * @param boolean  $isFrame
     * @return void
     */
    public static function print(Response $response, bool $isAjax, bool $isReload, bool $isFrame) {
        if ($response->isRedirect) {
            self::redirect($response, $isAjax, $isFrame);
        } elseif ($response->isAPI) {
            echo JSON::encode($response->data + [
                "adminJWT" => Auth::getToken(),
            ]);
        } elseif ($response->isJSON) {
            echo JSON::encode($response->data);
        } elseif ($response->isView) {
            self::view($response, $isAjax, $isReload, $isFrame);
        }
    }

    /**
     * Redirects to the Response url, if necesary
     * @param Response $response
     * @param boolean  $isAjax
     * @param boolean  $isFrame
     * @return void
     */
    private static function redirect(Response $response, bool $isAjax, bool $isFrame): void {
        $baseUrl = Config::getAdminUrl();
        
        $url = $baseUrl;
        if (!empty($response->url)) {
            $url = str_replace("//", "/", $response->url);
            if ($url[0] == "/") {
                $url = substr($url, 1, strlen($url));
            }
            $url = $baseUrl . $url;
        }
        
        if ($isAjax) {
            echo JSON::encode([
                "adminJWT" => Auth::getToken(),
                "forBody"  => !$isAjax && !$isFrame,
                "redirect" => $url,
                "reload"   => !empty($response->reload),
                "storage"  => $response->storage,
            ]);
        } else {
            header("Location: $url");
        }
    }
    
    /**
     * Prints the the Response template, if necesary
     * @param Response $response
     * @param boolean  $isAjax
     * @param boolean  $isReload
     * @param boolean  $isFrame
     * @return void
     */
    private static function view(Response $response, bool $isAjax, bool $isReload, bool $isFrame): void {
        $forBody    = $isReload || (!$isAjax && !$isFrame);
        $credential = Auth::getCredential();
        $data       = [
            "version"         => Config::getVersion()->full,
            "siteName"        => Config::get("name"),
            "url"             => Config::getAdminUrl(),
            "appUrl"          => Config::getUrl(),
            "baseUrl"         => Config::getBaseUrl(),
            "publicUrl"       => Config::getPublicUrl(),
            "filesUrl"        => Config::getFilesUrl(),
            "showHeader"      => $forBody,
            "showFooter"      => $forBody,
            "userName"        => !empty($credential) ? $credential->name     : "",
            "userAvatar"      => !empty($credential) ? $credential->gravatar : "",
            "jwtToken"        => Auth::getToken(),
            "isLoggedIn"      => Auth::isLoggedIn(),
            "hasEditorAccess" => Auth::isEditor(),
            "hasAdminAccess"  => Auth::isAdmin(),
            "isMenuSel"       => function ($val) use ($response) {
                return $val == $response->mainMenu ? "menu-item-selected" : "";
            },
            "isSubSel"        => function ($val) use ($response) {
                return $val == $response->subMenu ? "sub-item-selected" : "";
            },
        ] + $response->data;
        
        if ($isAjax) {
            $content = Mustache::render($response->template, $data);
            echo JSON::encode([
                "forBody"  => $forBody,
                "mainMenu" => $response->mainMenu,
                "subMenu"  => $response->subMenu,
                "content"  => $content,
            ]);
        } else {
            Mustache::print("core/index", $data);
        }
    }
}