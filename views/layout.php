<?php
/**
 * Estrutura comum das páginas internas: menu lateral, cabeçalho e área de conteúdo.
 *
 * @var string $conteudo HTML da página
 * @var string $titulo
 * @var string $pagina chave da página ativa no menu
 * @var string|null $script módulo JavaScript da página
 * @var array<string, mixed> $usuario
 * @var string $csrf
 * @var array<int, array{tipo: string, texto: string}> $avisos
 */
$menuPrincipal = [
    ['visao-geral', 'Visão geral', 'visao', '/visao-geral'],
    ['transacoes', 'Transações', 'transacoes', '/transacoes'],
    ['contas', 'Minhas contas', 'carteira', '/contas'],
    ['relatorios', 'Relatórios', 'relatorios', '/relatorios'],
    ['metas', 'Metas e objetivos', 'meta', '/metas'],
];
$menuPreferencias = [
    ['configuracoes', 'Configurações', 'config', '/configuracoes'],
    ['ajuda', 'Central de ajuda', 'ajuda', '/ajuda'],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <meta name="base-url" content="<?= e(url('/')) ?>">
    <title><?= e($titulo) ?> · Cofrelo</title>
    <link rel="icon" href="<?= e(recurso('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(recurso('assets/css/cofrelo.css')) ?>">
    <script type="module" src="<?= e(recurso('assets/js/app.js')) ?>"></script>
    <?php if (!empty($script)): ?>
    <script type="module" src="<?= e(recurso('assets/js/paginas/' . $script . '.js')) ?>"></script>
    <?php endif; ?>
</head>
<body>
<?php require __DIR__ . '/parciais/icones.php'; ?>
<div class="app" id="app">
    <aside class="menu-lateral" id="menu-lateral">
        <a class="marca" href="<?= e(url('/visao-geral')) ?>">
            <span class="marca-nome">cofrelo</span>
            <span class="marca-slogan">Finanças em equilíbrio</span>
        </a>

        <nav class="menu-grupo" aria-label="Principal">
            <span class="menu-titulo">Principal</span>
            <?php foreach ($menuPrincipal as [$chave, $rotulo, $icone, $caminho]): ?>
                <a class="menu-item<?= $pagina === $chave ? ' ativo' : '' ?>" href="<?= e(url($caminho)) ?>"<?= $pagina === $chave ? ' aria-current="page"' : '' ?>>
                    <svg class="icone" aria-hidden="true"><use href="#i-<?= e($icone) ?>"/></svg><?= e($rotulo) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <nav class="menu-grupo" aria-label="Preferências">
            <span class="menu-titulo">Preferências</span>
            <?php foreach ($menuPreferencias as [$chave, $rotulo, $icone, $caminho]): ?>
                <a class="menu-item<?= $pagina === $chave ? ' ativo' : '' ?>" href="<?= e(url($caminho)) ?>"<?= $pagina === $chave ? ' aria-current="page"' : '' ?>>
                    <svg class="icone" aria-hidden="true"><use href="#i-<?= e($icone) ?>"/></svg><?= e($rotulo) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="menu-rodape">
            <strong>Cofrelo pessoal</strong>
            <span class="texto-2">Seu espaço financeiro</span>
        </div>
    </aside>

    <div class="area">
        <header class="topo">
            <div class="topo-acoes">
                <button class="botao-icone botao-menu" id="botao-menu" type="button" aria-label="Abrir o menu" aria-controls="menu-lateral" aria-expanded="false">
                    <svg class="icone" aria-hidden="true"><use href="#i-menu"/></svg>
                </button>
                <div class="topo-titulo">
                    <span>Meu espaço</span>
                    <h1><?= e($titulo) ?></h1>
                </div>
            </div>

            <div class="topo-acoes">
                <div class="ancora">
                    <button class="botao-icone" id="botao-notificacoes" type="button" aria-label="Notificações" aria-haspopup="true" aria-expanded="false" aria-controls="painel-notificacoes">
                        <svg class="icone" aria-hidden="true"><use href="#i-sino"/></svg>
                        <span class="selo" id="selo-notificacoes" hidden>0</span>
                    </button>
                    <div class="suspenso painel-notificacoes" id="painel-notificacoes" hidden>
                        <header>
                            <strong>Notificações</strong>
                            <button class="botao botao-texto" id="marcar-lidas" type="button">Marcar todas como lidas</button>
                        </header>
                        <ul id="lista-notificacoes"></ul>
                    </div>
                </div>

                <span class="separador-vertical" aria-hidden="true"></span>

                <div class="ancora">
                    <button class="usuario-botao" id="botao-usuario" type="button" aria-label="Olá, <?= e($usuario['primeiro_nome']) ?>: menu da conta" aria-haspopup="true" aria-expanded="false" aria-controls="menu-usuario">
                        <div>
                            <strong>Olá, <?= e($usuario['primeiro_nome']) ?></strong>
                            <span>Conta pessoal</span>
                        </div>
                        <svg class="icone" aria-hidden="true"><use href="#i-chevron"/></svg>
                    </button>
                    <div class="suspenso menu-usuario" id="menu-usuario" hidden>
                        <a href="<?= e(url('/configuracoes')) ?>"><svg class="icone" aria-hidden="true"><use href="#i-config"/></svg>Configurações</a>
                        <a href="<?= e(url('/ajuda')) ?>"><svg class="icone" aria-hidden="true"><use href="#i-ajuda"/></svg>Central de ajuda</a>
                        <form method="post" action="<?= e(url('/sair')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                            <button class="perigo" type="submit"><svg class="icone" aria-hidden="true"><use href="#i-sair"/></svg>Sair</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="conteudo" id="conteudo">
            <?php foreach ($avisos as $aviso): ?>
                <div class="alerta-pagina<?= $aviso['tipo'] === 'erro' ? ' erro' : '' ?>" role="status">
                    <svg class="icone" aria-hidden="true"><use href="#i-<?= $aviso['tipo'] === 'erro' ? 'alerta' : 'check' ?>"/></svg>
                    <?= e($aviso['texto']) ?>
                </div>
            <?php endforeach; ?>
            <?= $conteudo ?>
        </main>
    </div>
</div>

<?php require __DIR__ . '/parciais/modal-transacao.php'; ?>
<?php require __DIR__ . '/parciais/modal-deposito.php'; ?>
<?php require __DIR__ . '/parciais/modal-confirmar.php'; ?>
<div class="avisos" id="avisos" aria-live="polite"></div>
</body>
</html>
