<?php

namespace App\Controllers;

require_once __DIR__ . '/../Models/User.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use PDOException;

class UserController {
    // GET: Retorna todos los usuarios de la BD
    public static function getAllUsers(Request $request, Response $response) {
      //Validar ser admin
      $db = \DB::getConnection();
      $stmt = $db->query("SELECT name, email FROM users");
      $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $response->getBody()->write(json_encode($data));
      return $response;
    }

    // GET: Retorna un usuario dado un ID
    public static function getUserById(Request $request, Response $response, array $args) {
      //Validar ser admin o el mismo usuario
      $userId = $args['id'];

      if(!is_numeric($userId)) {
        $response = $response->withStatus(400);
        $response->getBody()->write(json_encode(['error' => 'ID de usuario inválido.']));
        return $response;
      }

      $db = \DB::getConnection();
      $stmt = $db->query("SELECT * FROM users WHERE id = " . $userId);
      $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $response->getBody()->write(json_encode($data));
      return $response;
    }

    // PUT: Update an existing user
    public static function updateUser(Request $request, Response $response, array $args) {
      try {
        $db = \DB::getConnection();
        $id = $args['id'];
        $data = $request->getParsedBody();

        $stmt = $db->prepare("UPDATE users SET nombre = :nombre, usuario = :usuario, password = :password WHERE id = :id");
        $stmt->execute([
          ':id' => $id,
          ':nombre' => $data['nombre'] ?? '',
          ':usuario' => $data['usuario'] ?? '',
          ':password' => $data['password'] ?? ''
        ]);

        if ($stmt->rowCount() > 0) {
            $response->getBody()->write(json_encode(['status' => 'User updated']));
        } else {
          $response = $response->withStatus(404);
          $response->getBody()->write(json_encode(['error' => 'User not found or no changes made']));
        }

      } catch (PDOException $e) {
        $response = $response->withStatus(500);
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      }

      return $response;
    }

    // DELETE: Remove a user by ID
    public static function deleteUser(Request $request, Response $response, array $args) {
      try {
        $db = \DB::getConnection();
        $id = $args['id'];

        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() > 0) {
            $response->getBody()->write(json_encode(['status' => 'User deleted']));
        } else {
          $response = $response->withStatus(404);
          $response->getBody()->write(json_encode(['error' => 'User not found']));
        }
      } catch (PDOException $e) {
        $response = $response->withStatus(500);
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      }
      
      return $response;
    }

  public static function createUser(Request $request, Response $response) {
		//Validar admin??
		try {
			$data = $request->getParsedBody();
			if($data['name'] == '' || $data['email'] == '' || $data['password'] == '') {
				$response = $response->withStatus(400);
				$response->getBody()->write(json_encode(['error' => 'Faltan campos (name, email, password)']));
				return $response;
			}
			// name, email, password, balance, is_admin, token, token_expired_at, created_at
			if(!validatePassword($data['password'])) {
				$response = $response->withStatus(400);
				$response->getBody()->write(json_encode(
				[
					'error' => 'La contraseña debe tener al menos 8 caracteres, una mayúscula, 
											una minúscula, un número y un carácter especial.'
				]
				));
				return $response;            
			}
			if(!validateName($data['name'])) {
				$response = $response->withStatus(400);
				$response->getBody()->write(json_encode(['error' => 'El nombre es inválido.']));
				return $response;            
			}
			if(!validateEmail($data['email'])) {
				$response = $response->withStatus(400);
				$response->getBody()->write(json_encode(['error' => 'El email es inválido.']));
				return $response;            
			}
			
			$db = \DB::getConnection();
			$stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
			$stmt->execute([':email' => $data['email']]);
			$emailExistente = $stmt->fetchAll(PDO::FETCH_ASSOC);

			if(!empty($emailExistente)) {
				$response = $response->withStatus(400);
				$response->getBody()->write(json_encode(['error' => 'El email ya está registrado.']));
				return $response;            
			}
			$stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
			$success = $stmt->execute([
				':name' => $data['name'] ?? '',
				':password' => $data['password'] ?? '',
				':email' => $data['email'] ?? ''
			]);

			if ($success) {
				$response->getBody()->write(json_encode(['status' => 'User created']));
			} else {
				$response = $response->withStatus(400);
				$response->getBody()->write(json_encode(['error' => 'User could not be created']));
			}

		} catch (PDOException $e) {
			$response = $response->withStatus(500);
			$response->getBody()->write(json_encode(['error' => $e->getMessage()]));
		}

		return $response;
	}
}
