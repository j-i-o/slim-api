<?php

namespace App\Controllers;

require_once __DIR__ . '/../Utils/validations.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Firebase\JWT\JWT;
use App\Middleware\IsLoggedMiddleware;

use PDO;

class AssetController {

	// GET: Retorna todos los assets y su precio actual (filtro de tipo, precio mínimo y máximo)
	public static function getAssets(Request $request, Response $response) {
		$data = $request->getQueryParams();
		$db = \DB::getConnection();
		$stmt = $db->query("SELECT * FROM assets");
		$where = 'WHERE ';
		if(isset($data['type'])) {
			$where .= "name = '" . $data['type'] . "' AND ";
		}
		if(isset($data['min_price'])) {
			$where .= "current_price >= " . $data['min_price'] . " AND ";
		}
		if(isset($data['max_price'])) {
			$where .= "current_price <= " . $data['max_price'] . " AND ";
		}
		if($where != 'WHERE ') {
			//Quito el ultimo AND
			$where = substr($where, 0, -4);
			$stmt = $db->query("SELECT * FROM assets " . $where);
		}
		$stmt->execute();
		$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$response->getBody()->write(json_encode($assets));
		return $response;
	}

	public static function updateAssetsPrice(Request $request, Response $response) {
		$db = \DB::getConnection();
		$stmt = $db->query("SELECT * FROM assets");
		$stmt->execute();
		$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach($assets as $asset) {
			$newPrice = self::variarPrecioPorTiempo($asset['current_price'], strtotime($asset['last_update']));
			$stmtUpdate = $db->prepare("UPDATE assets SET current_price = :current_price, last_update = :last_update WHERE id = :id");
			$stmtUpdate->execute([
				':id' => $asset['id'],
				':current_price' => $newPrice,
				':last_update' => date('Y-m-d H:i:s')
			]);
		}
		$response->getBody()->write(json_encode([true]));
		return $response;
	}

	//Implementar el get historial de transacciones

	private static function variarPrecioPorTiempo($precioActual, $ultimoTimestamp, $volatilidadPorSegundo = 0.05) {
		$tiempoPasado = time() - $ultimoTimestamp;
		if ($tiempoPasado <= 0) {
			return $precioActual; // Sin variación
		}

		$direccion = mt_rand(-100, 100) / 100; // Variación aleatoria entre -1 y 1
		$delta = $direccion * $volatilidadPorSegundo * $tiempoPasado;

		return $precioActual + $delta;
	}
}
