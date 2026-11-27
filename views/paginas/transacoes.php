<section class="pagina-cabecalho">
    <div>
        <h2>Cada movimento, no seu controle.</h2>
        <p>Consulte suas movimentações e mantenha tudo organizado.</p>
    </div>
    <div class="pagina-acoes">
        <button class="botao botao-primario" type="button" data-nova-transacao>
            <svg class="icone" aria-hidden="true"><use href="#i-mais"/></svg>Nova transação
        </button>
    </div>
</section>

<section class="grade grade-3" aria-label="Totais do período">
    <article class="cartao indicador">
        <div class="indicador-topo"><span id="rotulo-receitas">Receitas do mês</span></div>
        <strong class="indicador-valor esqueleto" id="total-receitas">R$ 0.000,00</strong>
        <div class="indicador-rodape"><span id="qtd-receitas">&nbsp;</span></div>
    </article>
    <article class="cartao indicador">
        <div class="indicador-topo"><span id="rotulo-despesas">Despesas do mês</span></div>
        <strong class="indicador-valor esqueleto" id="total-despesas">R$ 0.000,00</strong>
        <div class="indicador-rodape"><span id="qtd-despesas">&nbsp;</span></div>
    </article>
    <article class="cartao indicador indicador-destaque">
        <div class="indicador-topo"><span id="rotulo-balanco">Balanço do mês</span></div>
        <strong class="indicador-valor esqueleto" id="total-balanco">R$ 0.000,00</strong>
        <div class="indicador-rodape"><span class="destaque-lima" id="frase-balanco">&nbsp;</span></div>
    </article>
</section>

<section class="cartao">
    <div class="cartao-cabecalho">
        <h3>Histórico de transações</h3>
        <button class="botao botao-secundario botao-pequeno" type="button" id="exportar-csv">
            Exportar CSV <svg class="icone icone-sm" aria-hidden="true"><use href="#i-baixar"/></svg>
        </button>
    </div>

    <div class="filtros">
        <label class="entrada-com-icone">
            <span class="visualmente-oculto">Buscar</span>
            <svg class="icone" aria-hidden="true"><use href="#i-busca"/></svg>
            <input class="entrada" type="search" id="filtro-busca" placeholder="Buscar por descrição ou valor" autocomplete="off">
        </label>
        <label>
            <span class="visualmente-oculto">Categoria</span>
            <select class="selecao" id="filtro-categoria"><option value="">Todas as categorias</option></select>
        </label>
        <label>
            <span class="visualmente-oculto">Conta</span>
            <select class="selecao" id="filtro-conta"><option value="">Todas as contas</option></select>
        </label>
        <div class="ancora">
            <button class="botao botao-claro botao-periodo botao-largo" id="filtro-periodo" type="button" aria-haspopup="dialog" aria-expanded="false">
                <svg class="icone" aria-hidden="true"><use href="#i-calendario"/></svg><span>Este mês</span>
            </button>
        </div>
    </div>

    <div class="filtros-rodape">
        <div class="abas abas-compactas" role="tablist" aria-label="Tipo" id="abas-tipo">
            <button type="button" role="tab" data-tipo="" aria-selected="true">Todas</button>
            <button type="button" role="tab" data-tipo="receita" aria-selected="false">Receitas</button>
            <button type="button" role="tab" data-tipo="despesa" aria-selected="false">Despesas</button>
            <button type="button" role="tab" data-tipo="transferencia" aria-selected="false">Transferências</button>
            <button type="button" role="tab" data-tipo="assinatura" aria-selected="false">Assinaturas</button>
        </div>
        <span id="contador-transacoes" aria-live="polite">&nbsp;</span>
    </div>

    <div id="painel-tabela">
        <div class="tabela-area">
            <table class="tabela" id="tabela-transacoes">
                <thead>
                    <tr>
                        <th class="ordenavel" data-ordenar="descricao" aria-sort="none">Descrição</th>
                        <th class="ordenavel" data-ordenar="categoria" aria-sort="none">Categoria</th>
                        <th class="ordenavel" data-ordenar="conta" aria-sort="none">Conta</th>
                        <th class="ordenavel" data-ordenar="data" aria-sort="descending">Data</th>
                        <th class="ordenavel" data-ordenar="status" aria-sort="none">Status</th>
                        <th class="ordenavel direita" data-ordenar="valor" aria-sort="none">Valor</th>
                        <th><span class="visualmente-oculto">Ações</span></th>
                    </tr>
                </thead>
                <tbody id="linhas-transacoes"></tbody>
            </table>
        </div>
        <nav class="paginacao" aria-label="Páginas">
            <span id="texto-paginacao">&nbsp;</span>
            <div class="paginacao-botoes" id="botoes-paginacao"></div>
        </nav>
    </div>

    <div id="painel-assinaturas" hidden>
        <p class="texto-2" id="resumo-assinaturas">&nbsp;</p>
        <ul class="lista-simples" id="lista-assinaturas"></ul>
    </div>
</section>

<div class="fundo-modal" id="modal-assinatura" hidden>
    <form class="modal modal-pequeno" id="formulario-assinatura" role="dialog" aria-modal="true" aria-labelledby="ma-titulo" novalidate>
        <div class="modal-cabecalho">
            <div>
                <h2 id="ma-titulo">Editar assinatura</h2>
                <p>As mudanças valem para as próximas cobranças.</p>
            </div>
            <button class="botao-icone" type="button" data-fechar-modal aria-label="Fechar"><svg class="icone" aria-hidden="true"><use href="#i-fechar"/></svg></button>
        </div>
        <div class="formulario">
            <div class="campo" data-campo="descricao">
                <label for="ma-descricao">Descrição</label>
                <input class="entrada" id="ma-descricao" name="descricao" maxlength="120">
                <span class="erro-campo" data-erro="descricao"></span>
            </div>
            <div class="linha-campos">
                <div class="campo" data-campo="valor">
                    <label for="ma-valor">Valor (R$)</label>
                    <input class="entrada" id="ma-valor" name="valor" inputmode="decimal">
                    <span class="erro-campo" data-erro="valor"></span>
                </div>
                <div class="campo" data-campo="dia_cobranca">
                    <label for="ma-dia">Dia da cobrança</label>
                    <select class="selecao" id="ma-dia" name="dia_cobranca"></select>
                    <span class="erro-campo" data-erro="dia_cobranca"></span>
                </div>
            </div>
            <div class="caixa-opcao">
                <label class="chave"><input type="checkbox" id="ma-ativa" name="ativa"><span></span></label>
                <div><strong>Assinatura ativa</strong><span>Desligada, o Cofrelo para de lançar as próximas cobranças.</span></div>
            </div>
        </div>
        <div class="modal-rodape">
            <button class="botao botao-claro" type="button" data-fechar-modal>Cancelar</button>
            <button class="botao botao-primario" type="submit">Salvar</button>
        </div>
    </form>
</div>
