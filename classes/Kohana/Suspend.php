<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Application shouldn't be accessible during deployment, while it can be in unconsistent state.
 * Deploy module marks application as suspended before deployment process and as ready
 * when process is over. Somewhere - optionally in bootstrap - should be something like:
 *
 * if(Suspend::instance()->check_suspended() && PHP_SAPI !== 'cli')
 * {
 *      throw new HTTP_Exception_503;
 * }
 *
 * @package Deploy
 * @author Mutant Industries ltd. <mutant-industries@hotmail.com>
 */
abstract class Kohana_Suspend {

    /**
     * @var Suspend
     */
    protected static $_instance;

    /**
     * @return Suspend
     */
    public static function instance()
    {
        if(Suspend::$_instance === null)
        {
            $config = Kohana::$config->load('suspend');

            $handler_class = 'Suspend_'.  ucfirst($config->handler);

            Suspend::$_instance = new $handler_class($config);
        }

        return Suspend::$_instance;
    }

    /**
     * Mark application as temporarily unavaliable
     */
    abstract public function mark_suspended();

    /**
     * Mark application as ready
     */
    abstract public function mark_done();

    /**
     * @return boolean false if application is ready
     */
    abstract public function check_suspended();

}
