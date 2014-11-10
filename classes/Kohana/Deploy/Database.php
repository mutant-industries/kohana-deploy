<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @package Deploy
 * @author Mutant Industries ltd. <mutant-industries@hotmail.com>
 */
abstract class Kohana_Deploy_Database extends Deploy_Task {
    use Rollback;

    protected $_db_config;

    protected function __construct()
    {
        parent::__construct();

        // database versioning is always done upon default database connection
        $this->_db_config = Kohana::$config->load('database')->{$this->_db_group()};
    }

    protected function _db_group()
    {
        return Database::$default;
    }

}
