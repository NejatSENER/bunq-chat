<?php

declare(strict_types=1);

use App\Controllers\GroupController;
use App\Controllers\MessageController;
use App\Controllers\UserController;
use App\Database\Database;
use App\Middleware\AuthMiddleware;
use DI\Container;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();

$container->set(Database::class, fn() => new Database());
$container->set(UserController::class, fn(Container $c) => new UserController($c->get(Database::class)));
$container->set(GroupController::class, fn(Container $c) => new GroupController($c->get(Database::class)));
$container->set(MessageController::class, fn(Container $c) => new MessageController($c->get(Database::class)));
$container->set(AuthMiddleware::class, fn(Container $c) => new AuthMiddleware($c->get(Database::class)));

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(displayErrorDetails: true, logErrors: false, logErrorDetails: false);

// Public: create a user (no auth needed)
$app->post('/users', UserController::class . ':create');

// Auth-protected routes
$auth = $app->group('', function ($group) {
    $group->get('/groups', GroupController::class . ':list');
    $group->post('/groups', GroupController::class . ':create');
    $group->post('/groups/{id:[0-9]+}/join', GroupController::class . ':join');
    $group->get('/groups/{id:[0-9]+}/messages', MessageController::class . ':list');
    $group->post('/groups/{id:[0-9]+}/messages', MessageController::class . ':send');
})->addMiddleware($container->get(AuthMiddleware::class));

$app->run();
