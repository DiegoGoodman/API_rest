<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

 $app = AppFactory::create();
 $app->addBodyParsingMiddleware(); // Necessario per leggere il JSON dal body
 $app->addRoutingMiddleware();
 $app->addErrorMiddleware(true, true, true);

// Connessione al DB (modifica con le tue credenziali)
$mysqli = new mysqli("my_mariadb", "root", "ciccio", "minibank");
if ($mysqli->connect_error) {
    die(json_encode(["error" => "Database connection failed"]));
}


// ======================================================================
// ENDPOINT MOVIMENTI
// ======================================================================

// GET Lista movimenti
 $app->get('/accounts/{id}/transactions', function (Request $request, Response $response, array $args) use ($mysqli) {
    $accountId = $args['id'];

    // Debug: Stampa l'ID del conto
    error_log("Account ID: $accountId");

    // 1. Controllo esistenza conto (con prepare e gestione errori)
    $stmt = $mysqli->prepare("SELECT id FROM accounts WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $mysqli->error);
        return errorResponse($response, 500, "Database error");
    }
    $stmt->bind_param('i', $accountId);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return errorResponse($response, 500, "Database error");
    }
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        error_log("Account not found");
        return errorResponse($response, 404, "Account not found");
    }

    // 2. Recupera i movimenti (con prepare e gestione errori)
    $stmt = $mysqli->prepare("SELECT * FROM transactions WHERE account_id = ? ORDER BY created_at DESC");
    if (!$stmt) {
        error_log("Prepare failed: " . $mysqli->error);
        return errorResponse($response, 500, "Database error");
    }
    $stmt->bind_param('i', $accountId);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return errorResponse($response, 500, "Database error");
    }
    $result = $stmt->get_result();

    // Debug: Stampa il numero di righe
    error_log("Num rows: " . $result->num_rows);

    // 3. Elabora i risultati
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $row['amount'] = (float)$row['amount'];
        $row['balance_after'] = (float)$row['balance_after'];
        $transactions[] = $row;
    }

    // Debug: Stampa il numero di transazioni
    error_log("Transactions found: " . count($transactions));

    // 4. Restituisci la risposta
    $response->getBody()->write(json_encode($transactions));
    return $response->withHeader('Content-Type', 'application/json');
});
// GET Dettaglio movimento
 $app->get('/accounts/{id}/transactions/{tid}', function (Request $request, Response $response, array $args) use ($mysqli) {
    $accountId = (int)$args['id'];
    $tid = (int)$args['tid'];

    $stmt = $mysqli->prepare("SELECT * FROM transactions WHERE id = ? AND account_id = ?");
    $stmt->bind_param('ii', $tid, $accountId);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();

    if (!$transaction) return errorResponse($response, 404, "Transaction not found");

    $transaction['amount'] = (float)$transaction['amount'];
    $transaction['balance_after'] = (float)$transaction['balance_after'];
    
    $response->getBody()->write(json_encode($transaction));
    return $response->withHeader('Content-Type', 'application/json');
});

// POST Deposito
 $app->post('/accounts/{id}/deposits', function (Request $request, Response $response, array $args) use ($mysqli) {
    $accountId = (int)$args['id'];
    $data = $request->getParsedBody();

    if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
        return errorResponse($response, 400, "Amount must be a number greater than zero");
    }

    $amount = (float)$data['amount'];
    $description = $data['description'] ?? null;

    $mysqli->begin_transaction();
    try {
        $balance = getCurrentBalance($mysqli, $accountId);
        $newBalance = $balance + $amount;

        $stmt = $mysqli->prepare("INSERT INTO transactions (account_id, type, amount, description, balance_after) VALUES (?, 'deposit', ?, ?, ?)");
        $stmt->bind_param('idsd', $accountId, $amount, $description, $newBalance);
        $stmt->execute();
        
        $mysqli->commit();
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json')->getBody()->write(json_encode(["message" => "Deposit successful", "new_balance" => $newBalance]));
    } catch (Exception $e) {
        $mysqli->rollback();
        return errorResponse($response, 500, "Transaction failed");
    }
});

