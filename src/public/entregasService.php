<?php 
    use \Psr\Http\Message\ServerRequestInterface as Request;
    use \Psr\Http\Message\ResponseInterface as Response;
	use \Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;
    	
    require '../vendor/autoload.php';	
	
	$username = !empty($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
	$password = !empty($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : null;
	
	$config['displayErrorDetails'] = true;
	$config['addContentLengthHeader'] = false;
	$config['db']['host'] = "localhost";
	$config['db']['user'] = "root";
	$config['db']['pass'] = "root";
	$config['db']['dbname'] = "logistica";
	
	$app= new \Slim\App(["config" => $config]);
	$container = $app->getContainer();
	
	$container['db'] = function($c) {
		$dbConfig= $c['config']['db'];
		$pdo= new PDO("mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['dbname'], $dbConfig['user'], $dbConfig['pass']);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$db= new NotORM($pdo);
		return $db;
	};
	
	$usersList["username"] = "";
	$usersList["password"] = "";
	
	if ($username != null && $password != null) {
		$users = $container['db']->users->where("username = ?", $username)->where("password = ?", $password);
		
		foreach ($users as $users) {
			$usersList["username"] = $users["username"];
			$usersList["password"] = $users["password"];
		}
	}
	
	$app->add(new \Slim\Middleware\HttpBasicAuthentication([
		"users" => [
			$usersList["username"] => $usersList["password"]
		],
		"error" => function ($request, $response, $arguments) {
			$data["status"] = "error";
			$data["message"] = $arguments["message"];
			return $response
				->withHeader("Content-Type", "application/json")
				->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
		}
	]));
	
    $app->put('/entrega/{numeroPedido}', function(Request $request, Response $response) {
		$numeroPedido = $request->getAttribute('numeroPedido');
		$entrega = $this->db->entregas("numero_pedido=?",$numeroPedido)->fetch();
		if(empty($entrega)){
			return $response->withJson("404 NOT FOUND", 404);
		}
		$body = json_decode($request->getBody());
		
		// Update some fields
		$newEntrega = [];
		
		if (isset($body->nomeRecebedor) && isset($body->cpfRecebedor) && isset($body->dataHoraEntrega)) {
			$newEntrega['nome_recebedor']	= $body->nomeRecebedor;	
			$newEntrega['cpf_recebedor']	= $body->cpfRecebedor;	
			$newEntrega['data_hora_entrega'] = date("Y-m-d H:i:s",strtotime(str_replace('/','-',$body->dataHoraEntrega)));		
		}
		else return $response->withStatus(400);
		
		if ($entrega->update($newEntrega) == 0) {
			return $response->withStatus(500);
		}
		return $this->db->entregas("numero_pedido=?",$numeroPedido)->fetch();
	});

	$app->delete('/entrega/{numeroPedido}', function(Request $request, Response $response) {
		$numeroPedido = $request->getAttribute('numeroPedido');
		$entrega = $this->db->entregas("numero_pedido=?",$numeroPedido)->fetch();
		if(empty($entrega)){
			return $response->withJson("404 NOT FOUND", 404);
		}
		if ( $this->db->entregas("numero_pedido=?",$numeroPedido)->delete() == 0) {
			return $response->withStatus(500);
		}
		return $response->withJson("Deleted", 200);
	});

    $app->run();
?>