<?php
namespace Admin\Route;

use Admin\Admin;
use Admin\IO\Response;
use Admin\Auth\Auth;
use Admin\Config\Config;
use Admin\File\File;
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
     * @return boolean
     */
    public static function print(Response $response, bool $isAjax, bool $isReload, bool $isFrame): bool {
        if ($response->isRedirect) {
            self::redirect($response, $isAjax, $isFrame);
            return true;
        }
        if ($response->isAPI) {
            echo JSON::encode($response->data + [
                "adminJWT" => Auth::getToken(),
            ]);
            return true;
        }
        if ($response->isJSON) {
            echo JSON::encode($response->data);
            return true;
        }
        if ($response->isView) {
            self::view($response, $isAjax, $isReload, $isFrame);
            return true;
        }
        return false;
    }

    /**
     * Redirects to the Response url, if necessary
     * @param Response $response
     * @param boolean  $isAjax
     * @param boolean  $isFrame
     * @return boolean
     */
    private static function redirect(Response $response, bool $isAjax, bool $isFrame): bool {
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
        return true;
    }

    /**
     * Prints the the Response template, if necessary
     * @param Response $response
     * @param boolean  $isAjax
     * @param boolean  $isReload
     * @param boolean  $isFrame
     * @return boolean
     */
    private static function view(Response $response, bool $isAjax, bool $isReload, bool $isFrame): bool {
        $forBody    = $isReload || (!$isAjax && !$isFrame);
        $credential = Auth::getCredential();
        $data       = [
            "showHeader"      => $forBody,
            "showFooter"      => $forBody,
            "version"         => Config::getVersion()->full,
            "siteName"        => Config::get("name"),
            "url"             => Config::getAdminUrl(),
            "siteUrl"         => Config::getUrl(),
            "baseUrl"         => Config::getBaseUrl(),
            "publicUrl"       => Config::getPublicUrl(),
            "filesUrl"        => Config::getFilesUrl(),
            "internalUrl"     => Config::getInternalUrl(),
            "userName"        => !empty($credential) ? $credential->name     : "",
            "userAvatar"      => !empty($credential) ? $credential->gravatar : "",
            "jwtToken"        => Auth::getToken(),
            "isLoggedIn"      => Auth::isLoggedIn(),
            "hasEditorAccess" => Auth::isEditor(),
            "hasAdminAccess"  => Auth::isAdmin(),
            "hasSlides"       => Admin::hasSlides(),
            "hasPersonalize"  => Admin::hasPersonalize(),
            "hasContact"      => Admin::hasContact(),
            "hasStyles"       => Admin::hasStyles(),
            "menuItems"       => $forBody ? self::getMenuItems() : [],
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
        return true;
    }

    /**
     * Returns the Menu Items
     * @return mixed[]
     */
    private static function getMenuItems(): array {
        $items    = Admin::loadData(Admin::MenuData);
        $sections = Admin::getSections();
        $result   = [];

        foreach ($items as $item) {
            $subItems = [];
            if (!empty($item["items"])) {
                foreach ($item["items"] as $subItem) {
                    $subItems[] = [
                        "subKey"  => $subItem["key"],
                        "subUrl"  => $subItem["url"],
                        "subName" => $subItem["name"],
                    ];
                }
            }
            $result[] = [
                "menuKey"    => $item["key"],
                "menuUrl"    => $item["url"],
                "menuIcon"   => $item["icon"],
                "menuName"   => $item["name"],
                "hasSubmenu" => !empty($subItems),
                "subItems"   => $subItems,
            ];
        }

        foreach ($sections as $section) {
            $result[] = [
                "menuKey"    => $section["url"],
                "menuUrl"    => $section["url"],
                "menuIcon"   => $section["icon"],
                "menuName"   => $section["name"],
                "hasSubmenu" => false,
                "subItems"   => [],
            ];
        }
        return $result;
    }
}
