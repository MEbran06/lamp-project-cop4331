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
$router->get('/health', function ($request) {
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
$router->post('/signup', function ($request) {
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
    // authenticate the user immidiately, we are not making them admin
    authenticateUser($user_id, false);

    // send response
    $res->sendJson(Response::STATUS_CREATED, [
        "success" => true,
        "user_id" => $user_id,
        "csrf_token" => $_SESSION['csrf_token'],
        "timestamp" => date('Y-m-d H:i:s', time())
    ]); 
    

});

/*
* sign up admin user (Only another admin can do this)
*/
$router->post("/admin/signup", function($request) {
    session_start();
    date_default_timezone_set('UTC');
    $res = new Response();
    // check if the user isn't logged in (restricts this endpoint for admin users only)
    check_auth($res, true);
    // validate csrf
    validate_csrf($res);

    $body = $request->getBody();
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
        // insert user with elevated permissions into table
        $db = getDB();
        $sql = "INSERT INTO User (FirstName, LastName, UserName, Password, is_elevated) VALUES (:fname, :lname, :uname, :pass, 1)";
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
    // send response
    $res->sendJson(Response::STATUS_CREATED, [
        "success" => true,
        "user_id" => $user_id,
        "timestamp" => date('Y-m-d H:i:s', time())
    ]); 
});

/*
* Change users' password (Admin Only)
*/
$router->post("/admin/set-password", function($request) {
    session_start();
    date_default_timezone_set('UTC');
    $res = new Response();
    // check if the user isn't logged in (restricts this endpoint to admins)
    check_auth($res, true);
    // validate csrf
    validate_csrf($res);

    $body = $request->getBody();
    if ($body == null)
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "body could not be parsed"
        ]);
        return;
    }
    // check that username and password are defined
    if (!array_key_exists('username', $body) || !array_key_exists('password', $body))
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "missing data fields"
        ]);
        // end the handler
        return;
    }
    // check that username and password are not empty strings
    if (!is_string($body['username']) || $body['username'] === '' ||
        !is_string($body['password']) || $body['password'] === '' )
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "data fields are incorrect type or empty"
        ]);
        // end the handler
        return;
    }

    // validate user password
    validatePassword($body, $res);

    $hash = password_hash($body['password'],PASSWORD_DEFAULT);

    // update user password
    $db = getDB();
    $sql = "UPDATE User SET password = :pass WHERE username = :uname LIMIT 1;";
    $stmt = $db->prepare($sql);
    $stmt->execute([':pass' => $hash,
                    ':uname' => $body['username']]);

    if ($stmt->rowCount() == 0)
    {
        $res->sendJson(Response::STATUS_NOT_FOUND, [
            "success" => false,
            "error" => "User could not be found, check that the username is correct.",
            "timestamp" => date('Y-m-d H:i:s', time())
        ]); 
        return;
    }

    // send response
    $res->sendJson(Response::STATUS_OK, [
        "success" => true,
        "timestamp" => date('Y-m-d H:i:s', time())
    ]); 

});

/*
* Retrive users' and their entried based on search
*/
$router->post("/admin/search", function($request) {
    session_start();
    date_default_timezone_set('UTC');
    $res = new Response();
    // check if the user isn't logged in (restricts this endpoint to admins)
    check_auth($res, true);
    // validate csrf
    validate_csrf($res);

    $body = $request->getBody();
    if ($body == null)
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "body could not be parsed"
        ]);
        return;
    }
    if (!array_key_exists('username', $body))
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "missing data fields"
        ]);
        // end the handler
        return;
    }

    // get the query parameters
    $params = $request->getQueryParams();
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * 10; // hardcode limit to 10

    // add the wildcard
    $username = $body['username'] === "" ?  $body['username']: $body['username'] . "%";

    // update user password
    $db = getDB();
    $sql = "SELECT 
            User.firstname AS user_fname, 
            User.lastname AS user_lname, 
            User.username AS user_uname, 
            CASE
                WHEN COUNT(Contact.userid) = 0 THEN JSON_ARRAY()
                ELSE JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'contact_email', Contact.Email,
                        'contact_fname', Contact.FirstName,
                        'contact_lname', Contact.LastName,
                        'contact_phone', Contact.Phone
                    )
                )
            END AS contacts
        FROM User 
        LEFT JOIN Contact ON User.id = Contact.userid 
        WHERE User.username LIKE :uname
        GROUP BY User.id
        LIMIT 10 OFFSET :offset;";
    $stmt = $db->prepare($sql);
    $stmt->execute([':uname' => $username,
                    ':offset' => $offset]);
    $users = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['contacts'] = json_decode($row['contacts'] ?? '[]', true);
        $users[] = $row;
    }

    $res->sendJson(Response::STATUS_OK, [
        "success" => true,
        "data" => $users,
        "timestamp" => date('Y-m-d H:i:s', time()),
    ]);

});

