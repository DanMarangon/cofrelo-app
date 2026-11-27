<?php
/**
 * @var int $status
 * @var string $mensagem
 */
$titulos = [403 => 'Acesso negado', 404 => 'Página não encontrada', 405 => 'Ação não permitida', 503 => 'Serviço indisponível'];
$titulo = $titulos[$status] ?? 'Algo deu errado';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Cofrelo</title>
    <link rel="icon" href="<?= e(recurso('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(recurso('assets/css/cofrelo.css')) ?>">
</head>
<body>
<main class="pagina-erro">
    <div>
        <div class="codigo"><?= e($status) ?></div>
        <h1><?= e($titulo) ?></h1>
        <p><?= e($mensagem) ?></p>
        <a class="botao botao-primario" href="<?= e(url('/visao-geral')) ?>">Voltar para a Visão geral</a>
    </div>
</main>
</body>
</html>
