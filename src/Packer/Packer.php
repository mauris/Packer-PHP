<?php
namespace Packer;

/**
 * Packer class
 * 
 * Performs Packer functionality and protocols with internal caching
 *
 * @author Sam-Mauris Yong / mauris@hotmail.sg
 * @copyright Copyright (c) 2010-2012, Sam-Mauris Yong
 * @license http://www.opensource.org/licenses/bsd-license New BSD License
 * @package Packer
 * @since 1.0.3
 */
class Packer implements IPacker, \ArrayAccess, \IteratorAggregate
{
    /**
     * The actual Packer file access object
     * @var Packer\File
     */
    private $file;

    private $cache = array();

    private $writeCache = array();

    private $removeCache = array();

    public function __construct($file)
    {
        $this->file = new File($file);
    }

    public function write($key, $value)
    {
        if (isset($this->removeCache[$key])) {
            unset($this->removeCache[$key]);
        }
        $this->writeCache[$key] = $value;
        $this->cache[$key] = $value;
    }

    public function keys()
    {
        $keys = $this->file->keys();
        $keys = array_diff($keys, array_keys($this->removeCache));
        $keys += array_keys($this->writeCache);
        return $keys;
    }

    public function read($key)
    {
        if (isset($this->removeCache[$key])) {
            return null;
        }
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->file->read($key);
        }
        return $this->cache[$key];
    }

    public function exists($key)
    {
        if (isset($this->removeCache[$key])) {
            return false;
        }
        if (isset($this->writeCache[$key])) {
            return true;
        }
        return $this->file->exists($key);
    }

    public function delete($key)
    {
        if (isset($this->writeCache[$key])) {
            unset($this->writeCache[$key]);
        }
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
        }
        $this->removeCache[$key] = true;
    }

    public function clear()
    {
        $this->cache = array();
        $this->writeCache = array();
        $this->removeCache = array_flip($this->file->keys());
    }

    public function __destruct()
    {
        foreach ($this->removeCache as $key => $value) {
            $this->file->delete($key);
        }
        foreach ($this->writeCache as $key => $value) {
            $this->file->write($key, $value);
        }
    }

    /**
     *
     * @param string $offset
     * @return mixed
     * @internal
     * @ignore
     */
    public function offsetExists($offset)
    {
        return $this->exists($offset);
    }

    /**
     *
     * @param string $offset
     * @return mixed
     * @internal
     * @ignore
     */
    public function offsetGet($offset)
    {
        return $this->read($offset);
    }

    /**
     *
     * @param string $offset
     * @param mixed $value
     * @internal
     * @ignore
     */
    public function offsetSet($offset, $value)
    {
        $this->write($offset, $value);
    }

    /**
     *
     * @param string $offset
     * @internal
     * @ignore
     */
    public function offsetUnset($offset)
    {
        $this->delete($offset);
    }

    /**
     *
     * @return \ArrayIterator
     * @internal
     * @ignore
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->keys());
    }
}
