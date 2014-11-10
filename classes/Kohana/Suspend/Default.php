<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Simple Suspend implementation using lock file.
 *
 * @package Deploy
 * @author Mutant Industries ltd. <mutant-industries@hotmail.com>
 */
class Kohana_Suspend_Default extends Suspend {

    /**
     *
     * @return mixed false|int suspended timestamp
     */
    public function check_suspended()
    {
        return file_exists($this->_get_lock_file()) ? filemtime($this->_get_lock_file()) : false;
    }

    /**
     * delete lock file
     */
    public function mark_done()
    {
        file_exists($this->_get_lock_file()) && unlink($this->_get_lock_file());
    }

    /**
     * create lock file
     *
     * @throws HTTP_Exception_403
     */
    public function mark_suspended()
    {
        // redundant security check
        if(PHP_SAPI !== 'cli')
        {
            throw new HTTP_Exception_403;
        }

        touch($this->_get_lock_file());

        chmod($this->_get_lock_file(), 0660);
    }

    protected function _get_lock_file()
    {
        return APPPATH.'suspended.lock';
    }

}
