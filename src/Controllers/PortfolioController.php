<?php

namespace App\Controllers;

require_once __DIR__ . '/../Utils/validations.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use PDO;

class PortfolioController {

	public static function getPortfolio(Request $request, Response $response){
		$userId = $request->getAttribute('usuario');
		$db = \DB::getConnection();

		$stmt = $db->prepare("SELECT * FROM portfolio WHERE user_id = :user_id");
		$stmt->execute([':user_id' => $userId]);
		$portfolio = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$portfolioResponse = [];
		foreach ($portfolio as $userAsset) {
			$stmt = $db->prepare("SELECT * FROM assets WHERE id = :id");
			$stmt->execute([':id' => $userAsset['asset_id']]);
			$asset = $stmt->fetch(PDO::FETCH_ASSOC);
			

			$portfolioResponse[$asset['name']] = $userAsset['quantity'] * $asset['current_price'];
		}

		$response->getBody()->write(json_encode($portfolioResponse));
		return $response;
	}

	// DELETE: Eliminar un activo del portfolio
	public static function deleteAsset(Request $request, Response $response, array $args) {
		$userId = $request->getAttribute('usuario');
		$assetId = $args['asset_id'];
		$db = \DB::getConnection();

		$stmt = $db->prepare("SELECT * FROM portfolio WHERE asset_id = :asset_id AND user_id = :user_id");
		$stmt->execute([':asset_id' => $assetId, ':user_id' => $userId]);
		$portfolioAsset = $stmt->fetch(PDO::FETCH_ASSOC);

		if(!$portfolioAsset) {
			$response->getBody()->write(json_encode(['error' => 'El activo no existe en su portfolio']));
			return $response->withStatus(404);
		}

		if($portfolioAsset['quantity'] > 0) {
			$response->getBody()->write(json_encode(['error' => 'No puedes quitar un activo de tu portfolio si
				aún tienes unidades. Debes venderlas primero.']));
			return $response->withStatus(409);
		}

		$stmt = $db->prepare("DELETE FROM portfolio WHERE id = :id");
		$stmt->execute([':id' => $portfolioAsset['id']]);

		$response->getBody()->write(json_encode(['success' => 'Activo eliminado exitosamente']));
		return $response;
	}
}
