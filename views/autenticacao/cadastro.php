<?php
/**
 * @var string $csrf
 * @var array<int, array{tipo: string, texto: string}> $avisos
 * @var array<string, string> $antigos
 * @var array<string, string> $erros
 */
?>
<div class="caixa">
    <div>
        <h2>Criar sua conta</h2>
    </div>
    <p>Leva menos de um minuto. Você já começa com uma conta principal.</p>

    <?php foreach ($avisos as $aviso): ?>
        <div class="alerta-pagina<?= $aviso['tipo'] === 'erro' ? ' erro' : '' ?>" role="alert"><?= e($aviso['texto']) ?></div>
    <?php endforeach; ?>

    <form class="formulario" method="post" action="<?= e(url('/cadastro')) ?>" id="formulario-cadastro" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="campo<?= isset($erros['nome']) ? ' com-erro' : '' ?>" data-campo="nome">
            <label for="cad-nome">Nome</label>
            <input class="entrada" id="cad-nome" name="nome" value="<?= e($antigos['nome'] ?? '') ?>" required maxlength="120" autocomplete="name">
            <span class="erro-campo" data-erro="nome"><?= e($erros['nome'] ?? '') ?></span>
        </div>
        <div class="campo<?= isset($erros['email']) ? ' com-erro' : '' ?>" data-campo="email">
            <label for="cad-email">E-mail</label>
            <input class="entrada" id="cad-email" name="email" type="email" value="<?= e($antigos['email'] ?? '') ?>" required maxlength="160" autocomplete="email">
            <span class="erro-campo" data-erro="email"><?= e($erros['email'] ?? '') ?></span>
        </div>
        <div class="linha-campos">
            <div class="campo<?= isset($erros['senha']) ? ' com-erro' : '' ?>" data-campo="senha">
                <label for="cad-senha">Senha</label>
                <input class="entrada" id="cad-senha" name="senha" type="password" required minlength="8" autocomplete="new-password">
                <span class="erro-campo" data-erro="senha"><?= e($erros['senha'] ?? '') ?></span>
            </div>
            <div class="campo<?= isset($erros['confirmacao']) ? ' com-erro' : '' ?>" data-campo="confirmacao">
                <label for="cad-confirmacao">Repita a senha</label>
                <input class="entrada" id="cad-confirmacao" name="confirmacao" type="password" required autocomplete="new-password">
                <span class="erro-campo" data-erro="confirmacao"><?= e($erros['confirmacao'] ?? '') ?></span>
            </div>
        </div>
        <div class="progresso" id="forca-senha"><span></span></div>
        <small class="texto-2" id="forca-senha-texto">Use pelo menos 8 caracteres, com letras e números.</small>
        <button class="botao botao-primario botao-largo" type="submit">Criar conta</button>
    </form>

    <p class="texto-2">Já tem conta? <a href="<?= e(url('/login')) ?>">Entrar</a></p>
</div>
