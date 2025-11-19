<?php
$routesFile = __DIR__ . '/../routes/api.php';
$outputFile = __DIR__ . '/hurream_erp_api.postman_collection.json';
if (!file_exists($routesFile)) {
    fwrite(STDERR, "Unable to locate routes file at {$routesFile}\n");
    exit(1);
}
$contents = file_get_contents($routesFile);
$pattern = '/\$router->add\(\'([A-Z]+)\',\s*\'([^\']+)\',\s*\[([A-Za-z0-9_\\\\\\\\]+)::class,\s*\'([^\']+)\'\](?:,\s*(\[[^;]+?\]))?\);/';
if (!preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
    fwrite(STDERR, "No routes matched in {$routesFile}\n");
    exit(1);
}
$groups = [];
$order = [];
foreach ($matches as $match) {
    [$full, $method, $path, $controllerFqcn, $action] = $match;
    $middlewareRaw = $match[5] ?? '';
    $controllerParts = explode('\\\\', $controllerFqcn);
    $controller = end($controllerParts);
    $groupName = preg_replace('/Controller$/', '', $controller);
    if (!isset($groups[$groupName])) {
        $groups[$groupName] = [];
        $order[] = $groupName;
    }
    $middleware = [];
    if (!empty($middlewareRaw)) {
        $trimmed = trim($middlewareRaw);
        $trimmed = trim($trimmed, '[]');
        if ($trimmed !== '') {
            $parts = preg_split('/\s*,\s*/', $trimmed);
            foreach ($parts as $mw) {
                $middleware[] = trim($mw, "'\"");
            }
        }
    }
    $groups[$groupName][] = [
        'method' => $method,
        'path' => $path,
        'controller' => $controller,
        'action' => $action,
        'middleware' => $middleware,
    ];
}
$collection = [
    'info' => [
        'name' => 'Hurream ERP API (Auto-generated)',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        'description' => "Postman collection generated directly from routes/api.php. Regenerate whenever new routes are added using php docs/generate_postman_collection.php.",
    ],
    'auth' => [
        'type' => 'bearer',
        'bearer' => [
            [
                'key' => 'token',
                'value' => '{{access_token}}',
                'type' => 'string',
            ],
        ],
    ],
    'variable' => [
        [
            'key' => 'base_url',
            'value' => 'http://localhost:8000',
            'type' => 'string',
        ],
        [
            'key' => 'access_token',
            'value' => '',
            'type' => 'string',
        ],
    ],
    'item' => [],
];
foreach ($order as $groupName) {
    $requests = [];
    foreach ($groups[$groupName] as $route) {
        $request = buildRequestItem($route);
        $requests[] = $request;
    }
    $collection['item'][] = [
        'name' => $groupName,
        'item' => $requests,
    ];
}
file_put_contents($outputFile, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Postman collection written to {$outputFile}\n";

function buildRequestItem(array $route): array
{
    $method = $route['method'];
    $path = $route['path'];
    $cleanPath = trim($path, '/');
    $segments = $cleanPath === '' ? [] : explode('/', $cleanPath);
    array_unshift($segments, 'api');
    $postmanSegments = array_map(function ($segment) {
        if (preg_match('/\{([^}]+)\}/', $segment, $match)) {
            return ':' . $match[1];
        }
        return $segment;
    }, $segments);
    $url = [
        'raw' => '{{base_url}}/api' . ($path ? $path : ''),
        'host' => ['{{base_url}}'],
        'path' => $postmanSegments,
    ];
    if (preg_match_all('/\{([^}]+)\}/', $path, $varMatches)) {
        $url['variable'] = array_map(function ($variable) {
            return [
                'key' => $variable,
                'value' => '',
            ];
        }, $varMatches[1]);
    }
    $headers = [
        [
            'key' => 'Accept',
            'value' => 'application/json',
        ],
    ];
    $body = null;
    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $headers[] = [
            'key' => 'Content-Type',
            'value' => 'application/json',
        ];
        $body = [
            'mode' => 'raw',
            'raw' => "{\n  \"example\": \"value\"\n}",
        ];
    }
    $description = $route['controller'] . '::' . $route['action'];
    if (!empty($route['middleware'])) {
        $description .= "\nMiddleware: " . implode(', ', $route['middleware']);
    }
    $request = [
        'name' => $method . ' ' . $path,
        'request' => [
            'method' => $method,
            'header' => $headers,
            'url' => $url,
            'description' => $description,
        ],
        'response' => [],
    ];
    if ($body) {
        $request['request']['body'] = $body;
    }
    return $request;
}
