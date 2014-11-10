<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Liquibase update / rollback handler. Expects liquibase command in path. Task searches
 * provided directories and generates master changelog, that is than passed to liquibase.
 *
 * snakeyaml.jar must be in liquibase classpath
 *
 * http://www.liquibase.org/
 *
 * @package Deploy
 * @author Mutant Industries ltd. <mutant-industries@hotmail.com>
 */
class Kohana_Task_Deploy_Liquibase extends Deploy_Database {

    /**
     * changelog_dir - all files in this directory will be included in generated master
     *  changelog, format support: xml, json, yaml
     * execute_module_changelogs - modules changelog location - MODPATH.'module_name/db/changelog.xml'
     * log_level - liquibase log level
     *
     * @var array
     */
    protected $_options = array
    (
        'changelog_dir' => null,
        'execute_module_changelogs' => 'false',
        'log_level' => 'info',
    );

    protected function _execute(array $params)
    {
        return $this->_execute_liquibase($this->_generate_master_changelog($params),
                'update', $params['log_level']);
    }

    protected function _rollback(array $params)
    {
        return $this->_execute_liquibase($this->_generate_master_changelog($params),
                'rollbackToDate '.date("Y-m-d\TH:i:s", strtotime($params['rollback'])), $params['log_level']);
    }

    /**
     * execute generated changelog on database connection
     *
     * @param string $changelog master changelog realpath
     * @param string $_command
     * @param string $log_level debug|info|warning|severe|off
     * @return liquibase output
     * @throws Liquibase_Exception
     */
    protected function _execute_liquibase($changelog, $_command, $log_level)
    {
        $connection = $this->_db_config['connection'];

        // ------- generate shell command -----------
        $command = 'liquibase --changeLogFile='.$changelog.' --url=jdbc:';

        if(isset($connection['dsn']))   // PDO
        {
            $command.= $connection['dsn'];
        }
        else
        {
            $command.= strtolower($this->_db_config['type']).'://'.$connection['hostname'];

            if(isset($connection['database']))
            {
                $command.= '/'.$connection['database'];
            }
        }

        if( ! empty($connection['username']))
        {
            $command.= ' --username='.$connection['username'];
        }
        if( ! empty($connection['password']))
        {
            $command.= ' --password='.$connection['password'];
        }

        $command.= ' --logLevel='.$log_level.' '.$_command;
//        Log::instance()->add(Log::DEBUG, 'Executing command: '.$command);
        // ---------------------------------------------------------------------

        $pipes = array();

        $p = proc_open($command, array(
            1 => array("pipe", "w"),
            2 => array("pipe", "w") // liquibase likes to write to stderr very much
         ), $pipes);

        $output = stream_get_contents($pipes[2]);
        array_map('fclose',$pipes);
        $retval = proc_close($p);
        // delete generated changelog
        unlink($changelog);

        if($retval !== 0)
        {
            throw new Liquibase_Exception($output);
        }

        return $output;
    }

    /**
     * generate master changelog and return its realpath
     *
     * @param array $params
     * @return string
     * @throws Liquibase_Exception
     */
    protected function _generate_master_changelog(array $params) {

        if( ! count($list = $this->_source_files($params)))
        {
            throw new Liquibase_Exception('No liquibase changelogs to be applied !');
        }

        $changelog = array('databaseChangeLog' => array());

        foreach ($list as $one_changelog_filename)
        {
            $changelog['databaseChangeLog'][] = array(
                'include' => array('file' => $one_changelog_filename)
                );
        }

        $file = fopen(sys_get_temp_dir().DIRECTORY_SEPARATOR.microtime(true).'.json', 'w');
        fwrite($file, json_encode($changelog, JSON_UNESCAPED_SLASHES));

        $metadata = stream_get_meta_data($file);

        return $metadata["uri"];
    }

    protected function _source_files($params)
    {
        $list = array();

        // modules go first
        if($params['execute_module_changelogs'] === 'true'
                && $module_changelogs = Kohana::find_file('db', 'changelog', 'xml', true))
        {
            $list = Arr::merge($list, $module_changelogs);
        }
        if(isset($params['changelog_dir']))
        {
            $list = Arr::merge($list, Kohana::list_files($params['changelog_dir']));
        }

        return $list;
    }

    public function build_validation(Validation $validation)
    {
        return parent::build_validation($validation)
            ->rule('execute_module_changelogs', 'regex', array(':value', '/true|false/'))
            ->rule('log_level', 'regex', array(':value', '/debug|info|warning|severe|off/'))
            ->rule('changelog_dir', 'not_empty');
    }

}
