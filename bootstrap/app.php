<?php

use App\Core\Application;
use App\Core\Router;
use App\Support\Env;

require __DIR__ . '/../vendor/autoload.php';

Env::load(__DIR__ . '/../.env');

$router = new Router();

(require __DIR__ . '/../routes/api.php')($router);

return new Application($router);
