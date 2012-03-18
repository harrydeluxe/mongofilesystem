<?php

//abstract class MongoNode implements Sabre_DAV_INode
abstract class MongoNode
{
    /**
     * The path to the current node
     * 
     * @var string 
     */
    protected $path;

    /**
     * Sets up the node, expects a full path name 
     * 
     * @param string $path 
     * @return void
     */
    public function __construct($path)
    {
        $this->path = $path;
    }


    /**
     * Returns the name of the node 
     * 
     * @return string 
     */
    public function getName()
    {
        list(, $name) = Sabre_DAV_URLUtil::splitPath($this->path);
        return $name;
    }


    public function setName($name)
    {

        list($parentPath, ) = Sabre_DAV_URLUtil::splitPath($this->path);
        list(, $newName) = Sabre_DAV_URLUtil::splitPath($name);

        $newPath = $parentPath . '/' . $newName;
        M::rename($this->path, $newPath);

        $this->path = $newPath;
    }


    /**
     * Returns the last modification time, as a unix timestamp 
     * 
     * @return int 
     */
    public function getLastModified()
    {
        return null;
        //return 1296187451;
        return M::filemtime($this->path);
    }
}
