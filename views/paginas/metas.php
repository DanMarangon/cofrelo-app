<section class="pagina-cabecalho">
    <div>
        <h2>Dê um destino aos seus sonhos.</h2>
        <p>Transforme planos em conquistas, um passo de cada vez.</p>
    </div>
    <div class="pagina-acoes">
        <button class="botao botao-primario" type="button" id="criar-meta">
            <svg class="icone" aria-hidden="true"><use href="#i-mais"/></svg>Criar nova meta
        </button>
    </div>
</section>

<section class="grade grade-3" aria-label="Resumo das metas">
    <article class="cartao indicador indicador-destaque">
        <div class="indicador-topo"><span>Total reservado</span></div>
        <strong class="indicador-valor esqueleto" id="total-reservado">R$ 00.000,00</strong>
        <div class="indicador-rodape"><span class="destaque-lima">Seu dinheiro construindo o futuro</span></div>
    </article>
    <article class="cartao indicador">
        <div class="indicador-topo"><span>Metas ativas</span></div>
        <strong class="indicador-valor esqueleto" id="metas-ativas">0 metas</strong>
        <div class="indicador-rodape"><span id="metas-detalhe">&nbsp;</span></div>
    </article>
    <article class="cartao indicador">
        <div class="indicador-topo"><span>Próxima conquista</span></div>
        <strong class="indicador-valor esqueleto" id="proxima-conquista">Carregando</strong>
        <div class="indicador-rodape"><span id="proxima-detalhe">&nbsp;</span></div>
    </article>
</section>

<section aria-labelledby="titulo-objetivos">
    <div class="cartao-cabecalho">
        <h3 id="titulo-objetivos">Meus objetivos</h3>
        <div class="abas abas-compactas" role="tablist" aria-label="Situação das metas" id="abas-metas">
            <button type="button" role="tab" data-filtro="ativas" aria-selected="true">Ativas (0)</button>
            <button type="button" role="tab" data-filtro="concluidas" aria-selected="false">Concluídas (0)</button>
        </div>
    </div>
    <div class="grade grade-3" id="lista-metas"></div>
</section>

<section class="cartao" aria-labelledby="titulo-simulador">
    <div class="cartao-cabecalho">
        <div>
            <h3 id="titulo-simulador">Simulador de metas</h3>
            <p>Descubra quanto guardar por mês. Os resultados mudam enquanto você digita.</p>
        </div>
    </div>
    <div class="simulador">
        <form class="formulario" id="formulario-simulador" novalidate>
            <div class="linha-campos">
                <div class="campo">
                    <label for="sim-objetivo">Quanto quero juntar (R$)</label>
                    <input class="entrada" id="sim-objetivo" name="objetivo" inputmode="decimal" value="20.000,00">
                </div>
                <div class="campo">
                    <label for="sim-atual">Já tenho guardado (R$)</label>
                    <input class="entrada" id="sim-atual" name="atual" inputmode="decimal" value="12.000,00">
                </div>
            </div>
            <div class="linha-campos">
                <div class="campo">
                    <label for="sim-meses">Em quantos meses</label>
                    <input class="entrada" id="sim-meses" name="meses" type="number" min="1" max="600" value="12">
                </div>
                <div class="campo">
                    <label for="sim-rendimento">Rendimento ao mês (%)</label>
                    <input class="entrada" id="sim-rendimento" name="rendimento" inputmode="decimal" value="0,8">
                    <small>Poupança rende perto de 0,5% ao mês.</small>
                </div>
            </div>
            <div class="campo">
                <label for="sim-aporte">Quanto consigo guardar por mês (R$)</label>
                <input class="entrada" id="sim-aporte" name="aporte" inputmode="decimal" placeholder="Deixe em branco para usar o valor sugerido">
            </div>
            <label class="caixa-opcao">
                <span class="chave"><input type="checkbox" id="sim-usar-meta"><span></span></span>
                <span><strong>Usar os dados de uma meta</strong><span>Preenche o simulador com a meta escolhida.</span></span>
            </label>
            <select class="selecao" id="sim-meta" hidden></select>
        </form>

        <div>
            <div class="resultados" aria-live="polite">
                <div class="resultado principal"><span>Guarde por mês</span><output id="res-aporte-necessario" for="sim-objetivo sim-atual sim-meses sim-rendimento">R$ 0,00</output></div>
                <div class="resultado"><span>Saldo ao final</span><output id="res-saldo-final">R$ 0,00</output></div>
                <div class="resultado"><span>Rendimento no período</span><output id="res-rendimento">R$ 0,00</output></div>
                <div class="resultado"><span>Total que você vai guardar</span><output id="res-total">R$ 0,00</output></div>
                <div class="resultado"><span>Meses para chegar lá</span><output id="res-meses">—</output></div>
            </div>
            <p class="frase-simulacao" id="frase-simulacao"></p>
            <div class="grafico" id="grafico-simulacao" role="img" aria-label="Evolução do saldo simulado mês a mês"></div>
        </div>
    </div>
