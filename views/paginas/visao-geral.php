<section class="pagina-cabecalho">
    <div>
        <h2>Seu dinheiro, com mais clareza.</h2>
        <p>Acompanhe suas finanças e planeje seus próximos passos.</p>
    </div>
    <div class="pagina-acoes">
        <div class="ancora">
            <button class="botao botao-claro botao-periodo" id="seletor-mes" type="button" aria-haspopup="dialog" aria-expanded="false">
                <svg class="icone" aria-hidden="true"><use href="#i-calendario"/></svg><span>Mês atual</span>
            </button>
        </div>
        <button class="botao botao-primario" type="button" data-nova-transacao>
            <svg class="icone" aria-hidden="true"><use href="#i-mais"/></svg>Nova transação
        </button>
    </div>
</section>

<section class="grade grade-3" aria-label="Resumo do mês">
    <article class="cartao indicador indicador-destaque">
        <div class="indicador-topo">
            <span>Saldo total</span>
            <span class="icone-caixa"><svg class="icone" aria-hidden="true"><use href="#i-carteira"/></svg></span>
        </div>
        <strong class="indicador-valor esqueleto" id="saldo-total">R$ 00.000,00</strong>
        <div class="indicador-rodape">
            <span class="variacao" id="saldo-variacao"></span>
            <span>Seu patrimônio disponível</span>
        </div>
    </article>
    <article class="cartao indicador">
        <div class="indicador-topo">
            <span>Receitas do mês</span>
            <svg class="icone positivo" aria-hidden="true"><use href="#i-receita"/></svg>
        </div>
        <strong class="indicador-valor esqueleto" id="receitas">R$ 0.000,00</strong>
        <div class="indicador-rodape">
            <span class="variacao" id="receitas-variacao"></span>
            <span>em relação ao mês anterior</span>
        </div>
    </article>
    <article class="cartao indicador">
        <div class="indicador-topo">
            <span>Despesas do mês</span>
            <svg class="icone" aria-hidden="true"><use href="#i-despesa"/></svg>
        </div>
        <strong class="indicador-valor esqueleto" id="despesas">R$ 0.000,00</strong>
        <div class="indicador-rodape">
            <span class="variacao" id="despesas-variacao"></span>
            <span>em relação ao mês anterior</span>
        </div>
    </article>
</section>

<section class="grade grade-principal">
    <article class="cartao">
        <div class="cartao-cabecalho">
            <div>
                <h3>Fluxo de caixa</h3>
                <p>O equilíbrio entre o que entra e o que sai.</p>
            </div>
            <div class="legenda">
                <span><i class="ponto" data-cor="receita"></i>Receitas</span>
                <span><i class="ponto" data-cor="despesa"></i>Despesas</span>
            </div>
        </div>
        <div class="grafico" id="grafico-fluxo" role="img" aria-label="Gráfico de receitas e despesas dos últimos seis meses"></div>
    </article>

    <article class="cartao">
        <div class="cartao-cabecalho">
            <div>
                <h3>Onde você mais gastou</h3>
                <p>Distribuição das despesas neste mês</p>
            </div>
        </div>
        <div class="rosca-area">
            <div class="rosca" id="rosca-categorias"></div>
            <ul class="rosca-legenda" id="legenda-categorias"></ul>
        </div>
        <div class="orcamento">
            <p id="texto-orcamento">&nbsp;</p>
            <div class="progresso" id="barra-orcamento"><span></span></div>
        </div>
    </article>
</section>

<section class="grade grade-principal">
    <article class="cartao">
        <div class="cartao-cabecalho">
            <h3>Últimas transações</h3>
            <a class="botao botao-texto" href="<?= e(url('/transacoes')) ?>">Ver todas <svg class="icone icone-sm" aria-hidden="true"><use href="#i-direita"/></svg></a>
        </div>
        <div class="tabela-area">
            <table class="tabela">
                <thead>
                    <tr><th>Descrição</th><th>Categoria</th><th>Data</th><th class="direita">Valor</th></tr>
                </thead>
                <tbody id="ultimas-transacoes"></tbody>
            </table>
        </div>
    </article>

    <article class="cartao cartao-meta" id="cartao-meta">
        <span class="sobretitulo">Um passo de cada vez</span>
        <h3>Seu próximo sonho está mais perto.</h3>
        <div class="meta-linha">
            <span id="meta-nome">Carregando meta…</span>
            <span class="positivo" id="meta-percentual"></span>
        </div>
        <div class="progresso grosso" id="meta-barra"><span></span></div>
        <div class="meta-linha">
            <strong id="meta-atual"></strong>
            <span class="texto-2" id="meta-objetivo"></span>
        </div>
        <p class="texto-2" id="meta-frase">Continue assim! Cada valor guardado conta.</p>
        <button class="botao botao-claro botao-largo" type="button" id="meta-guardar">Adicionar à meta</button>
    </article>
</section>
