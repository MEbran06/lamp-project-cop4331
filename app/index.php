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
    // authenticate the user immidiately, we are not making them admin default to enabled
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
            "reason" => "body could not be parsed",
             "timestamp" => date('Y-m-d H:i:s', time())
        ]);
        return;
    }
    if (!array_key_exists('username', $body) || !is_string($body['username']))
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "invalid username",
            "timestamp" => date('Y-m-d H:i:s', time())
        ]);
        // end the handler
        return;
    }

    // get the query parameters
    $params = $request->getQueryParams();
    $limit = 10; // number of rows per page
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // add the wildcard
    $username = $body['username'] === "" ?  "%": $body['username'] . "%";

    // update user password
    $db = getDB();

    // get page data
    $countStmt = $db->prepare("SELECT COUNT(*) FROM User WHERE username LIKE :uname;");
    $countStmt->execute([
        ':uname' => $username
    ]);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($total / $limit);

    // add the page matadata
    $meta = [
        'current_page' => $page,
        'per_page' => $limit,
        'total' => $total,
        'total_pages' => $totalPages,
    ];

    $sql = "SELECT 
            User.id AS user_id,
            User.firstname AS user_fname, 
            User.lastname AS user_lname, 
            User.username AS user_uname, 
            CASE
                WHEN COUNT(Contact.userid) = 0 THEN JSON_ARRAY()
                ELSE JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'contact_id', Contact.ID,
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
        LIMIT :limit OFFSET :offset;";
    $stmt = $db->prepare($sql);
    $stmt->execute([':uname' => $username,
                    ':offset' => $offset,
                    ':limit' => $limit]);
    $users = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['contacts'] = json_decode($row['contacts'] ?? '[]', true);
        $users[] = $row;
    }

    $res->sendJson(Response::STATUS_OK, [
        "success" => true,
        "data" => $users,
        "meta" => $meta,
        "timestamp" => date('Y-m-d H:i:s', time()),
    ]);

});

/*
* Admin search user by Id
*/
$router->get("/admin/search/{id}", function($request) {
    session_start();
    date_default_timezone_set('UTC');
    $res = new Response();
    // check if the user isn't logged in (restricts this endpoint to admins)
    check_auth($res, true);
    // validate csrf
    validate_csrf($res);

    // get the query parameters
    $userId = (int)$request->getParamByName('id');
    if (!$userId)
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "invalid user id.",
            "timestamp" => date('Y-m-d H:i:s', time())
        ]);
        // end the handler
        return;
    }
    $db = getDB();

    $sql = "SELECT 
            User.id AS user_id,
            User.firstname AS user_fname, 
            User.lastname AS user_lname, 
            User.username AS user_uname, 
            CASE
                WHEN COUNT(Contact.userid) = 0 THEN JSON_ARRAY()
                ELSE JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'contact_id', Contact.ID,
                        'contact_email', Contact.Email,
                        'contact_fname', Contact.FirstName,
                        'contact_lname', Contact.LastName,
                        'contact_phone', Contact.Phone
                    )
                )
            END AS contacts
        FROM User 
        LEFT JOIN Contact ON User.id = Contact.userid 
        WHERE User.id = :user_id GROUP BY User.id;";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch();

    if ($user)
    {
        $user['contacts'] = json_decode($user['contacts'], true);
        $res->sendJson(Response::STATUS_OK, [
            "success" => true,
            "data" => $user,
            "timestamp" => date('Y-m-d H:i:s', time()),
        ]);
    }
    else {
        $res->sendJson(Response::STATUS_NOT_FOUND, [
            "success" => false,
            "error" => "user not found",
            "timestamp" => date('Y-m-d H:i:s', time()),
        ]);
    }

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
    $sql = "SELECT id, password, is_elevated, is_enabled FROM User WHERE username = :uname LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':uname' => $username]);
    $user = $stmt->fetch();

    // login succeds
    if ($user)
    {
        $is_enabled = (bool)$user['is_enabled'];
        // ensure the user is enabled
        if (!$is_enabled)
        {
            $res->sendJson(Response::STATUS_UNAUTHORIZED, [
                    'success'   => false,
                    'error'     => 'user has been disabled. Contact an admin to be enabled.',
                    "timestamp" => date('Y-m-d H:i:s', time())
                ]);
            return;
        }

        if (password_verify($password, $user['password']))
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
                    'error'     => 'Username or Password incorrect',
                    "timestamp" => date('Y-m-d H:i:s', time())
                ]);
        }
    }
    else 
    {
        // login failed
        $res->sendJson(Response::STATUS_UNAUTHORIZED, [
                'success'   => false,
                'error'     => 'user doesn\'t exist.',
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
    // see utils.php for details
    validateContact($body, $res);


    $userID = $_SESSION['user_id'];
    $db = getDB();
    //AI spit out a try catch version of my code when I was error checking:
    $sql = "
    INSERT INTO Contact
    (FirstName, LastName, Email, Phone, UserID)
    VALUES
    (:fname, :lname, :email, :phone, :uid);
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':fname' => trim($body['firstname']),
        ':lname' => trim($body['lastname']),
        ':email' => $body['email'] ?? null,
        ':phone' => $body['phone'] ?? null,
        ':uid'   => (int) $userID,
    ]);

    $res->sendJson(Response::STATUS_CREATED, [
        'success' => true,
        'contact_id' => (int) $db->lastInsertId()
    ]);

});//end contact create

