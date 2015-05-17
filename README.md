Kohana Deploy Module
====================

The deploy module aims to simplify deployment process of kohana applications.
Database versioning with liquibase, custom task execution, application suspension, cache flushing
and rollbacks are supported out of the box. Deploy process is divided into minion tasks - optionally
extended from Deploy_Task and using rollback - which are executed in order specified in
configuration file. If any of tasks fails, rollback is initiated. When deploy / rollback process is finished,
all 'after' tasks are executed.

> deploy:liquibase task generates master changelog file, where all found changelogs are included.
Xml, JSON and yaml formats are supported, 'liquibase' command is expected in path.

> deploy:custom is very similar to liquibase, while any php script can be executed.

Installation
------------

1. git submodule add https://github.com/mutant-industries/kohana-deploy.git
2. enable module in your bootstrap.php file
3. customize config file

Usage
-----

- php index.php deploy
- php index.php deploy --no_suspend
- php index.php deploy --rollback='2014-10-14 12:42:30'
- php index.php deploy:liquibase --changelog_dir=db --log_level=warning --execute_module_changelogs=false
- php index.php deploy:liquibase --changelog_dir=db --execute_module_changelogs=true --rollback='2014-10-09 20:18:35'
- php index.php deploy:custom --php_changelog_dir=db/custom
- php index.php cache:flush --cache_type=memcache
- php index.php cache:flush --cache_type=redis --tags=foo,bar

If you wand to have application suspended during deployment process, add the following code
optionally to bootstrap.php:

    if(Suspend::instance()->check_suspended() && PHP_SAPI !== 'cli')
    {
        throw new HTTP_Exception_503;
    }

See userguide for detailed information and usage.
