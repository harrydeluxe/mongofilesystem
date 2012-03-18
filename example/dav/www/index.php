<?php

define('PROTECTED_PATH', dirname(__FILE__) . '/../protected/');

// settings
date_default_timezone_set('Germany/Berlin');
$baseUri = '/delacap/web/labs/mongofilesystem/example/dav/www/';
$publicDir = 'public';
$tmpDir = PROTECTED_PATH . 'tmp/'; // must be read- and writeable

// Files we need
require_once('../../../lib/MongoFs.class.php');
require_once('../../../lib/MongoFsStatic.class.php');

require_once(PROTECTED_PATH . 'lib/Sabre/autoload.php');
require_once(PROTECTED_PATH . 'lib/mongo/MongoNode.php');
require_once(PROTECTED_PATH . 'lib/mongo/MongoDirectory.php');
require_once(PROTECTED_PATH . 'lib/mongo/MongoFile.php');


$rootNode = new MongoDirectory($publicDir);
$server = new Sabre_DAV_Server($rootNode);

if (isset($baseUri))
    $server->setBaseUri($baseUri);

// Support for html frontend
$browser = new Sabre_DAV_Browser_Plugin();
$server->addPlugin($browser);

// Temporary file filter
$tempFF = new Sabre_DAV_TemporaryFileFilterPlugin($tmpDir);
$server->addPlugin($tempFF);

$lockBackend = new Sabre_DAV_Locks_Backend_File($tmpDir.'locks.dat');
$lockPlugin = new Sabre_DAV_Locks_Plugin($lockBackend);
$server->addPlugin($lockPlugin);

$server->exec();