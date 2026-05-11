<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

use App\Middleware\IsLoggedMiddleware;
use Firebase\JWT\JWT;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\AssetController;
use App\Controllers\TradeController;

require_once __DIR__ . '/Models/DB.php';
require_once __DIR__ . '/Utils/validations.php';

return function (App $app) {
  $app->options('/{routes:.*}', function (Request $request, Response $response) {
	  // CORS Pre-Flight OPTIONS Request Handler
	  return $response;
  });

	// Root test route
  $app->get('/test', function (Request $request, Response $response) {
	  $response->getBody()->write(json_encode(['message' => 'Hello World!']));
	  return $response;
  });

	$app->group('/users', function (Group $group) {
		$group->get('', UserController::class . '::getAllUsers')->add(IsLoggedMiddleware::class);
		$group->get('/{id}', UserController::class . '::getUserById')->add(IsLoggedMiddleware::class);
		$group->put('/{id}', UserController::class . '::updateUser')->add(IsLoggedMiddleware::class);
		$group->delete('/{id}', UserController::class . '::deleteUser')->add(IsLoggedMiddleware::class);
		$group->post('', UserController::class . '::createUser');
	}); //le indico que las rutas antemencionadas requieren ejecutar este middleware

	$app->group('', function (Group $group) {
		$group->post('/login', AuthController::class . '::login');
		$group->post('/logout', AuthController::class . '::logout')->add(IsLoggedMiddleware::class);
	});

	$app->group('/assets', function (Group $group) {
		$group->get('', AssetController::class . '::getAssets');
		$group->put('', AssetController::class . '::updateAssetsPrice')->add(IsLoggedMiddleware::class);
	});

	$app->group('/trade', function (Group $group) {
		$group->post('/buy', TradeController::class . '::buyAsset');
		$group->post('/sell', TradeController::class . '::sellAsset');
	})->add(IsLoggedMiddleware::class);
};