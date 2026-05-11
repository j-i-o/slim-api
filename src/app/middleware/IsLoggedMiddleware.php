<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseFactoryInterface;

class IsLoggedMiddleware implements Middleware
{
	private ResponseFactoryInterface $responseFactory;
	
	public static $secret = 'superSecret';

	public function __construct() {
			$this->responseFactory = new \Slim\Psr7\Factory\ResponseFactory();
	}

	/**
	 * {@inheritdoc}
	 */
	public function process(Request $request, RequestHandler $handler): Response {
		//Este Middleware realiza chequeos del token
		try {
			//Busco que el header tenga la clave "Authorization" que es donde viaja el token
			//Si este no existe, la ejecución se detiene y envía el mensaje correspondiente
			if ($request->hasHeader("Authorization")){
				$token = $request->getHeaderLine("Authorization");

				if (!empty($token)){
					//Creo una Key, usando una palabra secreta y un algoritmo
					$key = new Key(self::$secret, "HS256");
					//Con la key puedo abrir/decodificar el token recibido y extraer los datos de usuarioId y la fecha de expiración
					$dataToken = JWT::decode($token, $key);
					$now = (new \DateTime("now"))->format("Y-m-d H:i:s");

					if ($dataToken->expired_at < $now){
						$response = $this->responseFactory->createResponse();
						$response->getBody()->write(json_encode(["error"=>'Token vencido']));
						$response->withHeader("Content-Type", "application/json");

						return $response->withStatus(401);
					}else{
						//Si el token es correcto, puedo obtener el ID de usuario
						//WithAttribute sirve para setear un valor en el request 
						//que podrá ser leido en el controller 
						//$usuario = $request->getAttribute('usuario');
						$request = $request->withAttribute('usuario', $dataToken->usuario);
						//Le indico al middleware que le devuelva el control normal a la función que lo llamó
						$response = $handler->handle($request);
						return $response;
					}
				}
			}
			//Si el token no existe, indico que la acción que quiere realizar requiere login
			$response = $this->responseFactory->createResponse();
			$response->getBody()->write(json_encode(["error"=>'Acción requiere login']));
			$response->withHeader("Content-Type", "application/json");
			
			return $response->withStatus(401);
		} catch (\Exception $e) {
			$response = $this->responseFactory->createResponse();
			$response->getBody()->write(json_encode(["error"=>$e->getMessage()]));
			$response->withHeader("Content-Type", "application/json");
			return $response->withStatus(500);
		}
	}
}
