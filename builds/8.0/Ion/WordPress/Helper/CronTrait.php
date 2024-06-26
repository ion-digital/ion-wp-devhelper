<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace Ion\WordPress\Helper;

use Throwable;
use WP_Post;
use Ion\WordPress\WordPressHelperInterface;
use Ion\WordPress\WordPressHelper as WP;
use Ion\WordPress\Helper\WordPressHelperException;
use Ion\WordPress\Helper\Tools;
use Ion\WordPress\Helper\Constants;
use Ion\PhpHelper as PHP;
use Ion\Package;
use Ion\SemVerInterface;
use Ion\SemVer;
/**
 * Description of CronTrait*
 * @author Justus
 */
trait CronTrait
{
    //const CRON_SNAPSHOT_NAME = 'wp-devhelper::cron-jobs';
    private static $cronIntervals = [];
    private static $cronJobs = [];
    protected static function initialize()
    {
        static::registerWrapperAction('init', function () {
            foreach (static::$cronIntervals as $intervalName => $interval) {
                add_filter('cron_schedules', function ($schedules) use($intervalName, $interval) {
                    $schedules[$intervalName] = array('interval' => $interval['interval'], 'display' => esc_html__(!PHP::isEmpty($interval['label']) ? $interval['label'] : $intervalName));
                    return $schedules;
                });
            }
            $jobsToAdd = [];
            $jobsToRemove = [];
            $jobsToUpdate = [];
            if (PHP::count(static::$cronJobs) > 0) {
                //                echo "<pre>";
                //                var_dump(_get_cron_array());
                //                die('</pre>');
                // We have a couple of CRON jobs - first check what has changed
                WP::registerLog('cron', 'CRON');
                if (WP::hasSiteOption('wp-helper::cron-jobs')) {
                    $snapShot = (array) WP::getSiteOption('wp-helper::cron-jobs');
                    foreach (static::$cronJobs as $jobName => $job) {
                        if (!array_key_exists($jobName, $snapShot) || wp_next_scheduled($jobName) === false) {
                            // this is a new job
                            $jobsToAdd[$jobName] = $job;
                        } else {
                            // this job *might* need to be updated
                            $snapShotJob = $snapShot[$jobName];
                            if ($job['time'] !== $snapShotJob['time'] || $job['interval'] !== $snapShotJob['interval']) {
                                // yep...
                                $jobsToRemove[] = $jobName;
                                $jobsToUpdate[$jobName] = $job;
                            }
                        }
                    }
                    foreach ($snapShot as $jobName => $job) {
                        if (!array_key_exists($jobName, static::$cronJobs) || array_key_exists($jobName, static::$cronJobs) && static::$cronJobs[$jobName]['job'] === null) {
                            // this job needs to be removed
                            if (!in_array($jobName, $jobsToRemove, true)) {
                                $jobsToRemove[] = $jobName;
                            }
                        }
                    }
                } else {
                    $jobsToAdd = static::$cronJobs;
                }
                // Now do the actual addition / removals; starting with removal
                foreach ($jobsToRemove as $jobName) {
                    $nextTime = wp_next_scheduled($jobName);
                    if ($nextTime !== false) {
                        wp_clear_scheduled_hook($jobName);
                    }
                }
                foreach ($jobsToAdd as $jobName => $job) {
                    $nextTime = wp_next_scheduled($jobName);
                    if ($nextTime === false) {
                        if ($job['interval'] === null) {
                            wp_schedule_single_event($job['time'], $jobName);
                        } else {
                            wp_schedule_event($job['time'], $job['interval'], $jobName);
                        }
                    }
                }
                foreach ($jobsToUpdate as $jobName => $job) {
                    wp_reschedule_event($job['time'], $job['interval'], $jobName);
                }
                // Save the snapshot so we have a reference for next time
                $tmp = [];
                foreach (static::$cronJobs as $jobName => $job) {
                    $tmp[$jobName] = $job;
                    if (array_key_exists('job', $tmp[$jobName])) {
                        unset($tmp[$jobName]['job']);
                    }
                }
                WP::setSiteOption('wp-helper::cron-jobs', $tmp);
                // Now add the actions for each job
                foreach (static::$cronJobs as $jobName => $job) {
                    //                    $nextTime = wp_next_scheduled($jobName);
                    //
                    //                    if($nextTime === false) {
                    //
                    //                        throw new WordPressHelperException("Something went wrong: '**{$jobName}** is meant to be scheduled, but wp_next_scheduled() returned FALSE.'");
                    //                    }
                    static::addAction($jobName, function () use($jobName, $job) {
                        if ($job === null || $job !== null && !array_key_exists('job', $job)) {
                            static::log("Job '**{$jobName}**' did not complete - no closure was defined to execute.", LogLevel::WARNING, 'cron');
                            return;
                        }
                        static::log("Job '**{$jobName}**' invoked.", LogLevel::INFO, 'cron');
                        try {
                            $job['job']();
                            static::log("Job '**{$jobName}**' completed successfully.", LogLevel::INFO, 'cron');
                        } catch (Throwable $throwable) {
                            static::log("Job '**{$jobName}**' failed - an " . ($throwable instanceof \Exception ? get_class($throwable) . " was raised" : get_class($throwable) . " occurred") . ": " . $throwable->getMessage(), LogLevel::ERROR, 'cron');
                            static::log("Stack-trace:\n\n" . $throwable->getTraceAsString(), LogLevel::DEBUG, 'cron');
                        }
                    });
                }
            } else {
                // No CRON jobs have been defined
                if (WP::hasSiteOption('wp-helper::cron-jobs')) {
                    WP::removeSiteOption('wp-helper::cron-jobs');
                }
            }
        });
    }
    public static function addCronInterval(string $name, int $interval, string $label = null) : string
    {
        static::$cronIntervals[$name] = ['name' => $name, 'interval' => $interval, 'label' => $label];
        return $name;
    }
    public static function addCronJob(string $name, int $startTimeStamp, string $intervalName, callable $job) : void
    {
        static::$cronJobs[$name] = ['time' => $startTimeStamp, 'interval' => strtolower(trim($intervalName)) === 'once' ? null : $intervalName, 'job' => $job];
        return;
    }
    public static function removeCronJob(string $name) : void
    {
        if (array_key_exists($name, static::$cronJobs)) {
            unset(static::$cronJobs[$name]);
        }
        wp_clear_scheduled_hook($name);
        return;
    }
    public static function getCronIntervals(bool $asList = false) : array
    {
        $schedules = wp_get_schedules();
        foreach (static::$cronIntervals as $name => $schedule) {
            $schedules[$name] = ["interval" => $schedule["interval"], "display" => $schedule["label"]];
        }
        if ($asList) {
            $tmp = [];
            foreach ($schedules as $name => $schedule) {
                $tmp[$schedule["display"]] = $name;
            }
            return $tmp;
        }
        return $schedules;
    }
    public static function cronJobExists(string $name) : bool
    {
        return (bool) (wp_next_scheduled($name) !== false);
    }
    public static function getCronArray() : array
    {
        return _get_cron_array();
    }
}