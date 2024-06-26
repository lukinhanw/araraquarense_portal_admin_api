<?php

include_once 'conn.php';
require_once __DIR__ . '/vendor/autoload.php'; // Caminho para o arquivo de autoload do Composer

use \Firebase\JWT\JWT;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$secret = $_ENV['JWT_SECRET'];


function generateJwt($payload) {
    global $secret;
    $key = $secret;  // substitua por sua chave secreta

    // Adicionando algumas propriedades padrão ao payload
    $payload["iss"] = "FemsaRGV";  // substitua por seu emissor
    $payload["aud"] = "FemsaRGV";  // substitua por sua audiência
    $payload["iat"] = time();
    $payload["exp"] = time() + (60 * 60);  // token expira após 1 hora

    return JWT::encode($payload, $key);
}

function checkFieldUserExist($db, $field, $value, $excludeId) {
    $query = "SELECT COUNT(*) FROM usuarios WHERE {$field} = :value AND id != :excludeId";
    $stmt = $db->prepare($query);
    $stmt->execute([':value' => $value, ':excludeId' => $excludeId]);
    return $stmt->fetchColumn() > 0;
}

function login($db, $email, $password) {
    // Buscar o usuário no banco de dados
    $stmt = $db->prepare('SELECT * FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar se o usuário existe e a senha está correta
    if ($user && password_verify($password, $user['senha'])) {
        // Gerar o token JWT
        $tokenPayload = [
            "idUsuario" => $user['id'],
            "nivel" => $user['nivel'],
            "nomeCompleto" => $user['nome_completo'],
            'foto' => $user['foto'],
        ];

        $token = generateJwt($tokenPayload);
        // Retorna um array com o token e status de sucesso
        return ['token' => $token, 'status' => true];
    } else {
        // Autenticação falhou
        return ['status' => false, "message" => "Usuário ou senha estão incorretos."];
    }
}

function criarUsuario($db, $data) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['nome_completo', 'email', 'senha'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Verifica se senha e confirmar_senha são iguais.
    if ($data['senha'] != $data['senha_confirma']) {
        return ['status' => 400, 'message' => "As senhas digitadas não conferem."];
    }

    // Verifica se o campos existentes
    $checkFields = ['cpf', 'email'];
    foreach ($checkFields as $field) {
        if (isset($data[$field])) {
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE {$field} = :{$field}");
            $stmt->execute([":{$field}" => $data[$field]]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                return ['status' => 400, 'message' => ucfirst($field) . " '{$data[$field]}' já existe."];
            }
        }
    }

    //Definir o nivel padrão como Administrador
    if (!isset($data['nivel'])) {
        $data['nivel'] = 'Administrador';
    }

    // Se passar todas as verificações, cria o novo usuário
    $senha_hash = password_hash($data['senha'], PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO usuarios (nome_completo, email, senha, nivel, cpf, nascimento) 
                          VALUES (:nome_completo, :email, :senha, :nivel, :cpf, :nascimento)');
    $stmt->execute([
        ':nome_completo' => $data['nome_completo'],
        ':email' => $data['email'],
        ':senha' => $senha_hash,
        ':nivel' => $data['nivel'],
        ':cpf' => $data['cpf'],
        ':nascimento' => $data['nascimento'],
    ]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Usuário criado com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao criar usuário'];
    }
}

function moveUploadedFile($directory, $uploadedFile) {
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8)); // gera um nome de arquivo aleatório para evitar colisões de nome
    $filename = sprintf('%s.%0.8s', $basename, $extension);

    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

    return $filename;
}

//Titulo
function criarTitulo($db, $data) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['titulo', 'url', 'target', 'posicao'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Se passar todas as verificações, cria o novo título
    $stmt = $db->prepare('INSERT INTO titulos (titulo, url, target, posicao, ordem) 
                            VALUES (:titulo, :url, :target, :posicao, :ordem)');
    $stmt->execute([
        ':titulo' => $data['titulo'],
        ':url' => $data['url'],
        ':target' => $data['target'],
        ':posicao' => $data['posicao'],
        ':ordem' => $data['ordem']
    ]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Título criado com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao criar título'];
    }
}

function editarTitulo($db, $data, $id) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['titulo', 'url', 'posicao'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Se passar todas as verificações, atualiza o título
    $stmt = $db->prepare('UPDATE titulos SET titulo = :titulo, url = :url, target = :target, posicao = :posicao, ordem = :ordem WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':titulo' => $data['titulo'],
        ':url' => $data['url'],
        ':target' => $data['target'],
        ':posicao' => $data['posicao'],
        ':ordem' => $data['ordem']
    ]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Título atualizado com sucesso'];
    } else {
        return ['status' => 200, 'message' => 'Nenhum dado para atualizar'];
    }
}

function excluirTitulo($db, $id) {

    $stmt = $db->prepare('DELETE FROM titulos WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Título excluído com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao excluir título'];
    }
}

function obterTitulos($db) {

    $stmt = $db->prepare("SELECT * FROM titulos ORDER BY ordem");
    $stmt->execute();
    $titulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($titulos) {
        return ['status' => 200, 'message' => $titulos];
    } else {
        return ['status' => 400, 'message' => 'Nenhum título encontrado.'];
    }
}

