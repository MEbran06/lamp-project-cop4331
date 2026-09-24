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
    private const METHOD_PUT = 'PUT';
    private const METHOD_DELETE = 'DELETE';

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

    public function put(string $path, $handler): void
    {
        $this->addHandler(self::METHOD_PUT, $path, $handler);

    }

    public function delete(string $path, $handler): void
    {
        $this->addHandler(self::METHOD_DELETE, $path, $handler);

    }

    public function addNotFoundHandler($handler): void
    {
        $this->notFoundHandler = $handler;
    }

    private function addHandler(string $method, string $path, $handler) : void
    {
        $compiled = $this->compilePath($this->rootEndpoint . $path);
        $this->handlers[$method . $this->rootEndpoint . $path] = [
            'path' =>  $compiled,
            'method' => $method,
            'handler' => $handler,
        ];
    }

    // replace {} with regex group matching for a path
    private function compilePath(string $path): string
    {
        $regex = preg_replace_callback(
            '#\{(\w+)\}|([^{]+)#', // match {} or just normal string literal
            function ($m) {
                if (isset($m[2])) {
                    return preg_quote($m[2], '#'); // literal part so ignore
                }
                return '(?P<' . $m[1] . '>[^/]+)';
            },
            $path
        );

        return '#^' . $regex . '$#';
    }

    public function run()
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI']);
        $requestPath = $requestUri['path'];
        $method = $_SERVER['REQUEST_METHOD'];
        
        $callback = null;
        $params = [];
        $key = $method . $requestPath;
        if (isset($this->handlers[$key])) {
            $callback = $this->handlers[$key]['handler'];
        }
        else 
        {
            foreach ($this->handlers as $handler) 
            {
                if ($handler['method'] === $method &&
                    preg_match($handler['path'], $requestPath, $matches)) 
                {
                    $callback = $handler['handler'];
                    // only keep the named parameters
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    break;
                }
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
        $request->setParams($params);

        call_user_func($callback, $request);
    }
}
?>
