<?php
namespace Packer;

/**
 * File class
 * 
 * Performs and follows Packer file functionality and protocols
 *
 * @author Sam-Mauris Yong / mauris@hotmail.sg
 * @copyright Copyright (c) 2010-2012, Sam-Mauris Yong
 * @license http://www.opensource.org/licenses/bsd-license New BSD License
 * @package Packer
 * @since 1.0.0
 */
class File implements IPacker, \ArrayAccess, \IteratorAggregate
{
    /**
     * Header Signature
     * determines that the file is a valid Packer file
     * Decimal 181
     */
    const SIGNER = 0xB5;

    /**
     * The pathname to the file
     *
     * @var string
     */
    private $file;

    /**
     * The resource handle
     *
     * @var resource
     */
    private $handle;

    /**
     * The index array
     *
     * @var array
     */
    private $index = array();

    /**
     * Create a new Packer object
     *
     * @param string $file Path name to a Packer file. If file does not exist,
     *                     it will be created.
     */
    public function __construct($file)
    {
        $this->file = $file;
        $this->checkFile();
        $this->index();
    }

    /**
     * Perform a Packfire file creation
     * 
     * @param string $file Path name to the file to be created. 
     */
    protected static function createFile($file)
    {
        $handle = fopen($file, 'wb');
        self::writeHeaderSignature($handle);
        fclose($handle);
    }

    /**
     * Check a Packer file to see if the file is valid
     *
     * @throws Exception Thrown when file is invalid.
     */
    protected function checkFile()
    {
        if (!file_exists($this->file)) {
            self::createFile($this->file);
        }
        $this->handle = fopen($this->file, 'rb+');
        flock($this->handle, LOCK_SH);
        $data = fread($this->handle, 1);
        if ($data) {
            $unpackedData = unpack('C*', $data);
            if ($unpackedData[1] != self::SIGNER) {
                throw new Exception('Not valid Packer file');
            }
        } else {
            self::writeHeaderSignature($this->handle);
        }
    }

    /**
     * Perform indexing of the file
     */
    protected function index()
    {
        while (!feof($this->handle)) {
            $startPos = ftell($this->handle);
            list($keyLength, $valueLength) = $this->readMeta();
            if (!$keyLength || !$valueLength) {
                break;
            }
            $key = fread($this->handle, $keyLength);
            $this->index[$key] = $startPos;
            fseek($this->handle, $valueLength, SEEK_CUR);
        }
    }

    /**
     * Read the meta data (8 bytes) of the next entry
     *
     * @return array Returns the data in array($keyLength, $valueLength)
     */
    protected function readMeta()
    {
        $meta = fread($this->handle, 8);
        if ($meta) {
            list(, $keyLength, $valueLength) = unpack('N*', $meta);
            return array($keyLength, $valueLength);
        } else {
            return array(0, 0);
        }
    }

    /**
     * Write a new entry a file handle
     * 
     * @param resource $handle The file handle
     * @param string $key The key name
     * @param mixed $value The value to be written
     * @param boolean $encode (optional) determine if an encoding operation
     *                  is required. Defaults to true.
     */
    protected static function writeEntry($handle, $key, $value, $encode = true)
    {
        if ($encode) {
            $value = json_encode($value);
        }
        fwrite($handle, pack('N*', strlen($key)));
        fwrite($handle, pack('N*', strlen($value)));
        fwrite($handle, $key);
        fwrite($handle, $value);
    }

    protected static function writeHeaderSignature($handle)
    {
        fwrite($handle, pack('C*', self::SIGNER));
    }

    /**
     * Write an entry to Packer
     * If the same key already exists in Packer, the key will be overwritten.
     *
     * @param string $key The key to identify
     * @param mixed $value The value to be written
     */
    public function write($key, $value)
    {
        if (array_key_exists($key, $this->index)) {
            $this->overwrite($key, $value);
        } else {
            fseek($this->handle, 0, SEEK_END);
            $pos = ftell($this->handle);
            self::writeEntry($this->handle, $key, $value);
            $this->index[$key] = $pos;
        }
    }

    /**
     * Get all the keys in the Packer file
     *
     * @return array Returns a list of keys
     */
    public function keys()
    {
        return array_keys($this->index);
    }

    /**
     * Check if a key exists in the Packer file
     *
     * @param string $key The key to be checked
     * @return boolean Returns true if the key exists, false otherwise.
     */
    public function exists($key)
    {
        if (is_string($key) || is_integer($key)) {
            return array_key_exists($key, $this->index);
        }
        return false;
    }

    /**
     * Read the data for a given key
     *
     * @param string $key The key to retrieve the data
     * @return mixed Returns the data read from the file
     */
    public function read($key)
    {
        if (array_key_exists($key, $this->index)) {
            fseek($this->handle, $this->index[$key]);
            list($keyLength, $valueLength) = $this->readMeta();
            fseek($this->handle, $keyLength, SEEK_CUR);
            return json_decode(fread($this->handle, $valueLength));
        }
    }

    /**
     * Performs an overwrite operation by removing the entry then overwriting
     * it with a different value.
     *
     * @param string $key The key of the entry to be overwritten
     * @param mixed $value (optional) The value to write over.
     */
    protected function overwrite($key, $value = null)
    {
        fseek($this->handle, 1);
        $tmp = tmpfile();
        $startPos = $this->index[$key];
        if ($startPos - 1 > 0) {
            fwrite($tmp, fread($this->handle, $startPos - 1));
        }
        list($keyLength, $valueLength) = $this->readMeta();
        if (func_num_args() == 2) {
            self::writeEntry($tmp, $key, $value);
        }
        $reindexpos = ftell($tmp) + 1;
        $pos = ftell($this->handle) + $keyLength + $valueLength;
        fseek($this->handle, 0, SEEK_END);
        $end = ftell($this->handle);
        if ($end - $pos > 0) {
            fseek($this->handle, $pos);
            fwrite($tmp, fread($this->handle, $end - $pos));
        }
        ftruncate($this->handle, 1);
        fseek($tmp, 0, SEEK_END);
        $length = ftell($tmp);
        fseek($tmp, 0);
        fwrite($this->handle, fread($tmp, $length));
        fclose($tmp);
        fseek($this->handle, $reindexpos);
        $this->index();
    }

    /**
     * Perform a release by unlocking the file and closing file handle.
     */
    protected function release()
    {
        flock($this->handle, LOCK_UN);
        fclose($this->handle);
    }

    /**
     * Clear all entries from the Packer file
     */
    public function clear()
    {
        // we truncate to 1 because we still want
        // to keep that header signature.
        ftruncate($this->handle, 1);
        $this->index = array(); // clear index
    }

    /**
     * Perform release and clean up for the Packer
     */
    public function __destruct()
    {
        $this->release();
    }

    /**
     * Delete an entry from the Packer file
     *
     * @param string $key Key of the entry to be removed.
     */
    public function delete($key)
    {
        if (array_key_exists($key, $this->index)) {
            $this->overwrite($key);
            unset($this->index[$key]);
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
