<?php
namespace Admin\Provider;

use Admin\Admin;
use Admin\File\File;
use Admin\Utils\Strings;

use Mustache_Autoloader;
use Mustache_Engine;
use Mustache_Loader_CascadingLoader;
use Mustache_Loader_FilesystemLoader;
use Mustache_Exception_UnknownTemplateException;

/**
 * The Mustache Provider
 */
class Mustache {
    
    private static $loaded = false;

    private static $simpleEngine = null;
    private static $adminEngine  = null;
    private static $siteEngine   = null;
    
    
    /**
     * Creates the Mustache Provider
     * @param boolean $forSite Optional.
     * @return void
     */
    public static function load(bool $forSite = false): void {
        if (!self::$loaded) {
            Mustache_Autoloader::register();
            self::$loaded       = true;
            self::$simpleEngine = new Mustache_Engine();
        }

        $path     = Admin::getPath(Admin::PublicDir, $forSite ? "site" : "admin");
        $internal = Admin::getPath(Admin::PublicDir, "internal");

        if (File::exists($path)) {
            $config  = [ "extension" => ".html" ];
            $loaders = [];

            // Create the Admin Eengine
            if (!$forSite && self::$adminEngine == null) {
                // Main templates should be in public/templates
                $internalPath = File::getPath($internal, Admin::TemplatesDir);
                if (File::exists($path, Admin::TemplatesDir)) {
                    $loaderPath = File::getPath($path, Admin::TemplatesDir);
                    $loaders["loader"] = new Mustache_Loader_CascadingLoader([
                        new Mustache_Loader_FilesystemLoader($loaderPath, $config),
                        new Mustache_Loader_FilesystemLoader($internalPath, $config),
                    ]);
                } else {
                    $loaders["loader"] = new Mustache_Loader_FilesystemLoader($internalPath, $config);
                }

                // Partials should be in public/partials
                $internalPath = File::getPath($internal, Admin::PartialsDir);
                if (File::exists($path, Admin::PartialsDir)) {
                    $loaderPath = File::getPath($path, Admin::PartialsDir);
                    $loaders["partials_loader"] = new Mustache_Loader_CascadingLoader([
                        new Mustache_Loader_FilesystemLoader($loaderPath, $config),
                        new Mustache_Loader_FilesystemLoader($internalPath, $config),
                    ]);
                } else {
                    $loaders["partials_loader"] = new Mustache_Loader_FilesystemLoader($internalPath, $config);
                }

                self::$adminEngine = new Mustache_Engine($loaders);
            }

            // Create the Site Engine
            if ($forSite && self::$siteEngine == null) {
                // Main templates should be in public/templates
                if (File::exists($path, Admin::TemplatesDir)) {
                    $loaderPath = File::getPath($path, Admin::TemplatesDir);
                    $loaders["loader"] = new Mustache_Loader_FilesystemLoader($loaderPath, $config);
                }

                // Partials should be in public/partials
                if (File::exists($path, Admin::PartialsDir)) {
                    $loaderPath = File::getPath($path, Admin::PartialsDir);
                    $loaders["partials_loader"] = new Mustache_Loader_FilesystemLoader($loaderPath, $config);
                }

                self::$siteEngine = new Mustache_Engine($loaders);
            }
        }
    }
    
    
    
    /**
     * Renders the template using any of the Engines depending on the first parameter
     * @param string  $templateOrPath
     * @param array   $data
     * @param boolean $forSite        Optional.
     * @return string
     */
    public static function render(string $templateOrPath, array $data, bool $forSite = false): string {
        self::load($forSite);

        if (!Strings::match($templateOrPath, '/^[a-z\/]*$/')) {
            return self::$simpleEngine->render($templateOrPath, $data);
        }
        if (!$forSite && self::$adminEngine != null) {
            return self::$adminEngine->render($templateOrPath, $data);
        }
        if ($forSite && self::$siteEngine != null) {
            return self::$siteEngine->render($templateOrPath, $data);
        }
        return "";
    }

    /**
     * Renders and prints the template using any of the Engines depending on the first parameter
     * @param string  $templateOrPath
     * @param array   $data
     * @param boolean $forSite        Optional.
     * @return void
     */
    public static function print(string $templateOrPath, array $data, bool $forSite = false) {
        self::load($forSite);
        echo self::render($templateOrPath, $data, $forSite);
    }
}