//Subtitulo
function criarSubtitulo($db, $data) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['subtitulo', 'titulo_id'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Se passar todas as verificações, cria o novo subtitulo
    $stmt = $db->prepare('INSERT INTO subtitulos (subtitulo, titulo_id, ordem) 
                            VALUES (:subtitulo, :titulo_id, :ordem)');
    $stmt->execute([
        ':subtitulo' => $data['subtitulo'],
        ':titulo_id' => $data['titulo_id'],
        ':ordem' => $data['ordem']
    ]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Subtítulo criado com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao criar subtítulo'];
    }
}

function editarSubtitulo($db, $data, $id) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['subtitulo', 'titulo_id'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Se passar todas as verificações, atualiza o subtitulo
    $stmt = $db->prepare('UPDATE subtitulos SET subtitulo = :subtitulo, titulo_id = :titulo_id, ordem = :ordem WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':subtitulo' => $data['subtitulo'],
        ':titulo_id' => $data['titulo_id'],
        ':ordem' => $data['ordem']
    ]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Subtítulo atualizado com sucesso'];
    } else {
        return ['status' => 200, 'message' => 'Nenhum dado para atualizar'];
    }
}

function excluirSubtitulo($db, $id) {

    $stmt = $db->prepare('DELETE FROM subtitulos WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Subtítulo excluído com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao excluir subtítulo'];
    }
}

function obterSubtitulos($db) {

    $stmt = $db->prepare("SELECT subtitulos.id, subtitulos.titulo_id, subtitulos.ordem, subtitulos.subtitulo, titulos.titulo, titulos.posicao FROM subtitulos LEFT JOIN titulos ON subtitulos.titulo_id = titulos.id ORDER BY ordem");
    $stmt->execute();
    $subtitulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($subtitulos) {
        return ['status' => 200, 'message' => $subtitulos];
    } else {
        return ['status' => 200, 'message' => 'Nenhum subtítulo encontrado.'];
    }
}

//Paginas
function criarPagina($db, $data, $userID) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['pagina', 'subtitulo', 'subtitulo_id'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }


    $stmt = $db->prepare('INSERT INTO paginas (pagina, subtitulo, subtitulo_id, url, descricao, target, data_horario, usuario_id, ordem) 
                            VALUES (:pagina, :subtitulo, :subtitulo_id, :url, :descricao, :target, NOW(), :usuario_id, :ordem)');
    $stmt->execute([
        ':pagina' => $data['pagina'],
        ':subtitulo' => $data['subtitulo'],
        ':subtitulo_id' => $data['subtitulo_id'],
        ':url' => $data['url'],
        ':descricao' => $data['descricao'],
        ':target' => $data['target'],
        ':ordem' => $data['ordem'],
        ':usuario_id' => $userID
    ]);




    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Página criada com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao criar página'];
    }
}

function editarPagina($db, $data, $id) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['pagina', 'subtitulo', 'subtitulo_id'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Se passar todas as verificações, atualiza a página
    $stmt = $db->prepare('UPDATE paginas SET pagina = :pagina, subtitulo = :subtitulo, subtitulo_id = :subtitulo_id, url = :url, descricao = :descricao, target = :target, ordem = :ordem WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':pagina' => $data['pagina'],
        ':subtitulo' => $data['subtitulo'],
        ':subtitulo_id' => $data['subtitulo_id'],
        ':url' => $data['url'],
        ':descricao' => $data['descricao'],
        ':target' => $data['target'],
        ':ordem' => $data['ordem']
    ]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Página atualizada com sucesso'];
    } else {
        return ['status' => 200, 'message' => 'Nenhum dado para atualizar'];
    }
}

function excluirPagina($db, $id) {

    $stmt = $db->prepare('DELETE FROM paginas WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Página excluída com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao excluir página'];
    }
}

function obterPaginas($db) {

    $stmt = $db->prepare("SELECT paginas.id, paginas.pagina, paginas.ordem, paginas.subtitulo, paginas.subtitulo_id, paginas.url, paginas.descricao, paginas.target, titulos.titulo, subtitulos.subtitulo as subtitulo_nome FROM paginas LEFT JOIN titulos ON paginas.subtitulo_id = titulos.id LEFT JOIN subtitulos ON paginas.subtitulo = subtitulos.id");
    $stmt->execute();
    $paginas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($paginas) {
        return ['status' => 200, 'message' => $paginas];
    } else {
        return ['status' => 200, 'message' => 'Nenhuma página encontrada.'];
    }
}

//Noticias
function criarNoticia($db, $data, $files, $userID) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['titulo', 'modalidade_id', 'descricao', 'assunto'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Verificar se o arquivo foi enviado
    if (!isset($files['file'])) {
        return ['status' => 400, 'message' => 'Arquivo não enviado.'];
    }

    $file = $files['file'];
    $directory = __DIR__ . '/fotos-noticias';

    if ($file->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $file);

        // Se passar todas as verificações, cria a nova noticia
        $stmt = $db->prepare('INSERT INTO noticias (titulo, modalidade_id, descricao, data_horario, usuario_id, assunto, imagem) 
                                VALUES (:titulo, :modalidade_id, :descricao, NOW(), :usuario_id, :assunto, :imagem)');
        $stmt->execute([
            ':titulo' => $data['titulo'],
            ':modalidade_id' => $data['modalidade_id'],
            ':descricao' => $data['descricao'],
            ':assunto' => $data['assunto'],
            ':imagem' => $filename,
            ':usuario_id' => $userID
        ]);

        if ($stmt->rowCount() > 0) {
            return ['status' => 200, 'message' => 'Notícia criada com sucesso'];
        } else {
            return ['status' => 400, 'message' => 'Falha ao criar notícia'];
        }
    } else {
        return ['status' => 400, 'message' => 'Erro ao fazer upload da foto.'];
    }
}

function obterNoticias($db) {

    $stmt = $db->prepare("SELECT noticias.id, noticias.titulo, noticias.assunto, noticias.modalidade_id, noticias.descricao, noticias.imagem, modalidades.nome as modalidade FROM noticias LEFT JOIN modalidades ON noticias.modalidade_id = modalidades.id");
    $stmt->execute();
    $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($noticias) {
        return ['status' => 200, 'message' => $noticias];
    } else {
        return ['status' => 200, 'message' => 'Nenhuma notícia encontrada.'];
    }
}

