<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Any changes that have to be executed just once, optionally including rollback:
 *  - db insert / delete / update with ORM
 *  - cache flush
 *  - external service notification (tell google about new stuff etc.)
 *  - ... or any callable php / bash script
 *
 * Similar to liquibase, uses the same table for versioning, eg. DATABASECHANGELOG
 *  Tables are auto-generated by liquibase, use provided changelog-[dbtype].sql if used as is.
 *
 * @package Deploy
 * @author Mutant Industries ltd. <mutant-industries@hotmail.com>
 */
class Kohana_Task_Deploy_Custom extends Deploy_Database {

    protected $_options = array
    (
        'php_changelog_dir' => null,
    );

    /**
     * @var Model_Changelog[] where key is changelog hash
     */
    protected $_actions_executed = array();

    protected function __construct()
    {
        parent::__construct();

        foreach(ORM::factory('Changelog')->find_all() as $record)
        {
            $this->_actions_executed[$record->hash()] = $record;
        }
    }

    /**
     * Execute exerything that hasn't been executed yet
     *
     * @param array $params
     * @throws Deploy_Exception
     */
    protected function _execute(array $params)
    {
        $start_time = date(DATE_ATOM);
        $list = Kohana::list_files($params['php_changelog_dir']);

        if( ! count($list))
        {
            throw new Deploy_Exception('No custom changelogs to be applied !');
        }

        foreach ($list as $changelog_file)
        {
            foreach(Kohana::load($changelog_file) as $changeset)
            {
                try
                {
                    $this->_apply_changeset($changeset, $changelog_file);
                }
                catch (Deploy_Exception $e)
                {
                    try
                    {
                        $this->_options['rollback'] = $start_time;
                        $this->execute();
                    }
                    catch (Rollback_Exception $ex)
                    {
                        Log::instance()->add(Log::WARNING, $ex->getMessage(), null, array('exception' => $ex));
                    }

                    throw $e;
                }
            }
        }
    }

    /**
     * Rollback to certain date.
     *
     * @param array $params
     * @throws Rollback_Exception
     */
    protected function _rollback(array $params)
    {
        $list = Kohana::list_files($params['php_changelog_dir']);

        if( ! count($list))
        {
            throw new Rollback_Exception('no custom changelogs found !');
        }

        foreach (ORM::factory('Changelog')->executed_since($params['rollback'])->find_all() as $rollback_record)
        {
            if(($changeset = $this->_find_changeset_by_record($rollback_record, $list)) === null)
            {
                continue;
            }

            $this->_rollback_changeset($changeset, $params['rollback']);

            unset($this->_actions_executed[$rollback_record->hash()]);
            $rollback_record->delete();
        }
    }

