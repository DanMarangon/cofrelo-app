<?php
/**
 * @var string $csrf
 * @var array<int, array{tipo: string, texto: string}> $avisos
 * @var array<string, string> $antigos
 * @var bool $mostrarDemo
 */
?>
<div class="caixa">
    <div>
        <h2>Entrar no Cofrelo</h2>
    </div>
    <p>Que bom ter você de volta.</p>

    <?php foreach ($avisos as $aviso): ?>
        <div class="alerta-pagina<?= $aviso['tipo'] === 'erro' ? ' erro' : '' ?>" role="alert"><?= e($aviso['texto']) ?></div>
    <?php endforeach; ?>

    <form class="formulario" method="post" action="<?= e(url('/login')) ?>" id="formulario-acesso" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="campo" data-campo="email">
            <label for="acesso-email">E-mail</label>
            <div class="entrada-com-icone">
                <svg class="icone" aria-hidden="true"><use href="#i-email"/></svg>
                <input class="entrada" id="acesso-email" name="email" type="email" value="<?= e($antigos['email'] ?? '') ?>" required autocomplete="email" autofocus>
            </div>
            <span class="erro-campo" data-erro="email"></span>
        </div>
        <div class="campo" data-campo="senha">
            <label for="acesso-senha">Senha</label>
            <div class="entrada-com-icone">
                <svg class="icone" aria-hidden="true"><use href="#i-cadeado"/></svg>
                <input class="entrada" id="acesso-senha" name="senha" type="password" required autocomplete="current-password">
            </div>
            <label class="texto-2"><input type="checkbox" id="mostrar-senha"> Mostrar senha</label>
            <span class="erro-campo" data-erro="senha"></span>
        </div>
        <button class="botao botao-primario botao-largo" type="submit">Entrar</button>
    </form>

    <p class="texto-2">Ainda não tem conta? <a href="<?= e(url('/cadastro')) ?>">Criar conta grátis</a></p>

    <?php if ($mostrarDemo): ?>
        <div class="demo">
            Para conhecer o sistema, use a conta de demonstração:<br>
            <code>mariana@cofrelo.app</code> e a senha <code>cofrelo123</code>
            <button class="botao botao-texto" type="button" id="usar-demo">Preencher para mim</button>
        </div>
    <?php endif; ?>
</div>
