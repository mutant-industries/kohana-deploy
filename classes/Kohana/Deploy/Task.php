<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @package Deploy
 * @author Mutant Industries ltd. <mutant-industries@hotmail.com>
 */
abstract class Kohana_Deploy_Task extends Minion_Task {

    /**
     * @var Deploy_Task
     */
    protected $_parent;

    /**
     * @var array
     */
    protected $_inherited_options;

    /**
     * Create new deploy task, set parent and pass options.
     *
     * @param String $task
     * @param array... $params
     */
    protected function _create_task($_task/*, array $params...*/)
    {
        $task = Deploy_Task::factory(array($_task));
        $task->_parent = $this;
        $task->_inherited_options = $this->_inherited_options !== null ?
                $this->_inherited_options : $this->get_options();

        $options = array();

        for($i = 1; $i < func_num_args(); $i++)
        {
            $options = Arr::merge($options, func_get_arg($i));
        }

        $options = Arr::overwrite($task->get_options(), $options, $task->_inherited_options);

        if(isset($task->_inherited_options['rollback']))
        {
            $options['rollback'] = $task->_inherited_options['rollback'];
        }

        $task->set_options($options);

        return $task;
    }

    public function parent()
    {
        return $this->_parent;
    }

    /**
     * @throws Deploy_Exception and prints help if input is not valid
     */
	public function execute()
	{
		// Validate $options
		$validation = $this->build_validation(Validation::factory($this->get_options()));

		if ($this->_parent !== null && ($this->_method === '_help' || ! $validation->check()))
		{
			echo View::factory('minion/error/validation')
				->set('task', Minion_Task::convert_class_to_task($this))
				->set('errors', $validation->errors($this->get_errors_file()));

            throw new Deploy_Exception('Invalid input parameters !');
		}

        ob_start();

        parent::execute();

        if(strlen($output = ob_get_clean()))
        {
            Log::instance()->add(Log::INFO, trim($output));
        }
	}

    /**
     * Check wherher task class uses Rollback trait
     *
     * @param Minion_Task $task
     * @return boolean
     */
    public static function is_rollback_supported(Minion_Task $task)
    {
        $current = $task;
        $traits = array();

        do
        {
            $traits = Arr::merge(class_uses($current), $traits);
        }
        while($current = get_parent_class($current));

        return in_array('Rollback', $traits);
    }

}
