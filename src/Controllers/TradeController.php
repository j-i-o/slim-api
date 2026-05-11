<?php

namespace App\Controllers;

require_once __DIR__ . '/../Utils/validations.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Firebase\JWT\JWT;

use Exception;

use PDO;

class TradeController {

	// POST: Comprar un activo
	public static function buyAsset(Request $request, Response $response) {
		$data = $request->getParsedBody();
		$userId = $request->getAttribute('usuario');
		$assetId = $data['asset_id'];
		$quantity = $data['quantity'];

		$db = \DB::getConnection();
		$stmt = $db->prepare("SELECT current_price FROM assets WHERE id = :id");
		$stmt->execute([':id' => $assetId]);
		$asset = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$asset) {
			$response->getBody()->write(json_encode(['error' => 'Activo no encontrado']));
			return $response->withStatus(404);
		}

		$precioTotal = $asset['current_price'] * $quantity;

		try {
			$db->beginTransaction();

			$stmtUser = $db->prepare("SELECT balance FROM users WHERE id = :id");
			$stmtUser->execute([':id' => $userId]);
			$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

			if ($user['balance'] < $precioTotal) {
				throw new Exception('Saldo insuficiente');
			}

			// Actualizar saldo del usuario
			$stmtActBalance = $db->prepare("UPDATE users SET balance = balance - :amount WHERE id = :id");
			$stmtActBalance->execute([':amount' => $precioTotal, ':id' => $userId]);

			// Actualizar portfolio
			$stmtInsertPortfolio = $db->prepare("INSERT INTO portfolio (user_id, asset_id, quantity) VALUES (:user_id, :asset_id, :quantity)");
			$stmtInsertPortfolio->execute([':user_id' => $userId, ':asset_id' => $assetId, ':quantity' => $quantity]);
			
			// Registrar transacción
			$stmtInsertTransactions = $db->prepare("INSERT INTO transactions (user_id, asset_id, transaction_type, quantity, price_per_unit, total_amount, transaction_date) VALUES (:user_id, :asset_id, :transaction_type, :quantity, :price_per_unit, :total_amount, :transaction_date)");
			$stmtInsertTransactions->execute([
				':user_id' => $userId,
				':asset_id' => $assetId, 
				':transaction_type' => 'buy', 
				':quantity' => $quantity, 
				':price_per_unit' => $asset['current_price'], 
				':total_amount' => $precioTotal, 
				':transaction_date' => date('Y-m-d H:i:s')
				]);

			$db->commit();
			$response->getBody()->write(json_encode(['success' => 'Compra exitosa!']));
			return $response;
		} catch (Exception $e) {
			$db->rollBack();
			$response->getBody()->write(json_encode(['error' => $e->getMessage()]));
			return $response->withStatus(500);
		}
	}

	//POST: Vender un activo
	public static function sellAsset(Request $request, Response $response) {
		$data = $request->getParsedBody();
		$userId = $request->getAttribute('usuario');
		$assetId = $data['asset_id'];
		$quantity = $data['quantity'];

		$db = \DB::getConnection();

		//Valido que exista el activo
		$stmt = $db->prepare("SELECT * FROM assets WHERE id = :id");
		$stmt->execute([':id' => $assetId]);
		$asset = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$asset) {
			$response->getBody()->write(json_encode(['error' => 'Activo no encontrado']));
			return $response->withStatus(404);
		}

		//Valido que el usuario tenga el activo en su portfolio y en suficiente cantidad para vender
		$stmt = $db->prepare("SELECT quantity FROM portfolio WHERE user_id = :user_id AND asset_id = :asset_id");
		$stmt->execute([':user_id' => $userId, ':asset_id' => $assetId]);
		$userAsset = $stmt->fetch(PDO::FETCH_ASSOC);
		if(!isset($userAsset) || $userAsset['quantity'] < $quantity) {
			$response->getBody()->write(json_encode(['error' => 'No posee suficientes unidades']));
			return $response->withStatus(401);
		}

		$precioTotal = $asset['current_price'] * $quantity;
		$finalAmount = $userAsset['quantity'] - $quantity;

		try {
			$db->beginTransaction();

			$stmtUser = $db->prepare("SELECT balance FROM users WHERE id = :id");
			$stmtUser->execute([':id' => $userId]);
			$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

			// Actualizar saldo del usuario
			$stmtActBalance = $db->prepare("UPDATE users SET balance = balance + :amount WHERE id = :id");
			$stmtActBalance->execute([':amount' => $precioTotal, ':id' => $userId]);

			// Actualizar portfolio
			$stmtInsertPortfolio = $db->prepare("UPDATE portfolio SET quantity = :quantity WHERE user_id = :user_id AND asset_id = :asset_id");
			$stmtInsertPortfolio->execute([':user_id' => $userId, ':asset_id' => $assetId, ':quantity' => $finalAmount]);
			
			// Registrar transacción
			$stmtInsertTransactions = $db->prepare("INSERT INTO transactions (user_id, asset_id, transaction_type, quantity, price_per_unit, total_amount, transaction_date) VALUES (:user_id, :asset_id, :transaction_type, :quantity, :price_per_unit, :total_amount, :transaction_date)");
			$stmtInsertTransactions->execute([
				':user_id' => $userId,
				':asset_id' => $assetId, 
				':transaction_type' => 'sell', 
				':quantity' => $quantity, 
				':price_per_unit' => $asset['current_price'], 
				':total_amount' => $precioTotal, 
				':transaction_date' => date('Y-m-d H:i:s')
				]);

			$db->commit();
			$response->getBody()->write(json_encode(['success' => 'Venta exitosa!']));
			return $response;
		} catch (Exception $e) {
			$db->rollBack();
			$response->getBody()->write(json_encode(['error' => $e->getMessage()]));
			return $response->withStatus(500);
		}
	}
}