function editarNoticia($db, $data, $files, $id) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['titulo', 'modalidade_id', 'descricao', 'assunto'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Verificar se o arquivo foi enviado
    if (!isset($files['file'])) {
        return ['status' => 400, 'message' => 'Arquivo não enviado.'];
    }

    $file = $files['file'];
    $directory = __DIR__ . '/fotos-noticias';

    if ($file->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $file);

        // Se passar todas as verificações, atualiza a noticia
        $stmt = $db->prepare('UPDATE noticias SET titulo = :titulo, modalidade_id = :modalidade_id, descricao = :descricao, assunto = :assunto, imagem = :imagem WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':titulo' => $data['titulo'],
            ':modalidade_id' => $data['modalidade_id'],
            ':descricao' => $data['descricao'],
            ':imagem' => $filename,
            ':assunto' => $data['assunto']
        ]);

        if ($stmt->rowCount() > 0) {
            return ['status' => 200, 'message' => 'Notícia atualizada com sucesso'];
        } else {
            return ['status' => 200, 'message' => 'Nenhum dado para atualizar'];
        }
    } else {
        return ['status' => 400, 'message' => 'Erro ao fazer upload da foto.'];
    }
}

function excluirNoticia($db, $id) {

    // Primeiro, obtenha o caminho do arquivo da imagem
    $stmt = $db->prepare('SELECT imagem FROM noticias WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $imagem = $stmt->fetchColumn();

    // Em seguida, exclua o registro
    $stmt = $db->prepare('DELETE FROM noticias WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        $path_imagem = "fotos-noticias/{$imagem}";

        // Se o registro foi excluído, apague o arquivo da imagem
        if (file_exists($path_imagem)) {
            unlink($path_imagem);
            return ['status' => 200, 'message' => 'Noticia excluído com sucesso'];
        } else {
            return ['status' => 400, 'message' => 'Falha ao excluir a foto'];
        }
    } else {
        return ['status' => 400, 'message' => 'Falha ao excluir evento'];
    }

}

//Slides
function criarSlide($db, $data, $files) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['titulo'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Verificar se o arquivo foi enviado
    if (!isset($files['file'])) {
        return ['status' => 400, 'message' => 'Arquivo não enviado.'];
    }

    $file = $files['file'];
    $directory = __DIR__ . '/fotos-slides';

    if ($file->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $file);

        // Se passar todas as verificações, cria o novo slide
        $stmt = $db->prepare('INSERT INTO slides (titulo, url, descricao, target, imagem) 
                                    VALUES (:titulo, :url, :descricao, :target, :imagem)');
        $stmt->execute([
            ':titulo' => $data['titulo'],
            ':url' => $data['url'],
            ':descricao' => $data['descricao'],
            ':target' => $data['target'],
            ':imagem' => $filename
        ]);

        if ($stmt->rowCount() > 0) {
            return ['status' => 200, 'message' => 'Slide criado com sucesso'];
        } else {
            return ['status' => 400, 'message' => 'Falha ao criar slide'];
        }
    } else {
        return ['status' => 400, 'message' => 'Erro ao fazer upload da foto.'];
    }
}

function obterSlides($db) {

    $stmt = $db->prepare("SELECT * FROM slides");
    $stmt->execute();
    $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($slides) {
        return ['status' => 200, 'message' => $slides];
    } else {
        return ['status' => 200, 'message' => 'Nenhum slide encontrado.'];
    }
}

function excluirSlide($db, $id) {

    // Primeiro, obtenha o caminho do arquivo da imagem
    $stmt = $db->prepare('SELECT imagem FROM slides WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $imagem = $stmt->fetchColumn();

    // Em seguida, exclua o registro
    $stmt = $db->prepare('DELETE FROM slides WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        $path_imagem = "fotos-slides/{$imagem}";

        // Se o registro foi excluído, apague o arquivo da imagem
        if (file_exists($path_imagem)) {
            unlink($path_imagem);
            return ['status' => 200, 'message' => 'Evento excluído com sucesso'];
        } else {
            return ['status' => 400, 'message' => 'Falha ao excluir a foto'];
        }
    } else {
        return ['status' => 400, 'message' => 'Falha ao excluir evento'];
    }
}

function editarSlide($db, $data, $files, $id) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['titulo'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Verificar se o arquivo foi enviado
    if (!isset($files['file'])) {
        return ['status' => 400, 'message' => 'Arquivo não enviado.'];
    }

    $file = $files['file'];
    $directory = __DIR__ . '/fotos-slides';

    if ($file->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $file);
        // Se passar todas as verificações, atualiza o slide
        $stmt = $db->prepare('UPDATE slides SET titulo = :titulo, url = :url, descricao = :descricao, target = :target, imagem = :imagem WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':titulo' => $data['titulo'],
            ':url' => $data['url'],
            ':descricao' => $data['descricao'],
            ':target' => $data['target'],
            ':imagem' => $filename
        ]);

        if ($stmt->rowCount() > 0) {
            return ['status' => 200, 'message' => 'Slide atualizado com sucesso'];
        } else {
            return ['status' => 200, 'message' => 'Nenhum dado para atualizar'];
        }
    } else {
        return ['status' => 400, 'message' => 'Erro ao fazer upload da foto.'];
    }
}

