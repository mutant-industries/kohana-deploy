<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Cache flush task.
 *
 * @package Deploy
 * @author Mutant Industries ltd. <mutant-industries@hotmail.com>
 */
class Kohana_Task_Cache_Flush extends Deploy_Task {
    use Rollback;

    /**
     * cache_type - file|memcache...
     * tags - comma separated tags, optional
     *
     * @var array
     */
    protected $_options = array
    (
        'cache_type' => null,
        'tags' => null,
    );

    protected function _execute(array $params)
    {
        $cache = Cache::instance($params['cache_type']);

        if( ! empty($params['tags']))
        {
            if( ! $cache instanceof Cache_Tagging)
            {
                throw new Cache_Exception('Unable to flush by tags, :driver doesn\'t support tagging !',
                        array(':driver' => $params['cache_type']));
            }

            foreach (explode(',', $params['tags']) as $tag)
            {
                $cache->delete_tag($tag);
            }
        }
        else
        {
            $cache->delete_all();
        }

        return ucfirst($params['cache_type']).' flushed'.( ! empty($params['tags']) ? ' following tags: '.$params['tags'] : '').'.';
    }

    /**
     * Rollback actually equals execution - for example:
     *  column is added to table => table cache must be flushed
     *  on rollback the column is removed => table cache must be flushed the same way again
     *
     * @param array $params
     * @return String
     */
    protected function _rollback(array $params)
    {
        return $this->_execute($params);
    }

    public function build_validation(Validation $validation)
    {
        return parent::build_validation($validation)
            ->rule('cache_type', 'not_empty')
            ->rule('cache_type', function($type) {
                return Kohana::$config->load('cache')->offsetExists($type);
            })
            ->rule('tags', 'regex', array(':value', '/^\w+(,\w+)*$/'));
    }

}
