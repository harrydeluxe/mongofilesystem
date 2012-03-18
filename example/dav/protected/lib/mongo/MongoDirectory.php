<?php

class MongoDirectory extends MongoNode implements Sabre_DAV_ICollection, Sabre_DAV_IQuota
{
    public function createFile($name, $data = null)
    {
        $newPath = $this->path . '/' . $name;
        M::file_put_contents($newPath, $data);
    }


    public function createDirectory($name)
    {
        $newPath = $this->path . '/' . $name;
        M::mkdir($newPath);
    }


    public function getChild($name)
    {
        $path = $this->path . '/' . $name;
        
        if (!M::file_exists($path))
            throw new Sabre_DAV_Exception_NotFound('File with name ' . $path . ' could not be located');

        if (M::is_dir($path))
        {

            return new MongoDirectory($path);
        }
        else
        {
            return new MongoFile($path);
        }
    }


    public function getChildren()
    {
        $nodes = array();
        foreach(M::scandir($this->path) as $node)
            if ($node != '.' && $node != '..')
                $nodes[] = $this->getChild($node);
        return $nodes;
    }


    public function childExists($name)
    {

        $path = $this->path . '/' . $name;
        return M::file_exists($path);
    }


    public function delete()
    {

        foreach($this->getChildren() as $child)
            $child->delete();
        M::rmdir($this->path);
    }

    /**
     * Returns available diskspace information 
     * 
     * @return array 
     */
    public function getQuotaInfo()
    {
        return array(
            100000000, 200000000
        );
    }
}
