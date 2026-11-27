<div class="fundo-modal" id="modal-deposito" role="dialog" aria-modal="true" aria-labelledby="md-titulo" hidden>
    <form class="modal modal-pequeno" id="formulario-deposito" novalidate>
        <div class="modal-cabecalho">
            <div>
                <h2 id="md-titulo">Guardar dinheiro</h2>
                <p id="md-subtitulo">Quanto você vai guardar nesta meta?</p>
            </div>
            <button class="botao-icone" type="button" data-fechar-modal aria-label="Fechar">
                <svg class="icone" aria-hidden="true"><use href="#i-fechar"/></svg>
            </button>
        </div>
        <div class="formulario">
            <div class="campo" data-campo="valor">
                <label for="md-valor">Valor</label>
                <div class="entrada-valor">
                    <span>R$</span>
                    <input id="md-valor" name="valor" inputmode="numeric" autocomplete="off" value="0,00">
                </div>
                <span class="erro-campo" data-erro="valor"></span>
            </div>
            <div class="escolhas" id="md-sugestoes" aria-label="Valores sugeridos"></div>
            <div class="campo" data-campo="data">
                <label for="md-data">Data</label>
                <input class="entrada" type="date" id="md-data" name="data">
                <span class="erro-campo" data-erro="data"></span>
            </div>
            <p class="texto-2" id="md-previsao"></p>
        </div>
        <div class="modal-rodape">
            <button class="botao botao-claro" type="button" data-fechar-modal>Cancelar</button>
            <button class="botao botao-primario" type="submit">Guardar</button>
        </div>
    </form>
</div>