//Eventos
function criarEvento($db, $data, $files, $userID) {
    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['titulo', 'categoria_id', 'descricao', 'data_horario'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Verificar se o arquivo foi enviado
    if (!isset($files['file'])) {
        return ['status' => 400, 'message' => 'Arquivo não enviado.'];
    }

    $file = $files['file'];
    $directory = __DIR__ . '/fotos-eventos';

    if ($file->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $file);

        // Se passar todas as verificações, cria o novo evento
        $stmt = $db->prepare('INSERT INTO eventos (titulo, categoria_id, descricao, imagem, data_horario, usuario_id) 
                            VALUES (:titulo, :categoria_id, :descricao, :imagem, :data_horario, :usuario_id)');
        $stmt->execute([
            ':titulo' => $data['titulo'],
            ':categoria_id' => $data['categoria_id'],
            ':descricao' => $data['descricao'],
            ':data_horario' => $data['data_horario'],
            ':imagem' => $filename,
            ':usuario_id' => $userID
        ]);

        if ($stmt->rowCount() > 0) {
            return ['status' => 200, 'message' => 'Evento criado com sucesso'];
        } else {
            return ['status' => 400, 'message' => 'Falha ao criar evento'];
        }
    } else {
        return ['status' => 400, 'message' => 'Erro ao fazer upload da foto.'];
    }
}

function obterEventos($db) {

    $stmt = $db->prepare("SELECT eventos.id, eventos.titulo, eventos.categoria_id, eventos.data_horario, eventos.descricao, eventos.imagem, categorias_eventos.nome as categoria FROM eventos LEFT JOIN categorias_eventos ON eventos.categoria_id = categorias_eventos.id");
    $stmt->execute();
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($eventos) {
        return ['status' => 200, 'message' => $eventos];
    } else {
        return ['status' => 200, 'message' => 'Nenhum evento encontrado.'];
    }
}

function editarEvento($db, $data, $files, $id) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['titulo', 'categoria_id', 'descricao', 'data_horario'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Verificar se o arquivo foi enviado
    if (!isset($files['file'])) {
        return ['status' => 400, 'message' => 'Arquivo não enviado.'];
    }

    $file = $files['file'];
    $directory = __DIR__ . '/fotos-eventos';

    if ($file->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $file);
        // Se passar todas as verificações, atualiza o evento
        $stmt = $db->prepare('UPDATE eventos SET titulo = :titulo, categoria_id = :categoria_id, descricao = :descricao, imagem = :imagem, data_horario = :data_horario WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':titulo' => $data['titulo'],
            ':categoria_id' => $data['categoria_id'],
            ':data_horario' => $data['data_horario'],
            ':descricao' => $data['descricao'],
            ':imagem' => $filename
        ]);

        if ($stmt->rowCount() > 0) {
            return ['status' => 200, 'message' => 'Evento atualizado com sucesso'];
        } else {
            return ['status' => 200, 'message' => 'Nenhum dado para atualizar'];
        }
    } else {
        return ['status' => 400, 'message' => 'Erro ao fazer upload da foto.'];
    }
}

function excluirEvento($db, $id) {

    // Primeiro, obtenha o caminho do arquivo da imagem
    $stmt = $db->prepare('SELECT imagem FROM eventos WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $imagem = $stmt->fetchColumn();

    // Em seguida, exclua o registro
    $stmt = $db->prepare('DELETE FROM eventos WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        $path_imagem = "fotos-eventos/{$imagem}";

        // Se o registro foi excluído, apague o arquivo da imagem
        if (file_exists($path_imagem)) {
            unlink($path_imagem);
            return ['status' => 200, 'message' => 'Evento excluído com sucesso'];
        } else {
            return ['status' => 400, 'message' => 'Falha ao excluir a foto'];
        }
    } else {
        return ['status' => 400, 'message' => 'Falha ao excluir evento'];
    }
}

//Patrocinadores
function criarPatrocinador($db, $data) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['imagem', 'ordem'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Se ordem for vazio então pegar o valor da ultima ordem
    if ($data['ordem'] == '' || $data['ordem'] == null) {
        $stmt = $db->prepare("SELECT ordem FROM patrocinadores ORDER BY ordem DESC LIMIT 1");
        $stmt->execute();
        $ordem = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['ordem'] = $ordem['ordem'] + 1;
    }

    // Se passar todas as verificações, cria o novo patrocinador
    $stmt = $db->prepare('INSERT INTO patrocinadores (imagem, ordem) 
                            VALUES (:imagem, :ordem)');
    $stmt->execute([
        ':imagem' => $data['imagem'],
        ':ordem' => $data['ordem']
    ]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Patrocinador criado com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao criar patrocinador'];
    }
}

function obterPatrocinadores($db) {

    $stmt = $db->prepare("SELECT * FROM patrocinadores ORDER BY ordem");
    $stmt->execute();
    $patrocinadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($patrocinadores) {
        return ['status' => 200, 'message' => $patrocinadores];
    } else {
        return ['status' => 200, 'message' => 'Nenhum patrocinador encontrado.'];
    }
}

function editarPatrocinador($db, $data, $id) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['imagem', 'ordem'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Se passar todas as verificações, atualiza o patrocinador
    $stmt = $db->prepare('UPDATE patrocinadores SET imagem = :imagem, ordem = :ordem WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':imagem' => $data['imagem'],
        ':ordem' => $data['ordem']
    ]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Patrocinador atualizado com sucesso'];
    } else {
        return ['status' => 200, 'message' => 'Nenhum dado para atualizar'];
    }
}

function excluirPatrocinador($db, $id) {

    $stmt = $db->prepare('DELETE FROM patrocinadores WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Patrocinador excluído com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao excluir patrocinador'];
    }
}

