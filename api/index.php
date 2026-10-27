<?php

declare(strict_types=1);

// Vercel entrypoint: hand every request to Symfony's front controller.
$root = \dirname(__DIR__);
$_SERVER['SCRIPT_FILENAME'] = $root.'/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

chdir($root.'/public');
require $root.'/public/index.php';
