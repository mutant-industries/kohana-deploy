<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @package Deploy
 * @author Mutant Industries ltd. <mutant-industries@hotmail.com>
 */
class Model_Deploy_Changelog extends ORM {

    /**
     * description that doesn't match any used by liquibase
     */
    const RECORD_IDENTIFIER = '__custom__';

    /**
     * liquibase changelog table
     */
    const TABLE_NAME = 'databasechangelog';

    protected $_table_names_plural = false;

    /**
     * liquibase databasechangelog is usually created with capitalized columns except for postgresql..
     *
     * @var string
     */
    protected $_column_name_modifier = 'strtoupper';

    /**
     * Get all changelogs executed since certain time
     *
     * @param string $time
     * @return Model_Changelog
     */
    public function executed_since($time)
    {
        return $this->where(call_user_func($this->_column_name_modifier, 'dateexecuted'), '>=', $time);
    }

    public function save(\Validation $validation = NULL)
    {
        $last_executed = DB::select(
                        array(DB::expr('max(orderexecuted)'), 'last_executed')
                )->from($this->_table_name)->execute();

        // liquibase compatible
        $this->dateexecuted($this->_db instanceof Database_SQLite ? gmdate('Y-m-d H:i:s') : Db::expr('now()'))
                ->exectype('EXECUTED')
                ->description(Model_Changelog::RECORD_IDENTIFIER)
                ->orderexecuted(intval($last_executed[0]['last_executed']) + 1)
                ->md5sum($this->hash());

        return parent::save($validation);
    }

    /**
     * While sharing the same table with liquibase we do not want to touch liquibase records
     *
     * @param type $type
     * @return ORM
     */
    protected function _build($type)
    {
        $this->where(call_user_func($this->_column_name_modifier, 'description'), '=', Model_Changelog::RECORD_IDENTIFIER);

        return parent::_build($type);
    }

    public function hash()
    {
        return md5($this->id().$this->author().$this->filename());
    }

    /**
     * attribute getter / setter
     *
     * @param type $name
     * @param type $arguments
     * @return \Model_Deploy_Changelog|string
     */
    public function __call($name, $arguments)
    {
        $attribute = call_user_func($this->_column_name_modifier, $name);

        if ( ! isset($arguments[0]))
        {
            return $this->$attribute;
        }

        $this->$attribute = $arguments[0];

        return $this;
    }

    protected function _initialize()
    {
        $this->_db = Database::instance();

        if ($this->_db instanceof Database_PostgreSQL)
        {
            $this->_column_name_modifier = 'strtolower';
        }

        $this->_table_name = call_user_func($this->_column_name_modifier, Model_Changelog::TABLE_NAME);
        $this->_primary_key = call_user_func($this->_column_name_modifier, 'id');

        $this->_sorting = array(
            call_user_func($this->_column_name_modifier, 'dateexecuted') => 'desc',
            call_user_func($this->_column_name_modifier, 'orderexecuted') => 'desc',
        );

        parent::_initialize();
    }

}
