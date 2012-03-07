<?php
/**
 * MongoFsStatic
 *
 * @copyright Copyright (c) 2011 Harald Hanek
 * @license http://www.opensource.org/licenses/mit-license.php
 */

class M extends MongoFsStatic
{

}

class MongoFsStatic
{
    protected static $_fs = null;

    protected static function getFs()
    {
        if (self::$_fs == null)
        {
            try
            {
                $connection = new Mongo('mongodb://username:password@127.0.0.1/foo', array(
                    'persist' => 'freename'
                ));
                $db = $connection->selectDB('foo');
                self::$_fs = new MongoFs($db);
            }
            catch(Exception $e)
            {
                exit('keine verbindung zu mongo db');
            }
        }
        return self::$_fs;
    }


    public static function get($id)
    {
        return self::getFs()->get($id);
    }


    public static function etag($file)
    {
        return self::getFs()->etag($file);
    }


    public static function mimetype($file)
    {
        return self::getFs()->mimetype($file);
    }


    public static function fileatime($file)
    {
    }

    public static function filemtime($file)
    {
        return self::getFs()->filemtime($file);
    }

    public static function filesize($file)
    {
        return self::getFs()->filesize($file);
    }


    public static function filetype($file)
    {
    }


    public static function is_file($file, $returnObject = false)
    {
        return self::getFs()->is_file($file, $returnObject);
    }


    public static function file_exists($file)
    {
        return self::getFs()->file_exists($file);
    }


    public static function basename($file)
    {
        return self::getFs()->basename($file);
    }


    public static function readfile($file)
    {
        return self::getFs()->readfile($file);
    }


    public static function file_get_contents($file)
    {
        return self::getFs()->file_get_contents($file);
    }


    public static function file_put_contents($file, $data, $options = null)
    {
        return self::getFs()->file_put_contents($file, $data, $options);
    }


    public static function import($file, $realfile, $options = null)
    {
        return self::getFs()->import($file, $realfile, $options);
    }


    public static function rename($oldname, $newname)
    {
        return self::getFs()->rename($oldname, $newname);
    }


    public static function delete($file)
    {
        return self::getFs()->delete($file);
    }


    public static function unlink($file)
    {
        return self::getFs()->unlink($file);
    }


    public static function copy($source, $dest)
    {
        return self::getFs()->copy($source, $dest);
    }


    public static function scandir($path, $sortorder = 0)
    {
        return self::getFs()->scandir($path, $sortorder);
    }


    public static function readdir($path)
    {
        return self::getFs()->readdir($path);
    }


    public static function rmdir($path)
    {
        return self::getFs()->rmdir($path);
    }


    public static function dirname($dir)
    {
        return self::getFs()->dirname($dir);
    }


    public static function mkdir($path, $recursive = true)
    {
        return self::getFs()->mkdir($path, $recursive);
    }


    public static function is_dir($path, $returnObject = false)
    {
        return self::getFs()->is_dir($path, $returnObject);
    }


    public static function chmod($filename, $mode)
    {
        return self::getFs()->chmod($filename, $mode);
    }

}