// POST Prelievo
 $app->post('/accounts/{id}/withdrawals', function (Request $request, Response $response, array $args) use ($mysqli) {
    $accountId = (int)$args['id'];
    $data = $request->getParsedBody();

    if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
        return errorResponse($response, 400, "Amount must be a number greater than zero");
    }

    $amount = (float)$data['amount'];
    $description = $data['description'] ?? null;

    $balance = getCurrentBalance($mysqli, $accountId);
    
    if ($amount > $balance) {
        return errorResponse($response, 422, "Insufficient funds");
    }

    $mysqli->begin_transaction();
    try {
        $newBalance = $balance - $amount;

        $stmt = $mysqli->prepare("INSERT INTO transactions (account_id, type, amount, description, balance_after) VALUES (?, 'withdrawal', ?, ?, ?)");
        $stmt->bind_param('idsd', $accountId, $amount, $description, $newBalance);
        $stmt->execute();
        
        $mysqli->commit();
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json')->getBody()->write(json_encode(["message" => "Withdrawal successful", "new_balance" => $newBalance]));
    } catch (Exception $e) {
        $mysqli->rollback();
        return errorResponse($response, 500, "Transaction failed");
    }
});

// PUT Modifica descrizione
 $app->put('/accounts/{id}/transactions/{tid}', function (Request $request, Response $response, array $args) use ($mysqli) {
    $accountId = (int)$args['id'];
    $tid = (int)$args['tid'];
    $data = $request->getParsedBody();

    if (!isset($data['description'])) {
        return errorResponse($response, 400, "Missing description field");
    }

    $stmt = $mysqli->prepare("UPDATE transactions SET description = ? WHERE id = ? AND account_id = ?");
    $stmt->bind_param('sii', $data['description'], $tid, $accountId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        return errorResponse($response, 404, "Transaction not found");
    }

    $response->getBody()->write(json_encode(["message" => "Description updated"]));
    return $response->withHeader('Content-Type', 'application/json');
});

// DELETE Elimina movimento (Regola: solo l'ultimo movimento)
 $app->delete('/accounts/{id}/transactions/{tid}', function (Request $request, Response $response, array $args) use ($mysqli) {
    $accountId = (int)$args['id'];
    $tid = (int)$args['tid'];

    // Trovo l'ID dell'ultimo movimento
    $lastTx = $mysqli->query("SELECT id FROM transactions WHERE account_id = $accountId ORDER BY created_at DESC, id DESC LIMIT 1")->fetch_assoc();
    
    if (!$lastTx || (int)$lastTx['id'] !== $tid) {
        return errorResponse($response, 400, "You can only delete the most recent transaction");
    }

    $stmt = $mysqli->prepare("DELETE FROM transactions WHERE id = ? AND account_id = ?");
    $stmt->bind_param('ii', $tid, $accountId);
    $stmt->execute();

    $response->getBody()->write(json_encode(["message" => "Transaction deleted"]));
    return $response->withHeader('Content-Type', 'application/json');
});

// ======================================================================
// ENDPOINT SALDO E CONVERSIONI
// ======================================================================

