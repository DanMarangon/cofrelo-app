<?php
/**
 * Estrutura das telas de acesso (entrar e criar conta): painel da marca à esquerda e formulário à direita.
 *
 * @var string $titulo
 * @var string $conteudo
 */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Cofrelo</title>
    <link rel="icon" href="<?= e(recurso('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(recurso('assets/css/cofrelo.css')) ?>">
    <script type="module" src="<?= e(recurso('assets/js/paginas/acesso.js')) ?>"></script>
</head>
<body>
<?php require dirname(__DIR__) . '/parciais/icones.php'; ?>
<div class="tela-acesso">
    <aside class="acesso-marca">
        <a class="marca" href="<?= e(url('/')) ?>">
            <span class="marca-nome">cofrelo</span>
            <span class="marca-slogan">Finanças em equilíbrio</span>
        </a>
        <div>
            <h1>Seu dinheiro, com mais clareza.</h1>
            <p>Contas, transações, metas e relatórios em um só lugar, para você decidir com calma para onde vai cada real.</p>
        </div>
        <div class="acesso-numeros">
            <div><strong>Contas</strong><span>Todo o seu dinheiro somado num lugar só</span></div>
            <div><strong>Metas</strong><span>Simulador que diz quanto guardar por mês</span></div>
            <div><strong>Avisos</strong><span>Lembretes antes de cada cobrança</span></div>
        </div>
    </aside>
    <main class="acesso-formulario">
        <?= $conteudo ?>
    </main>
</div>
</body>
</html>