//Redes Sociais
function criarRede($db, $data) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['rede', 'id_rede', 'url'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Se passar todas as verificações, cria a nova rede
    $stmt = $db->prepare('INSERT INTO redes_sociais (rede, id_rede, url, target) 
                            VALUES (:rede, :id_rede, :url, :target)');
    $stmt->execute([
        ':rede' => $data['rede'],
        ':id_rede' => $data['id_rede'],
        ':url' => $data['url'],
        ':target' => $data['target']
    ]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Rede social criada com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao criar rede social'];
    }
}

function obterRedes($db) {

    $stmt = $db->prepare("SELECT * FROM redes_sociais");
    $stmt->execute();
    $redes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($redes) {
        return ['status' => 200, 'message' => $redes];
    } else {
        return ['status' => 200, 'message' => 'Nenhuma rede social encontrada.'];
    }
}

function obterUsuarios($db) {

    $stmt = $db->prepare("SELECT * FROM usuarios");
    $stmt->execute();
    $redes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($redes) {
        return ['status' => 200, 'message' => $redes];
    } else {
        return ['status' => 200, 'message' => 'Nenhum usuário encontrado.'];
    }
}

function editarRede($db, $data, $id) {

    // Verifica se todos os campos necessários estão presentes
    $requiredFields = ['rede', 'id_rede', 'url'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return ['status' => 400, 'message' => "O campo '{$field}' é obrigatório."];
        }
    }

    // Se passar todas as verificações, atualiza a rede
    $stmt = $db->prepare('UPDATE redes_sociais SET rede = :rede, id_rede = :id_rede, url = :url, target = :target WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':rede' => $data['rede'],
        ':id_rede' => $data['id_rede'],
        ':url' => $data['url'],
        ':target' => $data['target']
    ]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Rede social atualizada com sucesso'];
    } else {
        return ['status' => 200, 'message' => 'Nenhum dado para atualizar'];
    }
}

function excluirRede($db, $id) {

    $stmt = $db->prepare('DELETE FROM redes_sociais WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Rede social excluída com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao excluir rede social'];
    }
}

//Outros
function obterModalidades($db) {

    $stmt = $db->prepare("SELECT * FROM modalidades");
    $stmt->execute();
    $modalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($modalidades) {
        return ['status' => 200, 'message' => $modalidades];
    } else {
        return ['status' => 200, 'message' => 'Nenhuma modalidade encontrada.'];
    }
}

function obterCategoriasEventos($db) {

    $stmt = $db->prepare("SELECT * FROM categorias_eventos");
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($categorias) {
        return ['status' => 200, 'message' => $categorias];
    } else {
        return ['status' => 200, 'message' => 'Nenhuma categoria encontrada.'];
    }
}

function obterInformacoesQtd($db) {

    $stmt = $db->prepare("SELECT (SELECT COUNT(*) FROM eventos) as eventos, (SELECT COUNT(*) FROM slides) as slides, (SELECT COUNT(*) FROM noticias) as noticias");
    $stmt->execute();
    $informacoes = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($informacoes) {
        return ['status' => 200, 'message' => $informacoes];
    } else {
        return ['status' => 200, 'message' => 'Nenhuma informação encontrada.'];
    }
}

//Usuarios
function obterUsuario($db, $userId) {

    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        return ['status' => 200, 'message' => $user];
    } else {
        return ['status' => 400, 'message' => 'Usuário não encontrado.'];
    }
}

function editarUsuario($db, $userId, $data) {

    // Verifica se o ID do usuário foi fornecido
    if (empty($userId)) {
        return ['status' => 400, 'message' => "ID do usuário é obrigatório."];
    }

    // Verifica se o usuário existe
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return ['status' => 400, 'message' => "Usuário não encontrado."];
    }

    // Verifica se os dados existem antes de editar
    $fieldsToCheck = ['cpf', 'email'];
    foreach ($fieldsToCheck as $field) {
        if (isset($data[$field]) && checkFieldUserExist($db, $field, $data[$field], $userId)) {
            return ['status' => 400, 'message' => ucfirst($field) . " '{$data[$field]}' já está em uso por outro usuário."];
        }
    }

    // Prepara a consulta de atualização com os campos fornecidos
    $sqlSetParts = [];
    $sqlParams = [':id' => $userId];

    // Lista de chaves a serem ignoradas
    $ignoreKeys = ['senha', 'confirmacaoSenha', 'foto'];

    // Atualiza somente os campos fornecidos, excluindo as chaves na lista de ignorados e campos vazios
    foreach ($data as $key => $value) {
        if (!in_array($key, $ignoreKeys) && isset($value) && !empty($value)) {
            $sqlSetParts[] = "{$key} = :{$key}";
            $sqlParams[":{$key}"] = $value;
        }
    }

    // Verifica e atualiza a senha, se fornecida e confirmada corretamente
    if (isset($data['senha'], $data['confirmacaoSenha']) && $data['senha'] == $data['confirmacaoSenha']) {
        $sqlSetParts[] = "senha = :senha";
        $sqlParams[':senha'] = password_hash($data['senha'], PASSWORD_DEFAULT);
    } elseif (isset($data['senha']) || isset($data['confirmacaoSenha'])) {
        return ['status' => 400, 'message' => "As senhas digitadas não conferem."];
    }

    if (empty($sqlSetParts)) {
        return ['status' => 400, 'message' => "Nenhum dado para atualizar."];
    }

    $sql = "UPDATE usuarios SET " . join(', ', $sqlSetParts) . " WHERE id = :id";
    $stmt = $db->prepare($sql);

    if ($stmt->execute($sqlParams)) {
        return ['status' => 200, 'message' => 'Usuário atualizado com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao atualizar usuário'];
    }
}

function excluirUsuario($db, $id) {

    $stmt = $db->prepare('DELETE FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        return ['status' => 200, 'message' => 'Usuário excluído com sucesso'];
    } else {
        return ['status' => 400, 'message' => 'Falha ao excluir usuário'];
    }
}

