<?php 
namespace App;

class Request
{
    private array $get;
    private array $post;
    private array $cookie;
    private array $files;
    private array $server;
    private array $params = [];

    public function __construct( 
        array $get,
        array $post,
        array $cookie,
        array $files,
        array $server,)
    {
        $this->get = $get;
        $this->post = $post;
        $this->cookie = $cookie;
        $this->files = $files;
        $this->server = $server;
    }

    public function getBody()
    {
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $decoded = json_decode($rawInput, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $this->post ?? [];
    }

    public function getQueryParams()
    {
        return $this->get;
    }

    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function getParamByName($name)
    {
        return $this->params[$name] ?? null;
    }
}
?>