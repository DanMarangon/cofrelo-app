<?php /* Modal de nova transação. A aba "Assinatura" atende ao comentário do protótipo e substitui a antiga tela de assinaturas. */ ?>
<div class="fundo-modal" id="modal-transacao" role="dialog" aria-modal="true" aria-labelledby="mt-titulo" hidden>
    <form class="modal" id="formulario-transacao" novalidate>
        <div class="modal-cabecalho">
            <div>
                <h2 id="mt-titulo">Nova transação</h2>
                <p id="mt-subtitulo">Registre uma entrada ou saída de dinheiro.</p>
            </div>
            <button class="botao-icone" type="button" data-fechar-modal aria-label="Fechar">
                <svg class="icone" aria-hidden="true"><use href="#i-fechar"/></svg>
            </button>
        </div>

        <div class="formulario">
            <div class="abas" role="tablist" aria-label="Tipo de transação" id="mt-abas">
                <button type="button" role="tab" data-tipo="despesa" aria-selected="true">Despesa</button>
                <button type="button" role="tab" data-tipo="receita" aria-selected="false">Receita</button>
                <button type="button" role="tab" data-tipo="transferencia" aria-selected="false">Transferência</button>
                <button type="button" role="tab" data-tipo="assinatura" aria-selected="false">Assinatura</button>
            </div>

            <div class="campo" data-campo="valor">
                <label for="mt-valor">Valor</label>
                <div class="entrada-valor">
                    <span>R$</span>
                    <input id="mt-valor" name="valor" inputmode="numeric" autocomplete="off" value="0,00" aria-describedby="mt-erro-valor">
                </div>
                <span class="erro-campo" id="mt-erro-valor" data-erro="valor"></span>
            </div>

            <div class="campo" data-campo="descricao">
                <label for="mt-descricao">Descrição</label>
                <input class="entrada" id="mt-descricao" name="descricao" maxlength="120" placeholder="Ex.: supermercado, salário, aluguel" autocomplete="off">
                <small id="mt-sugestao" hidden></small>
                <span class="erro-campo" data-erro="descricao"></span>
            </div>

            <div class="linha-campos">
                <div class="campo" data-campo="categoria_id" id="mt-campo-categoria">
                    <label for="mt-categoria">Categoria</label>
                    <select class="selecao" id="mt-categoria" name="categoria_id"></select>
                    <span class="erro-campo" data-erro="categoria_id"></span>
                </div>
                <div class="campo" data-campo="conta_id">
                    <label for="mt-conta" id="mt-rotulo-conta">Conta</label>
                    <select class="selecao" id="mt-conta" name="conta_id"></select>
                    <span class="erro-campo" data-erro="conta_id"></span>
                </div>
                <div class="campo" data-campo="conta_destino_id" id="mt-campo-destino" hidden>
                    <label for="mt-destino">Para a conta</label>
                    <select class="selecao" id="mt-destino" name="conta_destino_id"></select>
                    <span class="erro-campo" data-erro="conta_destino_id"></span>
                </div>
            </div>

            <div class="linha-campos">
                <div class="campo" data-campo="data">
                    <label for="mt-data" id="mt-rotulo-data">Data</label>
                    <input class="entrada" type="date" id="mt-data" name="data">
                    <span class="erro-campo" data-erro="data"></span>
                </div>
                <div class="campo" data-campo="forma_pagamento" id="mt-campo-forma">
                    <label for="mt-forma">Forma de pagamento</label>
                    <select class="selecao" id="mt-forma" name="forma_pagamento">
                        <option value="debito">Cartão de débito</option>
                        <option value="credito">Cartão de crédito</option>
                        <option value="pix">Pix</option>
                        <option value="boleto">Boleto</option>
                        <option value="debito_automatico">Débito automático</option>
                        <option value="transferencia">Transferência</option>
                        <option value="dinheiro">Dinheiro</option>
                    </select>
                    <span class="erro-campo" data-erro="forma_pagamento"></span>
                </div>
            </div>

            <div class="campo" id="mt-campo-status">
                <span class="campo-rotulo" id="mt-rotulo-status">Status</span>
                <div class="escolhas" role="group" aria-labelledby="mt-rotulo-status">
                    <button type="button" data-status="pago" aria-pressed="true">Pago</button>
                    <button type="button" data-status="pendente" aria-pressed="false">Pendente</button>
                </div>
            </div>

            <div class="caixa-opcao" id="mt-caixa-assinatura" hidden>
                <svg class="icone icone-lg" aria-hidden="true"><use href="#i-assinatura"/></svg>
                <div>
                    <strong>Cobrança todo mês</strong>
                    <span>O Cofrelo lança a cobrança sozinho em cada mês e avisa você antes de ela acontecer.</span>
                    <div class="linha-campos detalhes">
                        <div class="campo" data-campo="dia_cobranca">
                            <label for="mt-dia">Dia da cobrança</label>
                            <select class="selecao" id="mt-dia" name="dia_cobranca"></select>
                            <span class="erro-campo" data-erro="dia_cobranca"></span>
                        </div>
                        <div class="campo" data-campo="aviso_dias">
                            <label for="mt-aviso">Avisar antes</label>
                            <select class="selecao" id="mt-aviso" name="aviso_dias">
                                <option value="0">No dia</option>
                                <option value="1">1 dia antes</option>
                                <option value="3" selected>3 dias antes</option>
                                <option value="5">5 dias antes</option>
                                <option value="7">7 dias antes</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <p class="alerta-pagina" id="mt-aviso-assinatura" hidden>
                <svg class="icone" aria-hidden="true"><use href="#i-assinatura"/></svg>
                Esta despesa faz parte de uma assinatura. Para mudar as próximas cobranças, use a aba Assinaturas em Transações.
            </p>

            <div class="campo" data-campo="observacao">
                <label for="mt-observacao">Observação (opcional)</label>
                <input class="entrada" id="mt-observacao" name="observacao" maxlength="255" placeholder="Ex.: compra do mês">
                <span class="erro-campo" data-erro="observacao"></span>
            </div>
        </div>

        <div class="modal-rodape">
            <button class="botao botao-claro" type="button" data-fechar-modal>Cancelar</button>
            <button class="botao botao-primario" type="submit" id="mt-salvar">Salvar transação</button>
        </div>
    </form>
</div>
