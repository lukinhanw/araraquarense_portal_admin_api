<?php
$accessToken = 'IGQWROYmZAOVlFEOTJ5eV9iUk1QTU03ZAkgzS3h2XzYxV09EaXNXY09pdy03NGdyMExtWDk3QWJ6YWRGV0hkVmg0YkhYa0xmY2o4R3hTek9LU2NsVkJaeTBNVmttejhOUFJhRFpBR25DR1lUQQZDZD';
$userId = '45955301925'; // Substitua pelo ID do usuário Instagram

// Endpoint da API para buscar as mídias
$endpoint = "https://graph.instagram.com/me/media?fields=id,caption,media_url&access_token=id,username&access_token={$accessToken}";

// Faz a requisição para a API do Instagram
$response = file_get_contents($endpoint);
$data = json_decode($response, true);

// Inicializa a lista de postagens
$postagens = [];

// Itera sobre cada mídia e extrai as informações necessárias
foreach ($data['data'] as $post) {
    $postagem = [
        'id' => $post['id'],
        'descricao' => isset($post['caption']) ? $post['caption'] : '',
        'imagem' => $post['media_url']
    ];
    $postagens[] = $postagem;
}

// Retorna a lista de postagens como JSON
header('Content-Type: application/json');
echo json_encode($postagens);
?>