</section>

<div class="fundo-modal" id="modal-meta" role="dialog" aria-modal="true" aria-labelledby="mm-titulo" hidden>
    <form class="modal modal-pequeno" id="formulario-meta" novalidate>
        <div class="modal-cabecalho">
            <div>
                <h2 id="mm-titulo">Criar nova meta</h2>
                <p>Dê um nome, um valor e, se quiser, um prazo.</p>
            </div>
            <button class="botao-icone" type="button" data-fechar-modal aria-label="Fechar"><svg class="icone" aria-hidden="true"><use href="#i-fechar"/></svg></button>
        </div>
        <div class="formulario">
            <div class="campo" data-campo="nome">
                <label for="mm-nome">Nome da meta</label>
                <input class="entrada" id="mm-nome" name="nome" maxlength="80" placeholder="Ex.: Viagem de férias">
                <span class="erro-campo" data-erro="nome"></span>
            </div>
            <div class="campo" data-campo="descricao">
                <label for="mm-descricao">Frase de motivação (opcional)</label>
                <input class="entrada" id="mm-descricao" name="descricao" maxlength="120" placeholder="Ex.: Novas memórias começam aqui.">
            </div>
            <div class="linha-campos">
                <div class="campo" data-campo="valor_objetivo">
                    <label for="mm-objetivo">Objetivo (R$)</label>
                    <input class="entrada" id="mm-objetivo" name="valor_objetivo" inputmode="decimal" placeholder="0,00">
                    <span class="erro-campo" data-erro="valor_objetivo"></span>
                </div>
                <div class="campo" data-campo="valor_atual">
                    <label for="mm-atual">Já guardado (R$)</label>
                    <input class="entrada" id="mm-atual" name="valor_atual" inputmode="decimal" placeholder="0,00">
                    <span class="erro-campo" data-erro="valor_atual"></span>
                </div>
            </div>
            <div class="linha-campos">
                <div class="campo" data-campo="data_limite">
                    <label for="mm-limite">Prazo (opcional)</label>
                    <input class="entrada" type="date" id="mm-limite" name="data_limite">
                    <span class="erro-campo" data-erro="data_limite"></span>
                </div>
                <div class="campo" data-campo="icone">
                    <label for="mm-icone">Ícone</label>
                    <select class="selecao" id="mm-icone" name="icone">
                        <option value="alvo">Alvo</option>
                        <option value="grafico">Crescimento</option>
                        <option value="carteira">Carteira</option>
                        <option value="casa">Casa</option>
                        <option value="aviao">Viagem</option>
                        <option value="carro">Carro</option>
                        <option value="presente">Presente</option>
                        <option value="estudo">Estudos</option>
                    </select>
                </div>
            </div>
            <p class="texto-2" id="mm-sugestao"></p>
        </div>
        <div class="modal-rodape">
            <button class="botao botao-claro" type="button" data-fechar-modal>Cancelar</button>
            <button class="botao botao-primario" type="submit">Salvar meta</button>
        </div>
    </form>
</div>
