<?php 

require_once __DIR__ . '/src/router.php';
require_once __DIR__ . '/src/response.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/utils.php';

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
* register users
*/
$router->post('/signup', function (Request $request) {
    date_default_timezone_set('UTC');

    // we need the json body
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
    // validates signup or ends the request
    validateSignUp($body, $res);

    $hash = password_hash($body['password'], PASSWORD_DEFAULT);

    try
    {
        // insert user into table
        $db = getDB();
        $sql = "INSERT INTO User (FirstName, LastName, UserName, Password) VALUES (:fname, :lname, :uname, :pass)";
        $stmt = $db->prepare($sql);
        $stmt->execute([':fname' => $body['firstname'],
                        ':lname' => $body['lastname'],
                        ':uname' => $body['username'],
                        ':pass' => $hash]);
    } catch (PDOException $e) {
        // we failed to enter user
        $res->sendJson(Response::STATUS_CONFLICT, [
            "success" => false,
            "error" => "Username already taken",
            "timestamp" => date('Y-m-d H:i:s', time())
        ]); 
        return;
    }
    
    $user_id = $db->lastInsertId();
    // authenticate the user immidiately
    authenticateUser($user_id);

    // send response
    $res->sendJson(Response::STATUS_CREATED, [
        "success" => true,
        "user_id" => $user_id,
        "csrf_token" => $_SESSION['csrf_token'],
        "timestamp" => date('Y-m-d H:i:s', time())
    ]); 
    

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
    $sql = "SELECT id, password FROM User WHERE username = :uname LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':uname' => $username]);
    $user = $stmt->fetch();

    // login succeds
    if ($user && password_verify($password, $user['password']))
    {
        // authenticate user
        $user_id = (int) $user['id'];
        authenticateUser($user_id);

        $data = [
            "success" => true,
            "user_id" => $user_id,
            // send over the csrf token for fronted to keep
            "csrf_token" => $_SESSION['csrf_token'],
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
                'error'     => 'Username or Password incorrect or don\'t exits',
                "timestamp" => date('Y-m-d H:i:s', time())
            ]);
    }

});

$router->post("/logout", function () {
    session_start();
    date_default_timezone_set('UTC');
    $res = new Response();

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
    check_auth($res);
    // validate csrf
    validate_csrf($res);

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