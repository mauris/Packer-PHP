<?php

spl_autoload_register(function($class) {
    $class = ltrim($class, '\\');
    $fileName  = realpath(__DIR__ . '/../src') . DIRECTORY_SEPARATOR;
    $fileName .= str_replace(array('\\', '_'), DIRECTORY_SEPARATOR, $class) . '.php'; 
    if(file_exists($fileName)){
        require_once($fileName);
    }
});
