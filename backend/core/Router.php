<?php

class Router
{
    /**
     * Danh sách routes
     *
     * Cấu trúc:
     *
     * [
     *     'GET' => [
     *         '/api/trips' => callback
     *     ],
     *     'POST' => [
     *         ...
     *     ]
     * ]
     */
    private $routes = [];


    /**
     * Đăng ký GET route
     */
    public function get($path, $callback)
    {
        $this->routes['GET'][$path] = $callback;
    }


    /**
     * Đăng ký POST route
     */
    public function post($path, $callback)
    {
        $this->routes['POST'][$path] = $callback;
    }


    /**
     * Đăng ký PUT route
     */
    public function put($path, $callback)
    {
        $this->routes['PUT'][$path] = $callback;
    }


    /**
     * Đăng ký DELETE route
     */
    public function delete($path, $callback)
    {
        $this->routes['DELETE'][$path] = $callback;
    }


    /**
     * Xử lý request
     *
     * Ví dụ request:
     *
     * PUT /api/trips?id=5
     *
     * Router phải lấy:
     *
     * method = PUT
     * path   = /api/trips
     *
     * Còn:
     *
     * id=5
     *
     * vẫn để PHP xử lý trong $_GET['id'].
     */
    public function dispatch($method, $path)
    {
        /*
         * Chuẩn hóa HTTP method.
         *
         * Ví dụ:
         * put -> PUT
         * Put -> PUT
         */
        $method = strtoupper((string) $method);


        /*
         * Nếu path không phải string
         * thì chuyển thành chuỗi rỗng.
         */
        $path = (string) $path;


        /*
         * QUAN TRỌNG NHẤT:
         *
         * Loại bỏ query string khỏi path.
         *
         * Ví dụ:
         *
         * /api/trips?id=5
         *
         * sẽ thành:
         *
         * /api/trips
         *
         * Nhưng $_GET['id'] vẫn giữ nguyên giá trị 5.
         */
        $path = parse_url($path, PHP_URL_PATH);


        /*
         * Nếu parse_url trả null thì dùng "/".
         */
        if ($path === null || $path === false || $path === '') {
            $path = '/';
        }


        /*
         * Decode URL nếu path có ký tự encoded.
         */
        $path = urldecode($path);


        /*
         * Chuẩn hóa dấu / cuối URL.
         *
         * Ví dụ:
         *
         * /api/trips/
         *
         * thành:
         *
         * /api/trips
         *
         * Nhưng giữ nguyên "/" của root.
         */
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }


        /*
         * Tìm route.
         */
        if (
            isset($this->routes[$method]) &&
            isset($this->routes[$method][$path])
        ) {

            return call_user_func(
                $this->routes[$method][$path]
            );
        }


        /*
         * Không tìm thấy endpoint.
         */
        http_response_code(404);

        header(
            'Content-Type: application/json; charset=utf-8'
        );


        echo json_encode(
            [
                'success' => false,
                'message' => 'API endpoint not found'
            ],
            JSON_UNESCAPED_UNICODE
        );

        return null;
    }
}