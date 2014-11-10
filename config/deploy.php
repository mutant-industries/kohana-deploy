<?php defined('SYSPATH') or die('No direct script access.');

return array(
    /*
     * tasks are executed in given order
     */
    'tasks' => array(
        /*
         * Changelog files array is ksorted, which means that r120.xml is
         * executed before r140.xml, while foo/r140.xml is executed before r120.xml.
         * Custom (unsorted) changelog set is always executed first.
         */
        'deploy:liquibase' => array(
            'changelog_dir' => 'db/liquibase',
            'execute_module_changelogs' => 'true',  // execute everything in MODPATH.'module_name/db'
            'log_level' => 'warning',    // debug|info|warning|severe|off
        ),
        /*
         * Stuff ment to be executed just once
         */
        'deploy:custom' => array(
            'php_changelog_dir' => 'db/custom',
        ),
    ),
    /*
     * after deploy - sass compile, uglifyjs...
     */
    'after' => array(

    ),
);
