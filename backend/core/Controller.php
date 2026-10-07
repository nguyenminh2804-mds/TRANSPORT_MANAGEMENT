<?php

class Controller
{
    protected function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    protected function success($data = [], $message = 'Success')
    {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    protected function error($message, $statusCode = 400)
    {
        $this->json([
            'success' => false,
            'message' => $message
        ], $statusCode);
    }
}