    /**
     * @param array $changeset
     * @param string $changelog_file
     * @return void
     * @throws Deploy_Exception
     */
    protected function _apply_changeset(array $changeset, $changelog_file)
    {
        if( ! isset($changeset['id']) || ! isset($changeset['author']) || ! isset($changeset['changes']))
        {
            throw new Deploy_Exception($changelog_file.' contains invalid changeset: id, author or changes not set !');
        }

        $record = ORM::factory('Changelog');

        $record->id($changeset['id'])
                ->author($changeset['author'])
                ->comments(isset($changeset['comment']) ? $changeset['comment'] : null)
                ->filename($changelog_file);

        // already executed
        if(array_key_exists($record->hash(), $this->_actions_executed))
        {
            return;
        }

        $callback = null;
        $parameters = isset($changeset['parameters']) ? $changeset['parameters'] : array();
        $skip = false;

        // ---------------- callback init ----------------
        if(is_callable($changeset['changes']))
        {
            $callback = $changeset['changes'];
        }
        else if(is_string($changeset['changes']))
        {
            try
            {
                $changeset['changes'] = $this->_create_task($changeset['changes'], $parameters);
            }
            catch (Minion_Exception_InvalidTask $e)
            {
                throw new Deploy_Exception('Invalid task :task', array(':task' => $changeset['changes']), 0, $e);
            }
        }
        if($changeset['changes'] instanceof Minion_Task)
        {
            $callback = array($changeset['changes'], 'execute');
        }

        if($callback === null)
        {
            throw new Deploy_Exception($changeset['changes'].' not callable !');
        }

        // ---------------- precondition check ----------------
        if(isset($changeset['precondition']))
        {
            $precondition = $changeset['precondition'];

            if( ! $precondition instanceof Precondition)
            {
                throw new Deploy_Exception($changelog_file.': '.$changeset['id'].' '.$changeset['author'].' contains invalid precondition !');
            }
            if( ! $precondition->check())
            {
                $error = $changelog_file.': '.$changeset['id'].' '.$changeset['author'].' unsatisfied precondition !';

                if($precondition->on_fail() === Precondition::HALT)
                {
                    throw new Deploy_Exception($error);
                }
                else if($precondition->on_fail() === Precondition::WARN)
                {
                    Log::instance()->add(Log::WARNING, $error);
                }
                else if($precondition->on_fail() === Precondition::MARK_RAN)
                {
                    $skip = true;
                }
            }
        }

        try
        {
            $skip || call_user_func_array($callback, $parameters);
            $record->save();
        }
        catch (Exception $e)
        {
            throw new Deploy_Exception($changelog_file.': '.$changeset['id'].' '.$changeset['author'].' failed: '.$e->getMessage(), null, 0, $e);
        }

        $this->_actions_executed[$record->hash()] = $record;
        Log::instance()->add(Log::INFO, 'changeset '.$changeset['id'].'-'.$changeset['author']
                .(isset($changeset['comment']) ? ': '.$changeset['comment'] : '').' applied');
    }

    /**
     * @param array $changeset
     * @throws Rollback_Exception
     */
    protected function _rollback_changeset(array $changeset)
    {
        // ------------- unimportant stuff, shouldn't happen -------------------
        if( ! isset($changeset['id']) || ! isset($changeset['author']) || ! isset($changeset['changes']))
        {
            throw new Rollback_Exception('invalid changeset to be rolled back: id, author or changes not set !');
        }
        // ---------------------------------------------------------------------

        $callback = null;
        $parameters = isset($changeset['parameters']) ? $changeset['parameters'] : array();

        if(isset($changeset['rollback']))
        {
            if(is_callable($changeset['rollback']))
            {
                $callback = $changeset['rollback'];
            }
        }
        else if(is_string($changeset['changes']))   // autorollback where supported
        {
            try
            {
                $task = $this->_create_task($changeset['changes'], $parameters);
            }
            catch (Minion_Exception_InvalidTask $e) // shouldn't happen
            {
                throw new Rollback_Exception('Invalid task :task', array(':task' => $changeset['changes']), null, $e);
            }
            if( ! Deploy_Task::is_rollback_supported($task))
            {
                throw new Rollback_Exception('Rollback not possible for task :task', array(':task' => $changeset['changes']));
            }

            $callback = array($task, 'execute');
        }

        if($callback === null)
        {
            throw new Rollback_Exception($changeset['id'].' '.$changeset['author'].' rollback not supported');
        }

        try
        {
            call_user_func_array($callback, $parameters);
        }
        catch (Exception $e)
        {
            throw new Rollback_Exception($changeset['id'].' '.$changeset['author'].' rollback failed: '.$e->getMessage(), null, 0, $e);
        }

        Log::instance()->add(Log::INFO, 'changeset '.$changeset['id'].'-'.$changeset['author']
                .(isset($changeset['comment']) ? ': '.$changeset['comment'] : '').' rolled back');
    }

    protected function _find_changeset_by_record(Model_Changelog $record, array $list)
    {
        $changeset_record = ORM::factory('Changelog');

        foreach ($list as $changelog_file)
        {
            foreach (Kohana::load($changelog_file) as $changeset)
            {
                $changeset_record->id($changeset['id']);
                $changeset_record->author($changeset['author']);
                $changeset_record->filename($changelog_file);

                if($changeset_record->hash() === $record->hash())
                {
                    return $changeset;
                }
            }
        }

        return null;
    }

    /**
     * @param Validation $validation
     * @return Validation
     */
    public function build_validation(Validation $validation)
    {
        return parent::build_validation($validation)
            ->rule('php_changelog_dir', 'not_empty');
    }

}