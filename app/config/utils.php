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
?>