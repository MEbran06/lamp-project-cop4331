<?php 

require_once __DIR__ . '/src/router.php';
require_once __DIR__ . '/src/response.php';
require_once __DIR__ . '/config/db.php';

use App\{Router, Request, Response};

$router = new Router('/api');


/*
*  Simple health check
*/
$router->get('/health', function (Request $request) {
    date_default_timezone_set('UTC');

    $data = [
        "success" => true,
        "timestamp" => date('Y-m-d H:i:s', time()),
    ];

    $res = new Response();
    $res->sendJson(Response::STATUS_OK, $data);
});

/*
* login router
*/
$router->post('/login', function (Request $request) {
    date_default_timezone_set('UTC');

    // we expect a json body
    $body = $request->getBody();
    $res = new Response();
    if ($body == null)
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "body could not be parsed"
        ]);
        // end the handler
        return;
    }
    if (!array_key_exists('username', $body) || !array_key_exists('password', $body))
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "useranme and password are not defined"
        ]);
        // end the handler
        return;
    }
    
    $username = $body['username'];
    $password = $body['password'];

    // check database
    $db = getDB();
    $sql = "SELECT user_id FROM User WHERE username = :uname AND password = :pass LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':uname' => $username, ':pass' => $password]);
    $user = $stmt->fetch();

    // login success
    if ($user)
    {
        // start the session
        session_start();
        // set session parameters
        $user_id = (int) $user['user_id'];
        $_SESSION['user_id'] = $user_id;
        $_SESSION['loggedIn'] = true; 

        $data = [
            "success" => true,
            "user_id" => $user_id,
            "timestamp" => date('Y-m-d H:i:s', time())
        ];

        $res->sendJson(Response::STATUS_OK, $data);
    }
    else
    {
        // login failed
        $res->sendJson(Response::STATUS_UNAUTHORIZED, [
                'success'   => false,
                'user_id'   => 0,
                'error'     => 'No Records Found',
                "timestamp" => date('Y-m-d H:i:s', time())
            ]);
    }

});

$router->post("/logout", function () {
    session_start();
    date_default_timezone_set('UTC');
    // Unset all of the session variables.
    $_SESSION = array();

    // delete the session cookie.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // destroy the session.
    session_destroy();

    $res = new Response();
    $res->sendJson(Response::STATUS_OK, [
        "success" => true,
        "timestamp" => date('Y-m-d H:i:s', time()),
    ]);
});

/*
* get a protected resource
*/
$router->get("/resource", function() {
    session_start();
    date_default_timezone_set('UTC');
    $res = new Response();
    // check if the user isn't logged in
    if (!isset($_SESSION['loggedIn']) || !$_SESSION['loggedIn'])
    {
        $res->sendJson(Response::STATUS_FORBIDDEN, [
            "success" => false,
            "reason" => "unauthorized access."
        ]);
        return;
    }

    $res->sendJson(Response::STATUS_OK, [
        "success" => true,
        "timestamp" => date('Y-m-d H:i:s', time()),
    ]);
});

/*
*  Handle endpoint requests to endpoints that don't exist
*/
$router->addNotFoundHandler(function() {
    
    $data = [
        "success" => false,
        "reason" => "endpoint not found"
    ];
    $res = new Response();
    $res->sendJson(Response::STATUS_NOT_FOUND, $data);

});

$router->run();

?>