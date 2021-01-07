<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace ion\WordPress;

/**
 * Description of WordPressHelper
 *
 * @author Justus Meyer
 */
use \Exception as Throwable;
use WP_Post;
use WP_Term;
use WP_User;
use ion\WordPress\IWordPressHelper;
use ion\WordPress\Helper\IHelperContext;
use ion\WordPress\Helper\HelperContext;
use ion\WordPress\Helper\Tools;
use ion\WordPress\Helper\Constants;
use ion\PhpHelper as PHP;
use ion\Package;
use ion\ISemVer;
use ion\SemVer;
use ion\WordPress\Helper\Api\Wrappers\OptionMetaType;
use ion\WordPress\Helper\WordPressHelperException;

final class WordPressHelper implements IWordPressHelper
{
    use \ion\WordPress\Helper\Wrappers\TActions;
    use \ion\WordPress\Helper\Wrappers\TAdmin;
    use \ion\WordPress\Helper\Wrappers\TCommon;
    use \ion\WordPress\Helper\Wrappers\TCron;
    use \ion\WordPress\Helper\Wrappers\TDatabase;
    use \ion\WordPress\Helper\Wrappers\TFilters;
    use \ion\WordPress\Helper\Wrappers\TTemplate;
    use \ion\WordPress\Helper\Wrappers\TLogging;
    use \ion\WordPress\Helper\Wrappers\TOptions;
    use \ion\WordPress\Helper\Wrappers\TPaths;
    use \ion\WordPress\Helper\Wrappers\TPosts;
    use \ion\WordPress\Helper\Wrappers\TRewrites;
    use \ion\WordPress\Helper\Wrappers\TShortCodes;
    use \ion\WordPress\Helper\Wrappers\TTaxonomies;
    use \ion\WordPress\Helper\Wrappers\TWidgets;
    //    private static $currentContextCycle = Constants::CONTEXT_PLUGIN;
    private static $helperInitialized = false;
    private static $helperFinalized = false;
    private static $settings = [];
    private static $contexts = [];
    private static $wrapperActions = [];
    private static $tools = null;
    /**
     * method
     * 
     * 
     * @return void
     */
    
    private static function registerWrapperAction($actionName, callable $init, $priority = 0, $returnFirstResult = false)
    {
        if (!array_key_exists($actionName, static::$wrapperActions)) {
            static::$wrapperActions[$actionName] = [];
        }
        static::$wrapperActions[$actionName][] = ['priority' => $priority, 'callable' => $init, 'returnFirstResult' => $returnFirstResult];
        return;
    }
    
    /**
     * method
     * 
     * @return void
     */
    
    private static function invokeWrapperActions()
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
    
    /**
     * method
     * 
     * @return mixed
     */
    
    private static function getContentDir()
    {
        return static::getContentDirectory();
    }
    
    /**
     * method
     * 
     * @return array
     */
    
    public static function &getContexts()
    {
        return static::$contexts;
    }
    
    /**
     * method
     * 
     * @return mixed
     */
    
    public static function getContentDirectory()
    {
        $tmp = explode(DIRECTORY_SEPARATOR, trim(static::getContentPath(), DIRECTORY_SEPARATOR));
        return array_pop($tmp);
    }
    
    /**
     * method
     * 
     * @return mixed
     */
    
