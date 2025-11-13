<?php

use App\Core\Request;

$app = require __DIR__ . '/../bootstrap/app.php';

$request = Request::capture();
$response = $app->handle($request);
$response->send();
