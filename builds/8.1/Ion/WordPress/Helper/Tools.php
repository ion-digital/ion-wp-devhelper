<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace Ion\WordPress\Helper;

/**
 * Description of PanelPlugIn
 *
 * @author Justus
 */
use Ion\WordPress\WordPressHelperInterface;
use Ion\WordPress\WordPressHelper as WP;
use Ion\WordPress\Helper\IHelperContext;
use Ion\WordPress\Helper\Constants;
use Ion\Package;
use Ion\Autoloading\Autoloader;
use Ion\Autoloading\AutoloaderSettings;
use Ion\PhpHelper as PHP;
use Parsedown;
use IntlDateFormatter;
final class Tools
{
    public static function isHidden()
    {
        if (WP::hasSiteOption(Constants::TOOLS_FULLY_HIDDEN_OPTION) === false) {
            return false;
        }
        return (bool) WP::getSiteOption(Constants::TOOLS_FULLY_HIDDEN_OPTION, false) === true;
    }
    public static function isDisabled()
    {
        if (WP::hasSiteOption(Constants::TOOLS_HIDDEN_OPTION) === false) {
            if (WP::isDebugMode()) {
                return false;
            }
            return true;
        }
        return (bool) WP::getSiteOption(Constants::TOOLS_HIDDEN_OPTION, false) === true;
    }
    public static function isQuick404Enabled() : bool
    {
        if (static::isDisabled()) {
            return false;
        }
        if (!WP::isDebugMode()) {
            return false;
        }
        if (PHP::toBool(PHP::filterInput('wp-devhelper-disable-quick-404', [INPUT_GET], FILTER_DEFAULT))) {
            return false;
        }
        return (bool) WP::getSiteOption(Constants::QUICK_404_OPTION, false);
    }
    public static function enable()
    {
        WP::setSiteOption(Constants::TOOLS_HIDDEN_OPTION, true);
    }
    public static function disable()
    {
        WP::setSiteOption(Constants::TOOLS_HIDDEN_OPTION, false);
    }
    public static function addEnableMenuItem()
    {
        if (WP::getSiteOption(Constants::TOOLS_FULLY_HIDDEN_OPTION, false) === false) {
            WP::getAdminMenuPage('tools.php')->addSubMenuPage('Helper', static::getEnableView(), 'wp-devhelper-enable');
        }
    }
    private static function getEnableView()
    {
        return function () {
            foreach (WP::getForms() as $form) {
                if ($form->getId() == 'wp-devhelper-tools-settings') {
                    echo $form->render();
                    return;
                }
            }
            WP::addAdminForm("Settings", 'wp-devhelper-tools-settings')->setOptionPrefix(null)->addGroup("General")->addField(WP::checkBoxInputField("Hidden", Constants::TOOLS_HIDDEN_OPTION, null, null, "Hide the WP Devhelper settings interface in the 'Tools' menu."))->addField(WP::checkBoxInputField("Fully hidden", Constants::TOOLS_FULLY_HIDDEN_OPTION, null, null, "Fully hide the WP Devhelper settings interface. <br /><br /><strong>Be careful!</strong> You will need to be able to edit your WordPress database options table to revert this - look for the '<em>option_name</em>' with the value '<em>" . Constants::TOOLS_FULLY_HIDDEN_OPTION . "</em>' and remove the record to enable."))->redirect(function ($values) {
                // /wordpress/wp-admin/tools.php?page=wp-devhelper-enable
                if ($values[Constants::TOOLS_FULLY_HIDDEN_OPTION] === true && $values[Constants::TOOLS_HIDDEN_OPTION] === true) {
                    WP::setSiteOption(Constants::TOOLS_HIDDEN_OPTION, false);
                    WP::redirect(WP::getAdminUrl('index'));
                }
                if ($values[Constants::TOOLS_HIDDEN_OPTION] === false) {
                    WP::setSiteOption(Constants::TOOLS_FULLY_HIDDEN_OPTION, false);
                    WP::redirect(WP::getAdminUrl('admin') . '?page=wp-devhelper-settings');
                }
            })->render();
        };
    }
    private static function getSettingsView(HelperContextInterface $context)
    {
        return function () use($context) {
            //FIXME: Investigate: why are forms being added twice anyway?
            foreach (WP::getForms() as $form) {
                if ($form->getId() == 'wp-devhelper-settings') {
                    echo $form->render();
                    return;
                }
            }
            WP::addAdminForm("Settings", 'wp-devhelper-settings')->setOptionPrefix(null)->addGroup("General")->addField(WP::checkBoxInputField("Hide settings interface", Constants::TOOLS_HIDDEN_OPTION, null, null, "Hide the WP Devhelper settings interface (you can enable it again, by navigating to 'Tools &gt; Helper')."))->addField(WP::checkBoxInputField("HTML Auto paragraphs", Constants::TOOLS_AUTO_PARAGRAPHS_OPTION, null, null, "Enable automatic paragraph insertion for content."))->addGroup("Logging", null, null, 1)->addField(WP::checkBoxInputField("Enable logging", Constants::ENABLE_LOGGING, null, null, "Enable logging functionality."))->addField(WP::textInputField("Purge age", Constants::LOGS_PURGE_AGE, null, null, "The amount of days before archived log entries are purged (specify <em>0</em> for never)."))->addField(WP::textInputField("Max displayed log entries", Constants::MAX_DISPLAYED_LOG_ENTRIES, null, null, "The maximum amount of records to display when viewing a log (in the administration panel)."))->addGroup("Development Tools", null, null, 1)->addField(WP::checkBoxInputField("Enable quick 404 override", Constants::QUICK_404_OPTION, null, null, "Override the 404 functionality of the site to immediately display a very simple message and end the script."))->redirect(function ($values) {
                if ($values[Constants::TOOLS_HIDDEN_OPTION] === true) {
                    WP::setSiteOption(Constants::TOOLS_FULLY_HIDDEN_OPTION, false);
                    WP::redirect(WP::getAdminUrl('tools') . '?page=wp-devhelper-enable');
                }
            })->render();
        };
    }
    private static function getStateDetailView(HelperContextInterface $context)
    {
        return null;
    }
    private static function getStateView(HelperContextInterface $context)
    {
        return function () use($context) {
            $package = Package::getInstance('ion', 'wp-devhelper');
            echo "<h2>Primary WP Dev/helper package</h2>";
            echo "<p>Version: <strong>" . $package->getVersion()->toString() . "</strong></p>";
            echo "<p>Path: <strong>" . WP::getHelperDirectory() . "</strong></p>";
            $debug = AutoloaderSettings::get($package)->isDebugEnabled();
            echo "<p>Package mode: <strong>" . ($debug ? "Development" : "Release") . "</strong></p>";
            echo "<h2>Contexts</h2>";
            $list = WP::addAdminTable("Contexts", "contexts", "Context", "Contexts", 'package-name', static::getStateDetailView($context), false, false, false, ['<a href="' . WP::getAdminUrl('admin') . '?page=wp-devhelper-logs&form={record}">View log</a>' => function ($id) {
                if (array_key_exists($id, WP::getLogs())) {
                    return true;
                }
                return false;
            }])->addColumnGroup("Information", "information")->addColumn(WP::textTableColumn("Package", "package-name", "package-name"))->addColumn(WP::textTableColumn("Parent", "parent-package-name", "parent-package-name"))->addColumn(WP::textTableColumn("Context Type", "type", "type"))->addColumnGroup("Paths", "paths")->addColumn(WP::textTableColumn('Working Path', 'working-dir', 'working-dir'))->addColumn(WP::textTableColumn('Working URI', 'working-uri', 'working-uri'))->addColumn(WP::textTableColumn('Entry Point', 'loading-path', 'loading-path'))->addColumn(WP::textTableColumn('Version', 'context-version', 'context-version'));
            $list->onRead(function () {
                $groupedRows = [];
                foreach (WP::getContexts() as $id => $ctx) {
                    $type = null;
                    switch ($ctx->getType()) {
                        case Constants::CONTEXT_THEME:
                            $type = 'Theme';
                            break;
                        case Constants::CONTEXT_PLUGIN:
                            $type = 'Plugin';
                            break;
                    }
                    $grouping = $ctx->getParent() !== null ? $ctx->getParent()->getPackageName() : $ctx->getPackageName();
                    if (!array_key_exists($grouping, $groupedRows)) {
                        $groupedRows[$grouping] = [];
                    }
                    $groupedRows[$grouping][] = ['package-name' => $ctx->getParent() === null ? "<strong>{$ctx->getPackageName()}</strong>" : "<span> &nbsp; </span> {$ctx->getPackageName()}", 'parent-package-name' => $ctx->getParent() === null ? '-' : "{$ctx->getParent()->getPackageName()}", 'type' => $type, 'working-dir' => $ctx->getWorkingDirectory(), 'working-uri' => $ctx->getWorkingUri(), 'loading-path' => basename($ctx->getLoadPath()), 'context-version' => $ctx->getVersion() !== null ? $ctx->getVersion()->toString() : null, 'sort-key' => $ctx->getId(), 'parent' => $ctx->getParent() !== null];
                }
                $rows = [];
                foreach ($groupedRows as $grouping => $groupingRows) {
                    usort($groupingRows, function ($a, $b) {
                        if ($a['parent'] === true) {
                            return 1;
                            //                                    if($a['sort-key'] > $b['sort-key']) {
                            //
                            //                                        return -1;
                            //                                    }
                            //
                            //                                    if($a['sort-key'] < $b['sort-key']) {
                            //
                            //                                        return 0;
                            //                                    }
                        }
                        if ($a['parent'] === false) {
                            return -1;
                        }
                        return 0;
                    });
                    foreach ($groupingRows as $tmp) {
                        $rows[] = $tmp;
                    }
                }
                return array_values($rows);
            })->render();
        };
    }
    private static function getWordPressStateView(HelperContextInterface $context)
    {
        return function () use($context) {
            echo "<p>This page shows various configuration settings that are important to developers when debugging their projects.<br />More information can be found at <a target=\"_blank\" href=\"" . Constants::WP_CONFIG_DOCUMENTATION_URL . "\">the official documentation</a>.</p>";
            //$errorLogPath = @ini_get(Constants::PHP_ERROR_LOG);
            //FIXME: Investigate: why are forms being added twice anyway?
            foreach (WP::getForms() as $form) {
                if ($form->getId() == 'wp-devhelper-edit-wordpress-settings') {
                    echo $form->render();
                    return;
                }
            }
            WP::addAdminForm("WordPress Settings", 'wp-devhelper-edit-wordpress-settings')->setOptionPrefix(null)->addGroup("Installation", null, null, 3)->addField(WP::textInputField("Site URI", Constants::SITE_URI, null, null, "The site root URI.", false, false, false, true, true))->addField(WP::textInputField("Site path", Constants::SITE_PATH, null, null, "The site root server path.", false, false, false, true, true))->addField(WP::textInputField("WordPress URI", Constants::WORDPRESS_URI, null, null, "The WordPress installation root URI.", false, false, false, true, true))->addField(WP::textInputField("WordPress path", Constants::WORDPRESS_PATH, null, null, "The WordPress installation root server path.", false, false, false, true, true))->addField(WP::textInputField("WordPress content URI", Constants::WORDPRESS_CONTENT_URI, null, null, "The WordPress installation content root URI.", false, false, false, true, true))->addField(WP::textInputField("WordPress content path", Constants::WORDPRESS_CONTENT_PATH, null, null, "The WordPress installation root server content path.", false, false, false, true, true))->addGroup("Debug Settings", null, null, 3)->addField(WP::checkBoxInputField('Debugging', Constants::WP_CONFIG_DEBUG, null, null, "The value of the " . Constants::WP_CONFIG_DEBUG . " setting in the <em>wp-config.php</em>.", false, true, true))->addField(WP::checkBoxInputField('Display errors (WP)', Constants::WP_CONFIG_DEBUG_DISPLAY, null, null, "The value of the " . Constants::WP_CONFIG_DEBUG_DISPLAY . " setting in the <em>wp-config.php</em>.", false, true, true))->addField(WP::checkBoxInputField('Log errors (WP)', Constants::WP_CONFIG_DEBUG_LOG, null, null, "The value of the " . Constants::WP_CONFIG_DEBUG_LOG . " setting in the <em>wp-config.php</em>.", false, true, true))->addField(WP::textInputField("Error log location", Constants::PHP_ERROR_LOG, null, null, "The location of the PHP error log (the value of <em>ini_get('" . Constants::PHP_ERROR_LOG . "')</em>).", false, false, false, true, true))->addField(WP::textInputField("Log errors (PHP)", Constants::PHP_LOG_ERRORS, null, null, "Whether PHP is currently set to log errors (the value of <em>ini_get('" . Constants::PHP_LOG_ERRORS . "')</em>).", false, false, false, true, true))->addField(WP::textInputField("Display errors (PHP)", Constants::PHP_DISPLAY_ERRORS, null, null, "Whether PHP is currently set to display errors (the value of <em>ini_get('" . Constants::PHP_DISPLAY_ERRORS . "')</em>).", false, false, false, true, true))->addField(WP::checkBoxInputField('Save <em>$wpdb</em> queries', Constants::WP_CONFIG_SAVEQUERIES, null, null, "The value of the " . Constants::WP_CONFIG_SAVEQUERIES . " setting in the <em>wp-config.php</em>.", false, true, true))->addField(WP::checkBoxInputField('Unminified internal styles/scripts', Constants::WP_CONFIG_SCRIPT_DEBUG, null, null, "The value of the " . Constants::WP_CONFIG_SCRIPT_DEBUG . " setting in the <em>wp-config.php</em>.", false, true, true))->addField(WP::checkBoxInputField('Concatenate back-end scripts', Constants::WP_CONFIG_CONCATENATE_SCRIPTS, null, null, "The value of the " . Constants::WP_CONFIG_CONCATENATE_SCRIPTS . " setting in the <em>wp-config.php</em>.", false, true, true))->addGroup("CRON Settings", null, null, 3)->addField(WP::checkBoxInputField('Trigger WP CRON manually', Constants::WP_CONFIG_DISABLE_CRON, null, null, "The value of the " . Constants::WP_CONFIG_DISABLE_CRON . " setting in the <em>wp-config.php</em>.", false, true, true))->addField(WP::checkBoxInputField('Alternate WP CRON trigger method', Constants::WP_CONFIG_ALTERNATE_CRON, null, null, "The value of the " . Constants::WP_CONFIG_ALTERNATE_CRON . " setting in the <em>wp-config.php</em>.", false, true, true))->addField(WP::textInputField('WP CRON timeout lock', Constants::WP_CONFIG_CRON_LOCK_TIMEOUT, null, null, "The value of the " . Constants::WP_CONFIG_CRON_LOCK_TIMEOUT . " setting in the <em>wp-config.php</em>.", false, false, false, true, true))->onRead(function () {
                $checkBoolConst = function (string $const, bool $default = null) : ?bool {
                    if (defined($const)) {
                        return (bool) constant($const);
                    }
                    return null;
                };
                $checkIntConst = function (string $const, int $default = null) : ?int {
                    if (defined($const)) {
                        return (int) constant($const);
                    }
                    return null;
                };
                $checkString = function (string $string, string $default = null) : ?string {
                    return $string;
                };
                return [
                    // Installation
                    Constants::SITE_URI => $checkString(WP::getSiteUri()),
                    Constants::SITE_PATH => $checkString(WP::getSitePath()),
                    Constants::WORDPRESS_URI => $checkString(WP::getWordPressUri()),
                    Constants::WORDPRESS_PATH => $checkString(WP::getWordPressPath()),
                    Constants::WORDPRESS_CONTENT_URI => $checkString(WP::getContentUri()),
                    Constants::WORDPRESS_CONTENT_PATH => $checkString(WP::getContentPath()),
                    // Debugging
                    Constants::WP_CONFIG_DEBUG => $checkBoolConst(Constants::WP_CONFIG_DEBUG),
                    Constants::WP_CONFIG_DEBUG_DISPLAY => $checkBoolConst(Constants::WP_CONFIG_DEBUG_DISPLAY),
                    Constants::WP_CONFIG_DEBUG_LOG => $checkBoolConst(Constants::WP_CONFIG_DEBUG_LOG),
                    Constants::WP_CONFIG_SAVEQUERIES => $checkBoolConst(Constants::WP_CONFIG_SAVEQUERIES),
                    Constants::WP_CONFIG_SCRIPT_DEBUG => $checkBoolConst(Constants::WP_CONFIG_SCRIPT_DEBUG),
                    Constants::WP_CONFIG_CONCATENATE_SCRIPTS => $checkBoolConst(Constants::WP_CONFIG_CONCATENATE_SCRIPTS),
                    Constants::PHP_LOG_ERRORS => $checkString(@ini_get(Constants::PHP_LOG_ERRORS)),
                    Constants::PHP_DISPLAY_ERRORS => $checkString(@ini_get(Constants::PHP_DISPLAY_ERRORS)),
                    Constants::PHP_ERROR_LOG => $checkString(@ini_get(Constants::PHP_ERROR_LOG)),
                    // CRON
                    Constants::WP_CONFIG_DISABLE_CRON => $checkBoolConst(Constants::WP_CONFIG_DISABLE_CRON),
                    Constants::WP_CONFIG_ALTERNATE_CRON => $checkBoolConst(Constants::WP_CONFIG_ALTERNATE_CRON),
                    Constants::WP_CONFIG_CRON_LOCK_TIMEOUT => $checkIntConst(Constants::WP_CONFIG_CRON_LOCK_TIMEOUT),
                ];
            })->render();
        };
    }
    private static function getPhpErrorLogView(HelperContextInterface $context)
    {
        return function () use($context) {
            $ajaxUrl = WP::getAjaxUrl('wp-devhelper-phperrors');
            $lastError = error_get_last();
            $tmp = <<<TMP
    <p><em>None!</em></p>
TMP;
            if ($lastError !== null) {
                $tmp = <<<TMP
    <p>
        <strong>{$lastError['message']}</strong><br />
        <em>{$lastError['file']}, line {$lastError['line']}</em>
    </p>
TMP;
            }
            echo <<<HTML
<h3>Last Error</h3>
{$tmp}
<h3>Error Log</h3>
<p>Log entries have been reversed to show the newest items first.</p>            
<div class="wp-devhelper phperrors viewport-container">

    <div class="wp-devhelper phperrors viewport">
        <iframe src="{$ajaxUrl}"></iframe>
    </div>
</div>    
HTML;
        };
    }
    private static function getLogListView(HelperContextInterface $context)
    {
        return function () use($context) {
            WP::addAdminTable("Logs", "logs", "Log", "Logs", "log-slug", static::getLogDetailView($context), false, false, false, ['<a href="' . WP::getAdminUrl('admin') . '?page=wp-devhelper-logs&form={record}">View</a>' => function ($id) {
                return true;
            }])->addColumn(WP::textTableColumn("Log", "name", "name"))->onRead(function () {
                $rows = [];
                foreach (WP::getLogs() as $log) {
                    $rows[] = ['log-slug' => $log->getSlug(), 'name' => $log->getName()];
                }
                return $rows;
            })->render();
        };
    }
    private static function getLogDetailView(HelperContextInterface $context, WordPressHelperLogInterface $log = null)
    {
        return function () use($context, $log) {
            if ($log !== null) {
                $btnId = 'btn-clear-log-' . $log->getSlug();
                $actionSlug = 'wp-devhelper-clear-log-' . $log->getSlug();
                $ajaxUrl = WP::getAjaxUrl($actionSlug);
                WP::button('Clear', $btnId, 'Clear the log.', null, false, false, PHP::strStripWhiteSpace(<<<JS
                        
var json = jQuery.parseJSON(data);

console.log(json);

if(json.errorCode != null && json.errorCode == 0) {    

    window.location.reload();
    return;
}                    
                        
var error = 'Log clearing operation was NOT successful';

if(json.errorMessage) {

    error = error + ' - ' + json.errorMessage;
}

alert(error + '.');
return false;
JS
), true);
            }
            WP::addAdminTable("Log", "log-entries", "Log Entry", "Log Entries", "id", null, false, false, false)->addColumn(WP::textTableColumn("ID", "id", "id"))->addColumn(WP::textTableColumn("Level", "level", "level"))->addColumn(WP::textTableColumn("Time", "time", "time"))->addColumn(WP::textTableColumn("Message", "message", "message"))->onRead(function () use($log) {
                $formatLevel = function ($level) {
                    $class = "level log-level-{$level}";
                    return "<span class=\"{$class}\">{$level}</span>";
                };
                $formatTime = function ($time) {
                    // $today = strftime('%F', current_time('timestamp'));
                    // $yesterday = strftime('%F', strtotime('-1 day', current_time('timestamp')));
                    $today = date('Y-m-d', current_time('timestamp'));
                    $yesterday = date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
                    // Today
                    if (date('Y-m-d', $time) == $today) {
                        // return strftime('<strong>Today</strong> %T', $time);
                        return "<strong>Today</strong> " . date('H:i:s', $time);
                    }
                    // Yesterday
                    if (date('Y-m-d', $time) == $yesterday) {
                        // return strftime('<strong>Yesterday</strong> %T', $time);
                        return "<strong>Yesterday</strong> " . date('H:i:s', $time);
                    }
                    // Other days
                    // return strftime('<strong>%a %F</strong> %T', $time);
                    return "<strong>" . date("D Y-m-d", $time) . "</strong> " . date('H:i:s', $time);
                };
                $formatMessage = function ($message) {
                    $parseDown = new Parsedown();
                    return str_replace("\n", "<br />\n", $parseDown->text($message));
                };
                $rows = [];
                $age = intval(WP::getSiteOption(Constants::LOGS_PURGE_AGE));
                if ($log !== null && $log->getLogger() !== null) {
                    foreach ($log->getLogger()->getEntries($age) as $entry) {
                        $rows[] = ['id' => $entry['id'], 'slug' => $entry['slug'], 'time' => $formatTime($entry['time']), 'level' => $formatLevel($entry['level']), 'message' => $formatMessage($entry['message'])];
                    }
                }
                return $rows;
            })->render();
        };
    }
    private static function getPhpInfoView(HelperContextInterface $context)
    {
        return function () use($context) {
            $ajaxUrl = WP::getAjaxUrl('wp-devhelper-phpinfo');
            echo <<<HTML
<div class="wp-devhelper phpinfo viewport-container">
    <div class="wp-devhelper phpinfo viewport">
        <iframe src="{$ajaxUrl}"></iframe>
    </div>
</div>    
HTML;
        };
    }
    private static function getHtAccessView(HelperContextInterface $context)
    {
        return function () use($context) {
            $ajaxUrl = WP::getAjaxUrl('wp-devhelper-htaccess');
            $path = WP::getSitePath(is_multisite()) . '.htaccess';
            echo <<<HTML
<p>Path: <strong>{$path}</strong></p>
<div class="wp-devhelper htaccess viewport-container">
    <div class="wp-devhelper htaccess viewport">
        <iframe src="{$ajaxUrl}"></iframe>
    </div>
</div>    
HTML;
        };
    }
    private static function getWordPressOptionDetailView(HelperContextInterface $context)
    {
        return function () use($context) {
            //FIXME: Investigate: why are forms being added twice anyway?
            foreach (WP::getForms() as $form) {
                if ($form->getId() == 'wp-devhelper-edit-wordpress-option') {
                    echo $form->render();
                    return;
                }
            }
            $valueField = WP::textInputField('Value', 'option_value', null, null, null, true);
            WP::addAdminForm("Edit Option", 'wp-devhelper-edit-wordpress-option', null, 1, false)->setOptionPrefix(null)->addField(WP::textInputField('Name', 'option_name'))->addField($valueField)->addField(WP::checkBoxInputField('Auto-load', 'autoload', null, null, "If the 'Auto-load' field is modified, the option needs to be removed and recreated, and will be appended to the options table."))->readFromSqlTable('options')->onUpdate(function ($index, $newValues, $oldValues) {
                if ($newValues['autoload'] !== $oldValues['autoload']) {
                    WP::removeSiteOption($index, null);
                }
                WP::setSiteOption($index, $newValues['option_value'], $newValues['autoload']);
            })->onRead(function (string $index = null) {
                if ($index !== null) {
                    $autoLoad = false;
                    $tbl = WP::getDbTableName('options');
                    $result = WP::dbQuery("SELECT `autoload` FROM `{$tbl}` WHERE `option_name` LIKE (%s)", [$index]);
                    if (PHP::count($result) > 0) {
                        $autoLoad = filter_var($result[0]['autoload'], FILTER_VALIDATE_BOOLEAN);
                    }
                    return ['option_name' => $index, 'autoload' => $autoLoad, 'option_value' => WP::getSiteOption($index, null)];
                }
                return [];
            })->onCreate(function ($values) {
                if (!WP::setSiteOption($values['option_name'], $values['option_value'], $values['autoload'])) {
                    //                            throw new \Exception("???");
                }
            })->render();
        };
    }
    private static function getWordPressOptionsView(HelperContextInterface $context)
    {
        return function () use($context) {
            WP::addAdminTable('Options', 'wordpress-options', 'Option', 'Options', 'option_name', static::getWordPressOptionDetailView($context), true, true, true)->addColumn(WP::textTableColumn('Name', 'option_name', 'option-name'))->addColumn(WP::textTableColumn('Value', 'option_value', 'option-value'))->addColumn(WP::checkBoxTableColumn('Auto-load', 'autoload', 'autoload'))->readFromSqlTable('options', ['option_name' => ['not like' => '\\_%']])->onDelete(function ($items) {
                foreach ($items as $item) {
                    WP::removeSiteOption($item);
                }
            })->render();
        };
    }
    private static function getAboutView(HelperContextInterface $context)
    {
        return function () use($context) {
            $imgSrc = WP::getHelperUri() . "resources/images/helper-logo.png";
            $copyRight = '2017';
            $copyYear = intval(date('Y'));
            if ($copyYear > 2017) {
                $copyRight .= " - {$copyYear}";
            }
            $copyRight .= " by " . Constants::AUTHOR_COMPANY;
            $authorName = Constants::AUTHOR_NAME;
            $authorUrl = Constants::AUTHOR_SITE;
            $authorLink = parse_url($authorUrl)['host'] . parse_url($authorUrl)['path'];
            $maintainerName = Constants::MAINTAINER_NAME;
            $maintainerUrl = Constants::MAINTAINER_SITE;
            $authorUrl .= '?return-url=' . rawurlencode(WP::siteLink(null, null, true, false));
            $maintainerUrl .= '?return-url=' . rawurlencode(WP::siteLink(null, null, true, false));
            $version = Package::getInstance('ion', 'wp-devhelper')->getVersion()->toString();
            $content = WP::applyTemplate(Constants::ABOUT_CONTENT, ['wp-devhelper-version' => $version, 'state-link' => WP::getAdminUrl('admin') . '?page=wp-devhelper-state', 'maintainer-link' => $maintainerUrl, 'site-name' => 'this site']);
            echo <<<HTML

<span class="wp-devhelper-about">
                    
    <span class="header">
        <span class="header-logo">
            <a href="{$authorUrl}"><img id="wp-devhelper-logo" src="{$imgSrc}" /></a>
        </span>
        <span class="header-content">
            <p>
            WP Devhelper <strong>{$version}</strong><br />
            <a href="{$authorUrl}" target="_blank">{$authorLink}</a>
            </p>

            <p>
            Copyright &copy {$copyRight}<br />
            Created by {$authorName} for <a href="{$maintainerUrl}">{$maintainerName}</a>
            </p>
        </span>
    </span>
                    
    <span class="content">
    {$content}
    </span>
                    
</span>

HTML;
        };
    }
    public function __construct(HelperContextInterface $context, array $wpHelperSettings = null)
    {
        WP::addFilter('admin_footer_text', function () {
            // $serverFormatter = new IntlDateFormatter(null, IntlDateFormatter::LONG, IntlDateFormatter::LONG);
            // $serverTime = $serverFormatter->format(time());
            $wordpressFormatter = new IntlDateFormatter(get_locale(), IntlDateFormatter::LONG, IntlDateFormatter::LONG);
            $wordPressTime = $wordpressFormatter->format(current_time('timestamp', 0));
            //$serverTime = strftime('%a %e %b %G, %R');
            //$wordPressTime = strftime('%a %e %b %G, %R', current_time('timestamp', 0));
            $mem = memory_get_peak_usage(true) / 1024 / 1024 . 'Mb';
            $helperVersion = Package::getInstance('ion', 'wp-devhelper')->getVersion()->toString();
            $wordPressVersion = get_bloginfo('version');
            //TODO: Need a wrapper function for bloginfo?
            if (WP::isDebugMode() === true) {
                echo '<span class="debug-mode">Debug mode</span> ';
            }
            $wpHelperBlurbUri = Constants::HELPER_SITE;
            $wpBlurbUri = Constants::WORDPRESS_SITE;
            $wpDevBlurb = Constants::AUTHOR_SITE;
            // echo '<span>Powered by <a href="'. $wpBlurbUri . '" target="_blank">WordPress</a> <strong>' . $wordPressVersion . '</strong></span> | <span>Fueled by <a href="' . $wpHelperBlurbUri . '" target="_blank">WP Dev/helper</a> <strong>' . $helperVersion . '</strong></span> | <span>Need Custom WordPress Solutions? <a href="' . $wpDevBlurb . '" target="_blank">Custom WordPress Development</a></span> | <span>Server time: ' . $serverTime . '</span> | <span>WordPress time: ' . $wordPressTime . '</span> | <span>Peak memory usage: ' . $mem . '</span>';
            echo '<span>Powered by <a href="' . $wpBlurbUri . '" target="_blank">WordPress</a> <strong>' . $wordPressVersion . '</strong></span> | <span>Fueled by <a href="' . $wpHelperBlurbUri . '" target="_blank">WP Dev/helper</a> <strong>' . $helperVersion . '</strong></span> | <span>Need Custom WordPress Solutions? <a href="' . $wpDevBlurb . '" target="_blank">Custom WordPress Development</a></span> | <span>Time: ' . $wordPressTime . '</span> | <span>Peak memory usage: ' . $mem . '</span>';
        });
        add_action('init', function () use($context, $wpHelperSettings) {
            if (!WP::hasSiteOption(Constants::TOOLS_HIDDEN_OPTION) || !WP::hasSiteOption(Constants::TOOLS_FULLY_HIDDEN_OPTION)) {
                if (!WP::hasSiteOption(Constants::TOOLS_HIDDEN_OPTION)) {
                    WP::setSiteOption(Constants::TOOLS_HIDDEN_OPTION, true);
                }
                if (!WP::hasSiteOption(Constants::TOOLS_FULLY_HIDDEN_OPTION)) {
                    WP::setSiteOption(Constants::TOOLS_FULLY_HIDDEN_OPTION, false);
                }
            }
            if (WP::getSiteOption(Constants::TOOLS_FULLY_HIDDEN_OPTION, false) === false) {
                WP::addScript("wp-devhelper-tools-inline', '( function() { console.log('WP Devhelper Tools initialized.'); } )();", true, true, true, false);
                WP::addAjaxAction('wp-devhelper-phpinfo', function () {
                    ob_start();
                    phpinfo();
                    echo ob_get_clean();
                    exit(200);
                }, true, false);
                //wp-devhelper-htaccess
                WP::addAjaxAction('wp-devhelper-htaccess', function () {
                    $data = file_get_contents(WP::getSitePath(is_multisite()) . '.htaccess');
                    if (!$data) {
                        exit(404);
                    }
                    $data = htmlentities($data);
                    echo "<html><head></head><body><pre>{$data}</pre></body></html>";
                    exit(200);
                }, true, false);
                WP::addAjaxAction('wp-devhelper-run-cron-task', function () {
                    $nonce = PHP::toString(PHP::filterInput('nonce', [INPUT_GET], FILTER_DEFAULT));
                    if (!current_user_can('administrator') || !PHP::isEmpty($nonce) && !wp_verify_nonce($nonce, 'cron-task') || PHP::isEmpty($nonce)) {
                        wp_die("Sorry, you do not have permission to execute a CRON Task.");
                        return;
                    }
                    $task = PHP::toString(PHP::filterInput('task', [INPUT_GET], FILTER_DEFAULT));
                    if (PHP::isEmpty($task)) {
                        wp_die("No task specified to execute!");
                        return;
                    }
                    ob_start();
                    try {
                        $crons = _get_cron_array();
                        foreach ($crons as $timeStamp => $cronHooks) {
                            if (!array_key_exists($task, $cronHooks)) {
                                continue;
                            }
                            foreach ($cronHooks as $hook => $keys) {
                                foreach ($keys as $k => $v) {
                                    do_action_ref_array($hook, $v['args']);
                                }
                            }
                        }
                        http_response_code(200);
                    } catch (\Throwable $error) {
                        http_response_code(500);
                        echo "<pre>{$error}</pre>";
                    }
                    echo ob_get_clean();
                    return;
                }, true, false);
                WP::addAjaxAction('wp-devhelper-phperrors', function () {
                    $path = @ini_get(Constants::PHP_ERROR_LOG);
                    if (file_exists($path)) {
                        $size = 1024 * 128;
                        $fSize = filesize($path);
                        $offset = $fSize - $size;
                        $data = file_get_contents($path, false, null, $offset, $size);
                        $more = '';
                        if ($size <= $fSize) {
                            $more = '...';
                        }
                        if ($data !== false) {
                            $data = join("\n", array_reverse(array_slice(explode("\n", $data), 1)));
                            echo "<html><head></head><body><pre>{$data}\n{$more}</pre></body></html>";
                        }
                    } else {
                        echo "<html><head></head><body><span style=\"red\">PHP error log not found at '{$path}'</span></body></html>";
                    }
                    exit(200);
                }, true, false);
                $icon = null;
                $iconDir = WP::getHelperDirectory() . "resources/images/icon.svg";
                if (file_exists($iconDir)) {
                    $icon = 'data:image/svg+xml;base64,';
                    $icon .= base64_encode(@file_get_contents($iconDir));
                }
                $page = WP::addPlugInAdminMenuPage('About', static::getAboutView($context), 'Helper', 'wp-devhelper-about', $icon, null)->addSubMenuPage('About', static::getAboutView($context), 'wp-devhelper-about')->addSubMenuPage('Settings', static::getSettingsView($context), 'wp-devhelper-settings')->addSubMenuPage('State', static::getStateView($context), 'wp-devhelper-state')->addSubMenuPageTab('WordPress', static::getWordPressStateView($context), 'wp-devhelper-wpstate')->addSubMenuPageTab('Cron', static::getCronStateView($context), 'wp-devhelper-cron')->addSubMenuPageTab('PHP Info', static::getPhpInfoView($context), 'wp-devhelper-phpinfo');
                if (file_exists(WP::getSitePath(is_multisite()) . '.htaccess')) {
                    $page = $page->addSubMenuPageTab('.htaccess', static::getHtAccessView($context), 'wp-devhelper-htaccess');
                }
                if (defined(Constants::WP_CONFIG_DEBUG_LOG) && constant(Constants::WP_CONFIG_DEBUG_LOG) === true && !PHP::isEmpty(@ini_get('error_log'))) {
                    $page = $page->addSubMenuPageTab('PHP Errors', static::getPhpErrorLogView($context), 'wp-devhelper-phperrors');
                }
                $page->addSubMenuPage('Options', static::getWordPressOptionsView($context), 'wp-wordpress-options');
                if (PHP::toBool(WP::getSiteOption(Constants::ENABLE_LOGGING, false)) === true && count(WP::getLogs()) > 0) {
                    $page = $page->addSubMenuPage('Logs', static::getLogListView($context), 'wp-devhelper-logs');
                    WP::addAction("wp_loaded", function () use($context, $page) {
                        foreach (WP::getLogs() as $log) {
                            $page->addSubMenuPageTab($log->getName(), static::getLogDetailView($context, $log), $log->getSlug());
                        }
                    });
                }
                foreach (WP::getLogs() as $log) {
                    WP::addAjaxAction('btn-clear-log-' . $log->getSlug(), function () use($log) {
                        $log->getLogger()->purge(true);
                        echo '{ "errorCode": 0 }';
                        exit(200);
                    });
                }
            }
        });
    }
    private static function getCronStateView(HelperContextInterface $context)
    {
        return function () use($context) {
            $task = PHP::toString(PHP::filterInput('task', [INPUT_GET], FILTER_DEFAULT));
            if (!PHP::isEmpty($task)) {
                $ajaxUrl = WP::getAjaxUrl('wp-devhelper-run-cron-task') . "&task={$task}&nonce=" . esc_attr(wp_create_nonce('cron-task'));
                $page = PHP::toString(filter_input(INPUT_GET, 'page', FILTER_DEFAULT));
                $form = PHP::toString(filter_input(INPUT_GET, 'form', FILTER_DEFAULT));
                $paged = PHP::toString(filter_input(INPUT_GET, 'paged', FILTER_DEFAULT));
                $backUrl = WP::getAdminUrl('admin') . '?page=' . $page . ($form !== null ? '&form=' . $form : "") . ($paged !== null ? '&paged=' . $paged : "");
                echo <<<HTML
<div class="wp-devhelper cron-task viewport-container">
    <div class="wp-devhelper cron-task viewport">
        <iframe src="{$ajaxUrl}"></iframe>
    </div>
</div>    
<p class="submit">
    <a href="{$backUrl}" id="btn-cancel" class="button">Back</a>
</p>
HTML;
                return;
            }
            $wordPressTime = date_i18n("j F Y, g:i a", current_time('timestamp'));
            echo "<p>Current time: <strong>{$wordPressTime}</strong></p>";
            $list = WP::addAdminTable("Contexts", "contexts", "Context", "Contexts", "context-slug", null, false, false, false)->addColumnGroup("Information", "information")->addColumn(WP::textTableColumn("Job", "job-slug", "job-slug"))->addColumn(WP::checkBoxTableColumn("Action Exists", "job-action", "job_action"))->addColumn(WP::textTableColumn("Arguments", "job-args", "job-args"))->addColumn(WP::textTableColumn("Schedule", "job-schedule", "job-schedule"))->addColumn(WP::textTableColumn("Next Run", "job-next-run", "job-next-run"))->addColumn(WP::textTableColumn("", "job-execute", "job-execute"));
            $list->onRead(function () {
                $rows = [];
                $cronArray = WP::getCronArray();
                $jobs = [];
                foreach ($cronArray as $key1 => $tmp1) {
                    foreach ($tmp1 as $key2 => $tmp2) {
                        foreach ($tmp2 as $key3 => $tmp3) {
                            $jobs[$key2 . ' ' . md5(implode($tmp3['args']))] = ['slug' => $key2, 'schedule' => $tmp3['schedule'], 'args' => $tmp3['args'], 'interval' => array_key_exists('interval', $tmp3) ? $tmp3['interval'] : null, 'time' => $key1];
                        }
                    }
                }
                foreach ($jobs as $key => $job) {
                    $slug = $job['slug'];
                    $args = null;
                    if (PHP::count($job['args']) > 0) {
                        $args = implode('<br />', $job['args']);
                    }
                    $when = 'In queue';
                    $seconds = $job['time'] - time();
                    if ($seconds > 0) {
                        $minutes = floor($seconds / 60);
                        $hours = floor($minutes / 60);
                        $days = floor($hours / 24);
                        if ($seconds < 60) {
                            $when = 'In ' . $seconds . ' seconds';
                        }
                        if ($seconds >= 60 && $minutes < 60) {
                            $rem = floor($seconds - $minutes * 60);
                            $when = 'In ' . $minutes . ($minutes === 1 ? ' minute' : ' minutes') . ($rem > 0 ? ' ' . (string) $rem . ' second' . ($rem <= 1 && $rem !== 0 ? '' : 's') : '');
                        }
                        if ($minutes >= 60 && $hours < 24) {
                            $rem = floor($minutes - $hours * 60);
                            $when = 'In ' . $hours . ($hours === 1 ? ' hour' : ' hours') . ($rem > 0 ? ' ' . (string) $rem . ' minute' . ($rem <= 1 && $rem !== 0 ? '' : 's') : '');
                        }
                        if ($hours >= 24 && $days >= 1) {
                            $rem = floor($hours - $days * 24);
                            $when = 'In ' . $days . ($days === 1 ? ' day' : ' days') . ($rem > 0 ? ' ' . (string) $rem . ' hour' . ($rem <= 1 && $rem !== 0 ? '' : 's') : '');
                        }
                    } else {
                        $when = 'Overdue';
                    }
                    $nextRun = implode('<br />', ["<strong>{$when}</strong>", date_i18n("j F Y", $job['time']), date_i18n("g:i a", $job['time'])]);
                    $schedule = null;
                    //$intervals = array_flip(WP::getCronIntervals());
                    $intervals = [];
                    foreach (WP::getCronIntervals() as $key => $schedule) {
                        $intervals[$key] = $schedule["display"];
                    }
                    if (PHP::isString($job['schedule']) && array_key_exists($job['schedule'], $intervals)) {
                        $schedule = $intervals[$job['schedule']];
                    }
                    $jobExecute = WP::button('Run', 'run', "", null, false, false, "window.location = window.location + '&task={$slug}';", false, false);
                    $rows[] = ['job-slug' => $slug, 'job-action' => WP::hasAction($slug), 'job-args' => $args, 'job-schedule' => $schedule, 'job-next-run' => $nextRun, 'job-execute' => $jobExecute['html']()];
                }
                return $rows;
            })->render();
        };
    }
}