// Funções publicas
function obterMenu($db) {
    // Consulta para obter todos os títulos
    $stmtTitulos = $db->prepare("SELECT * FROM titulos ORDER BY ordem ASC");
    $stmtTitulos->execute();
    $titulos = $stmtTitulos->fetchAll(PDO::FETCH_ASSOC);

    // Inicializa os arrays de menu
    $menu_left = [];
    $menu_right = [];

    foreach ($titulos as $titulo) {
        // Ajustar url e target para null se estiverem vazios
        $titulo['url'] = $titulo['url'] ?: null;
        $titulo['target'] = $titulo['target'] ?: null;

        // Consulta para obter todos os subtítulos para o título atual
        $stmtSubtitulos = $db->prepare("SELECT * FROM subtitulos WHERE titulo_id = :titulo_id ORDER BY ordem ASC");
        $stmtSubtitulos->execute([':titulo_id' => $titulo['id']]);
        $subtitulos = $stmtSubtitulos->fetchAll(PDO::FETCH_ASSOC);

        // Inicializa o array de submenus
        $subsmenu = [];

        foreach ($subtitulos as $subtitulo) {
            // Consulta para obter todas as páginas para o subtítulo atual
            $stmtPaginas = $db->prepare("SELECT * FROM paginas WHERE subtitulo_id = :subtitulo_id ORDER BY ordem ASC");
            $stmtPaginas->execute([':subtitulo_id' => $subtitulo['id']]);
            $paginas = $stmtPaginas->fetchAll(PDO::FETCH_ASSOC);

            //Se target for true, então ele é "_blank"
            if ($subtitulo['target'] == 1) {
                $subtitulo['target'] = '_blank';
            }

            // Monta o array de páginas
            $paginasArray = [];
            foreach ($paginas as $pagina) {

                //Se target for true, então ele é "_blank"
                if ($pagina['target'] == 1) {
                    $pagina['target'] = '_blank';
                }

                $paginasArray[] = [
                    'id' => $pagina['id'],
                    'titulo' => $pagina['pagina'],
                    'url' => $pagina['url'],
                    'target' => $pagina['target']
                ];
            }

            // Adiciona o subtítulo e suas páginas ao array de submenus
            $subsmenu[] = [
                'id' => $subtitulo['id'],
                'titulo' => $subtitulo['subtitulo'],
                'paginas' => $paginasArray
            ];
        }

        // Adiciona o título e seus submenus ao menu esquerdo ou direito com base na posição
        $menuItem = [
            'id' => $titulo['id'],
            'titulo' => $titulo['titulo'],
            'url' => $titulo['url'],
            'target' => $titulo['target'],
            'subsmenu' => $subsmenu ? $subsmenu : null
        ];

        if ($titulo['posicao'] === 'esquerdo') {
            $menu_left[] = $menuItem;
        } else if ($titulo['posicao'] === 'direito') {
            $menu_right[] = $menuItem;
        }
    }

    return [
        'menu_left' => $menu_left,
        'menu_right' => $menu_right
    ];
}

function obterRedesSociais($db) {
    // Consulta para obter todas as redes sociais
    $stmt = $db->prepare("SELECT * FROM redes_sociais");
    $stmt->execute();
    $redes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($redes as $rede) {
        $result[] = [
            'id' => $rede['id'],
            'url' => $rede['url'],
            'alt' => $rede['rede'],
            'rede' => $rede['rede']
        ];
    }

    return $result;
}

function obterSlidesPublico($db) {
    // Consulta para obter todos os slides
    $stmt = $db->prepare("SELECT * FROM slides");
    $stmt->execute();
    $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($slides as $slide) {
        $result[] = [
            'id' => $slide['id'],
            'titulo' => $slide['titulo'],
            'image' => $slide['imagem'],
            'alt' => $slide['titulo'],
            'descricao' => $slide['descricao'],
            'url' => $slide['url'],
            'target' => $slide['target'],
        ];
    }

    return $result;
}

function obterEventosPublicos($db) {
    // Consulta para obter todos os eventos
    $stmt = $db->prepare("SELECT eventos.id as evento_id, eventos.titulo, categorias_eventos.nome, eventos.data_horario, eventos.imagem FROM eventos INNER JOIN categorias_eventos ON eventos.categoria_id = categorias_eventos.id ORDER BY data_horario DESC");
    $stmt->execute();
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($eventos as $evento) { 
        $result[] = [
            'id' => $evento['evento_id'],
            'titulo' => $evento['titulo'],
            'categoria' => $evento['nome'],
            // 'descricao' => $evento['descricao'],
            'data' => date('d \d\e F \d\e Y', strtotime($evento['data_horario'])), // Formata a data no formato "23 de abril de 2024"
            'backgroundImage' => $evento['imagem'],
            'imagem' => $evento['imagem']
        ];
    }

    return $result;
}

function obterPatrocinadoresPublicos($db) {
    // Consulta para obter todos os patrocinadores
    $stmt = $db->prepare("SELECT * FROM patrocinadores ORDER BY ordem");
    $stmt->execute();
    $patrocinadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($patrocinadores as $patrocinador) {
        $result[] = [
            'id' => $patrocinador['id'],
            'image' => $patrocinador['imagem']
        ];
    }

    return $result;
}