$router->delete("/contact/delete/{id}", function($request){
    session_start();
    date_default_timezone_set('UTC');
    $res = new Response();
    check_auth($res);
    validate_csrf($res);
    $userID = $_SESSION['user_id'];
    $contactId = (int)$request->getParamByName('id');
    // pass contact id as a query parameter
    if (!$contactId)
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "invalid contact id.",
            "timestamp" => date('Y-m-d H:i:s', time()),
        ]);
        // end the handler
        return;
    }

    $db = getDB();
    $sql = "
        DELETE FROM Contact
        WHERE id = :contact_id
        AND UserID = :user_id;
        ";
    $stmt = $db->prepare($sql);
    //expects the ID for the contact being deleted to have been passed in the body
    $stmt->execute([
        ':contact_id' => $contactId,
        ':user_id' => (int) $userID
    ]);
    //if no rows are effected it lets you know
    if ($stmt->rowCount() === 0) {
        $res->sendJson(Response::STATUS_NOT_FOUND, [
            'success' => false,
            'reason' => 'contact not found',
            "timestamp" => date('Y-m-d H:i:s', time())
        ]);
        return;
    }

    $res->sendJson(Response::STATUS_OK, [
        'success' => true,
        'message' => 'contact deleted',
        "timestamp" => date('Y-m-d H:i:s', time())
    ]);
});//end contact delete

//specifically expects all of the fields again
$router->put("/contact/update/{id}", function($request){
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
            "reason" => "body could not be parsed",
            "timestamp" => date('Y-m-d H:i:s', time()),
        ]);
        // end the handler
        return;
    }
    $contactId = (int)$request->getParamByName('id');
    // pass contact id as a query parameter
    if (!$contactId)
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "invalid contact id.",
            "timestamp" => date('Y-m-d H:i:s', time()),
        ]);
        // end the handler
        return;
    }
    // see utils.php for details
    validateContact($body, $res);

    $sql = "
    UPDATE Contact
    SET
    FirstName = :fname,
    LastName  = :lname,
    Email     = :email,
    Phone     = :phone
    WHERE ID = :contact_id
    AND UserID = :user_id
    ";

    $stmt = $db->prepare($sql);

    $stmt->execute([
        ':fname'       => $body['firstname'],
        ':lname'       => $body['lastname'],
        ':email'       => $body['email'] ?? null,
        ':phone'       => $body['phone'] ?? null,
        ':contact_id'  => $contactId,
        ':user_id'     => $userID
    ]);

    //check that rows were affected
    if ($stmt->rowCount() === 0) {
        $res->sendJson(Response::STATUS_NOT_FOUND, [
            'success' => false,
            'reason' => 'contact not found or not owned by user',
            "timestamp" => date('Y-m-d H:i:s', time()),
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


    $params = $request->getQueryParams();
    $params['search'] = array_key_exists('search', $params) ? $params['search'] . "%" : "%";
    
    $db = getDB();
    $limit = 10;
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    $search = $params['search'];

    // get page data
    $countStmt = $db->prepare("SELECT COUNT(*) FROM Contact WHERE UserID = :user_id 
                            AND (FirstName LIKE :search_f OR LastName Like :search_l);");
    $countStmt->bindValue(':user_id', $userID, PDO::PARAM_INT);
    $countStmt->bindValue(':search_f', $search, PDO::PARAM_INT);
    $countStmt->bindValue(':search_l', $search, PDO::PARAM_INT);
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($total / $limit);

    // add the page matadata
    $meta = [
        'current_page' => $page,
        'per_page' => $limit,
        'total' => $total,
        'total_pages' => $totalPages,
    ];

    //this might be wrong. Not exactly sure how DB is setup
    $sql = '
    SELECT id, firstname, lastname, email, phone 
    FROM Contact WHERE UserID = :user_id
    AND (FirstName LIKE :search_f OR LastName Like :search_l)
    LIMIT :limit OFFSET :offset;';
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id'  => $userID,
       ':search_f'    => $search,
       ':search_l'    => $search,
        ':limit'    => $limit,
        ':offset'   => $offset
    ]);
    $contacts = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $contacts[] = $row;
    }

    $res->sendJson(Response::STATUS_OK, [
        "success" => true,
        "data" => $contacts,
        "meta" => $meta,
        "timestamp" => date('Y-m-d H:i:s', time()),
    ]);
});//end contact search

// search for a single contact by id (expects id query parameter)
$router->get("/contact/search/{id}", function($request){
    session_start();
    date_default_timezone_set('UTC');
    $res = new Response();
    check_auth($res);
    validate_csrf($res);
    $userID = $_SESSION['user_id'];
    $contactId = (int)$request->getParamByName('id');

    if (!$contactId)
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "ivalid contact id."
        ]);
        // end the handler
        return;
    }
    $db = getDB();

    $sql = '
    SELECT ID, FirstName, LastName, Email, Phone 
    FROM Contact WHERE UserID = :user_id
    AND id = :contact_id;';
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id'  => $userID,
       ':contact_id'    => $contactId
    ]);
    $contact = $stmt->fetch();

    if ($contact)
    {
        $res->sendJson(Response::STATUS_OK, [
            "success" => true,
            "data" => $contact,
            "timestamp" => date('Y-m-d H:i:s', time()),
        ]);
    }
    else {
        $res->sendJson(Response::STATUS_NOT_FOUND, [
            "success" => false,
            "error" => "Contact not found or not owned by User",
            "timestamp" => date('Y-m-d H:i:s', time()),
        ]);
    }
});


/*
*  Handle endpoint requests to endpoints that don't exist
*/
$router->addNotFoundHandler(function() {
    
    $data = [
        "success" => false,
        "reason" => "endpoint not found",
        "timestamp" => date('Y-m-d H:i:s', time()),
    ];
    $res = new Response();
    $res->sendJson(Response::STATUS_NOT_FOUND, $data);

});

$router->run();

?>
