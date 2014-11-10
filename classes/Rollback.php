<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @package Deploy
 * @author Mutant Industries ltd. <mutant-industries@hotmail.com>
 */
trait Rollback {

    /**
     * Presets task method to _rollback if rollback option is set.
     *
     * @throws Rollback_Exception if rollback is not valid date
     */
    public function execute()
    {
        $options = $this->get_options();

        if(isset($options['rollback']))
        {
            if( ! Valid::date($options['rollback']))
            {
                throw new Rollback_Exception('invalid rollback timestamp given');
            }

            $this->_method = '_rollback';
        }

        parent::execute();
    }

    /**
     * Makes rollback always valid option.
     *
     * @param Validation $validation
     * @param type $option
     * @return void
     */
	public function valid_option(Validation $validation, $option)
	{
        if($option === 'rollback')
        {
            return;
        }

        parent::valid_option($validation, $option);
	}

    abstract protected function _rollback(array $params);

}
