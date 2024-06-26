<?php

$app->get('/menu', function ($request, $response, $args) use ($db) {
    $result = obterMenu($db);
    return $response->withJson($result);
});

$app->get('/redes-sociais', function ($request, $response, $args) use ($db) {
    $result = obterRedesSociais($db);
    return $response->withJson($result);
});

$app->get('/slides', function ($request, $response, $args) use ($db) {
    $result = obterSlidesPublico($db);
    return $response->withJson($result);
});

$app->get('/eventos', function ($request, $response, $args) use ($db) {
    $result = obterEventosPublicos($db);
    return $response->withJson($result);
});

$app->get('/patrocinadores', function ($request, $response, $args) use ($db) {
    $result = obterPatrocinadoresPublicos($db);
    return $response->withJson($result);
});

$app->get('/noticias', function ($request, $response, $args) use ($db) {
    $result = obterNoticiasPublicos($db);
    return $response->withJson($result);
});

$app->get('/noticias/{limite}', function ($request, $response, $args) use ($db) {
    $limite = $args['limite'];
    $result = obterNoticiasPublicos($db, $limite);
    return $response->withJson($result);
});

$app->get('/noticia/{id}', function ($request, $response, $args) use ($db) {
    $id = $args['id'];
    $result = obterNoticiaPublica($db, $id);
    return $response->withJson($result);
});

$app->get('/modalidades', function ($request, $response, $args) use ($db) {
    $result = obterModalidadesPublicos($db);
    return $response->withJson($result);
});

$app->get('/categorias-evento', function ($request, $response, $args) use ($db) {
    $result = obterCategoriasEventoPublicos($db);
    return $response->withJson($result);
});

$app->get('/footer', function ($request, $response, $args) use ($db) {
    $result = obterFooterPublicos($db);
    return $response->withJson($result);
});

$app->get('/page/{id}', function ($request, $response, $args) use ($db) {
    $id = $args['id'];
    $result = obterPaginaPublica($db, $id);
    return $response->withJson($result);
});

$app->get('/evento/{id}', function ($request, $response, $args) use ($db) {
    $id = $args['id'];
    $result = obterEventoPublica($db, $id);
    return $response->withJson($result);
});

$app->get('/busca/{termo}', function ($request, $response, $args) use ($db, $accessToken) {
    $termo = $args['termo'];
    $result = buscarSite($db, $termo, $accessToken);
    return $response->withJson($result);
});

$app->get('/slide/{id}', function ($request, $response, $args) use ($db) {
    $id = $args['id'];
    $result = obterSlidePublico($db, $id);
    return $response->withJson($result);
});

// Instagram (Noticias)

$app->get('/instagram', function ($request, $response, $args) use ($accessToken) {
    $result = obterInstagram($accessToken);
    return $response->withJson($result);
});

$app->get('/instagram/{id}', function ($request, $response, $args) use ($accessToken) {
    $id = $args['id'];
    $result = obterInstagramID($accessToken, $id);
    return $response->withJson($result);
});

