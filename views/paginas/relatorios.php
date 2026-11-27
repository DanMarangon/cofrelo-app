<section class="pagina-cabecalho">
    <div>
        <h2>Entenda seus números. Planeje melhor.</h2>
        <p>Veja a evolução das suas finanças e descubra oportunidades para economizar.</p>
    </div>
    <div class="pagina-acoes">
        <div class="ancora">
            <button class="botao botao-claro botao-periodo" id="periodo-relatorio" type="button" aria-haspopup="dialog" aria-expanded="false">
                <svg class="icone" aria-hidden="true"><use href="#i-calendario"/></svg><span>Últimos 6 meses</span>
            </button>
        </div>
        <button class="botao botao-primario" type="button" id="exportar-relatorio">
            Exportar relatório <svg class="icone icone-sm" aria-hidden="true"><use href="#i-baixar"/></svg>
        </button>
    </div>
</section>

<section class="grade grade-4" aria-label="Resumo do período">
    <article class="cartao indicador">
        <div class="indicador-topo"><span>Receitas</span></div>
        <strong class="indicador-valor esqueleto" id="rel-receitas">R$ 0.000,00</strong>
        <div class="indicador-rodape"><span id="rel-qtd-receitas">&nbsp;</span></div>
    </article>
    <article class="cartao indicador">
        <div class="indicador-topo"><span>Despesas</span></div>
        <strong class="indicador-valor esqueleto" id="rel-despesas">R$ 0.000,00</strong>
        <div class="indicador-rodape"><span id="rel-qtd-despesas">&nbsp;</span></div>
    </article>
    <article class="cartao indicador">
        <div class="indicador-topo"><span>Resultado do período</span></div>
        <strong class="indicador-valor esqueleto" id="rel-resultado">R$ 0.000,00</strong>
        <div class="indicador-rodape"><span>Receitas menos despesas</span></div>
    </article>
    <article class="cartao indicador indicador-destaque">
        <div class="indicador-topo"><span>Taxa de economia</span></div>
        <strong class="indicador-valor esqueleto" id="rel-taxa">00,0%</strong>
        <div class="indicador-rodape"><span class="destaque-lima">Da sua renda ficou com você</span></div>
    </article>
</section>

<section class="cartao">
    <div class="cartao-cabecalho">
        <div>
            <h3>Evolução das receitas e despesas</h3>
            <p id="rel-subtitulo-evolucao">&nbsp;</p>
        </div>
        <div class="legenda">
            <span><i class="ponto" data-cor="receita"></i>Receitas</span>
            <span><i class="ponto" data-cor="despesa"></i>Despesas</span>
        </div>
    </div>
    <div class="grafico" id="grafico-evolucao" role="img" aria-label="Gráfico de receitas e despesas por mês"></div>
</section>

<section class="cartao">
    <div class="cartao-cabecalho">
        <div>
            <h3>Despesas por categoria</h3>
            <p id="rel-subtitulo-categorias">&nbsp;</p>
        </div>
    </div>
    <div class="grade grade-principal">
        <div class="tabela-area" tabindex="0" role="region" aria-label="Tabela de gastos por categoria">
            <table class="tabela tabela-categorias">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th class="direita" id="rel-coluna-anterior">Mês anterior</th>
                        <th class="direita" id="rel-coluna-atual">Mês atual</th>
                        <th class="direita">Participação</th>
                        <th class="direita">Variação</th>
                    </tr>
                </thead>
                <tbody id="rel-linhas-categorias"></tbody>
                <tfoot id="rel-total-categorias"></tfoot>
            </table>
        </div>
        <div class="rosca-area">
            <div class="rosca" id="rel-rosca"></div>
            <ul class="rosca-legenda" id="rel-legenda"></ul>
        </div>
    </div>
</section>

<section class="cartao">
    <div class="cartao-cabecalho">
        <div>
            <h3>Seu ano em gastos</h3>
            <p>Cada quadrado é um dia. Quanto mais escuro, mais você gastou naquele dia.</p>
        </div>
    </div>
    <div class="mapa grafico" id="mapa-gastos" role="img" aria-label="Mapa de gastos diários do último ano" tabindex="0"></div>
    <div class="mapa-rodape">
        <span id="mapa-resumo">&nbsp;</span>
        <span class="mapa-escala" id="mapa-escala">Menos</span>
    </div>
</section>