function obterNoticiasPublicos($db, $limite = 100) {
    // Consulta para obter todas as notícias
    $stmt = $db->prepare("SELECT noticias.id, noticias.titulo, noticias.imagem, noticias.descricao, noticias.data_horario, modalidades.nome AS categoria
                          FROM noticias
                          LEFT JOIN modalidades ON noticias.modalidade_id = modalidades.id
                          ORDER BY noticias.data_horario DESC
                          LIMIT $limite");
    $stmt->execute();
    $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($noticias as $noticia) {
        $result[] = [
            'id' => $noticia['id'],
            'titulo' => $noticia['titulo'],
            'imagem' => $noticia['imagem'],
            'alt' => $noticia['titulo'],
            'data' => date('d.m.Y - H\hi', strtotime($noticia['data_horario'])), // Formata a data no formato "10.05.2024 - 12h08"
            'categoria' => $noticia['categoria']
        ];
    }

    return $result;
}

function obterModalidadesPublicos($db) {
    // Consulta para obter todas as modalidades
    $stmt = $db->prepare("SELECT * FROM modalidades");
    $stmt->execute();
    $modalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($modalidades as $modalidade) {
        $result[] = [
            'id' => $modalidade['id'],
            'nome' => $modalidade['nome']
        ];
    }

    return $result;
}

function obterCategoriasEventoPublicos($db) {
    // Consulta para obter todas as modalidades
    $stmt = $db->prepare("SELECT * FROM categorias_eventos");
    $stmt->execute();
    $modalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($modalidades as $modalidade) {
        $result[] = [
            'id' => $modalidade['id'],
            'nome' => $modalidade['nome']
        ];
    }

    return $result;
}

function obterFooterPublicos($db) {
    // Consulta para obter todos os títulos da posição "footer"
    $stmtTitulos = $db->prepare("SELECT * FROM titulos WHERE posicao = 'footer' ORDER by ordem ASC");
    $stmtTitulos->execute();
    $titulos = $stmtTitulos->fetchAll(PDO::FETCH_ASSOC);

    $footer = [];

    foreach ($titulos as $titulo) {
        // Consulta para obter todos os subtítulos para o título atual
        $stmtSubtitulos = $db->prepare("SELECT * FROM subtitulos WHERE titulo_id = :titulo_id ORDER by ordem ASC");
        $stmtSubtitulos->execute([':titulo_id' => $titulo['id']]);
        $subtitulos = $stmtSubtitulos->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subtitulos as $subtitulo) {
            // Consulta para obter todas as páginas para o subtítulo atual
            $stmtPaginas = $db->prepare("SELECT * FROM paginas WHERE subtitulo_id = :subtitulo_id ORDER BY ordem ASC");
            $stmtPaginas->execute([':subtitulo_id' => $subtitulo['id']]);
            $paginas = $stmtPaginas->fetchAll(PDO::FETCH_ASSOC);

            // Inicializa o array de links
            $links = [];

            foreach ($paginas as $pagina) {
                $links[] = [
                    'id' => $pagina['id'],
                    'href' => $pagina['url'],
                    'text' => $pagina['pagina'],
                    'target' => $pagina['target']
                ];
            }

            // Adiciona o subtítulo e suas páginas ao footer
            $footer[] = [
                'title' => $subtitulo['subtitulo'],
                'links' => $links
            ];
        }
    }

    return $footer;
}

function obterNoticiaPublica($db, $id) {
    // Consulta para obter a notícia
    $stmt = $db->prepare("SELECT noticias.id, noticias.titulo, noticias.descricao, noticias.data_horario, noticias.usuario_id, modalidades.nome AS categoria
                          FROM noticias
                          LEFT JOIN modalidades ON noticias.modalidade_id = modalidades.id
                          WHERE noticias.id = :id");
    $stmt->execute([':id' => $id]);
    $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$noticia) {
        return ['status' => 400, 'message' => 'Notícia não encontrada.'];
    }

    // Consulta para obter o autor da notícia
    $stmtAutor = $db->prepare("SELECT nome_completo FROM usuarios WHERE id = :id");
    $stmtAutor->execute([':id' => $noticia['usuario_id']]);
    $autor = $stmtAutor->fetch(PDO::FETCH_ASSOC);

    $result = [
        'id' => $noticia['id'],
        'titulo' => $noticia['titulo'],
        'assunto' => $noticia['assunto'],
        'data' => date('d/m/Y H\hi', strtotime($noticia['data_horario'])), // Formata a data no formato "18/05/2024 09h00"
        'autor' => $autor['nome_completo'],
        'categoria' => $noticia['categoria'],
        'conteudoHTML' => $noticia['descricao']
    ];

    return $result;
}

