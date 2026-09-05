<?php 

require_once __DIR__ . '/src/router.php';
require_once __DIR__ . '/src/response.php';

use App\{Router, Request, Response};

$router = new Router('/api');


/*
*  Simple health check
*/
$router->post('/health', function (Request $request) {
    date_default_timezone_set('UTC');

    $data = [
        "status" => "success",
        "timestamp" => date('Y-m-d H:i:s', time()),
    ];

    $res = new Response();
    $res->sendJson(Response::STATUS_OK, $data);
});


/*
*  Handle endpoint requests to endpoints that don't exist
*/
$router->addNotFoundHandler(function() {
    
    $data = [
        "status" => "failure",
        "reason" => "endpoint not found"
    ];
    $res = new Response();
    $res->sendJson(Response::STATUS_NOT_FOUND, $data);

});

$router->run();

?>