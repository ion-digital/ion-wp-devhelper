<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace ion\WordPress\Helper;

/**
 * Description of Context
 *
 * @author Justus
 */
use ion\WordPress\WordPressHelper as WP;
use ion\WordPress\WordPressHelperInterface;
use ion\PhpHelper as PHP;
use ion\SemVerInterface;
use ion\Package;
use ion\SemVer;
final class HelperContext implements HelperContextInterface
{
    //    use \ion\ObserverTrait;
    const OPTION_ACTIVATION_TIMESTAMP = 'activation-timestamp';
    const OPTION_ACTIVATION_VERSION = 'activation-version';
    /**
     * method
     * 
     * @return void
     */
    public static function uninstall()
    {
        // empty for now!
    }
    private $extensions = [];
    private $initialized = false;
    private $finalized = false;
    private $workingDir = null;
    private $workingUri = null;
    private $loadPath = null;
    private $contextId = null;
    private $contextType = null;
    private $primary = false;
    private $log = null;
    private $version = null;
    private $activationTimeStamp = null;
    private $activationVersion = null;
    private $children = null;
    private $parent = null;
    private $initialize = null;
    private $finalize = null;
    private $activate = null;
    private $deactivate = null;
    private $uninstall = null;
    /**
     * method
     * 
     * 
     * @return mixed
     */
    public final function __construct($vendorName, $projectName, $loadPath, SemVerInterface $version = null, HelperContextInterface $parent = null)
    {
        $this->setParent($parent);
        $this->setInitializeOperation(function () {
            /* empty for now! */
        });
        $this->setFinalizeOperation(function () {
            /* empty for now! */
        });
        $this->setActivateOperation(function () {
            /* empty for now! */
        });
        $this->setDeactivateOperation(function () {
            /* empty for now! */
        });
        $this->setUninstallOperation(null);
        $workingUri = null;
        $this->children = [];
        $loadPath = realpath($loadPath);
        if (!is_file($loadPath)) {
            throw new WordPressHelperException("Please specify the entry-point load path filename for this context - __FILE__ should work. It must be either a full path or at least an existing filename (I was looking for '{$loadPath}').");
        }
        $workingDir = $loadPath;
        if (PHP::strEndsWith($loadPath, '.php')) {
            $workingDir = dirname($loadPath);
        }
        $workingDir = DIRECTORY_SEPARATOR . trim($workingDir, '/\\') . DIRECTORY_SEPARATOR;
        if (strpos($workingDir, DIRECTORY_SEPARATOR . WP::getContentDirectory())) {
            $workingUri = get_site_url() . substr($workingDir, strpos($workingDir, DIRECTORY_SEPARATOR . WP::getContentDirectory()));
        } else {
            throw new WordPressHelperException('Could not determine working URI.');
        }
        $this->loadPath = $loadPath;
        $this->workingDir = $workingDir;
        $this->workingUri = $workingUri;
        $this->contextId = PHP::count(array_values(WP::getContexts())) + 1;
        $this->primary = (bool) (!defined(Constants::WP_HELPER));
        if (!defined(Constants::WP_HELPER)) {
            define(Constants::WP_HELPER, Package::getInstance('ion', 'wp-devhelper')->getVersion()->toString());
        }
        $tmp = array_values(array_filter(explode(DIRECTORY_SEPARATOR, $this->getWorkingDirectory())));
        $this->contextType = strpos($workingDir, DIRECTORY_SEPARATOR . 'themes') ? Constants::CONTEXT_THEME : Constants::CONTEXT_PLUGIN;
        $this->contextVendorName = PHP::strToDashedCase($vendorName);
        $this->contextProjectName = PHP::strToDashedCase($projectName);
        if (!array_key_exists($this->getPackageName(), WP::getContexts())) {
            //$tmp = WP::getContexts()[$this->getPackageName()]->getLoadPath();
            //throw new WordPressHelperException("Context '{$this->getPackageName()}' has already been defined in '{$tmp}' - context package names need to be unique.");
            // This context has already been loaded, so do nothing!
            //return;
        }
        WP::getContexts()[] = $this;
        $aliases = ['wp-devhelper' => 'WP Devhelper'];
        if ($this->contextProjectName !== null) {
            $aliases[$this->getPackageName()] = $this->contextProjectName;
        }
        $this->log = WP::registerLog($this->getPackageName(), count($aliases) === 0 ? $this->getPackageName() : (array_key_exists($this->getPackageName(), $aliases) === true ? $aliases[$this->getPackageName()] : $this->getPackageName()));
        if ($version === null) {
            // Try to load the version
            if (Package::hasInstance($this->getVendorName(), $this->getProjectName())) {
                $version = Package::getInstance($this->getVendorName(), $this->getProjectName())->getVersion();
            } else {
                if (file_exists($loadPath) && $this->contextType === Constants::CONTEXT_PLUGIN) {
                    $entryFile = file_get_contents($loadPath);
                    $matches = [];
                    if (preg_match('/Version\\s*:\\s*([0-9]+\\.[0-9]+\\.[0-9]+)\\S*/i', $entryFile, $matches) === 1) {
                        $version = SemVer::parse($matches[1]);
                    }
                }
            }
        }
        $this->version = $version;
        //        add_action('admin_init', function() {
        if (WP::isAdmin()) {
            if ($this->getUninstallOperation() instanceof \Closure) {
                throw new WordPressHelperException("The uninstall hook for context '{$this->getProjectName()}' cannot be a Closure - it must be unspecified (NULL), a function or a static method.");
            }
            //            var_dump($this->contextType);
            //var_dump($this->getType());
            //var_dump(Constants::CONTEXT_PLUGIN);
            //die("X");
            //throw new \Exception($this->loadPath);
            if ($this->getType() === Constants::CONTEXT_PLUGIN) {
                register_activation_hook($this->loadPath, function () {
                    $this->invokeActivateOperation();
                });
                register_deactivation_hook($this->loadPath, function () {
                    $this->invokeDeactivateOperation();
                });
                if ($this->hasUninstallOperation()) {
                    register_uninstall_hook($this->loadPath, $this->getUninstallOperation());
                }
            } else {
                if ($this->getType() === Constants::CONTEXT_THEME) {
                    add_action("after_switch_theme", function () {
                        $this->invokeActivateOperation();
                    });
                    add_action("switch_theme", function () {
                        $this->invokeDeactivateOperation();
                    });
                }
            }
        }
        return;
    }
    /**
     * method
     * 
     * 
     * @return HelperContextInterface
     */
    public function setParent(HelperContextInterface $context = null)
    {
        $this->parent = $context;
        return $this;
    }
    /**
     * method
     * 
     * @return ?HelperContextInterface
     */
    public function getParent()
    {
        return $this->parent;
    }
    /**
     * method
     * 
     * @return bool
     */
    public function hasParent()
    {
        return $this->getParent() !== null;
    }
    /**
     * method
     * 
     * @return array
     */
    public function getChildren()
    {
        return array_values($this->children);
    }
    /**
     * method
     * 
     * @return bool
     */
    public function hasChildren()
    {
        return PHP::count($this->getChildren()) > 0;
    }
    /**
     * method
     * 
     * 
     * @return void
     */
    public function addChild(HelperContextInterface $child)
    {
        $key = $child->getPackageName();
        if (array_key_exists($key, $this->children)) {
            //            throw new \Exception("WHOOP");
            return;
        }
        $this->children[$key] = $child;
        $child->setParent($this);
        return;
    }
    /**
     * method
     * 
     * @return WordPressHelperLogInterface
     */
    public function getLog()
    {
        return $this->log;
    }
    /**
     * method
     * 
     * @return bool
     */
    public function isInitialized()
    {
        return $this->initialized;
    }
    /**
     * method
     * 
     * @return bool
     */
    public function isFinalized()
    {
        return $this->finalized;
    }
    /**
     * method
     * 
     * @return int
     */
    public function getId()
    {
        return $this->contextId;
    }
    /**
     * method
     * 
     * @return string
     */
    public function getPackageName()
    {
        return $this->getVendorName() . '/' . PHP::strToDashedCase($this->getProjectName());
    }
    /**
     * method
     * 
     * @return string
     */
    public function getVendorName()
    {
        return $this->contextVendorName;
    }
    /**
     * method
     * 
     * @return string
     */
    public function getProjectName()
    {
        return $this->contextProjectName;
    }
    /**
     * method
     * 
     * @return bool
     */
    public function isPrimary()
    {
        return (bool) $this->primary;
    }
    /**
     * method
     * 
     * @return string
     */
    public function getWorkingUri()
    {
        return $this->workingUri;
    }
    /**
     * method
     * 
     * @return string
     */
    public function getWorkingDirectory()
    {
        return $this->workingDir;
    }
    /**
     * method
     * 
     * @return string
     */
    public function getLoadPath()
    {
        return $this->loadPath;
    }
    /**
     * method
     * 
     * @return ?callable
     */
    public function getInitializeOperation()
    {
        return $this->initialize;
    }
    /**
     * method
     * 
     * @return ?callable
     */
    public function getFinalizeOperation()
    {
        return $this->finalize;
    }
    /**
     * method
     * 
     * @return ?callable
     */
    public function getActivateOperation()
    {
        return $this->activate;
    }
    /**
     * method
     * 
     * @return ?callable
     */
    public function getDeactivateOperation()
    {
        return $this->deactivate;
    }
    /**
     * method
     * 
     * @return ?array
     */
    public function getUninstallOperation()
    {
        return $this->uninstall;
    }
    /**
     * method
     * 
     * 
     * @return ?HelperContextInterface
     */
    public function setInitializeOperation(callable $operation = null)
    {
        $this->initialize = $operation;
        return $this;
    }
    /**
     * method
     * 
     * 
     * @return ?HelperContextInterface
     */
    public function setFinalizeOperation(callable $operation = null)
    {
        $this->finalize = $operation;
        return $this;
    }
    /**
     * method
     * 
     * 
     * @return HelperContextInterface
     */
    public function setActivateOperation(callable $operation = null)
    {
        $this->activate = $operation;
        return $this;
    }
    /**
     * method
     * 
     * 
     * @return HelperContextInterface
     */
    public function setDeactivateOperation(callable $operation = null)
    {
        $this->deactivate = $operation;
        return $this;
    }
    /**
     * method
     * 
     * 
     * @return HelperContextInterface
     */
    public function setUninstallOperation(array $operation = null)
    {
        $this->uninstall = $operation;
        return $this;
    }
    /**
     * method
     * 
     * @return bool
     */
    public function hasInitializeOperation()
    {
        return $this->getInitializeOperation() !== null;
    }
    /**
     * method
     * 
     * @return bool
     */
    public function hasFinalizeOperation()
    {
        return $this->getFinalizeOperation() !== null;
    }
    /**
     * method
     * 
     * @return bool
     */
    public function hasActivateOperation()
    {
        return $this->getActivateOperation() !== null;
    }
    /**
     * method
     * 
     * @return bool
     */
    public function hasDeactivateOperation()
    {
        return $this->getDeactivateOperation() !== null;
    }
    /**
     * method
     * 
     * @return bool
     */
    public function hasUninstallOperation()
    {
        return $this->getUninstallOperation() !== null;
    }
    /**
     * method
     * 
     * @return void
     */
    public function invokeInitializeOperation()
    {
        if ($this->isInitialized()) {
            //throw new WordPressHelperException("Context '{$this->getProjectName()}' has already been initialized.");
            return;
        }
        if ($this->hasInitializeOperation()) {
            $call = $this->getInitializeOperation();
            if ($call !== null) {
                $call($this);
            }
        }
        foreach (array_values($this->getChildren()) as $childContext) {
            $childContext->invokeInitializeOperation();
        }
        $this->initialized = true;
        return;
    }
    /**
     * method
     * 
     * @return void
     */
    public function invokeFinalizeOperation()
    {
        if ($this->isFinalized()) {
            //throw new WordPressHelperException("Context '{$this->getProjectName()}' has already been initialized.");
            return;
        }
        foreach (array_values($this->getChildren()) as $childContext) {
            $childContext->invokeFinalizeOperation();
        }
        if ($this->hasFinalizeOperation()) {
            $call = $this->getFinalizeOperation();
            if ($call !== null) {
                $call($this);
            }
        }
        $this->finalized = true;
        return;
    }
    /**
     * method
     * 
     * @return void
     */
    public function invokeActivateOperation()
    {
        foreach (array_values($this->getChildren()) as $childContext) {
            $childContext->invokeActivateOperation();
        }
        if ($this->getActivationVersion() === null) {
            $this->activationVersion = $this->getVersion();
            WP::setSiteOption("{$this->getPackageName()}:" . self::OPTION_ACTIVATION_VERSION, $this->getActivationVersion() !== null ? $this->getActivationVersion()->toString() : null);
        }
        if ($this->getActivationTimeStamp() === null) {
            $this->activationTimeStamp = PHP::toInt(current_time('timestamp', 1));
            WP::setSiteOption("{$this->getPackageName()}:" . self::OPTION_ACTIVATION_TIMESTAMP, $this->activationTimeStamp);
        }
        if ($this->hasActivateOperation() === false) {
            //            throw new WordPressHelperException('No activate operation to invoke.');
            return;
        }
        $call = $this->getActivateOperation();
        if ($call !== null) {
            $call($this);
        }
        return;
    }
    /**
     * method
     * 
     * @return void
     */
    public function invokeDeactivateOperation()
    {
        foreach (array_values($this->getChildren()) as $childContext) {
            $childContext->invokeDeactivateOperation();
        }
        if (WP::hasSiteOption("{$this->getPackageName()}:" . self::OPTION_ACTIVATION_VERSION)) {
            if (!WP::removeSiteOption("{$this->getPackageName()}:" . self::OPTION_ACTIVATION_VERSION)) {
                throw new WordPressHelperException("{$this->getPackageName()}:" . self::OPTION_ACTIVATION_VERSION . " could not be removed.");
            }
        }
        if (WP::hasSiteOption("{$this->getPackageName()}:" . self::OPTION_ACTIVATION_TIMESTAMP)) {
            if (!WP::removeSiteOption("{$this->getPackageName()}:" . self::OPTION_ACTIVATION_TIMESTAMP)) {
                throw new WordPressHelperException("{$this->getPackageName()}:" . self::OPTION_ACTIVATION_TIMESTAMP . " could not be removed.");
            }
        }
        if ($this->hasDeactivateOperation() === false) {
            //            throw new WordPressHelperException('No deactivate operation to invoke.');
            return;
        }
        $call = $this->getDeactivateOperation();
        if ($call !== null) {
            $call($this);
        }
    }
    /**
     * method
     * 
     * @return void
     */
    public function invokeUninstallOperation()
    {
        foreach (array_values($this->getChildren()) as $childContext) {
            $childContext->invokeUninstallOperation();
        }
        if ($this->hasUninstallOperation() === false) {
            //            throw new WordPressHelperException('No uninstall operation to invoke.');
            return;
        }
        $call = $this->getUninstallOperation();
        if ($call !== null) {
            call_user_func($call);
        }
    }
    /**
     * method
     * 
     * @return int
     */
    public function getType()
    {
        return (int) $this->contextType;
    }
    /**
     * method
     * 
     * @return ?int
     */
    public function getActivationTimeStamp()
    {
        if ($this->activationTimeStamp !== null) {
            return $this->activationTimeStamp;
        }
        $this->activationTimeStamp = PHP::toInt(WP::getSiteOption("{$this->getPackageName()}:" . self::OPTION_ACTIVATION_TIMESTAMP, PHP::toInt(current_time('timestamp', 1))));
        return $this->activationTimeStamp;
    }
    /**
     * method
     * 
     * @return ?SemVerInterface
     */
    public function getVersion()
    {
        return $this->version;
    }
    /**
     * method
     * 
     * @return ?SemVerInterface
     */
    public function getActivationVersion()
    {
        if ($this->activationVersion !== null) {
            return $this->activationVersion;
        }
        $tmp = WP::getSiteOption("{$this->getPackageName()}:" . self::OPTION_ACTIVATION_VERSION, null);
        if ($tmp === null) {
            return null;
        }
        $this->activationVersion = SemVer::parse($tmp);
        return $this->activationVersion;
    }
    /**
     * method
     * 
     * 
     * @return array
     */
    public function getTemplates($flat = true, $themeOnly = false, $labels = false, $nullItem = null, $relativePath = null)
    {
        $wpTemplates = get_page_templates();
        $templates = null;
        if ($nullItem !== null) {
            $templates[$nullItem] = null;
        }
        if ($flat == true) {
            $templates = $wpTemplates;
        } else {
            if (count($wpTemplates) > 0) {
                if ($labels === true) {
                    $templates[wp_get_theme()->Name] = $wpTemplates;
                } else {
                    $templates['theme'] = $wpTemplates;
                }
            } else {
                $templates = [];
            }
        }
        if ($themeOnly) {
            return $templates;
        }
        $customTemplates = [];
        $relativePath = $relativePath === null ? 'templates' : trim($relativePath, '/\\');
        $workingPath = $this->getWorkingDirectory() . $relativePath;
        if (is_dir($workingPath) === true) {
            $files = array_values(array_diff(scandir($workingPath), array('.', '..')));
            foreach ($files as $file) {
                if (is_dir($this->getWorkingDirectory() . "/{$relativePath}/" . $file)) {
                    continue;
                }
                $matches = [];
                $name = $file;
                if (preg_match('|Template Name:(.*)$|mi', file_get_contents($this->getWorkingDirectory() . "/{$relativePath}/" . $file), $matches)) {
                    $name = trim($matches[1]);
                }
                if (array_key_exists($name, $wpTemplates) === false) {
                    $customTemplates[$name] = $file;
                }
            }
        }
        if (PHP::isArray($customTemplates) && count($customTemplates) > 0) {
            if ($flat == true) {
                $templates = array_merge($templates, $customTemplates);
            } else {
                if ($labels === true) {
                    $templates['Other'] = $customTemplates;
                } else {
                    $templates['other'] = $customTemplates;
                }
            }
        }
        return $templates;
    }
    /**
     * method
     * 
     * 
     * @return bool
     */
    public function templateExists($name)
    {
        $result = locate_template($name);
        if ($result === '') {
            return false;
        }
        return true;
    }
    /**
     * method
     * 
     * 
     * @return void
     */
    public final function extend($name, callable $extension)
    {
        $this->extensions[strtolower($name)] = $extension;
        return;
    }
    /**
     * method
     * 
     * 
     * @return mixed
     */
    public final function __call($name, array $arguments)
    {
        if (array_key_exists(strtolower($name), $this->extensions)) {
            return call_user_func_array($this->extensions[strtolower($name)], $arguments);
        }
        if (method_exists($this, $name)) {
            $r = new ReflectionMethod(static::class, $name);
            if (!$r->isPublic()) {
                throw new WordPressHelperException("A non-public '{$name}' context method cannot be called.");
            }
            return call_user_func_array([$this, $name], $arguments);
        }
        throw new WordPressHelperException("Unknown context extension method called ('{$name}').");
    }
}