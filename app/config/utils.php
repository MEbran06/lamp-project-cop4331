<?php
require_once __DIR__ . '/../src/response.php';
use App\Response;

function loadEnv($path = null) {
    static $loaded = false;
    if ($loaded) {
        return;
    }

    if ($path === null) {
        $possiblePaths = [
            __DIR__ . '/../../.env',
            __DIR__ . '/../.env',
            __DIR__ . '/.env',
            (defined('ROOT_PATH') ? ROOT_PATH . '/.env' : null),
        ];
        foreach ($possiblePaths as $p) {
            if ($p && file_exists($p)) {
                $path = $p;
                break;
            }
        }
    }

    if ($path && file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name  = trim($name);
                $value = trim($value);

                // Strip surrounding quotes
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                if (getenv($name) === false) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
    $loaded = true;
}

/*
* Utility function that handles validating the the csrf token. 
* If not valid, terminates request and sends a response
* Parameter: the current response object
*/
function validate_csrf($res)
{
    $header_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $header_token)) 
    {
        $res->sendJson(Response::STATUS_FORBIDDEN, [
                'success'   => false,
                'error'     => 'invalid CSRF token',
                "timestamp" => date('Y-m-d H:i:s', time())
            ]);
        exit();
    }
}

/*
* Validate that the user is authenticated.
* End the request and send a response if user is not logged in
* Parameter: response object
*/
function check_auth($res)
{
    if (!isset($_SESSION['loggedIn']) || !$_SESSION['loggedIn'])
    {
        $res->sendJson(Response::STATUS_FORBIDDEN, [
            "success" => false,
            "error" => "unauthorized access.",
            "timestamp" => date('Y-m-d H:i:s', time())
        ]);
        exit();
    }
}

/*
* Authenticate user function, this handles starting the session and its 
* parameters
*/
function authenticateUser($user_id) 
{
    // start session so user is logged in right after signup
    session_set_cookie_params([
        'lifetime' => 1200, // 1200 seconds = 20 minutes
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
    // always regenerate the session on authentication
    session_regenerate_id(true);

    // set session parameters
    $_SESSION['user_id'] = $user_id;
    $_SESSION['loggedIn'] = true; 
    // csrf protection
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validateSignUp($body, $res)
{
    // check that username, password, first and last names are defined
    if (!array_key_exists('username', $body) || 
        !array_key_exists('password', $body) ||
        !array_key_exists('firstname', $body) || 
        !array_key_exists('lastname', $body))
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "missing data fields"
        ]);
        // end the handler
        exit();
    }

    // now check that first name, last name, and username are not empty strings
    if (!is_string($body['username']) || $body['username'] === '' ||
        !is_string($body['firstname']) || $body['firstname'] === '' ||
        !is_string($body['lastname']) || $body['lastname'] === '')
    {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "data fields are incorrect type or empty"
        ]);
        // end the handler
        exit();
    }

    // now validate username: characters, digits, and {_, -, !, .} are allowed
    $allowed = array(".", "-", "_", "!", "@");
    $parsed = str_replace($allowed, '', $body['username'] );
    // check if any invalid characters are in the username
    if(!ctype_alnum(str_replace($allowed, '', $body['username'] ))) {

        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "username must only contain characters, digits, or .,-,_,!,@"
        ]);
        // end the handler
        exit();
    } 

    // validate user password: at least 1 lowercase, 1 uppercase, 1 digit, and 1 special char
    // minimum length should be 8
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
    if (!preg_match($pattern, $body['password'])) {
        $res->sendJson(Response::STATUS_BAD_REQUEST, [
            "success" => false,
            "reason" => "password needs at least 1 upper and lower case character, a digit, and a special character and it must be at least 8 characters long"
        ]);
        // end the handler
        exit();
    } 

}
?>