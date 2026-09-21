<?php

use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

const ROOT = __DIR__ . '/..'; // path/to/src
#const ROOT = '/var/www/html';
$autoloader = require_once ROOT . '/web/autoload.php';
require_once ROOT . '/web/core/includes/bootstrap.inc';

require_once ROOT . '/web/core/includes/common.inc';
//require_once ROOT . '/web/core/includes/database.inc';
require_once ROOT . '/web/core/includes/schema.inc';
require_once ROOT . '/web/core/includes/file.inc';
require_once ROOT . '/web/core/modules/node/node.module';
require_once ROOT . '/web/core/modules/system/system.module';
require_once ROOT . '/web/modules/contrib/file_entity/file_entity.module';
require_once ROOT . '/web/core/modules/language/language.module';

$request = Request::createFromGlobals();
Settings::initialize(ROOT.'/web/core', DrupalKernel::findSitePath($request), $autoloader);

global $kernel;

function kernel_fast($request, $autoloader) {
  $kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
  $kernel->boot();
}

function kernel_request($request, $autoloader) {
  $kernel = new DrupalKernel('prod', $autoloader);
  #$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
  #$request = Request::createFromGlobals();
  #$request = Request::create(getenv("BASE_URL"));
  $response = $kernel->handle($request);
}

#kernel_fast($request, $autoloader);
kernel_request($request, $autoloader);