    private static function isHelperDebugMode()
    {
        if (defined('WP_HELPER_DEBUG') && WP_HELPER_DEBUG === true && WP_DEBUG === true) {
            return true;
        }
        return false;
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    protected static function debugLog($message)
    {
        // /* string */ $slug = null, /* string */ $level = null, /* string */ $message = null,
        if (static::isHelperDebugMode()) {
            static::log(Constants::WP_HELPER_DEBUG_SLUG, 'debug', $message);
        }
    }
    
    /**
     * method
     * 
     * 
     * @return void
     */
    
    private static function initializeHelper(IHelperContext $context, array $wpHelperSettings, $helperDir = null)
    {
        if (static::$helperInitialized) {
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
        //if(defined(Constants::WP_CONFIG_DEBUG) && constant(Constants::WP_CONFIG_DEBUG) === true && WP::getOption(Constants::QUICK_404_OPTION, false) === true) {
        static::addAction("template_redirect", function ($template) {
            if (is_404() && !PHP::toBool(PHP::filterInput('wp-devhelper-disable-quick-404', [INPUT_GET], FILTER_DEFAULT))) {
                $wpHelperPath = Constants::HELPER_SITE;
                $wpHelperSettingsPath = static::getAdminUrl('admin', 'wp-devhelper-settings');
                $req = PHP::getServerRequestUri();
                $unblockedUri = $req . (strpos($req, '?') ? '&' : '?') . "wp-devhelper-disable-quick-404=true";
                wp_die("This is a replacement 404 page generated by <a target=\"_blank\" href=\"{$wpHelperPath}\">WP Devhelper</a> <br /><br /> To disable: either set <strong>WP_DEBUG</strong> to <em>false</em> or <a target=\"_blank\" href=\"{$wpHelperSettingsPath}\">go to the settings page</a>. <br /><br /> To see the original template, please <a href=\"{$unblockedUri}\">click here</a>.<br /><br />", "404 Not Found", ['response' => 404, 'exit' => true]);
                return;
            }
            return $template;
        });
        //}
        //            static::addAction('wp_loaded', function() {
        //
        //                static::finalizeHelper($context);
        //            });
        //            static::setCurrentContextCycle(Constants::CONTEXT_PLUGIN);
        //
        //            add_action('plugins_loaded', function() {
        //
        //                die('plugins_loaded');
        //
        //                static::setCurrentContextCycle(Constants::CONTEXT_THEME);
        //            });
        //            add_action('switch_theme', function() {
        //
        //                static::setCurrentContextCycle(Constants::CONTEXT_THEME);
        //            });
        //            add_action('after_setup_theme', function() {
        //
        //                foreach(static::$contexts as $ctx) {
        //
        //                    if(!$ctx->isFinalized()) {
        //
        //                        throw new WordPressHelperException("Context '{$ctx->getProjectName()}' has not been finalized.");
        //                    }
        //
        ////                    echo "{$ctx->getProjectName()}<br />";
        //
        //
        //                    $ctx->invokeFinalizeOperation();
        //
        //                }
        //            }, 0);
        //TODO - template option has been removed
        //        if(!is_admin()) {
        //
        //            // NOTE: The following action is "wp" and not "wp_loaded," since "wp" is the first
        //            // hook where WordPress template tags return their proper values.
        //
        //            add_action("wp", function () use ($context) {
        //
        ////                echo "<pre>{$context->getName()}</pre>";
        //
        //                if($context->hasTemplateOperation()) {
        //
        //                    if(!$context->invokeTemplateOperation()) {
        //
        //                        exit;
        //                    }
        //                }
        //            });
        //        }
        //TODO: The order of these seem significant at the moment - more investigation needed.
        static::initialize_TLogging();
        static::initialize_TDatabase();
        static::initialize_TPaths();
        static::initialize_TCommon();
        static::initialize_TPosts();
        static::initialize_TTaxonomies();
        static::initialize_TCron();
        static::initialize_TOptions();
        static::initialize_TRewrites();
        static::initialize_TWidgets();
        static::initialize_TTemplate();
        static::initialize_TShortCodes();
        static::initialize_TActions();
        static::initialize_TFilters();
        static::initialize_TAdmin();
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
        static::$helperInitialized = true;
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    private static function getContextByIndex($index)
    {
        if (PHP::count(array_values(static::getContexts())) === 0) {
            throw new WordPressHelperException('There are currently no instances of WordPress Helper initialized.');
        }
        if ($index >= PHP::count(array_values(static::getContexts()))) {
            throw new WordPressHelperException("There is no instance at index {$index} - index is out of range.");
        }
        return array_values(static::getContexts())[$index];
    }
    
    /**
     * method
     * 
     * 
     * @return IHelperContext
     */
    
    public static function getContext($slug = null)
    {
        if ($slug === null) {
            return static::getCurrentContext();
        }
        if (array_key_exists($slug, static::getContexts())) {
            return static::getContexts()[$slug];
        }
        throw new WordPressHelperException("Could not find a context named '{$slug}.'");
    }
    
    /**
     * method
     * 
     * @return IHelperContext
     */
    
    public static function getCurrentContext()
    {
        return static::getContextByIndex(count(static::getContexts()) - 1);
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    private static function handleError($errorWord, $message, $code, $file, $line, array $trace)
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
    
    //    protected static function setCurrentContextCycle($cycle) {
    //
    //        static::$currentContextCycle = $cycle;
    //    }
    //
    //    public static function getCurrentContextCycle() {
    //
    //        return static::$currentContextCycle;
    //    }
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    private static function getSettingsValue(array &$array, $key)
    {
        if (array_key_exists($key, $array) === false) {
            return false;
        }
        return $array[$key];
    }
    
    //TODO: Move to version specific files.
    /**
     * method
     * 
     * @return mixed
     */
    
    private static function _getContexts()
    {
        if (static::$contexts === null) {
            return [];
        }
        return static::$contexts;
    }
    
    //    public static function context(): IWordPressHelper {
    //        return static::getCurrentContext();
    //    }
    /**
     * method
     * 
     * @return bool
     */
    
    public static function isHelperInitialized()
    {
        return (bool) static::$helperInitialized;
    }
    
    /**
     * method
     * 
     * @return bool
     */
    
    public static function isHelperFinalized()
    {
        return (bool) static::$helperFinalized;
    }
    
    /**
     * method
     * 
     * 
     * @return string
     */
    
    public static function slugify($s)
    {
        return PHP::strToDashedCase($s);
    }
    
    /**
     * method
     * 
     * @return bool
     */
    
    public static function isDebugMode()
    {
        if (defined("WP_DEBUG")) {
            return (bool) WP_DEBUG === true;
        }
        return false;
    }
    
    /**
     * method
     * 
     * 
     * @return void
     */
    
    public static function panic($errorMessage, $httpCode = null, $title = null)
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
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function hasCapability($capability, $user = null)
    {
        if ($user === null) {
            return current_user_can($capability);
        }
        return user_can($user, $capability);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function hasManageOptionsCapability($user = null)
    {
        return static::hasCapability("manage_options", $user);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function hasEditThemeOptionsCapability($user = null)
    {
        return static::hasCapability("edit_theme_options", $user);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function hasManageNetworkCapability($user = null)
    {
        return static::hasCapability("manage_network", $user);
    }
    
    /**
     * method
     * 
     * @return bool
     */
    
    public static function isLoggedIn()
    {
        return is_user_logged_in();
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    private static function isAssociativeArray($array)
    {
        return PHP::isAssociativeArray($array);
    }
    
    /**
     * method
     * 
     * 
     * @return IWordPressHelper
     */
    
    public static function createContext($vendorName, $projectName, $loadPath, $helperDir = null, array $wpHelperSettings = null, ISemVer $version = null, callable $initialize = null, callable $activate = null, callable $deactivate = null, callable $finalize = null, array $uninstall = null)
    {
        set_exception_handler(function (Throwable $throwable) {
            static::handleError('Exception / Error', $throwable->getMessage(), $throwable->getCode(), $throwable->getFile(), $throwable->getLine(), $throwable->getTrace());
        });
        if ($wpHelperSettings === null) {
            $wpHelperSettings = [];
        }
        $helper = new static($vendorName, $projectName, $loadPath, $helperDir, $wpHelperSettings, $version, $initialize, $activate, $deactivate, $finalize, $uninstall);
        //        $context = new Context($vendorName, $projectName, $loadPath, $version);
        //        $context->setInitializeOperation(function() use ($initialize) {
        //
        //            $initialize();
        //        });
        //
        //        $context->setActivateOperation(function() use ($activate) {
        //
        //            $activate();
        //        });
        //
        //        $context->setDeactivateOperation(function() use ($deactivate) {
        //
        //            $deactivate();
        //        });
        //
        //        $context->setUninstallOperation(function() use ($uninstall) {
        //
        //            $uninstall();
        //        });
        //
        //        $context->setFinalizeOperation(function() use ($finalize) {
        //
        //            $finalize();
        //        });
        static::initializeHelper(static::getContext(null), $wpHelperSettings, $helperDir);
        return $helper;
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    protected function __construct($vendorName, $projectName, $loadPath, $helperDir = null, array $wpHelperSettings = null, ISemVer $version = null, callable $initialize = null, callable $activate = null, callable $deactivate = null, callable $finalize = null, array $uninstall = null)
    {
        $context = new HelperContext($vendorName, $projectName, $loadPath, $helperDir, $wpHelperSettings, $version);
        $this->context = $context;
        $this->context->setInitializeOperation(function () use($initialize, $context) {
            if ($initialize !== null) {
                $initialize($context);
            }
        })->setActivateOperation(function () use($activate, $context) {
            if ($activate !== null) {
                $activate($context);
            }
        })->setDeactivateOperation(function () use($deactivate, $context) {
            if ($deactivate !== null) {
                $deactivate($context);
            }
        })->setUninstallOperation($uninstall)->setFinalizeOperation(function () use($finalize, $context) {
            if ($finalize !== null) {
                $finalize($context);
            }
        });
    }
    
    /**
     * method
     * 
     * 
     * @return IWordPressHelper
     */
    
    public function initialize(callable $call = null)
    {
        $this->getCurrentContext()->setInitializeOperation($call);
        return $this;
    }
    
    /**
     * method
     * 
     * 
     * @return IWordPressHelper
     */
    
    public function activate(callable $call = null)
    {
        $this->getCurrentContext()->setActivateOperation($call);
        return $this;
    }
    
    /**
     * method
     * 
     * 
     * @return IWordPressHelper
     */
    
    public function deactivate(callable $call = null)
    {
        $this->getCurrentContext()->setDeactivateOperation($call);
        return $this;
    }
    
    /**
     * method
     * 
     * 
     * @return IWordPressHelper
     */
    
    public function uninstall(array $call = null)
    {
        $this->getCurrentContext()->setUninstallOperation($call);
        return $this;
    }
    
    /**
     * method
     * 
     * 
     * @return IWordPressHelper
     */
    
    public function finalize(callable $call = null)
    {
        $this->getCurrentContext()->setFinalizeOperation($call);
        $this->getCurrentContext()->invokeFinalizeOperation();
        return $this;
    }

}