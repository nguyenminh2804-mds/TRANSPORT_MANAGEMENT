<?php

class Router
{
    private $routes = [];

    public function get($path, $callback)
    {
        $this->routes['GET'][$path] = $callback;
    }

    public function post($path, $callback)
    {
        $this->routes['POST'][$path] = $callback;
    }

    public function put($path, $callback)
    {
        $this->routes['PUT'][$path] = $callback;
    }

    public function delete($path, $callback)
    {
        $this->routes['DELETE'][$path] = $callback;
    }

    public function dispatch($method, $path)
    {
        if (isset($this->routes[$method][$path])) {
            return call_user_func($this->routes[$method][$path]);
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => 'API endpoint not found'
        ], JSON_UNESCAPED_UNICODE);
    }
}