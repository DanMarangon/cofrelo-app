<section class="pagina-cabecalho">
    <div>
        <h2>Todas as suas contas, juntas.</h2>
        <p>Uma visão completa de onde está o seu dinheiro.</p>
    </div>
    <div class="pagina-acoes">
        <button class="botao botao-primario" type="button" id="adicionar-conta">
            <svg class="icone" aria-hidden="true"><use href="#i-mais"/></svg>Adicionar conta
        </button>
    </div>
</section>

<section class="cartao patrimonio" aria-label="Patrimônio">
    <div>
        <span class="sobretitulo">Patrimônio disponível</span>
        <strong id="patrimonio-total" class="esqueleto">R$ 00.000,00</strong>
        <span id="patrimonio-contas">&nbsp;</span>
    </div>
    <span class="destaque-lima" id="patrimonio-variacao"></span>
</section>

<section aria-labelledby="titulo-contas">
    <div class="cartao-cabecalho">
        <h3 id="titulo-contas">Minhas contas</h3>
        <span class="texto-2" id="contas-ativas">&nbsp;</span>
    </div>
    <div class="grade grade-3" id="lista-contas"></div>
</section>

<section class="cartao">
    <div class="cartao-cabecalho">
        <div>
            <h3>Movimentações entre contas</h3>
            <p>Organize seu dinheiro sem alterar o saldo total.</p>
        </div>
        <button class="botao botao-secundario botao-pequeno" type="button" id="nova-transferencia">
            <svg class="icone icone-sm" aria-hidden="true"><use href="#i-transacoes"/></svg>Nova transferência
        </button>
    </div>
    <ul class="lista-simples" id="lista-transferencias"></ul>
</section>

<div class="fundo-modal" id="modal-conta" hidden>
    <form class="modal modal-pequeno" id="formulario-conta" role="dialog" aria-modal="true" aria-labelledby="mco-titulo" novalidate>
        <div class="modal-cabecalho">
            <div>
                <h2 id="mco-titulo">Adicionar conta</h2>
                <p>Conta corrente, carteira digital, reserva ou dinheiro em espécie.</p>
            </div>
            <button class="botao-icone" type="button" data-fechar-modal aria-label="Fechar"><svg class="icone" aria-hidden="true"><use href="#i-fechar"/></svg></button>
        </div>
        <div class="formulario">
            <div class="campo" data-campo="nome">
                <label for="mco-nome">Nome da conta</label>
                <input class="entrada" id="mco-nome" name="nome" maxlength="80" placeholder="Ex.: Conta do banco, Carteira">
                <span class="erro-campo" data-erro="nome"></span>
            </div>
            <div class="linha-campos">
                <div class="campo" data-campo="tipo">
                    <label for="mco-tipo">Tipo</label>
                    <select class="selecao" id="mco-tipo" name="tipo">
                        <option value="corrente">Conta corrente</option>
                        <option value="pagamento">Conta de pagamento</option>
                        <option value="investimento">Conta de investimento</option>
                        <option value="dinheiro">Dinheiro em espécie</option>
                    </select>
                </div>
                <div class="campo" data-campo="saldo_inicial">
                    <label for="mco-saldo">Saldo inicial (R$)</label>
                    <input class="entrada" id="mco-saldo" name="saldo_inicial" inputmode="decimal" placeholder="0,00">
                    <span class="erro-campo" data-erro="saldo_inicial"></span>
                </div>
            </div>
            <div class="campo" data-campo="descricao">
                <label for="mco-descricao">Descrição (opcional)</label>
                <input class="entrada" id="mco-descricao" name="descricao" maxlength="160">
            </div>
        </div>
        <div class="modal-rodape">
            <button class="botao botao-claro" type="button" data-fechar-modal>Cancelar</button>
            <button class="botao botao-primario" type="submit">Salvar conta</button>
        </div>
    </form>
</div>
