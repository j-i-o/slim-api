<?php

namespace App\Controllers;

require_once __DIR__ . '/../Utils/validations.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Firebase\JWT\JWT;
use App\Middleware\IsLoggedMiddleware;

use PDO;

class AuthController {

    // POST: Login de usuario
    public static function login(Request $request, Response $response) {
		$data = $request->getParsedBody();
		if(!validateEmail($data['email']) || !validatePassword($data['password'])) {
			$response = $response->withStatus(400);
			$response->getBody()->write(json_encode(['error' => 'Email o contraseña inválidos.']));
			return $response;
		}
		$db = \DB::getConnection();
		//Hashear la pass
		$stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND password = :password");
		$stmt->execute([
			':email' => $data['email'] ?? '',
			':password' => $data['password'] ?? ''
		]);
		$user = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$user) {
			$response = $response->withStatus(401);
			$response->getBody()->write(json_encode(['error' => 'Credenciales incorrectas.']));
			return $response;
		}
		
		//Sacar gestión de token a otra parte?? (controller o middleware)
		$expire = (new \DateTime("now"))->modify("+5 minutes")->format("Y-m-d H:i:s");
		$token = JWT::encode(["usuario"=> $user['id'], "expired_at" => $expire], IsLoggedMiddleware::$secret, 'HS256');

		$stmt = $db->prepare("UPDATE users SET token = :token, token_expired_at = :expired_at WHERE id = :id");
		$stmt->execute([
			':id' => $user['id'],
			':token' => $token,
			':expired_at' => $expire
		]);
		
		$response = $response->withHeader('token', $token);
		$response->getBody()->write(json_encode(['mensaje' => 'Sesión iniciada']));
		$response->withStatus(200);
		return $response;
	}

	// POST: Logout de usuario
	public static function logout(Request $request, Response $response) {
		$usuarioId = $request->getAttribute('usuario');
		$db = \DB::getConnection();
		//Hashear la pass
		$stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
		$stmt->execute([
			':id' => $usuarioId
		]);
		$user = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$user) {
			$response = $response->withStatus(401);
			$response->getBody()->write(json_encode(['error' => 'Credenciales incorrectas.']));
			return $response;
		}

		$stmt = $db->prepare("UPDATE users SET token = :token, token_expired_at = :expired_at WHERE id = :id");
		$stmt->execute([
			':id' => $user['id'],
			':token' => null,
			':expired_at' => null
		]);
		
		$response->getBody()->write(json_encode(['mensaje' => 'Sesión cerrada']));
		$response->withStatus(200);
		return $response;
	}
}
