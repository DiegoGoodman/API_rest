<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AlunniController
{
  public function index(Request $request, Response $response, $args){
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
    $result = $mysqli_connection->query("SELECT * FROM alunni");
    $results = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }

  public function read(Request $request, Response $response, $args){
    $id = $args['id'];
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
    $result = $mysqli_connection->query("SELECT * FROM alunni WHERE id = $id");
    $results = $result->fetch_all(MYSQLI_ASSOC);

    $response->getBody()->write(json_encode($results));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }

  public function delete(Request $request, Response $response, $args){
    $id = $args['id'];
    $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
    $result = $mysqli_connection->query("DELETE FROM alunni WHERE id = $id");

    $response->getBody()->write("");
    return $response->withHeader("Content-type", "application/json")->withStatus(204);
  }

    public function create(Request $request, Response $response, $args){
      $data = $request->getParsedBody();
  
      if (!isset($data['nome']) || !isset($data['cognome'])) {
          $response->getBody()->write(json_encode([
              "error" => "Nome e cognome sono obbligatori"
          ]));
          return $response->withHeader("Content-type", "application/json")->withStatus(400);
      }
  
      $nome = $data['nome'];
      $cognome = $data['cognome'];
  
      $mysqli_connection = new MySQLi('my_mariadb', 'root', 'ciccio', 'scuola');
  
      $stmt = $mysqli_connection->prepare(
          "INSERT INTO alunni (nome, cognome) VALUES (?, ?)"
      );
  
      $stmt->bind_param("ss", $nome, $cognome);
  
      if ($stmt->execute()) {
          $newId = $stmt->insert_id;
  
          $response->getBody()->write(json_encode([
              "message" => "Alunno creato correttamente",
              "id" => $newId
          ]));
  
          return $response
              ->withHeader("Content-type", "application/json")
              ->withStatus(201);
      } else {
          $response->getBody()->write(json_encode([
              "error" => "Errore nella creazione"
          ]));
  
          return $response
              ->withHeader("Content-type", "application/json")
              ->withStatus(500);
      }
  }
}
