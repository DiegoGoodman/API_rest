<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CertificazioniController
{

    public function index($request, $response, $args){
        $alunno_id = $args['alunno_id'];
        $mysqli = new MySQLi('my_mariadb','root','ciccio','scuola');

        $stmt = $mysqli->prepare("SELECT * FROM certificazioni WHERE alunno_id = ?");
        $stmt->bind_param("i", $alunno_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader("Content-Type","application/json")->withStatus(200);
    }

    public function read($request, $response, $args){
        $alunno_id = $args['alunno_id'];
        $id = $args['id'];
        $mysqli = new MySQLi('my_mariadb','root','ciccio','scuola');

        $stmt = $mysqli->prepare("SELECT * FROM certificazioni WHERE id = ? AND alunno_id = ?");
        $stmt->bind_param("ii", $id, $alunno_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader("Content-Type","application/json")->withStatus(200);
    }

    public function create($request, $response, $args){
        $alunno_id = $args['alunno_id'];
        $data = $request->getParsedBody();

        if(!isset($data['titolo']) || !isset($data['votazione']) || !isset($data['ente'])){
            $response->getBody()->write(json_encode(["error"=>"titolo, votazione e ente obbligatori"]));
            return $response->withHeader("Content-Type","application/json")->withStatus(400);
        }

        $titolo = $data['titolo'];
        $votazione = intval($data['votazione']); 
        $ente = $data['ente'];

        $mysqli = new MySQLi('my_mariadb','root','ciccio','scuola');

        $stmt = $mysqli->prepare("INSERT INTO certificazioni (alunno_id, titolo, votazione, ente) VALUES (?,?,?,?)");
        $stmt->bind_param("isis",$alunno_id, $titolo, $votazione, $ente);
        $stmt->execute();
        $newId = $stmt->insert_id;

        $response->getBody()->write(json_encode([
            "message"=>"Certificazione creata",
            "id"=>$newId
        ]));
        return $response->withHeader("Content-Type","application/json")->withStatus(201);
    }

    public function update($request, $response, $args){
        $alunno_id = $args['alunno_id'];
        $id = $args['id'];
        $data = $request->getParsedBody();

        // Controllo dei campi obbligatori
        if(!isset($data['titolo']) || !isset($data['votazione']) || !isset($data['ente'])){
            $response->getBody()->write(json_encode([
                "error" => "titolo, votazione e ente sono obbligatori"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        $titolo = $data['titolo'];
        $votazione = intval($data['votazione']);  // assicurati che sia un intero
        $ente = $data['ente'];

        $mysqli = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');

        // Aggiornamento della certificazione
        $stmt = $mysqli->prepare("
            UPDATE certificazioni 
            SET titolo = ?, votazione = ?, ente = ? 
            WHERE id = ? AND alunno_id = ?
        ");
        $stmt->bind_param("sisis", $titolo, $votazione, $ente, $id, $alunno_id);
        $stmt->execute();

        // Risposta al client
        if($stmt->affected_rows > 0){
            $response->getBody()->write(json_encode([
                "message" => "Certificazione aggiornata"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
        } else {
            $response->getBody()->write(json_encode([
                "error" => "Non trovato o dati invariati"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(404);
        }
    }

    public function delete($request,$response,$args){
        $alunno_id = $args['alunno_id'];
        $id = $args['id'];
        $mysqli = new MySQLi('my_mariadb','root','ciccio','scuola');

        $stmt = $mysqli->prepare("DELETE FROM certificazioni WHERE id=? AND alunno_id=?");
        $stmt->bind_param("ii",$id,$alunno_id);
        $stmt->execute();

        return $response->withHeader("Content-Type","application/json")->withStatus(204);
    }
}