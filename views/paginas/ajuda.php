<?php
/** @var string $versao */
$perguntas = [
    ['Como registro uma despesa ou receita?', 'Clique em "Nova transação" (ou aperte a tecla N), escolha Despesa ou Receita, preencha o valor, a descrição, a categoria e a conta e salve. A transação aparece na hora na Visão geral e em Transações.'],
    ['O que acontece quando marco uma assinatura?', 'Na aba Assinatura do modal de nova transação, a despesa vira uma cobrança mensal. Todo mês o Cofrelo lança a cobrança sozinho no dia escolhido e mostra um aviso no sino alguns dias antes. Você edita ou desliga a assinatura na aba Assinaturas da página Transações.'],
    ['Por que uma despesa aparece como pendente?', 'Transações com data no futuro, ou marcadas como pendentes, ainda não saíram da conta. Elas entram nos totais do mês, mas não diminuem o saldo até serem pagas.'],
    ['Como funciona o filtro de período?', 'Em Transações e Relatórios, o botão com o calendário abre um seletor de período. Use os atalhos (este mês, últimos 3 meses...) ou clique no primeiro e no último dia no calendário e confirme em Aplicar.'],
    ['Como guardo dinheiro em uma meta?', 'Em Metas e objetivos, clique em "Guardar dinheiro" no cartão da meta e informe o valor. A barra de progresso e o valor que falta são atualizados na hora.'],
    ['Para que serve o simulador de metas?', 'Ele calcula quanto você precisa guardar por mês para chegar ao objetivo no prazo, considerando o rendimento mensal. Os campos de resultado são recalculados a cada número digitado.'],
    ['Posso transferir dinheiro entre minhas contas?', 'Sim. Na aba Transferência do modal de nova transação, escolha a conta de origem e a de destino. O saldo total não muda; só a distribuição entre as contas.'],
    ['Como exporto meus dados?', 'Em Transações, o botão "Exportar CSV" baixa as transações do filtro atual. Em Relatórios, "Exportar relatório" baixa o resumo do período. Os arquivos abrem direto no Excel.'],
];
?>
<section class="pagina-cabecalho">
    <div>
        <h2>Como podemos ajudar?</h2>
        <p>Dúvidas comuns, atalhos e informações sobre o Cofrelo.</p>
    </div>
</section>

<section class="grade grade-principal">
    <article class="cartao">
        <div class="cartao-cabecalho">
            <h3>Perguntas frequentes</h3>
        </div>
        <label class="entrada-com-icone">
            <span class="visualmente-oculto">Buscar nas perguntas</span>
            <svg class="icone" aria-hidden="true"><use href="#i-busca"/></svg>
            <input class="entrada" type="search" id="busca-ajuda" placeholder="Buscar nas perguntas" autocomplete="off">
        </label>
        <p class="texto-2" id="ajuda-contador" aria-live="polite">&nbsp;</p>
        <div class="perguntas" id="lista-perguntas">
            <?php foreach ($perguntas as [$pergunta, $resposta]): ?>
                <details class="pergunta">
                    <summary><?= e($pergunta) ?><svg class="icone" aria-hidden="true"><use href="#i-chevron"/></svg></summary>
                    <p><?= e($resposta) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </article>

    <div class="grade">
        <article class="cartao">
            <div class="cartao-cabecalho"><h3>Atalhos de teclado</h3></div>
            <div class="atalhos-teclado">
                <span><kbd>N</kbd> Nova transação</span>
                <span><kbd>/</kbd> Buscar em Transações</span>
                <span><kbd>Esc</kbd> Fechar janelas</span>
                <span><kbd>G</kbd> + <kbd>V</kbd> Ir para a Visão geral</span>
            </div>
        </article>
        <article class="cartao">
            <div class="cartao-cabecalho"><h3>Sobre o Cofrelo</h3></div>
            <ul class="lista-simples">
                <li><div class="lista-texto"><strong>Versão</strong><span><?= e($versao) ?></span></div></li>
                <li><div class="lista-texto"><strong>Contato</strong><span>suporte@cofrelo.app</span></div></li>
                <li><div class="lista-texto"><strong>Seus dados</strong><span>As senhas são guardadas com criptografia (bcrypt) e nenhuma informação é compartilhada.</span></div></li>
            </ul>
        </article>
    </div>
</section>
