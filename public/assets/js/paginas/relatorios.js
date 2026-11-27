/** Relatórios: indicadores do período, evolução mensal, comparação por categoria e o mapa de gastos. */

import { api } from '../nucleo/api.js';
import { el, definirTexto, adiar } from '../nucleo/dom.js';
import { formatarMoeda, formatarPercentual, nomeDoMes } from '../lib/formatos.js';
import { periodoDoAtalho, paraISO, deISO } from '../lib/datas.js';
import { SeletorPeriodo } from '../componentes/calendario.js';
import { graficoBarras, graficoRosca, legendaRosca, mapaDeGastos, CORES } from '../componentes/graficos.js';
import { avisar } from '../componentes/avisos.js';

const inicial = periodoDoAtalho('ultimos-6-meses');
const periodo = { inicio: inicial.inicio, fim: inicial.fim };
let relatorio = null;

new SeletorPeriodo(document.getElementById('periodo-relatorio'), {
  inicio: periodo.inicio,
  fim: periodo.fim,
  atalho: 'ultimos-6-meses',
  aoMudar: ({ inicio, fim }) => {
    Object.assign(periodo, { inicio, fim });
    carregar();
  },
});

const parametros = () => ({ inicio: paraISO(periodo.inicio), fim: paraISO(periodo.fim) });

async function carregar() {
  try {
    relatorio = await api.get('/api/relatorios', parametros());
    desenhar();
  } catch (erro) {
    avisar(erro.message, 'erro');
  }
}

function desenhar() {
  const r = relatorio;
  definirTexto('rel-receitas', formatarMoeda(r.receitas));
  definirTexto('rel-despesas', formatarMoeda(r.despesas));
  definirTexto('rel-resultado', formatarMoeda(r.resultado));
  definirTexto('rel-taxa', formatarPercentual(r.taxa_economia));
  definirTexto('rel-qtd-receitas', `${r.qtd_receitas} ${r.qtd_receitas === 1 ? 'recebimento' : 'recebimentos'} no período`);
  definirTexto('rel-qtd-despesas', `${r.qtd_despesas} ${r.qtd_despesas === 1 ? 'pagamento' : 'pagamentos'} no período`);
  document.getElementById('rel-resultado').classList.toggle('negativo', r.resultado < 0);

  const primeiro = r.evolucao[0];
  const ultimo = r.evolucao.at(-1);
  if (primeiro && ultimo) {
    const [anoInicio, mesInicio] = primeiro.mes.split('-').map(Number);
    const [anoFim, mesFim] = ultimo.mes.split('-').map(Number);
    const inicioTexto = nomeDoMes(mesInicio - 1).toLowerCase();
    const fimTexto = `${nomeDoMes(mesFim - 1).toLowerCase()} de ${anoFim}`;
    const texto = primeiro.mes === ultimo.mes
      ? `${nomeDoMes(mesFim - 1)} de ${anoFim}`
      : `${inicioTexto.charAt(0).toUpperCase()}${inicioTexto.slice(1)}${anoInicio !== anoFim ? ` de ${anoInicio}` : ''} a ${fimTexto}`;
    definirTexto('rel-subtitulo-evolucao', `${texto} · valores por mês`);
  }

  desenharGraficos();
  desenharCategorias();
  desenharMapa();
}

function desenharGraficos() {
  graficoBarras(document.getElementById('grafico-evolucao'), {
    rotulos: relatorio.evolucao.map((m) => m.rotulo),
    series: [
      { nome: 'Receitas', cor: CORES.receita, valores: relatorio.evolucao.map((m) => m.receitas) },
      { nome: 'Despesas', cor: CORES.despesa, valores: relatorio.evolucao.map((m) => m.despesas) },
    ],
    destaque: relatorio.evolucao.length - 1,
    altura: 270,
  });
}

function desenharCategorias() {
  const { mes_atual: atual, mes_anterior: anterior, categorias } = relatorio.comparacao;
  definirTexto('rel-subtitulo-categorias', `Compare ${atual.toLowerCase()} com o mês anterior.`);
  definirTexto('rel-coluna-anterior', anterior);
  definirTexto('rel-coluna-atual', atual);

  const linhas = categorias.linhas;
  const corpo = document.getElementById('rel-linhas-categorias');
  if (linhas.length === 0) {
    corpo.replaceChildren(el('tr', {}, [el('td', { classe: 'vazio', atributos: { colspan: 5 }, texto: 'Sem despesas nesses meses.' })]));
  } else {
    corpo.replaceChildren(...linhas.map((linha) => {
      const ponto = el('i', { classe: 'ponto redondo' });
      ponto.style.background = linha.cor;
      return el('tr', {}, [
        el('td', {}, [el('span', { classe: 'categoria-ponto' }, [ponto, el('span', { texto: linha.nome })])]),
        el('td', { classe: 'direita texto-2', texto: formatarMoeda(linha.anterior) }),
        el('td', { classe: 'direita', texto: formatarMoeda(linha.atual) }),
        el('td', { classe: 'direita', texto: `${linha.participacao}%` }),
        celulaVariacao(linha.variacao),
      ]);
    }));
  }
  document.getElementById('rel-total-categorias').replaceChildren(el('tr', {}, [
    el('td', { texto: 'Total' }),
    el('td', { classe: 'direita', texto: formatarMoeda(categorias.total.anterior) }),
    el('td', { classe: 'direita', texto: formatarMoeda(categorias.total.atual) }),
    el('td', { classe: 'direita', texto: categorias.total.atual > 0 ? '100%' : '—' }),
    celulaVariacao(categorias.total.variacao),
  ]));

  const fatias = linhas.filter((linha) => linha.atual > 0).map((linha) => ({
    nome: linha.nome, valor: linha.atual, cor: linha.cor, percentual: linha.participacao,
  }));
  graficoRosca(document.getElementById('rel-rosca'), fatias);
  legendaRosca(document.getElementById('rel-legenda'), fatias);
}

function celulaVariacao(variacao) {
  if (variacao === null) return el('td', { classe: 'direita texto-2', texto: 'novo' });
  // Gasto que subiu aparece em vermelho; gasto que caiu, em verde.
  const classe = variacao > 0 ? 'subiu' : 'desceu';
  const sinal = variacao > 0 ? '+' : '−';
  return el('td', {
    classe: `direita variacao-celula ${classe}`,
    texto: `${sinal}${Math.abs(variacao).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`,
  });
}

function desenharMapa() {
  const mapa = relatorio.mapa_de_gastos;
  const cores = mapaDeGastos(document.getElementById('mapa-gastos'), { dias: mapa.dias, fim: deISO(mapa.fim) });
  const escala = document.getElementById('mapa-escala');
  escala.replaceChildren('Menos', ...cores.map((cor) => {
    const quadrado = el('i');
    quadrado.style.background = cor;
    return quadrado;
  }), 'Mais');

  let resumo = `${formatarMoeda(mapa.total)} gastos em ${mapa.dias_com_gasto} dias no último ano.`;
  if (mapa.maior_dia) {
    const dia = deISO(mapa.maior_dia.data);
    resumo += ` Dia de maior gasto: ${dia.toLocaleDateString('pt-BR')} (${formatarMoeda(mapa.maior_dia.valor)}).`;
  }
  definirTexto('mapa-resumo', resumo);
}

document.getElementById('exportar-relatorio').addEventListener('click', () => {
  api.baixar('/api/relatorios/exportar', parametros());
  avisar('Gerando o relatório em CSV.');
});
document.addEventListener('cofrelo:dados-alterados', carregar);
window.addEventListener('resize', adiar(() => relatorio && desenharGraficos(), 200));

carregar();
