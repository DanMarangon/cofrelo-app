<?php
/**
 * Configurações: formulários tradicionais enviados ao PHP (POST), sem JavaScript obrigatório.
 *
 * @var array<string, mixed> $usuario
 * @var array<string, string> $antigos
 * @var string $csrf
 */
$nome = $antigos['nome'] ?? $usuario['nome'];
$email = $antigos['email'] ?? $usuario['email'];
$orcamento = $antigos['orcamento_mensal'] ?? number_format((float) $usuario['orcamento_mensal'], 2, ',', '.');
?>
<section class="pagina-cabecalho">
    <div>
        <h2>Do seu jeito.</h2>
        <p>Atualize seus dados, o orçamento do mês e a sua senha.</p>
    </div>
</section>

<section class="cartao">
    <div class="secao-config">
        <div>
            <h3>Perfil e orçamento</h3>
            <p>O orçamento mensal aparece na Visão geral e gera um aviso quando você passa de 80% dele.</p>
        </div>
        <form class="formulario" method="post" action="<?= e(url('/configuracoes/perfil')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="campo">
                <label for="cfg-nome">Nome</label>
                <input class="entrada" id="cfg-nome" name="nome" value="<?= e($nome) ?>" maxlength="120" required autocomplete="name">
            </div>
            <div class="campo">
                <label for="cfg-email">E-mail</label>
                <input class="entrada" id="cfg-email" name="email" type="email" value="<?= e($email) ?>" maxlength="160" required autocomplete="email">
            </div>
            <div class="campo">
                <label for="cfg-orcamento">Orçamento mensal (R$)</label>
                <input class="entrada" id="cfg-orcamento" name="orcamento_mensal" value="<?= e($orcamento) ?>" inputmode="decimal">
                <small id="cfg-orcamento-dica">Sugestão: até 70% da sua renda mensal.</small>
            </div>
            <div><button class="botao botao-primario" type="submit">Salvar alterações</button></div>
        </form>
    </div>

    <div class="secao-config">
        <div>
            <h3>Senha</h3>
            <p>Use pelo menos 8 caracteres, misturando letras e números.</p>
        </div>
        <form class="formulario" method="post" action="<?= e(url('/configuracoes/senha')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="campo">
                <label for="cfg-senha-atual">Senha atual</label>
                <input class="entrada" id="cfg-senha-atual" name="senha_atual" type="password" required autocomplete="current-password">
            </div>
            <div class="linha-campos">
                <div class="campo">
                    <label for="cfg-senha">Nova senha</label>
                    <input class="entrada" id="cfg-senha" name="senha" type="password" required minlength="8" autocomplete="new-password">
                </div>
                <div class="campo">
                    <label for="cfg-confirmacao">Repita a nova senha</label>
                    <input class="entrada" id="cfg-confirmacao" name="confirmacao" type="password" required autocomplete="new-password">
                </div>
            </div>
            <div class="progresso" id="cfg-forca"><span></span></div>
            <small class="texto-2" id="cfg-forca-texto">Digite a nova senha para ver a força dela.</small>
            <div><button class="botao botao-primario" type="submit">Trocar senha</button></div>
        </form>
    </div>

    <div class="secao-config">
        <div>
            <h3>Excluir conta</h3>
            <p>Apaga você, suas contas, transações, assinaturas e metas. Não dá para desfazer.</p>
        </div>
        <form class="formulario" method="post" action="<?= e(url('/configuracoes/excluir')) ?>" id="form-excluir">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="campo">
                <label for="cfg-confirmar-exclusao">Para confirmar, digite EXCLUIR</label>
                <input class="entrada" id="cfg-confirmar-exclusao" name="confirmacao" autocomplete="off">
            </div>
            <div class="campo">
                <label for="cfg-senha-exclusao">Sua senha</label>
                <input class="entrada" id="cfg-senha-exclusao" name="senha" type="password" autocomplete="current-password">
            </div>
            <div><button class="botao botao-perigo" type="submit" id="cfg-botao-excluir" disabled>Excluir minha conta</button></div>
        </form>
    </div>
</section>
