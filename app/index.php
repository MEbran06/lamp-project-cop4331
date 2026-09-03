<?php 

require_once __DIR__ . '/src/router.php';

use App\{Router, Request};

$router = new Router();

$router->get('/', function () {
    echo 'Home Page';
});

$router->post('/info', function (Request $request) {
    $data = $request->getBody();

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
});

$router->get('/about', function () {
    echo 'About Page';
});

$router->addNotFoundHandler(function() {
    echo 'Not Found';
});

$router->run();

?>