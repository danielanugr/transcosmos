<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

class CorsMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS, PATCH',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, X-Requested-With, X-Upload-ID',
            'Access-Control-Max-Age' => '86400',
        ];

        foreach ($headers as $key => $val) {
            header("{$key}: {$val}");
        }

        return $response;
    }
}
