<?php

require_once 'conn.php';
require_once 'functions.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

global $db;
global $secret;

$app = new \Slim\App(['settings' => ['displayErrorDetails' => true]]);

// Retorno de erro generico
$container = $app->getContainer();
$container['errorHandler'] = function ($container) {
    return function ($request, $response, $exception) use ($container) {
        return
            $container['response']->withStatus(500)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode(['message' => $exception->getMessage()]));
    };
};

$app->add(new \Tuupola\Middleware\JwtAuthentication([
    "path" => "/api",
    "attribute" => "jwt",
    "secret" => $secret,
    "algorithm" => ["HS256"],
    "secure" => false,
    "error" => function ($response, $arguments) {
        $data = $arguments["message"];
        return $response
            ->withHeader("Content-Type", "application/problem+json")
            ->getBody()->write($data);
    }
]));

$app->post('/login', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();

    $email = $data['email'];
    $password = $data['password'];

    // Chamar a função login
    $loginResult = login($db, $email, $password);

    if ($loginResult['status']) {
        // Retornar o token na resposta
        return $response->withJson([
            'token' => $loginResult['token'],
        ]);
    } else {
        // Autenticação falhou
        return $response->withStatus(401)->withJson(['message' => $loginResult['message']]);
    }
});

// Titulo
$app->post('/api/novo-titulo', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $result = criarTitulo($db, $data);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->post('/api/editar-titulo/{id}', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $result = editarTitulo($db, $data, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->delete('/api/excluir-titulo/{id}', function ($request, $response, $args) use ($db) {
    $id = $args['id'];
    $result = excluirTitulo($db, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->get('/api/titulos', function ($request, $response, $args) use ($db) {
    $titulos = obterTitulos($db);
    return $response->withJson($titulos);
});

//Subtitulo
$app->post('/api/novo-subtitulo', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $result = criarSubtitulo($db, $data);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->post('/api/editar-subtitulo/{id}', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $result = editarSubtitulo($db, $data, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->delete('/api/excluir-subtitulo/{id}', function ($request, $response, $args) use ($db) {
    $id = $args['id'];
    $result = excluirSubtitulo($db, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->get('/api/subtitulos', function ($request, $response, $args) use ($db) {
    $subtitulos = obterSubtitulos($db);
    return $response->withJson($subtitulos);
});

//Pagina
$app->post('/api/nova-pagina', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $userID = $request->getAttribute('jwt')['idUsuario'];
    $result = criarPagina($db, $data, $userID);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->post('/api/editar-pagina/{id}', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $result = editarPagina($db, $data, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->delete('/api/excluir-pagina/{id}', function ($request, $response, $args) use ($db) {
    $id = $args['id'];
    $result = excluirPagina($db, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->get('/api/paginas', function ($request, $response, $args) use ($db) {
    $result = obterPaginas($db);
    return $response->withJson($result);
});

//Noticias
$app->post('/api/nova-noticia', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $userID = $request->getAttribute('jwt')['idUsuario'];
    $files = $request->getUploadedFiles();
    $result = criarNoticia($db, $data, $files, $userID);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->get('/api/noticias', function ($request, $response, $args) use ($db) {
    $result = obterNoticias($db);
    return $response->withJson($result);
});

$app->post('/api/editar-noticia/{id}', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $files = $request->getUploadedFiles();
    $result = editarNoticia($db, $data, $files, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->delete('/api/excluir-noticia/{id}', function ($request, $response, $args) use ($db) {
    $id = $args['id'];
    $result = excluirNoticia($db, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

//Slides
$app->post('/api/novo-slide', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $files = $request->getUploadedFiles();
    $result = criarSlide($db, $data, $files);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->get('/api/slides', function ($request, $response, $args) use ($db) {
    $result = obterSlides($db);
    return $response->withJson($result);
});

$app->delete('/api/excluir-slide/{id}', function ($request, $response, $args) use ($db) {
    $id = $args['id'];
    $result = excluirSlide($db, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->post('/api/editar-slide/{id}', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $files = $request->getUploadedFiles();
    $result = editarSlide($db, $data, $files, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

//Eventos
$app->post('/api/novo-evento', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $userID = $request->getAttribute('jwt')['idUsuario'];
    $files = $request->getUploadedFiles();
    $result = criarEvento($db, $data, $files, $userID);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->get('/api/eventos', function ($request, $response, $args) use ($db) {
    $result = obterEventos($db);
    return $response->withJson($result);
});

$app->post('/api/editar-evento/{id}', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $files = $request->getUploadedFiles();
    $result = editarEvento($db, $data, $files, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->delete('/api/excluir-evento/{id}', function ($request, $response, $args) use ($db) {
    $id = $args['id'];
    $result = excluirEvento($db, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

//Patrocinadores
$app->post('/api/novo-patrocinador', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $result = criarPatrocinador($db, $data);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->get('/api/patrocinadores', function ($request, $response, $args) use ($db) {
    $result = obterPatrocinadores($db);
    return $response->withJson($result);
});

$app->post('/api/editar-patrocinador/{id}', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $result = editarPatrocinador($db, $data, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->delete('/api/excluir-patrocinador/{id}', function ($request, $response, $args) use ($db) {
    $id = $args['id'];
    $result = excluirPatrocinador($db, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});


//Redes Sociais
$app->post('/api/nova-rede', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $result = criarRede($db, $data);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->get('/api/redes', function ($request, $response, $args) use ($db) {
    $result = obterRedes($db);
    return $response->withJson($result);
});

$app->post('/api/editar-rede/{id}', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $id = $args['id'];
    $result = editarRede($db, $data, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

$app->delete('/api/excluir-rede/{id}', function ($request, $response, $args) use ($db) {
    $id = $args['id'];
    $result = excluirRede($db, $id);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

// Usuarios
$app->get('/api/usuario/{id}', function ($request, $response, $args) use ($db) {
    $editUserResult = obterUsuario($db, $args['id']);
    return $response->withStatus($editUserResult['status'])->withJson(['message' => $editUserResult['message']]);
});

$app->get('/api/usuarios', function ($request, $response, $args) use ($db) {
    $editUserResult = obterUsuarios($db, $args['id']);
    return $response->withStatus($editUserResult['status'])->withJson(['message' => $editUserResult['message']]);
});


$app->post('/api/novo-usuario', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $createResult = criarUsuario($db, $data);
    return $response->withStatus($createResult['status'])->withJson(['message' => $createResult['message']]);
});

$app->delete('/api/excluir-usuario/{id}', function ($request, $response, $args) use ($db) {
    $createResult = excluirUsuario($db, $args['id']);
    return $response->withStatus($createResult['status'])->withJson(['message' => $createResult['message']]);
});

$app->post('/api/editar-usuario/{id}', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $editUserResult = editarUsuario($db, $args['id'], $data);
    return $response->withStatus($editUserResult['status'])->withJson(['message' => $editUserResult['message']]);
});

//Outros
$app->get('/api/modalidades', function ($request, $response, $args) use ($db) {
    $result = obterModalidades($db);
    return $response->withJson($result);
});

$app->get('/api/categorias-eventos', function ($request, $response, $args) use ($db) {
    $result = obterCategoriasEventos($db);
    return $response->withJson($result);
});

$app->get('/api/informacoes', function ($request, $response, $args) use ($db) {
    $result = obterInformacoesQtd($db);
    return $response->withJson($result);
});

$app->post('/api/trocar-senha', function ($request, $response, $args) use ($db) {
    $data = $request->getParsedBody();
    $userID = $request->getAttribute('jwt')['idUsuario'];
    $result = trocarSenha($db, $userID, $data);
    return $response->withStatus($result['status'])->withJson(['message' => $result['message']]);
});

include 'api_publica.php';

$app->run();
