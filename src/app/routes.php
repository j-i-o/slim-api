<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

use App\Application\Middleware\IsLoggedMiddleware;
use Firebase\JWT\JWT;

require_once __DIR__ . '/models/DB.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/utils/validations.php';

return function (App $app) {
  $app->options('/{routes:.*}', function (Request $request, Response $response) {
	  // CORS Pre-Flight OPTIONS Request Handler
	  return $response;
  });

  // Root test route
  $app->get('/', function (Request $request, Response $response) {
	  $response->getBody()->write(json_encode(['message' => 'Hello World!']));
	  return $response;
  });

	$app->post('/login', function (Request $request, Response $response) {
		$data = $request->getParsedBody();
		if(!validateEmail($data['email']) || !validatePassword($data['password'])) {
			$response = $response->withStatus(400);
			$response->getBody()->write(json_encode(['error' => 'Email o contraseña inválidos.']));
			return $response;
		}
		$db = DB::getConnection();
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
		$expire = (new \DateTime("now"))->modify("+1 hour")->format("Y-m-d H:i:s");
		$token = JWT::encode(["usuario"=> $user['id'], "expired_at" => $expire], IsLoggedMiddleware::$secret, 'HS256');
		
		$response = $response->withHeader('token', $token);
		$response->getBody()->write(json_encode(['mensaje' => 'Usuario loggeado!']));
		$response->withStatus(200);
		return $response;
	});

  // GET: Retorna todos los usuarios de la BD
  $app->get('/users', function (Request $request, Response $response) {
		//Validar ser admin
	  $db = DB::getConnection();
	  $stmt = $db->query("SELECT * FROM users");
	  $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

	  $response->getBody()->write(json_encode($data));
	  return $response;
  });

  // GET: Retorna un usuario dado un ID
  $app->get('/users/{id}', function (Request $request, Response $response, array $args) {
		//Validar ser admin o el mismo usuario
		$userId = $args['id'];

		if(!is_numeric($userId)) {
			$response = $response->withStatus(400);
			$response->getBody()->write(json_encode(['error' => 'ID de usuario inválido.']));
			return $response;
		}

		$db = DB::getConnection();
	  $stmt = $db->query("SELECT * FROM users WHERE id = " . $userId);
	  $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

	  $response->getBody()->write(json_encode($data));
	  return $response;
  });

  // POST: Crear un nuevo usuario
  $app->post('/users', function (Request $request, Response $response) {
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
			//validar que el email no exista en la base de datos
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
			
		  $db = DB::getConnection();
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
  });

  // PUT: Update an existing user
  $app->put('/users/{id}', function (Request $request, Response $response, array $args) {
	  try {
		  $db = DB::getConnection();
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
  });

  // DELETE: Remove a user by ID
  $app->delete('/users/{id}', function (Request $request, Response $response, array $args) {
	  try {
		  $db = DB::getConnection();
		  $id = $args['id'];

		  $stmt = $db->prepare("DELETE FROM usuario WHERE id = :id");
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
  });

  // GET: Retrieve users using controller logic
  // $app->get('/users/with-controller', \UserController::class . '::getUsers');

};