
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Environment;
use App\Core\ErrorHandler;
use App\Core\Session;
use App\Core\Router;

Environment::load(__DIR__ . '/../.env');
ErrorHandler::register();
Session::start();

$router = new Router();

require_once __DIR__ . '/../routes/web.php';

$router->dispatch();

