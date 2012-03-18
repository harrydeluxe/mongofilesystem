<?php

class MongoFile extends MongoNode implements Sabre_DAV_IFile
{

    public function put($data)
    {
        M::file_put_contents($this->path, $data);
    }


    public function get()
    {
        $ausgabe = M::readfile($this->path);
        return $ausgabe->getBytes();
    }


    public function getSize()
    {
        return M::filesize($this->path);
    }


    public function getContentType()
    {
        return M::mimetype($this->path);
    }


    public function getLastModified()
    {
        return M::filemtime($this->path);
    }


    public function delete()
    {
        M::unlink($this->path);
    }


    public function getETag()
    {
        return M::etag($this->path);
    }
}