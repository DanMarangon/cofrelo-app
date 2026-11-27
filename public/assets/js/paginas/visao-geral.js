/** Visão geral: indicadores do mês, fluxo de caixa, gastos por categoria, orçamento, últimas transações e meta. */

import { api } from '../nucleo/api.js';
import { el, definirTexto, preencherVariacao, adiar } from '../nucleo/dom.js';
import { formatarMoeda, formatarData, formatarPercentual } from '../lib/formatos.js';
import { hoje } from '../lib/datas.js';
import { graficoBarras, graficoRosca, legendaRosca, CORES } from '../componentes/graficos.js';
import { SeletorMes } from '../componentes/calendario.js';
import { abrirModalDeposito } from '../componentes/modal-deposito.js';
import { avisar } from '../componentes/avisos.js';
import { celulaDescricao, celulaValor, categoriaComCor } from '../componentes/transacao-visual.js';

let mes = new Date(hoje().getFullYear(), hoje().getMonth(), 1);
let resumo = null;

new SeletorMes(document.getElementById('seletor-mes'), {
  mes,
  aoMudar: (escolhido) => {
    mes = escolhido;
    carregar();
  },
});

async function carregar() {
  try {
    const anoMes = `${mes.getFullYear()}-${String(mes.getMonth() + 1).padStart(2, '0')}`;
    resumo = await api.get('/api/resumo', { mes: anoMes });
    desenhar();
    if (resumo.assinaturas_lancadas > 0) {
      const quantidade = resumo.assinaturas_lancadas;
      avisar(`${quantidade} ${quantidade === 1 ? 'cobrança de assinatura foi lançada' : 'cobranças de assinaturas foram lançadas'} automaticamente neste mês.`);
    }
  } catch (erro) {
    avisar(erro.message, 'erro');
  }
}

function desenhar() {
  definirTexto('saldo-total', formatarMoeda(resumo.saldo_total));
  preencherVariacao('saldo-variacao', resumo.saldo_variacao);
  definirTexto('receitas', formatarMoeda(resumo.receitas));
  preencherVariacao('receitas-variacao', resumo.receitas_variacao);
  definirTexto('despesas', formatarMoeda(resumo.despesas));
  preencherVariacao('despesas-variacao', resumo.despesas_variacao, { despesa: true });

  desenharGraficos();
  desenharOrcamento();
  desenharUltimas();
  desenharMeta();
}

function desenharGraficos() {
  graficoBarras(document.getElementById('grafico-fluxo'), {
    rotulos: resumo.fluxo.map((m) => m.rotulo),
    series: [
      { nome: 'Receitas', cor: CORES.receita, valores: resumo.fluxo.map((m) => m.receitas) },
      { nome: 'Despesas', cor: CORES.despesa, valores: resumo.fluxo.map((m) => m.despesas) },
    ],
    destaque: resumo.fluxo.length - 1,
  });

  const rosca = document.getElementById('rosca-categorias');
  const legenda = document.getElementById('legenda-categorias');
  if (resumo.categorias.length === 0) {
    rosca.replaceChildren();
    legenda.replaceChildren(el('li', { classe: 'vazio', texto: 'Nenhuma despesa neste mês.' }));
    return;
  }
  graficoRosca(rosca, resumo.categorias);
  legendaRosca(legenda, resumo.categorias);
}

function desenharOrcamento() {
  const texto = document.getElementById('texto-orcamento');
  const barra = document.getElementById('barra-orcamento');
  const { valor, percentual } = resumo.orcamento;
  if (!valor) {
    texto.textContent = 'Defina um orçamento mensal em Configurações para acompanhar seus gastos.';
    barra.querySelector('span').style.width = '0';
    return;
  }
  texto.textContent = `Você usou ${formatarPercentual(percentual, 0)} do orçamento de ${formatarMoeda(valor)}.`;
  barra.className = `progresso${percentual >= 100 ? ' estourado' : percentual >= 80 ? ' alerta' : ''}`;
  barra.querySelector('span').style.width = `${Math.min(100, percentual)}%`;
}

function desenharUltimas() {
  const corpo = document.getElementById('ultimas-transacoes');
  if (resumo.ultimas.length === 0) {
    corpo.replaceChildren(el('tr', {}, [el('td', { classe: 'vazio', atributos: { colspan: 4 } }, [
      el('strong', { texto: 'Nenhuma transação ainda' }), 'Use o botão "Nova transação" para registrar a primeira.',
    ])]));
    return;
  }
  // [DW 10.e] Tabela montada pelo JavaScript a partir dos dados da API.
  corpo.replaceChildren(...resumo.ultimas.map((transacao) => el('tr', {}, [
    el('td', {}, [celulaDescricao(transacao)]),
    el('td', {}, [categoriaComCor(transacao)]),
    el('td', { classe: 'texto-2', texto: formatarData(transacao.data) }),
    celulaValor(transacao),
  ])));
}

function desenharMeta() {
  const meta = resumo.meta_destaque;
  const cartao = document.getElementById('cartao-meta');
  const botao = document.getElementById('meta-guardar');
  if (!meta) {
    document.getElementById('meta-nome').textContent = 'Você ainda não tem metas ativas.';
    document.getElementById('meta-frase').textContent = 'Crie uma meta e acompanhe aqui o seu progresso.';
    botao.textContent = 'Criar uma meta';
    botao.onclick = () => { window.location.href = `${document.querySelector('meta[name="base-url"]').content.replace(/\/$/, '')}/metas`; };
    return;
  }
  document.getElementById('meta-nome').textContent = meta.nome;
  document.getElementById('meta-percentual').textContent = formatarPercentual(meta.percentual, 0);
  document.getElementById('meta-atual').textContent = formatarMoeda(meta.valor_atual).replace(/,00$/, '');
  document.getElementById('meta-objetivo').textContent = `de ${formatarMoeda(meta.valor_objetivo).replace(/,00$/, '')}`;
  cartao.querySelector('#meta-barra span').style.width = `${meta.percentual}%`;

  // [DW 10.a] A frase muda conforme o quanto já foi guardado.
  let frase = 'Continue assim! Cada valor guardado conta.';
  if (meta.percentual < 25) frase = 'Todo começo conta. Que tal guardar um pouco hoje?';
  else if (meta.percentual >= 75) frase = 'Falta pouco! Você está quase lá.';
  document.getElementById('meta-frase').textContent = frase;
  botao.textContent = 'Adicionar à meta';
  botao.onclick = () => abrirModalDeposito(meta);
}

document.addEventListener('cofrelo:dados-alterados', carregar);
// Ao mudar o tamanho da janela, os gráficos são redesenhados na nova largura.
window.addEventListener('resize', adiar(() => resumo && desenharGraficos(), 200));

carregar();
