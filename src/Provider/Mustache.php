<?php
namespace Admin\Provider;

use Admin\Admin;
use Admin\File\File;
use Admin\Utils\Strings;

use Mustache_Autoloader;
use Mustache_Engine;
use Mustache_Loader_CascadingLoader;
use Mustache_Loader_FilesystemLoader;

/**
 * The Mustache Provider
 */
class Mustache {

    private static bool             $loaded       = false;
    private static ?Mustache_Engine $simpleEngine = null;
    private static ?Mustache_Engine $adminEngine  = null;
    private static ?Mustache_Engine $siteEngine   = null;


    /**
     * Creates the Mustache Provider
     * @param boolean $forSite Optional.
     * @return boolean
     */
    public static function load(bool $forSite = false): bool {
        if (!self::$loaded) {
            Mustache_Autoloader::register();
            self::$loaded       = true;
            self::$simpleEngine = new Mustache_Engine();
        }

        $path     = Admin::getPath(Admin::PublicDir, $forSite ? "site" : "admin");
        $internal = Admin::getPath(Admin::PublicDir, "internal");
        $config   = [ "extension" => ".html" ];
        $loaders  = [];

        // Create the Admin Engine
        if (!$forSite && self::$adminEngine == null && File::exists($internal)) {
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
        if ($forSite && self::$siteEngine == null && File::exists($path)) {
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

        return true;
    }



    /**
     * Renders the template using any of the Engines depending on the first parameter
     * @param string  $templateOrPath
     * @param array{} $data
     * @param boolean $forSite        Optional.
     * @return string
     */
    public static function render(string $templateOrPath, array $data, bool $forSite = false): string {
        self::load($forSite);

        if (!Strings::match($templateOrPath, '/^[a-z\/\-]*$/')) {
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
     * @param array{} $data
     * @param boolean $forSite        Optional.
     * @return boolean
     */
    public static function print(string $templateOrPath, array $data, bool $forSite = false): bool {
        self::load($forSite);
        echo self::render($templateOrPath, $data, $forSite);
        return true;
    }
}
