<?php
namespace Packer;

/**
 * Packer class
 * 
 * Performs and follows Packer functionality and protocols
 *
 * @author Sam-Mauris Yong / mauris@hotmail.sg
 * @copyright Copyright (c) 2010-2012, Sam-Mauris Yong
 * @license http://www.opensource.org/licenses/bsd-license New BSD License
 * @package Packer
 * @since 1.0.0
 */
class Packer {
    
    /**
     * Header Signature
     * determines that the file is a valid Packer file
     */
    const SIGNER = 0xB5;
    
    /**
     * The pathname to the file
     * @var string
     */
    private $file;
    
    /**
     * The resource handle
     * @var resource
     */
    private $handle;
    
    /**
     * The index array
     * @var array
     */
    private $index = array();
    
    public function __construct($file){
        $this->file = $file;
        $this->checkFile();
        $this->index();
    }
    
    protected static function createFile($file){
        $handle = fopen($file, 'wb');
        fwrite($handle, pack('C*', self::SIGNER));
        fclose($handle);
    }
    
    protected function checkFile(){
        if(!file_exists($this->file)){
            self::createFile($this->file);
        }
        $this->handle = fopen($this->file, 'rb+');
        $data = fread($this->handle, 1);
        $unpackedData = unpack('C*', $data);
        if($unpackedData[1] != self::SIGNER){
            throw new Exception('Not valid Packer file');
        }
    }
    
    protected function index(){
        while(!feof($this->handle)){
            $startPos = ftell($this->handle);
            list($keyLength, $valueLength) = $this->readMeta();
            if(!$keyLength || !$valueLength){
                break;
            }
            $key = fread($this->handle, $keyLength);
            $this->index[$key] = $startPos;
            fseek($this->handle, $valueLength, SEEK_CUR);
        }
    }
    
    protected function readMeta(){
        $meta = fread($this->handle, 8);
        if($meta){
            list(, $keyLength, $valueLength) = unpack('N*', $meta);
            return array($keyLength, $valueLength);
        }else{
            return array(0, 0);
        }
    }
    
    protected static function writeEntry($handle, $key, $value){
        $value = json_encode($value);
        fwrite($handle, pack('N*', strlen($key)));
        fwrite($handle, pack('N*', strlen($value)));
        fwrite($handle, $key);
        fwrite($handle, $value);
    }
    
    public function write($key, $value){
        if(array_key_exists($key, $this->index)){
            $this->overwrite($key, $value);
        }else{
            fseek($this->handle, 0, SEEK_END);
            $pos = ftell($this->handle);
            self::writeEntry($this->handle, $key, $value);
            $this->index[$key] = $pos;
        }
    }
    
    public function keys(){
        return array_keys($this->index);
    }
    
    public function exist($key){
        return array_key_exists($key, $this->index);
    }
    
    public function read($key){
        if(array_key_exists($key, $this->index)){
            fseek($this->handle, $this->index[$key]);
            list($keyLength, $valueLength) = $this->readMeta();
            fseek($this->handle, $keyLength, SEEK_CUR);
            return json_decode(fread($this->handle, $valueLength));
        }
    }
    
    protected function overwrite($key, $value = null){
        fseek($this->handle, 1);
        self::createFile($this->file . '.tmp');
        $tmp = fopen($this->file . '.tmp', 'rb+');
        fseek($tmp, 1);
        $this->index = array();
        while(!feof($this->handle)){
            list($keyLength, $valueLength) = $this->readMeta();
            if(feof($this->handle)){
                break;
            }
            $startPos = ftell($tmp);
            $inputKey = fread($this->handle, $keyLength);
            if($inputKey == $key){
                fseek($this->handle, $valueLength, SEEK_CUR);
                if(func_num_args() == 2){
                    self::writeEntry($tmp, $key, $value);
                }
            }else{
                $inputValue = json_decode(fread($this->handle, $valueLength));
                self::writeEntry($tmp, $inputKey, $inputValue);
            }
            $this->index[$inputKey] = $startPos;
        }
        fclose($tmp);
        fclose($this->handle);
        unlink($this->file);
        copy($this->file . '.tmp', $this->file);
        unlink($this->file . '.tmp');
        $this->checkFile();
    }
    
    public function delete($key){
        if(array_key_exists($key, $this->index)){
            $this->overwrite($key);
            unset($this->index[$key]);
        }
    }
    
}