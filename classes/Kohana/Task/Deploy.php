<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Master deploy task. Tasks and order of execution is set in configuration file.
 *
 *  - suspend application
 *  - execute all tasks from config ini given order, execute after tasks
 *      if any of these fails, tasks that make use of Rollback trait are rolled back in reverse order
 *  - wakeup application
 *
 * Rollback continues on error, eg. if second task throws Rollback_Exception, third
 * is rolled back anyway
 *
 * @package Deploy
 * @author Mutant Industries ltd. <mutant-industries@hotmail.com>
 */
class Kohana_Task_Deploy extends Deploy_Task {

    use Rollback;

    protected $_config_name = 'deploy';

    protected $_config;

    protected $_actions_applied = array();

    protected function __construct()
    {
        parent::__construct();

        $this->_config = Kohana::$config->load($this->_config_name);
    }

    protected function _execute(array $params)
    {
        $this->_before($params);

        $start_time = date(DATE_ATOM);

        foreach ($this->_config->tasks as $_task => $options)
        {
            $task = $this->_create_task($_task, $options);

            try
            {
                $task->execute();
                $this->_actions_applied[] = $task;
            }
            catch (Exception $e)
            {
                foreach (array_reverse($this->_actions_applied, true) as $task)
                {
                    if ( ! Deploy_Task::is_rollback_supported($task)) continue;

                    try
                    {
                        $task->set_options(Arr::merge($task->get_options(), array('rollback' => $start_time)))->execute();
                    }
                    catch (Exception $e)
                    {
                        Log::instance()->add(Log::WARNING, 'task :task failed to rollback to current state', array(':task' => $task), array('exception' => $e));
                    }
                }

                $this->_after($params);

                throw $e;
            }
        }

        $this->_after($params);

        return Minion_CLI::color('deploy successful, have a nice day :-)', 'light_green');
    }

    protected function _rollback(array $params)
    {
        $this->_before($params);

        foreach (array_reverse($this->_config->tasks, true) as $task => $options)
        {
            $_task = $this->_create_task($task, $options);

            if ( ! Deploy_Task::is_rollback_supported($_task)) continue;

            try
            {
                $_task->execute();
            }
            catch (Exception $e)
            {
                Log::instance()->add(Log::WARNING, 'task :task failed to rollback to :time: ' . $e->getMessage(), array(':task' => $task, ':time' => $params['rollback']), array('exception' => $e));
            }
        }

        $this->_after($params);

        return Minion_CLI::color('finished', 'light_cyan');
    }

    /**
     * Method called before deploy or rollback
     *
     * @param array $params
     */
    protected function _before(array $params)
    {
        isset($params['no_suspend']) || Suspend::instance()->mark_suspended();
    }

    /**
     * Method called after deploy or rollback
     *
     * @param array $params
     */
    protected function _after(array $params)
    {
        foreach ($this->_config->after as $_task => $options)
        {
            $task = $this->_create_task($_task, $options);

            try
            {
                $task->execute();
            }
            catch (Exception $e)
            {
                Log::instance()->add(Log::WARNING, 'task :task failed to run: ' . $e->getMessage(), array(':task' => $task), array('exception' => $e));
            }
        }

        isset($params['no_suspend']) || Suspend::instance()->mark_done();
    }

    /**
     * Any option is valid and will be passed + validated in child tasks
     *
     * @param Validation $validation
     * @param type $option
     * @return boolean
     */
    public function valid_option(Validation $validation, $option)
    {

    }

}
