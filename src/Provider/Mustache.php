<?php
namespace Admin\Provider;

use Admin\Admin;
use Admin\File\File;
use Admin\Utils\Strings;

use Mustache_Autoloader;
use Mustache_Engine;
use Mustache_Loader_FilesystemLoader;
use Mustache_Exception_UnknownTemplateException;

/**
 * The Mustache Provider
 */
class Mustache {
    
    private static $loaded = false;
    private static $engine = null;
    private static $loader = null;
    
    
    /**
     * Creates the Mustache Provider
     * @return void
     */
    public static function load(): void {
        if (!self::$loaded) {
            self::$loaded = true;

            // Create a simple engine
            Mustache_Autoloader::register();
            self::$engine = new Mustache_Engine();
            
            // Create a loader engine
            $path = Admin::getPath(Admin::PublicDir);
            if (File::exists($path)) {
                $config  = [ "extension" => ".html" ];
                $loaders = [];
                
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

                self::$loader = new Mustache_Engine($loaders);
            }
        }
    }
    
    
    
    /**
     * Renders the template using any of the engines depending on the first parameter
     * @param string $templateOrPath
     * @param array  $data
     * @return string
     */
    public static function render(string $templateOrPath, array $data): string {
        self::load();
        if (Strings::match($templateOrPath, '/^[a-z\/]*$/')) {
            if (self::$loader != null) {
                return self::$loader->render($templateOrPath, $data);
            }
            return "";
        }
        return self::$engine->render($templateOrPath, $data);
    }
}
