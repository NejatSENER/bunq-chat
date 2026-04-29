<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\GroupController;
use App\Controllers\MessageController;
use App\Controllers\UserController;
use App\Database\Database;
use App\Middleware\AuthMiddleware;
use DI\Container;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

abstract class AppTestCase extends TestCase
{
    protected App $app;
    protected Database $db;

    protected function setUp(): void
    {
        $this->db = new Database(TEST_DB_PATH);

        $container = new Container();
        $container->set(Database::class, fn() => $this->db);
        $container->set(UserController::class, fn(Container $c) => new UserController($c->get(Database::class)));
        $container->set(GroupController::class, fn(Container $c) => new GroupController($c->get(Database::class)));
        $container->set(MessageController::class, fn(Container $c) => new MessageController($c->get(Database::class)));
        $container->set(AuthMiddleware::class, fn(Container $c) => new AuthMiddleware($c->get(Database::class)));

        AppFactory::setContainer($container);
        $this->app = AppFactory::create();
        $this->app->addBodyParsingMiddleware();
        $this->app->addErrorMiddleware(false, false, false);

        $this->app->post('/users', UserController::class . ':create');

        $this->app->group('', function ($group) {
            $group->get('/groups', GroupController::class . ':list');
            $group->post('/groups', GroupController::class . ':create');
            $group->post('/groups/{id:[0-9]+}/join', GroupController::class . ':join');
            $group->get('/groups/{id:[0-9]+}/messages', MessageController::class . ':list');
            $group->post('/groups/{id:[0-9]+}/messages', MessageController::class . ':send');
        })->addMiddleware($container->get(AuthMiddleware::class));
    }

    protected function request(
        string $method,
        string $path,
        array  $body = [],
        string $token = ''
    ): ResponseInterface {
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest($method, $path);

        if ($token !== '') {
            $request = $request->withHeader('X-User-Token', $token);
        }

        if (!empty($body)) {
            $stream = (new StreamFactory())->createStream(json_encode($body));
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($stream);
        }

        return $this->app->handle($request);
    }

    protected function json(ResponseInterface $response): mixed
    {
        return json_decode((string) $response->getBody(), true);
    }

    protected function createUser(string $username): array
    {
        $response = $this->request('POST', '/users', ['username' => $username]);
        return $this->json($response);
    }
}
