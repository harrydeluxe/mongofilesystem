<?php
/**
 * MongoFs
 *
 * @copyright Copyright (c) 2011 Harald Hanek
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class MongoFs
{
	const WEBSAFE = true;

	protected $_db;
	protected $_fs;

	protected $_tmpfile = array();

	protected $_collectionFolders = 'folders.files';
	protected $_collectionFs = 'folders';


	public function __construct($db)
	{
		$this->_db = $db;
		$this->_fs = $this->_db->getGridFS($this->_collectionFs);
	}


	/**
	 * Checks if file exists in temporary array
	 * @param string $filename
	 * @return Returns the file if exists or FALSE.
	 */
	private function _g($filename)
	{
		$file = trim($filename, '/');
		return (isset($this->_tmpfile[$file])) ? $this->_tmpfile[$file] : false;
	}


	/**
	 * Stores a file into a temporary array
	 * @param string $filename
	 * @param object $record
	 */
	private function _s($filename, $record)
	{
		if($record->file['type'] == 'file')
			$this->_tmpfile[trim($filename, '/')] = $record;
	}


	/**
	 * Get a file or folder from GridFS by id
	 * @param string $id
	 * @return Returns a file or folder if exists or FALSE.
	 */
	public function get($id)
	{
		if(($fe = $this->_fs->findOne(array(
			'_id' => new MongoId($id)
		))) != null)
		{
			return $fe;
		}
		return false;
	}


	/**
	 * Returns the md5 Hash of a file
	 * @param string $filename
	 * @return Returns the md5 Hash of a file or NULL.
	 */
	public function etag($filename)
	{
		if(($fe = $this->readfile($filename)) != false)
			return $fe->file['md5'];

		return null;
	}


	/**
	 * Detect MIME Content-type for a file
	 * @param string $filename
	 * @return string or NULL
	 */
	public function mimetype($filename)
	{
		if(($fe = $this->readfile($filename)) != false)
		{
			if(isset($fe->file['mimetype']))
			{
				return $fe->file['mimetype'];
			}
		}

		return null;
	}


	/**
	 * Gets last access time of file
	 * @param string $filename
	 * @return Returns the time the file was last accessed, or FALSE on failure. The time is returned as a Unix timestamp.
	 */
	public function fileatime($filename)
	{
	}

	
	/**
	 * This function returns the time when the data blocks of a file were being written to, that is, the time when the content of the file was changed.
	 * @param string $filename
	 * @return Returns the time the file was last modified, or NULL on failure. The time is returned as a Unix timestamp, which is suitable for the date() function.
	 */
	public function filemtime($filename)
	{
		if(($fe = $this->readfile($filename)) != false)
		{
			if(isset($fe->file['uploadDate']))
			{
				$date = $fe->file['uploadDate'];
				return $date->sec;
			}
			return null;
		}
		
		return null;
	}


	/**
	 * Gets the size for the given file.
	 * @param string $filename
	 * @return Returns the size of the file in bytes or 0.
	 */
	public function filesize($filename)
	{
		if(($fe = $this->readfile($filename)) != false)
			return $fe->getSize();

		return 0;
	}


	/**
	 * Gets file type
	 * @param string $filename
	 * @return Returns the type of the file.
	 */
	public function filetype($filename)
	{
	}


	/**
	 * Tells whether the filename is a regular file
	 * @param string $filename
	 * @param booleand $returnObject If TRUE, returns the file.
	 * @return Returns TRUE if the filename exists and is a regular file, FALSE otherwise.
	 */
	public function is_file($filename, $returnObject = false)
	{
		if(($fe = $this->_g($filename)) || ($fe = $this->_fs->findOne(array(
			'type' => 'file', 'filename' => trim($filename, '/')
		))) != null)
		{
			$this->_s($filename, $fe);
			if($returnObject)
				return $fe;
			return true;
		}
		return false;
	}


	/**
	 * Checks whether a file or directory exists
	 * @param string $filename
	 * @return Returns TRUE if the file or directory specified by filename exists; FALSE otherwise.
	 */
	public function file_exists($filename)
	{
		if(($fe = $this->_g($filename)) || ($fe = $this->_fs->findOne(array(
				'type' => array(
					'$in' => array(
						'folder', 'file'
					)
				),
				'filename' => trim($filename, '/')
		))) != null)
		{
			$this->_s($filename, $fe);
			return true;
		}
		return false;
	}


	/**
	 * Returns trailing name component of path
	 * @param string $path
	 * @return Returns the base name of the given path.
	 */
	public function basename($path)
	{
	}


	/**
	 * Outputs a file
	 * @param string $filename
	 * @return Returns the file specified by filename or FALSE on failure.
	 */
	public function readfile($filename)
	{
		if(($fe = $this->_g($filename)) || ($fe = $this->_fs->findOne(array(
			'type' => 'file', 'filename' => trim($filename, '/')
		))) != null)
		{
			$this->_s($filename, $fe);
			return $fe;
		}
		return false;
	}


	/**
	 * Reads entire file into a string
	 * @param string $filename
	 * @return The function returns the read data or FALSE on failure.
	 */
	public function file_get_contents($filename)
	{
		if(($fe = $this->_fs->findOne(array(
			'filename' => trim($filename, '/')
		))) != null)
		{
			return $fe->getBytes();
		}
		return false;
	}


	/**
	 * Write a string to GridFS
	 * @param string $filename
	 * @param mixed $data
	 * @param mixed $options
	 * @return The function returns the number of bytes that were written to the file, or FALSE on failure.
	 */
	public function file_put_contents($filename, $data, $options = null)
	{
		$file = trim($filename, '/');

		if(gettype($data) == 'resource')
		{
			$s = stream_get_contents($data);
			fclose($data);
			$data = $s;
		}

		// @todo mime_type ueber extension im name rausholen				
		// @mime_content_type($data)

		// check if exists
		if(($fe = $this->_fs->findOne(array(
			'filename' => $file
		))) != null)
		{
			$id = $fe->file['_id'];
			if(md5($data) == $fe->file['md5'])
			{
				return $id; // dateien sind identisch
			}
			$this->_fs->remove(array(
				'_id' => $id
			));
		}

		$p = explode('/', $file);

		$name = array_pop($p);

		$path = implode('/', array_slice($p, 0, count($p)));

		// @todo auto ordner dafuer erstellen
		$this->mkdir($path);

		$parent = count($p) > 1 ? implode('/', array_slice($p, 0, count($p) - 1)) : null;

		$meta = array(
				'name' => $name,
				'filename' => $file,
				'path' => $path,
				'parent' => $parent,
				'type' => 'file',
				'meta' => $options,
				'filetype' => null
		);
		if(isset($id))
			$meta['_id'] = $id;

		return $this->_fs->storeBytes($data, $meta);
	}


	/**
	 * Imports a file from filesystem to GridFS
	 * @param string $filename
	 * @param string $realfile
	 * @param mixed $options
	 * @return Ambiguous
	 */
	public function import($filename, $realfile, $options = null)
	{
		$file = trim($filename, '/');

		// check if exists
		if(($fe = $this->_fs->findOne(array(
			'filename' => $file
		))) != null)
		{
			$id = $fe->file['_id'];
			if(md5_file($realfile) == $fe->file['md5'])
			{
				return $id; // dateien sind identisch
			}
			$this->_fs->remove(array(
				'_id' => $id
			));
		}

		$p = explode('/', $file);

		$name = array_pop($p);

		$path = implode('/', array_slice($p, 0, count($p)));

		// @todo auto ordner dafuer erstellen
		$this->mkdir($path);

		$parent = count($p) > 1 ? implode('/', array_slice($p, 0, count($p) - 1)) : null;

		$meta = array(
				'name' => $name,
				'filename' => $file,
				'path' => $path,
				'parent' => $parent,
				'type' => 'file',
				'mimetype' => GFileHelper::getMimeTypeByExtension($realfile),
				'meta' => $options
		);
		if(isset($id))
			$meta['_id'] = $id;

		return $this->_fs->storeFile($realfile, $meta);
	}

	
	/**
	 * Renames a file or directory
	 * @todo nach dem rename tmp aktualisieren oder loeschen
	 * @param string $oldname
	 * @param string $newname
	 * @param boolean $overwrite
	 * @throws Exception
	 * @return boolean Returns TRUE on success or FALSE on failure.
	 */
	public function rename($oldname, $newname, $overwrite = false)
	{
		$oldname = trim($oldname, '/');
		$newname = trim($newname, '/');

		$p = explode('/', $newname);
		$name = array_pop($p);

		if($this->file_exists($newname))
		{
			if($overwrite === true)
				$this->unlink($newname);
			else
				throw new Exception("Could not rename '" . $oldname . "'. A file or folder with the specified name already exists");
		}


		if($this->is_file($oldname))
		{
			if(($fe = $this->_db->selectCollection($this->_collectionFolders)->findOne(array(
				'type' => 'file', 'filename' => $oldname
			))) != null)
			{
				$npath = $this->dirname($newname);

				$meta = array(
						'$set' => array(
								'name' => $name,
								'filename' => $newname,
								'path' => $npath,
								'parent' => $this->dirname($npath)
						)
				);

				$this->_db->selectCollection($this->_collectionFolders)->update(array(
					'_id' => $fe['_id']
				), $meta, array(
					"safe" => true
				));
				return true;
			}
		}

		if($this->is_dir($oldname))
		{
			if(($cursor = $this->_db->selectCollection($this->_collectionFolders)->find(array(
					'type' => array(
						'$in' => array(
							'folder', 'file'
						)
					),
					'$or' => array(
							array(
									'parent' => new MongoRegex("/^" . $oldname . "/i")
							),
							array(
									'path' => new MongoRegex("/^" . $oldname . "/i")
							)
					)
			))) != null)
			{
				foreach($cursor as $record)
				{
					if($record['filename'] == $oldname)
					{
						// der hauptordner
						$meta = array(
								'$set' => array(
										'name' => $name,
										'filename' => $newname,
										'path' => $newname
								)
						);

						$this->_db->selectCollection($this->_collectionFolders)->update(array(
							'_id' => $record['_id']
						), $meta, array(
							"safe" => true
						));
					}
					else
					{
						$meta = array(
								'$set' => array(
										'filename' => $this->_strr($record['filename'], $oldname, $newname),
										'path' => $this->_strr($record['path'], $oldname, $newname),
										'parent' => $this->_strr($record['parent'], $oldname, $newname)
								)
						);

						$this->_db->selectCollection($this->_collectionFolders)->update(array(
							'_id' => $record['_id']
						), $meta, array(
							"safe" => true
						));
					}
				}

				return true;
			}
		}

		return false;
	}

	
	private function _strr($string, $oldname, $newname)
	{
		if(strpos($string, $oldname) === 0)
		{
			return substr_replace($string, $newname, 0, strlen($oldname));
		}
		return $string;
	}


	/**
	 * See unlink()
	 * @param string $filename
	 */	
	public function delete($filename)
	{
		return $this->unlink($filename);
	}


	/**
	 * Deletes a file
	 * @todo auch in tmp loeschen
	 * @param string $filename
	 * @return Returns TRUE on success or FALSE on failure.
	 */
	public function unlink($filename)
	{
		if(($fe = $this->_fs->findOne(array(
			'filename' => trim($filename, '/')
		))) != null)
		{
			$id = $fe->file['_id'];
			$this->_fs->remove(array(
				'_id' => $id
			));
			return true;
		}
		return false;
	}


	/**
	 * Copies file
	 * @param string $source
	 * @param string $dest
	 * @return Returns TRUE on success or FALSE on failure.
	 */
	public function copy($source, $dest)
	{
	}


	/**
	 * List files and directories inside the specified path
	 * @param string $path
	 * @param number $sortorder
	 * @return Returns an array of files and directories from the directory or NULL.
	 */
	public function scandir($path, $sortorder = 0)
	{
		$path = trim($path, '/');

		$sortorder = ($sortorder === 1) ? -1 : 1;

		$criteria = array(
				'$or' => array(
						array(
							'parent' => trim($path, '/'), 'type' => 'folder'
						),
						array(
							'path' => trim($path, '/'), 'type' => 'file'
						)
				)
		);


		$sort = array(
			'type' => -1, 'name' => $sortorder
		);


		if(($cursor = $this->_fs->find($criteria)->sort($sort)) != null)
		{
			$p = explode('/', $path);

			$tmp = array();

			if(count($p) > 1)
			{
				array_push($tmp, '.');
				array_push($tmp, '..');
			}

			foreach($cursor as $record)
			{
				$this->_s($record->file['filename'], $record);
				array_push($tmp, $record->file['name']);
			}
			return $tmp;
		}
		return null;
	}


	/**
	 * Read entry from directory handle
	 * @param resource $dir
	 */
	public function readdir($dir)
	{
	}


	/**
	 * Removes directory
	 * @todo auch in tmp loeschen
	 * @param string $dir
	 * @return Returns TRUE on success or FALSE on failure.
	 */
	public function rmdir($dir)
	{
		$dir = trim($dir, '/');

		if($this->is_dir($dir))
		{
			if(($cursor = $this->_fs->find(array(
					'type' => array(
						'$in' => array(
							'folder', 'file'
						)
					),
					'$or' => array(
							array(
								'parent' => new MongoRegex("/^" . $dir . "/i")
							),
							array(
								'path' => new MongoRegex("/^" . $dir . "/i")
							)
					)
			))) != null)
			{
				foreach($cursor as $record)
				{
					$id = $record->file['_id'];
					$this->_fs->remove(array(
						'_id' => $id
					));
				}
				return true;
			}
		}
		return false;
	}


	/**
	 * Given a string containing the path of a file or directory, this function will return the parent directory's path.
	 * @param string $path
	 * @return string
	 */
	public function dirname($path)
	{
		$p = explode('/', trim($path, '/'));
		array_pop($p);
		return implode('/', array_slice($p, 0, count($p)));
	}


	/**
	 * Attempts to create the directory specified by pathname.
	 * @param string $path
	 * @param boolean $recursive
	 * @return Returns TRUE on success or FALSE on failure.
	 */
	public function mkdir($path, $recursive = true)
	{
		$path = trim($path, '/');

		if($this->is_dir($path))
		{
			return true;
		}

		$this->is_dir($this->dirname($path)) || $this->mkdir($this->dirname($path), $recursive);
		$p = explode('/', $path);

		$parent = count($p) > 1 ? implode('/', array_slice($p, 0, count($p) - 1)) : null;

		$name = array_pop($p);

		$meta = array(
				'name' => $name,
				'filename' => $path,
				'path' => $path,
				'parent' => $parent,
				'type' => 'folder',
				'meta' => null
		);

		return $this->_db->selectCollection($this->_collectionFolders)->insert($meta, array(
			"safe" => true
		));
	}


	/**
	 * Tells whether the given filename is a directory.
	 * @param string $path
	 * @param boolean $returnObject
	 * @return boolean|Ambiguous
	 */
	public function is_dir($path, $returnObject = false)
	{
		if(trim($path) == '')
			return true;

		if(($fe = $this->_db->selectCollection($this->_collectionFolders)->findOne(array(
			'type' => 'folder', 'filename' => trim($path, '/')
		))) != null)
		{

			if($returnObject)
				return $fe;
			return true;
		}
		return false;
	}


	/**
	 * Changes file mode
	 * @param string $filename
	 * @param int $mode
	 * @return Returns TRUE on success or FALSE on failure.
	 */
	public function chmod($filename, $mode)
	{
	}
}
