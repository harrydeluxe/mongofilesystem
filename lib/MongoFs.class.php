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


	private function _g($file)
	{
		$file = trim($file, '/');
		return (isset($this->_tmpfile[$file])) ? $this->_tmpfile[$file] : false;
	}


	private function _s($file, $record)
	{
		if($record->file['type'] == 'file')
			$this->_tmpfile[trim($file, '/')] = $record;
	}


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


	public function etag($file)
	{
		if(($fe = $this->readfile($file)) != false)
			return $fe->file['md5'];

		return null;
	}


	public function mimetype($file)
	{
		if(($fe = $this->readfile($file)) != false)
		{
			if(isset($fe->file['mimetype']))
			{
				return $fe->file['mimetype'];
			}
		}

		return null;
	}


	public function fileatime($file)
	{
	}

	public function filemtime($file)
	{
		if(($fe = $this->readfile($file)) != false)
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


	public function filesize($file)
	{
		if(($fe = $this->readfile($file)) != false)
			return $fe->getSize();

		return 0;
	}

	public function filetype($file)
	{
	}


	public function is_file($file, $returnObject = false)
	{
		if(($fe = $this->_g($file)) || ($fe = $this->_fs->findOne(array(
			'type' => 'file', 'filename' => trim($file, '/')
		))) != null)
		{
			$this->_s($file, $fe);
			if($returnObject)
				return $fe;
			return true;
		}
		return false;
	}


	public function file_exists($file)
	{
		if(($fe = $this->_g($file)) || ($fe = $this->_fs->findOne(array(
				'type' => array(
					'$in' => array(
						'folder', 'file'
					)
				),
				'filename' => trim($file, '/')
		))) != null)
		{
			$this->_s($file, $fe);
			return true;
		}
		return false;
	}


	public function basename($file)
	{
	}


	public function readfile($file)
	{
		if(($fe = $this->_g($file)) || ($fe = $this->_fs->findOne(array(
			'type' => 'file', 'filename' => trim($file, '/')
		))) != null)
		{
			$this->_s($file, $fe);
			return $fe;
		}
		return false;
	}


	public function file_get_contents($file)
	{
		if(($fe = $this->_fs->findOne(array(
			'filename' => trim($file, '/')
		))) != null)
		{
			return $fe->getBytes();
		}
		return false;
	}


	public function file_put_contents($file, $data, $options = null)
	{
		$file = trim($file, '/');

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

		// auto ordner dafuer erstellen
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


	public function import($file, $realfile, $options = null)
	{
		$file = trim($file, '/');

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

		// auto ordner dafuer erstellen
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
	 * @todo nach dem rename tmp aktualisieren oder loeschen
	 * Enter description here ...
	 * @param string $oldname
	 * @param string $newname
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


	public function delete($file)
	{
		return $this->unlink($file);
	}


	/**
	 * @todo auch in tmp loeschen
	 * Enter description here ...
	 * @param string $file
	 */
	public function unlink($file)
	{
		if(($fe = $this->_fs->findOne(array(
			'filename' => trim($file, '/')
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


	public function copy($source, $dest)
	{
	}


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


	public function readdir($dir)
	{
	}


	/**
	 * @todo auch in tmp loeschen
	 * Enter description here ...
	 * @param string $dir
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


	public function dirname($dir)
	{
		$p = explode('/', trim($dir, '/'));
		array_pop($p);
		return implode('/', array_slice($p, 0, count($p)));
	}


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


	public function chmod($filename, $mode)
	{
	}
}
