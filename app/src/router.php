<?php
namespace App;

require_once __DIR__ . '/request.php';

class Router
{
    private array $handlers;
    private $notFoundHandler;
    private $rootEndpoint;
    private const METHOD_POST = 'POST';
    private const METHOD_GET = 'GET';

    public function __construct($rootEndpoint = '')
    {
        $this->rootEndpoint = $rootEndpoint;
    }

    public function get(string $path, $handler): void
    {
        $this->addHandler(self::METHOD_GET, $path, $handler);
    }

    public function post(string $path, $handler): void
    {
        $this->addHandler(self::METHOD_POST, $path, $handler);

    }

    public function addNotFoundHandler($handler): void
    {
        $this->notFoundHandler = $handler;
    }

    private function addHandler(string $method, string $path, $handler) : void
    {
        $this->handlers[$method . $path] = [
            'path' =>  $this->rootEndpoint . $path,
            'method' => $method,
            'handler' => $handler,
        ];
    }

    public function run()
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI']);
        $requestPath = $requestUri['path'];
        $method = $_SERVER['REQUEST_METHOD'];
        
        $callback = null;
        foreach ($this->handlers as $handler) {
            if ($handler['path'] === $requestPath && $method === $handler['method']) {
                $callback = $handler['handler'];
            }
        }

        if (!$callback) {
            header("HTTP/1.0 404 Not Found");
            if (!empty($this->notFoundHandler)) {
                $callback = $this->notFoundHandler;
            }
        }

        // make the request object here
        $request = new Request($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);

        call_user_func($callback, $request);
    }
}
?>