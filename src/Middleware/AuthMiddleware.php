<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Database\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Database $db) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getHeaderLine('X-User-Token');

        if (empty($token)) {
            return $this->unauthorized('Missing X-User-Token header');
        }

        $stmt = $this->db->getPdo()->prepare('SELECT * FROM users WHERE token = ?');
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            return $this->unauthorized('Invalid token');
        }

        return $handler->handle($request->withAttribute('user', $user));
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = new Response(401);
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
