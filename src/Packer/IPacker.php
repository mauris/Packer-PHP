<?php
namespace Packer;

interface IPacker{

    public function __construct($file);

    public function write($key, $value);

    public function keys();

    public function read($key);
    
    public function exists($key);
    
    public function delete($key);

    public function clear();

}
