<?php

namespace WpEcs\Traits;

/**
 * A trait to implement lazy-loaded property values.
 * Use this to cache property values which are expensive to calculate and do not change.
 */
trait LazyPropertiesTrait
{
    /**
     * In-memory store for cached property values
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Magic method used to cache / lazy-load properties of an object.
     * If the property has not yet been calculated, it will execute
     * the method $this->get{$field} (where $field has been capitalized)
     *   e.g. a call to $this->expensiveProperty
     *        triggers this method with $field = 'expensiveProperty'
     *        which will execute $this->getExpensiveProperty()
     *        and cache the return value
     *
     * @param string $field
     *
     * @return mixed
     */
    public function __get($field)
    {
        $getMethod = 'get' . ucfirst($field);
        if (method_exists($this, $getMethod)) {
            if (!isset($this->cache[$field])) {
                $this->cache[$field] = call_user_func([$this, $getMethod]);
            }

            return $this->cache[$field];
        }
    }
}