// GET Saldo Attuale
 $app->get('/accounts/{id}/balance', function (Request $request, Response $response, array $args) use ($mysqli) {
    $accountId = (int)$args['id'];
    $acc = $mysqli->query("SELECT id, currency FROM accounts WHERE id = $accountId")->fetch_assoc();
    if (!$acc) return errorResponse($response, 404, "Account not found");

    $balance = getCurrentBalance($mysqli, $accountId);
    
    $response->getBody()->write(json_encode([
        "account_id" => $accountId,
        "currency" => $acc['currency'],
        "balance" => $balance
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// GET Conversione Fiat (Frankfurter) - Preso dalla traccia e adattato
 $app->get('/accounts/{id}/balance/convert/fiat', function (Request $request, Response $response, array $args) use ($mysqli) {
    $accountId = (int)$args['id'];
    $params = $request->getQueryParams();
    $to = strtoupper($params['to'] ?? '');

    if (!$to) return errorResponse($response, 400, "Missing target currency");

    $account = $mysqli->query("SELECT id, currency FROM accounts WHERE id = $accountId")->fetch_assoc();
    if (!$account) return errorResponse($response, 404, "Account not found");

    $from = strtoupper($account['currency']);
    $balance = getCurrentBalance($mysqli, $accountId);

    $url = "https://api.frankfurter.dev/v1/latest?base={$from}&symbols={$to}";
    $json = @file_get_contents($url);

    if ($json === false) return errorResponse($response, 502, "External exchange API unavailable");

    $data = json_decode($json, true);
    if (!isset($data['rates'][$to])) return errorResponse($response, 400, "Target currency not supported");

    $rate = (float)$data['rates'][$to];
    $converted = round($balance * $rate, 2);

    $response->getBody()->write(json_encode([
        'account_id' => $accountId, 'provider' => 'Frankfurter', 'conversion_type' => 'fiat',
        'from_currency' => $from, 'to_currency' => $to, 'original_balance' => $balance,
        'converted_balance' => $converted, 'rate' => $rate, 'date' => $data['date'] ?? null
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// GET Conversione Crypto (Binance) - Sviluppato seguendo la logica della traccia
 $app->get('/accounts/{id}/balance/convert/crypto', function (Request $request, Response $response, array $args) use ($mysqli) {
    $accountId = (int)$args['id'];
    $params = $request->getQueryParams();
    $toCrypto = strtoupper($params['to'] ?? '');

    if (!$toCrypto) return errorResponse($response, 400, "Missing target crypto (e.g. BTC)");

    $account = $mysqli->query("SELECT id, currency FROM accounts WHERE id = $accountId")->fetch_assoc();
    if (!$account) return errorResponse($response, 404, "Account not found");

    $fromCurrency = strtoupper($account['currency']);
    $balance = getCurrentBalance($mysqli, $accountId);

    // Costruisco il simbolo (es. BTC + EUR = BTCEUR)
    $marketSymbol = $toCrypto . $fromCurrency;
    
    // Chiamata API Binance (Spot ticker price)
    $url = "https://api.binance.com/api/v3/ticker/price?symbol=" . $marketSymbol;
    $json = @file_get_contents($url);

    if ($json === false) {
        return errorResponse($response, 502, "Binance API unavailable");
    }

    $data = json_decode($json, true);

    // Binance restituisce un errore JSON con codice -1121 se il simbolo non esiste
    if (isset($data['code']) && $data['code'] === -1121) {
        return errorResponse($response, 400, "Invalid Binance market symbol or pair not supported");
    }

    if (!isset($data['price'])) {
        return errorResponse($response, 502, "Unexpected response from Binance");
    }

    $price = (float)$data['price'];
    
    // Se il saldo è 0, evito divisioni per zero
    $convertedAmount = $balance > 0 ? $balance / $price : 0;
    $convertedAmount = round($convertedAmount, 8);

    $response->getBody()->write(json_encode([
        'account_id' => $accountId,
        'provider' => 'Binance',
        'conversion_type' => 'crypto',
        'from_currency' => $fromCurrency,
        'to_crypto' => $toCrypto,
        'market_symbol' => $marketSymbol,
        'original_balance' => $balance,
        'price' => $price,
        'converted_amount' => $convertedAmount
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// ======================================================================
// FUNZIONI HELPER
// ======================================================================

function getCurrentBalance($mysqli, $accountId): float {
    $stmt = $mysqli->prepare("
        SELECT COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) -
               COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) AS balance
        FROM transactions WHERE account_id = ?
    ");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (float)($row['balance'] ?? 0);
}

function errorResponse(Response $response, int $code, string $message): Response {
    $response->getBody()->write(json_encode(['error' => $message]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($code);
}

 $app->run();