<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Precondition class similar to liquibase precondition however simplified ment
 * to be used in custom php changelog.
 *
 * @package Deploy
 * @author Mutant Industries ltd. <mutant-industries@hotmail.com>
 */
class Kohana_Precondition {

    /**
     * throw Deploy_Exception if precondition not satisfied
     */
    const HALT = 'halt';

    /**
     * mark ran and skip
     */
    const MARK_RAN = 'mark_ran';

    /**
     * warn but execute anyway
     */
    const WARN = 'warn';

    /**
     * expected precondition result
     *
     * @var mixed
     */
    protected $_expected;

    /**
     * @var string
     */
    protected $_onfail;

    /**
     * @var Closure
     */
    protected $_handler;

    /**
     * 'precondition' => new Precondition(
     *      array(
     *          'onfail' => Precondition::MARK_RAN,
     *          'handler' => function(){
     *              return Kohana::$environment === Kohana::PRODUCTION;
     *          },
     *      )
     *  ),
     *
     * @param array $options handler[, onfail][, expected]
     * @throws Precondition_Exception if precondition handler is not callable
     */
    public function __construct(array $options)
	{
		if( ! isset($options['handler']) || ! is_callable($options['handler']))
        {
            throw new Precondition_Exception('Precondition handler not set or not callable !');
        }

        $this->_onfail = isset($options['onfail']) ? $options['onfail'] : Precondition::HALT;
        $this->_expected = isset($options['expected']) ? $options['expected'] : true;

        $this->_handler = $options['handler'];
	}

    /**
     * @return boolean
     */
	public function check()
	{
        return call_user_func($this->_handler) === $this->_expected;
	}

    public function on_fail()
    {
        return $this->_onfail;
    }

}