function obterPaginaPublica($db, $id) {

    // Consulta para obter a página
    $stmt = $db->prepare("SELECT paginas.id, paginas.pagina, paginas.subtitulo,
    paginas.subtitulo_id, paginas.descricao
    FROM paginas
    WHERE paginas.id = :id");
    $stmt->execute([':id' => $id]);
    $pagina = $stmt->fetch(PDO::FETCH_ASSOC);

    $result = [
        'id' => $pagina['id'],
        'titulo_pagina' => $pagina['pagina'],
        'descricao' => $pagina['conteudoHTML'],
        'subtitulo' => $pagina['subtitulo'],
        'conteudoHTML' => $pagina['descricao']
    ];

    return $result;
}

function obterSlidePublico($db, $id) {

    // Consulta para obter a página
    $stmt = $db->prepare("SELECT slides.id, slides.titulo, slides.descricao
    FROM slides
    WHERE slides.id = :id");
    $stmt->execute([':id' => $id]);
    $pagina = $stmt->fetch(PDO::FETCH_ASSOC);

    $result = [
        'id' => $pagina['id'],
        'titulo_pagina' => $pagina['titulo'],
        'descricao' => $pagina['descricao'],
        'subtitulo' => $pagina['subtitulo'],
        'conteudoHTML' => $pagina['descricao']
    ];

    return $result;
}

function obterEventoPublica($db, $id) {

    // Consulta para obter a página
    $stmt = $db->prepare("SELECT eventos.id, eventos.titulo, eventos.descricao, eventos.data_horario
    FROM eventos
    WHERE eventos.id = :id");
    $stmt->execute([':id' => $id]);
    $pagina = $stmt->fetch(PDO::FETCH_ASSOC);

    $result = [
        'id' => $pagina['id'],
        'titulo_pagina' => $pagina['titulo'],
        'descricao' => $pagina['descricao'],
        'conteudoHTML' => $pagina['descricao'],
        'data' => date('d/m/Y H\hi', strtotime($pagina['data_horario'])), // Formata a data no formato "18/05/2024 09h00"
    ];

    return $result;
}

function buscarSite($db, $termoBusca, $accessToken) {
    $termoBusca = "%" . $termoBusca . "%";

    // Inicializa o array de resultados
    $resultados = [];

    // Consulta para buscar nos eventos
    $stmtEventos = $db->prepare("SELECT id, titulo, descricao, 'evento' AS tipo FROM eventos WHERE titulo LIKE :busca OR descricao LIKE :busca");
    $stmtEventos->execute([':busca' => $termoBusca]);
    $eventos = $stmtEventos->fetchAll(PDO::FETCH_ASSOC);
    $resultados = array_merge($resultados, $eventos);

    foreach ($resultados as $key => $value) {
        $resultados[$key]['descricao'] = substr(strip_tags($value['descricao']), 0, 200) . '...';
    }

    // Consulta para buscar nas notícias
    $stmtNoticias = $db->prepare("SELECT id, titulo, descricao,'noticia' AS tipo FROM noticias WHERE titulo LIKE :busca OR descricao LIKE :busca");
    $stmtNoticias->execute([':busca' => $termoBusca]);
    $noticias = $stmtNoticias->fetchAll(PDO::FETCH_ASSOC);
    $resultados = array_merge($resultados, $noticias);

    foreach ($resultados as $key => $value) {
        $resultados[$key]['descricao'] = substr(strip_tags($value['descricao']), 0, 200) . '...';
    }

    // Consulta para buscar nas páginas
    $stmtPaginas = $db->prepare("SELECT id, descricao, pagina AS titulo, 'pagina' AS tipo FROM paginas WHERE pagina LIKE :busca OR descricao LIKE :busca");
    $stmtPaginas->execute([':busca' => $termoBusca]);
    $paginas = $stmtPaginas->fetchAll(PDO::FETCH_ASSOC);
    $resultados = array_merge($resultados, $paginas);

    foreach ($resultados as $key => $value) {
        $resultados[$key]['descricao'] = substr(strip_tags($value['descricao']), 0, 200) . '...';
    }

    // Adiciona os resultados do Instagram
    $postagensInstagram = obterInstagram($accessToken);
    foreach ($postagensInstagram as $postagem) {
        if (stripos($postagem['descricao'], str_replace('%', '', $termoBusca)) !== false) {
            $resultados[] = [
                'id' => $postagem['id'],
                'titulo' => $postagem['titulo'],
                'descricao' => substr(strip_tags($postagem['descricao']), 0, 200) . '...',
                'tipo' => 'instagram'
            ];
        }
    }

    return $resultados;
}

function obterInstagram($accessToken) {
    // Endpoint da API para buscar as mídias
    $endpoint = "https://graph.instagram.com/me/media?fields=id,caption,media_url,timestamp,media_type&access_token={$accessToken}";

    // Faz a requisição para a API do Instagram
    $response = file_get_contents($endpoint);
    $data = json_decode($response, true);

    // Inicializa a lista de postagens
    $postagens = [];

    // Itera sobre cada mídia e extrai as informações necessárias
    foreach ($data['data'] as $post) {
        if ($post['media_type'] === 'IMAGE' && strlen($post['caption']) > 1) {
            $postagem = [
                'id' => $post['id'],
                'titulo' => isset($post['caption']) ? substr($post['caption'], 0, 50) . '...' : '',
                'imagem' => isset($post['media_url']) ? $post['media_url'] : '',
                'alt' => isset($post['caption']) ? substr($post['caption'], 0, 20) . '...' : '',
                'data' => date('d.m.Y - H\hi', strtotime($post['timestamp'])),
                'categoria' => "Geral",
                'descricao' => isset($post['caption']) ? $post['caption'] : '',
                'tipo_noticia' => 'instagram'
            ];
            $postagens[] = $postagem;
        }
    }

    return $postagens;
}

function obterInstagramID($accessToken, $id) {

    // Endpoint da API para buscar as mídias
    $endpoint = "https://graph.instagram.com/{$id}?fields=id,caption,media_url,timestamp,media_type&access_token=id,username&access_token={$accessToken}";

    // Faz a requisição para a API do Instagram
    $response = file_get_contents($endpoint);

    $data = json_decode($response, true);


    if ($data['media_type'] === 'IMAGE' && strlen($data['caption']) > 1) {
        $newData = [
            'id' => $data['id'],
            'assunto' => null,
            'titulo' => isset($data['caption']) ? substr($data['caption'], 0, 50) . '...' : '',
            'imagem' => isset($data['media_url']) ? $data['media_url'] : '',
            'alt' => isset($data['caption']) ? substr($data['caption'], 0, 20) . '...' : '',
            'data' => date('d/m/Y H\hi', strtotime($data['timestamp'])),
            'autor' => "Clube Araraquarense",
            'categoria' => "Geral",
            'conteudoHTML' => isset($data['caption']) ? $data['caption'] : '',
            'tipo_noticia' => 'instagram'
        ];
    }
    return $newData;
}

function trocarSenha($db, $userID, $data) {

    $senhaAtual = $data['senhaAtual'];
    $novaSenha = $data['novaSenha'];

    // Verificar se o ID do usuário foi fornecido
    if (empty($userID)) {
        return ['status' => 400, 'message' => "ID do usuário é obrigatório."];
    }

    // Buscar o usuário no banco de dados
    $stmt = $db->prepare('SELECT * FROM usuarios WHERE id = ?');
    $stmt->execute([$userID]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar se o usuário existe e a senha atual está correta
    if ($user && password_verify($senhaAtual, $user['senha'])) {
        // Atualizar a senha do usuário
        $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
        if ($stmt->execute([$senhaHash, $userID])) {
            return ['status' => 200, 'message' => 'Senha atualizada com sucesso'];
        } else {
            return ['status' => 400, 'message' => 'Falha ao atualizar a senha'];
        }
    } else {
        return ['status' => 400, 'message' => 'Senha atual incorreta ou usuário não encontrado'];
    }
}
