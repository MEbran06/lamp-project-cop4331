<?php 
namespace App;

class Request
{
    private array $get;
    private array $post;
    private array $cookie;
    private array $files;
    private array $server;

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
        // get the raw data from the request and decode it as a json
        $data = json_decode(file_get_contents('php://input'), true);
        return $data;
    }

    public function getQueryParams()
    {
        return $this->get;
    }
}
?>