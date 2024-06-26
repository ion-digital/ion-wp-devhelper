<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace Ion\WordPress;

/**
 * Description of WordPressHelper
 *
 * @author Justus Meyer
 */
use Throwable;
use WP_Post;
use WP_Term;
use WP_User;
use Ion\WordPress\WordPressHelperInterface;
use Ion\WordPress\Helper\HelperContextInterface;
use Ion\WordPress\Helper\HelperContext;
use Ion\WordPress\Helper\Tools;
use Ion\WordPress\Helper\Constants;
use Ion\PhpHelper as PHP;
use Ion\Package;
use Ion\SemVerInterface;
use Ion\SemVer;
use Ion\WordPress\Helper\WordPressHelperException;
use ReflectionMethod;
final class WordPressHelper implements WordPressHelperInterface
{
    private const WORDPRESS_HTACCESS_START = "# BEGIN WordPress";
    private const WORDPRESS_HTACCESS_END = "# END WordPress";
    private const INITIALIZE_PRIORITY = 2;
    private const WRAPPER_PRIORITY = 100;
    use \Ion\WordPress\Helper\ActionsTrait, \Ion\WordPress\Helper\AdminTrait, \Ion\WordPress\Helper\CommonTrait, \Ion\WordPress\Helper\CronTrait, \Ion\WordPress\Helper\DatabaseTrait, \Ion\WordPress\Helper\FiltersTrait, \Ion\WordPress\Helper\TemplateTrait, \Ion\WordPress\Helper\LoggingTrait, \Ion\WordPress\Helper\OptionsTrait, \Ion\WordPress\Helper\PathsTrait, \Ion\WordPress\Helper\PostsTrait, \Ion\WordPress\Helper\RewritesTrait, \Ion\WordPress\Helper\ShortCodesTrait, \Ion\WordPress\Helper\TaxonomiesTrait, \Ion\WordPress\Helper\WidgetsTrait {
        \Ion\WordPress\Helper\ActionsTrait::initialize as initializeActions;
        \Ion\WordPress\Helper\AdminTrait::initialize as initializeAdmin;
        \Ion\WordPress\Helper\CommonTrait::initialize as initializeCommon;
        \Ion\WordPress\Helper\CronTrait::initialize as initializeCron;
        \Ion\WordPress\Helper\DatabaseTrait::initialize as initializeDatabase;
        \Ion\WordPress\Helper\FiltersTrait::initialize as initializeFilters;
        \Ion\WordPress\Helper\TemplateTrait::initialize as initializeTemplate;
        \Ion\WordPress\Helper\LoggingTrait::initialize as initializeLogging;
        \Ion\WordPress\Helper\OptionsTrait::initialize as initializeOptions;
        \Ion\WordPress\Helper\PathsTrait::initialize as initializePaths;
        \Ion\WordPress\Helper\PostsTrait::initialize as initializePosts;
        \Ion\WordPress\Helper\RewritesTrait::initialize as initializeRewrites;
        \Ion\WordPress\Helper\ShortCodesTrait::initialize as initializeShortCodes;
        \Ion\WordPress\Helper\TaxonomiesTrait::initialize as initializeTaxonomies;
        \Ion\WordPress\Helper\WidgetsTrait::initialize as initializeWidgets;
    }
    private static $helperConstructed = false;
    private static $helperInitialized = false;
    private static $settings = [];
    private static $contexts = [];
    private static $wrapperActions = [];
    private static $extensions = [];
    //    private static $overrides = [];
    private static $tools = null;
    private static function registerWrapperAction(string $actionName, callable $init, int $priority = self::WRAPPER_PRIORITY, bool $returnFirstResult = false) : void
    {
        if (!array_key_exists($actionName, static::$wrapperActions)) {
            static::$wrapperActions[$actionName] = [];
        }
        static::$wrapperActions[$actionName][] = ['priority' => $priority, 'callable' => $init, 'returnFirstResult' => $returnFirstResult];
        return;
    }
    private static function invokeWrapperActions() : void
    {
        foreach (static::$wrapperActions as $actionName => $actions) {
            add_action($actionName, function (...$param) use($actionName, $actions) {
                $lastResult = null;
                foreach ($actions as $action) {
                    $result = call_user_func_array($action['callable'], $param);
                    if ($action['returnFirstResult'] === true) {
                        return $result;
                    }
                    $lastResult = $result;
                }
                return $lastResult;
            });
        }
        return;
    }
    private static function getContentDir()
    {
        return static::getContentDirectory();
    }
    public static function &getContexts() : array
    {
        return static::$contexts;
    }
    public static function getContentDirectory()
    {
        $tmp = explode(DIRECTORY_SEPARATOR, trim(static::getContentPath(), DIRECTORY_SEPARATOR));
        return array_pop($tmp);
    }
    private static function isHelperDebugMode()
    {
        if (defined('WP_HELPER_DEBUG') && WP_HELPER_DEBUG === true && WP_DEBUG === true) {
            return true;
        }
        return false;
    }
    protected static function debugLog(
        /* string */
        $message
    )
    {
        // /* string */ $slug = null, /* string */ $level = null, /* string */ $message = null,
        if (static::isHelperDebugMode()) {
            static::log(Constants::WP_HELPER_DEBUG_SLUG, 'debug', $message);
        }
    }
    private static function initializeHelper(HelperContextInterface $context, array $wpHelperSettings, string $helperDir = null) : void
    {
        if (static::$helperConstructed) {
            return;
        }
        static::$helperUri = null;
        static::$helperDir = null;
        if (static::$settings === []) {
            static::$settings = $wpHelperSettings;
        }
        $helperDirs = [];
        if ($helperDir === null) {
            $helperDirs = ['..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..', '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..', '..' . DIRECTORY_SEPARATOR . '..', '..', '.', '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'wp-devhelper', '..' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'wp-devhelper', '..' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'wp-devhelper', '..' . DIRECTORY_SEPARATOR . 'includes', 'vendor' . DIRECTORY_SEPARATOR . 'wp-devhelper', 'includes' . DIRECTORY_SEPARATOR . 'wp-devhelper', 'include' . DIRECTORY_SEPARATOR . 'wp-devhelper', 'includes', 'include'];
            foreach ($helperDirs as &$helperDir) {
                $helperDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . $helperDir . DIRECTORY_SEPARATOR);
                if (!empty($helperDir)) {
                    $helperDir .= DIRECTORY_SEPARATOR;
                }
            }
        } else {
            $helperDirs[] = DIRECTORY_SEPARATOR . trim($helperDir, '/\\') . DIRECTORY_SEPARATOR;
        }
        foreach ($helperDirs as $dir) {
            $path = $dir . DIRECTORY_SEPARATOR . 'composer.json';
            if (!empty($path) && file_exists($path)) {
                $composerJson = json_decode(file_get_contents($path));
                if ($composerJson->name === 'ion/wp-devhelper') {
                    static::$helperDir = $dir;
                    break;
                }
            }
        }
        if (static::$helperDir === null) {
            throw new WordPressHelperException('Could not determine helper directory (I looked in: ' . "\n\n" . join("\n", $helperDirs) . "\n\n" . ')');
        }
        static::$helperDir = realpath(static::$helperDir);
        static::$helperDir = DIRECTORY_SEPARATOR . trim(static::$helperDir, '/\\') . DIRECTORY_SEPARATOR;
        if (strpos(static::$helperDir, DIRECTORY_SEPARATOR . static::getContentDirectory())) {
            static::$helperUri = get_site_url() . substr(static::$helperDir, strpos(static::$helperDir, DIRECTORY_SEPARATOR . static::getContentDirectory()));
        }
        if (static::$helperUri === null) {
            throw new WordPressHelperException('Could not determine helper URI.');
        }
        if (static::isAdmin()) {
            static::addSystemAdminMenuPage('index.php');
            static::addSystemAdminMenuPage('edit.php');
            static::addSystemAdminMenuPage('upload.php');
            static::addSystemAdminMenuPage('edit-comments.php');
            static::addSystemAdminMenuPage('themes.php');
            static::addSystemAdminMenuPage('plugins.php');
            static::addSystemAdminMenuPage('edit.php?post_type=page');
            static::addSystemAdminMenuPage('users.php');
            static::addSystemAdminMenuPage('tools.php');
            static::addSystemAdminMenuPage('options-general.php');
            static::addSystemAdminMenuPage('settings.php');
            if (!Tools::isDisabled() && static::getSettingsValue($wpHelperSettings, 'no-tools') === false) {
                static::$tools = new Tools($context, $wpHelperSettings);
            } else {
                Tools::addEnableMenuItem();
            }
        }
        static::addAction("template_redirect", function ($template) {
            if (!is_404()) {
                return $template;
            }
            if (!Tools::isQuick404Enabled()) {
                return $template;
            }
            $wpHelperPath = Constants::HELPER_SITE;
            $wpHelperSettingsPath = static::getAdminUrl('admin', 'wp-devhelper-settings');
            $req = PHP::getServerRequestUri();
            $unblockedUri = $req . (strpos($req, '?') ? '&' : '?') . "wp-devhelper-disable-quick-404=true";
            wp_die("This is a replacement 404 page generated by <a target=\"_blank\" href=\"{$wpHelperPath}\">WP Devhelper</a> <br /><br /> To disable: either set <strong>WP_DEBUG</strong> to <em>false</em> or <a target=\"_blank\" href=\"{$wpHelperSettingsPath}\">go to the settings page</a>. <br /><br /> To see the original template, please <a href=\"{$unblockedUri}\">click here</a>.<br /><br />", "404 Not Found", ['response' => 404, 'exit' => true]);
            return null;
        });
        static::initializeLogging();
        static::initializeDatabase();
        static::initializePaths();
        static::initializeCommon();
        static::initializePosts();
        static::initializeTaxonomies();
        static::initializeCron();
        static::initializeOptions();
        static::initializeRewrites();
        static::initializeWidgets();
        static::initializeTemplate();
        static::initializeShortCodes();
        static::initializeActions();
        static::initializeFilters();
        static::initializeAdmin();
        static::invokeWrapperActions();
        if (static::getSettingsValue(static::$settings, 'html-auto-paragraphs') === false) {
            add_filter("tiny_mce_before_init", function ($settings) {
                //            // Don't remove line breaks
                //            $settings['remove_linebreaks'] = false;
                //            // Convert newline characters to BR tags
                //            $settings['convert_newlines_to_brs'] = true;
                //            // Do not remove redundant BR tags
                //            $settings['remove_redundant_brs'] = false;
                $settings["extended_valid_elements"] = "*[*]";
                return $settings;
            });
        }
        static::$helperConstructed = true;
        add_action('init', function () {
            if (!session_id()) {
                session_start();
            }
            // First, do all initializations
            foreach (static::getContexts() as $helperContext) {
                if ($helperContext->hasParent()) {
                    continue;
                }
                $helperContext->invokeInitializeOperation();
            }
            // Then, do all finalizations
            foreach (static::getContexts() as $helperContext) {
                if ($helperContext->hasParent()) {
                    continue;
                }
                $helperContext->invokeFinalizeOperation();
            }
        }, self::INITIALIZE_PRIORITY);
    }
    private static function getContextByIndex(int $index)
    {
        if (PHP::count(array_values(static::getContexts())) === 0) {
            throw new WordPressHelperException('There are currently no instances of WordPress DevHelper initialized.');
        }
        if ($index >= PHP::count(array_values(static::getContexts()))) {
            throw new WordPressHelperException("There is no instance at index {$index} - index is out of range.");
        }
        return array_values(static::getContexts())[$index];
    }
    public static function getContext(string $slug = null) : HelperContextInterface
    {
        if ($slug === null) {
            return static::getCurrentContext();
        }
        foreach (array_values(static::getContexts()) as $context) {
            if ($context->getPackageName() !== $slug) {
                continue;
            }
            return $context;
        }
        //        if(array_key_exists($slug, static::getContexts())) {
        //
        //            return static::getContexts()[$slug];
        //        }
        throw new WordPressHelperException("Could not find a context named '{$slug}.'");
    }
    public static function getCurrentContext() : HelperContextInterface
    {
        return static::getContextByIndex(count(static::getContexts()) - 1);
    }
    private static function handleError(string $errorWord, string $message, int $code, string $file, int $line, array $trace)
    {
        $title = "";
        $traceOutput = "";
        $i = 1;
        foreach ($trace as $traceItem) {
            $traceItemFile = array_key_exists("file", $traceItem) === true ? $traceItem["file"] : "";
            $traceItemLine = array_key_exists("line", $traceItem) === true ? $traceItem["line"] : "";
            $traceItemClass = array_key_exists("class", $traceItem) === true ? "<em>" . $traceItem["class"] . "</em> :: " : "";
            $traceItemFunction = array_key_exists("function", $traceItem) === true ? $traceItem["function"] : "";
            $traceItemFunctionArguments = "";
            //implode(", ", $traceItem["args"]);
            //$trace .= "<tr><td>$i</td><td>$traceItemFile</td><td>$traceItemLine</td><td>$traceItemFunction</td><td>$traceItemFunctionArguments</td></tr>";
            $traceOutput .= "<li>{$traceItemClass}<b>{$traceItemFunction}</b> (line <b>{$traceItemLine}</b>):<p><em>{$traceItemFile}</em></p><p>{$traceItemFunctionArguments}</p></li>";
            $i++;
        }
        $template = null;
        $title = null;
        if (static::isDebugMode() === true) {
            $title = "Uncaught PHP {$errorWord} (code {$code})";
            $template = <<<TEMPLATE
<h1>{$title}</h1>
         
<h2>Message:</h2>
<p>{$message}</p>
<p>Defined in <em>{$file}</em> (line <b>{$line}</b>)</p>
                        
<h2>Stack Trace:</h2>
<ol>
{$traceOutput}
</ol>
                        
TEMPLATE;
        } else {
            $title = "Internal Error";
            $template = <<<TEMPLATE
<h1>{$title}</h1>
<p>An internal error has occurred - the site administrator has been notified.</p>
TEMPLATE;
        }
        static::panic(trim($template), 500, $title);
    }
    private static function getSettingsValue(
        array &$array,
        /* string */
        $key
    )
    {
        if (array_key_exists($key, $array) === false) {
            return false;
        }
        return $array[$key];
    }
    //TODO: Move to version specific files.
    private static function _getContexts()
    {
        if (static::$contexts === null) {
            return [];
        }
        return static::$contexts;
    }
    public static function isHelperInitialized() : bool
    {
        return (bool) static::$helperInitialized;
    }
    public static function slugify(string $s) : string
    {
        return PHP::strToDashedCase($s);
    }
    public static function isDebugMode() : bool
    {
        if (defined("WP_DEBUG")) {
            return (bool) WP_DEBUG === true;
        }
        return false;
    }
    public static function panic(string $errorMessage, int $httpCode = null, string $title = null) : void
    {
        if ($title === null) {
            $title = 'Gremlins in the system!';
        }
        if ($httpCode === null) {
            $httpCode = 500;
        }
        if (function_exists('wp_die') === true) {
            wp_die(trim($errorMessage), $title, ["response" => $httpCode, "back_link" => false, "text_direction" => "ltr"]);
        } else {
            switch ($httpCode) {
                case 403:
                    header('HTTP/1.1 403 Unauthorized');
                    break;
                case 500:
                default:
                    header('HTTP/1.1 500 Internal Server Error');
            }
            echo $errorMessage;
        }
        exit($httpCode);
    }
    public static function hasCapability(string $capability, int $user = null) : bool
    {
        if ($user === null) {
            return current_user_can($capability);
        }
        return user_can($user, $capability);
    }
    public static function hasManageOptionsCapability(int $user = null) : bool
    {
        return static::hasCapability("manage_options", $user);
    }
    public static function hasEditThemeOptionsCapability(int $user = null) : bool
    {
        return static::hasCapability("edit_theme_options", $user);
    }
    public static function hasManageNetworkCapability(int $user = null) : bool
    {
        return static::hasCapability("manage_network", $user);
    }
    public static function isLoggedIn() : bool
    {
        return is_user_logged_in();
    }
    private static function isAssociativeArray($array)
    {
        return PHP::isAssociativeArray($array);
    }
    public static function createContext(string $vendorName, string $projectName, string $loadPath, string $helperDir = null, array $wpHelperSettings = null, SemVerInterface $version = null, callable $initialize = null, callable $finalize = null, callable $activate = null, callable $deactivate = null, array $uninstall = null) : WordPressHelperInterface
    {
        set_exception_handler(function (Throwable $throwable) {
            static::handleError('Exception / Error', $throwable->getMessage(), $throwable->getCode(), $throwable->getFile(), $throwable->getLine(), $throwable->getTrace());
        });
        if ($wpHelperSettings === null) {
            $wpHelperSettings = [];
        }
        $helper = new static($vendorName, $projectName, $loadPath, $helperDir, $wpHelperSettings, $version, $initialize, $finalize, $activate, $deactivate, $uninstall);
        static::initializeHelper(static::getContext(null), $wpHelperSettings, $helperDir);
        return $helper;
    }
    protected function __construct(string $vendorName, string $projectName, string $loadPath, string $helperDir = null, array $wpHelperSettings = null, SemVerInterface $version = null, callable $initialize = null, callable $finalize = null, callable $activate = null, callable $deactivate = null, array $uninstall = null)
    {
        $context = new HelperContext($vendorName, $projectName, $loadPath, $version, null);
        $context->setInitializeOperation(function () use($initialize, $context) {
            if ($initialize !== null) {
                $initialize($context);
            }
        })->setFinalizeOperation(function () use($finalize, $context) {
            if ($finalize !== null) {
                $finalize($context);
            }
        })->setActivateOperation(function () use($activate, $context) {
            if ($activate !== null) {
                $activate($context);
            }
        })->setDeactivateOperation(function () use($deactivate, $context) {
            if ($deactivate !== null) {
                $deactivate($context);
            }
        })->setUninstallOperation($uninstall);
    }
    public function initialize(callable $call = null) : WordPressHelperInterface
    {
        $this->getCurrentContext()->setInitializeOperation($call);
        return $this;
    }
    public function finalize(callable $call = null) : WordPressHelperInterface
    {
        $this->getCurrentContext()->setFinalizeOperation($call);
        return $this;
    }
    public function activate(callable $call = null) : WordPressHelperInterface
    {
        $this->getCurrentContext()->setActivateOperation($call);
        return $this;
    }
    public function deactivate(callable $call = null) : WordPressHelperInterface
    {
        $this->getCurrentContext()->setDeactivateOperation($call);
        return $this;
    }
    public function uninstall(array $call = null) : WordPressHelperInterface
    {
        $this->getCurrentContext()->setUninstallOperation($call);
        return $this;
    }
    public static final function extend(string $name, callable $extension) : void
    {
        static::$extensions[strtolower($name)] = $extension;
        return;
    }
    public static final function __callStatic(string $name, array $arguments)
    {
        if (array_key_exists(strtolower($name), static::$extensions)) {
            return call_user_func_array(static::$extensions[strtolower($name)], $arguments);
        }
        if (method_exists(static::class, $name)) {
            $r = new ReflectionMethod(static::class, $name);
            if (!$r->isPublic()) {
                throw new WordPressHelperException("A non-public '{$name}' method cannot be called.");
            }
            return call_user_func_array([static::class, $name], $arguments);
        }
        throw new WordPressHelperException("Unknown extension method called ('{$name}').");
    }
}