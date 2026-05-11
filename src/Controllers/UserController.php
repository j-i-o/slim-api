<?php

require_once __DIR__ . '/../Models/User.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController {

    // Handle GET /users
    public static function getUsers(Request $request, Response $response) {
        $users = User::getAll();
        $response->getBody()->write(json_encode($users));
        return $response;
    }
    // Handle POST /users
    public static function createUser(Request $request, Response $response) {
        $data = $request->getParsedBody();
        // name, email, password, balance, is_admin, token, token_expired_at, created_at.
        
        $user = new User($data['nombre'] ?? '', $data['usuario'] ?? '', $data['password'] ?? '');
        // $user->save();
        $response->getBody()->write(json_encode(['status' => 'User created']));
                    return $response;
    }
}