/*
* login router
*/
$router->post('/login', function ($request) {
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
    $sql = "SELECT id, password, is_elevated FROM User WHERE username = :uname LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':uname' => $username]);
    $user = $stmt->fetch();

    // login succeds
    if ($user && password_verify($password, $user['password']))
    {
        // authenticate user
        $user_id = (int) $user['id'];
        $is_admin = (bool)$user['is_elevated'];
        authenticateUser($user_id, $is_admin);

        $data = [
            "success" => true,
            "user_id" => $user_id,
            "is_admin" => (bool)$user['is_elevated'],
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
    // check if the user isn't logged in (restricts this endpoint for regular users)
    check_auth($res);
    // validate csrf
    validate_csrf($res);

    $res->sendJson(Response::STATUS_OK, [
        "success" => true,
        "timestamp" => date('Y-m-d H:i:s', time()),
    ]);
});

$router->post("/contact/create", function($request){
    session_start();
    date_default_timezone_set('UTC');
    $res = new Response();
    check_auth($res);
    validate_csrf($res);
    $body = $request->getBody();
    if ($body == null)
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "body could not be parsed"
        ]);
        // end the handler
        return;
    }
    // ensures there is at least a first and last name for the contact
    if (!array_key_exists('FirstName', $body) || !array_key_exists('LastName', $body))
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "missing data fields"
        ]);
        // end the handler
        return;
    }
    $userID = $_SESSION['user_id'];
    $db = getDB();
    //AI spit out a try catch version of my code when I was error checking:

        $sql = "
        INSERT INTO Contact
        (FirstName, LastName, Email, Phone, User_ID)
        VALUES
        (:fname, :lname, :email, :phone, :uid)
        ";

        $stmt = $db->prepare($sql);

        if (!array_key_exists('Email', $body) && !array_key_exists('Phone', $body))
        {
            $res->sendJson(Response::STATUS_BAD_REQUEST, [
                "success" => false,
                "reason" => "Email OR Phone required"
            ]);
            // end the handler
            return;
        }

        $stmt->execute([
            ':fname' => trim($body['FirstName']),
                    ':lname' => trim($body['LastName']),
                    ':email' => $body['Email'] ?? null,
                    ':phone' => $body['Phone'] ?? null,
                    ':uid'   => (int) $userID,
        ]);

        $res->sendJson(Response::STATUS_CREATED, [
            'success' => true,
            'contact_id' => (int) $db->lastInsertId()
        ]);

});//end contact create

$router->delete("/contact/delete", function($request){
    session_start();
    date_default_timezone_set('UTC');
    $res = new Response();
    check_auth($res);
    validate_csrf($res);
    $userID = $_SESSION['user_id'];
    $db = getDB();
    $sql = "
        DELETE FROM Contact
        WHERE Contact_ID = :contact_id
        AND User_ID = :user_id
        ";
    $body = $request->getBody();
    if ($body == null)
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "body could not be parsed"
        ]);
        // end the handler
        return;
    }

    $stmt = $db->prepare($sql);
    //expects the ID for the contact being deleted to have been passed in the body
    $stmt->execute([
        ':contact_id' => $body['contactID'],
        ':user_id' => (int) $userID
    ]);
    //if no rows are effected it lets you know
    if ($stmt->rowCount() === 0) {
        $res->sendJson(Response::STATUS_NOT_FOUND, [
            'success' => false,
            'reason' => 'contact not found'
        ]);
        return;
    }

    $res->sendJson(Response::STATUS_OK, [
        'success' => true,
        'message' => 'contact deleted'
    ]);
});//end contact delete

//specifically expects all of the fields again
$router->put("/contact/update", function($request){
    session_start();
    date_default_timezone_set('UTC');
    $res = new Response();
    check_auth($res);
    validate_csrf($res);
    $userID = $_SESSION['user_id'];
    $db = getDB();

    $body = $request->getBody();
    if ($body == null)
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "body could not be parsed"
        ]);
        // end the handler
        return;
    }
    $sql = "
    UPDATE Contact
    SET
    FirstName = :fname,
    LastName  = :lname,
    Email     = :email,
    Phone     = :phone
    WHERE Contact_ID = :contact_id
    AND User_ID = :user_id
    ";

    $stmt = $db->prepare($sql);

    $stmt->execute([
        ':fname'       => $body['FirstName'],
        ':lname'       => $body['LastName'],
        ':email'       => $body['Email'] ?? null,
        ':phone'       => $body['Phone'] ?? null,
        ':contact_id'  => $body['contactID'],
        ':user_id'     => $userID
    ]);

    //check that rows were affected
    if ($stmt->rowCount() === 0) {
        $res->sendJson(Response::STATUS_NOT_FOUND, [
            'success' => false,
            'reason' => 'contact not found or not owned by user'
        ]);
        return;
    }

    $res->sendJson(Response::STATUS_OK, [
        "success" => true,
        "timestamp" => date('Y-m-d H:i:s', time()),
    ]);

});//end contact update

$router->get("/contact/search", function($request){
    session_start();
    date_default_timezone_set('UTC');
    $res = new Response();
    check_auth($res);
    validate_csrf($res);
    $userID = $_SESSION['user_id'];


    $body = $request->getBody();
    if ($body == null)
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "body could not be parsed"
        ]);
        // end the handler
        return;
    }
    if (!array_key_exists('search', $body))
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "`search` field required."
        ]);
        // end the handler
        return;
    }
    $db = getDB();
    $limit = 10;
    $params = $request->getQueryParams();
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;


    //this might be wrong. Not exactly sure how DB is setup
    $sql = '
    SELECT ID, FirstName, LastName, Email, Phone 
    FROM Contact WHERE UserID = :user_id
    AND (FirstName LIKE :search_f OR LastName Like :search_l)
    LIMIT :limit OFFSET :offset';
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id'  => $userID,
       ':search_f'    => $body['search'] . "%",
       ':search_l'    => $body['search'] . "%",
        ':limit'    => $limit,
        ':offset'   => $offset
    ]);
    $contacts = $stmt->fetch();

    if ($contacts)
    {
        $res->sendJson(Response::STATUS_OK, [
            "success" => true,
            "data" => $contacts,
            "timestamp" => date('Y-m-d H:i:s', time()),
        ]);
    }
    else {
        $res->sendJson(Response::STATUS_NOT_FOUND, [
            "success" => false,
            "error" => "Contacts not found",
            "timestamp" => date('Y-m-d H:i:s', time()),
        ]);
    }
});//end contact search
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
