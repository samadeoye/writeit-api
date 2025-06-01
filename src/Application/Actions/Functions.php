<?php

declare(strict_types=1);

namespace App\Application\Actions;

use Psr\Http\Message\ResponseInterface as Response;

class Functions
{
    /**
     * Return json response with header
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param array $data
     * @param int $status
     * @return Response
     */
    public static function getJsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}