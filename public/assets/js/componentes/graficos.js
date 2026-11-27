/**
 * Gráficos desenhados em SVG com JavaScript puro (sem biblioteca): barras, rosca, linha e o
 * mapa de gastos no estilo do calendário de contribuições do GitHub.
 */

import { el, svg } from '../nucleo/dom.js';
import { formatarMoeda, formatarMoedaCurta } from '../lib/formatos.js';
import { semanasAte, paraISO } from '../lib/datas.js';

export const CORES = { receita: '#1F7A52', despesa: '#C98A0B' };

const MESES_CURTOS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/** Escolhe um passo "redondo" para o eixo (1, 2, 2,5 ou 5 vezes uma potência de 10). */
function passoDoEixo(maximo, divisoes = 4) {
  if (maximo <= 0) return 1000;
  const bruto = maximo / divisoes;
  const potencia = 10 ** Math.floor(Math.log10(bruto));
  const multiplicador = [1, 2, 2.5, 5, 10].find((opcao) => opcao * potencia >= bruto);
  return multiplicador * potencia;
}

/** Retângulo com os cantos de cima arredondados (como as barras do protótipo). */
function caminhoBarra(x, y, largura, altura, raio = 4) {
  const r = Math.min(raio, largura / 2, altura);
  if (altura <= 0) return '';
  return `M${x},${y + altura} V${y + r} A${r},${r} 0 0 1 ${x + r},${y} H${x + largura - r} A${r},${r} 0 0 1 ${x + largura},${y + r} V${y + altura} Z`;
}

function criarDica(container) {
  const dica = el('div', { classe: 'dica', atributos: { hidden: true } });
  container.append(dica);
  return dica;
}

function posicionarDica(dica, container, evento) {
  const caixa = container.getBoundingClientRect();
  dica.style.left = `${evento.clientX - caixa.left}px`;
  dica.style.top = `${evento.clientY - caixa.top}px`;
  dica.hidden = false;
}

/**
 * Barras agrupadas por mês.
 * @param {HTMLElement} container
 * @param {{rotulos: string[], series: {nome: string, cor: string, valores: number[]}[], destaque?: number, altura?: number}} dados
 */
export function graficoBarras(container, { rotulos, series, destaque = -1, altura = 250 }) {
  const largura = Math.max(container.clientWidth || 640, 320);
  const margem = { topo: 10, direita: 6, base: 30, esquerda: 66 };
  const areaLargura = largura - margem.esquerda - margem.direita;
  const areaAltura = altura - margem.topo - margem.base;

  const maior = Math.max(0, ...series.flatMap((serie) => serie.valores));
  const passo = passoDoEixo(maior);
  const topo = passo * Math.max(4, Math.ceil(maior / passo));
  const escalaY = (valor) => margem.topo + areaAltura - (valor / topo) * areaAltura;

  const grafico = svg('svg', { viewBox: `0 0 ${largura} ${altura}`, role: 'presentation' });

  // [DW 10.a] Laço de repetição: uma linha de grade para cada marca do eixo.
  for (let marca = 0; marca <= topo; marca += passo) {
    const y = escalaY(marca);
    grafico.append(svg('line', { class: 'grade-linha', x1: margem.esquerda, x2: largura - margem.direita, y1: y, y2: y }));
    grafico.append(svg('text', { class: 'eixo-texto', x: margem.esquerda - 10, y: y + 4, 'text-anchor': 'end' }, [formatarMoedaCurta(marca)]));
  }

  const grupo = areaLargura / rotulos.length;
  const larguraBarra = Math.min(26, grupo * 0.26);
  const espaco = Math.min(8, grupo * 0.08);
  const larguraDoPar = larguraBarra * series.length + espaco * (series.length - 1);
  const dica = criarDica(container);
  const barrasPorGrupo = [];

  rotulos.forEach((rotulo, indice) => {
    const inicio = margem.esquerda + grupo * indice + (grupo - larguraDoPar) / 2;
    const barras = series.map((serie, s) => {
      const valor = serie.valores[indice] ?? 0;
      const y = escalaY(valor);
      const barra = svg('path', {
        class: 'barra',
        d: caminhoBarra(inicio + s * (larguraBarra + espaco), y, larguraBarra, margem.topo + areaAltura - y),
        fill: serie.cor,
      });
      grafico.append(barra);
      return barra;
    });
    barrasPorGrupo.push(barras);

    grafico.append(svg('text', {
      class: `eixo-texto${indice === destaque ? ' destaque' : ''}`,
      x: margem.esquerda + grupo * indice + grupo / 2,
      y: altura - 8,
      'text-anchor': 'middle',
    }, [rotulo]));

    // Área invisível maior que as barras para facilitar o mouse.
    const alvo = svg('rect', { x: margem.esquerda + grupo * indice, y: margem.topo, width: grupo, height: areaAltura, fill: 'transparent' });
    // [DW 10.g] Interação: ao passar o mouse, destaca o mês e mostra os valores.
    alvo.addEventListener('mousemove', (evento) => {
      container.classList.add('tem-foco');
      barrasPorGrupo.flat().forEach((b) => b.classList.remove('em-foco'));
      barras.forEach((b) => b.classList.add('em-foco'));
      dica.replaceChildren(
        el('strong', { texto: rotulo }),
        ...series.map((serie) => el('div', {}, [el('span', { texto: serie.nome }), el('span', { texto: formatarMoeda(serie.valores[indice] ?? 0) })])),
      );
      posicionarDica(dica, container, evento);
    });
    alvo.addEventListener('mouseleave', () => {
      container.classList.remove('tem-foco');
      dica.hidden = true;
    });
    grafico.append(alvo);
  });

  container.replaceChildren(grafico, dica);
}

/**
 * Rosca com a divisão por categoria. O total vai no centro.
 * @param {HTMLElement} container
 * @param {{nome: string, valor: number, cor: string, percentual?: number}[]} fatias
 */
export function graficoRosca(container, fatias, { rotulo = 'Total' } = {}) {
  const total = fatias.reduce((soma, fatia) => soma + fatia.valor, 0);
  const raio = 70;
  const circunferencia = 2 * Math.PI * raio;
  const grafico = svg('svg', { viewBox: '0 0 180 180', role: 'presentation' });
  grafico.append(svg('circle', { cx: 90, cy: 90, r: raio, fill: 'none', stroke: '#EEF2EC', 'stroke-width': 26 }));

  let acumulado = 0;
  const espaco = fatias.length > 1 ? 2 : 0;
  for (const fatia of fatias) {
    const comprimento = total > 0 ? (fatia.valor / total) * circunferencia : 0;
    const circulo = svg('circle', {
      class: 'rosca-fatia',
      cx: 90, cy: 90, r: raio, fill: 'none',
      stroke: fatia.cor, 'stroke-width': 26,
      'stroke-dasharray': `${Math.max(0, comprimento - espaco)} ${circunferencia}`,
      'stroke-dashoffset': -acumulado,
    }, [svg('title', {}, [`${fatia.nome}: ${formatarMoeda(fatia.valor)}`])]);
    circulo.addEventListener('mouseenter', () => { container.classList.add('tem-foco'); circulo.classList.add('em-foco'); });
    circulo.addEventListener('mouseleave', () => { container.classList.remove('tem-foco'); circulo.classList.remove('em-foco'); });
    grafico.append(circulo);
    acumulado += comprimento;
  }

  const centro = el('div', { classe: 'rosca-centro' }, [
    el('span', { texto: rotulo }),
    el('strong', { texto: formatarMoeda(total).replace(/,00$/, '') }),
  ]);
  container.replaceChildren(grafico, centro);
}

/** Legenda da rosca: cor, nome e percentual. */
export function legendaRosca(lista, fatias) {
  lista.replaceChildren(...fatias.map((fatia) => el('li', {}, [
    el('i', { classe: 'ponto redondo', atributos: { 'data-cor-fundo': fatia.cor } }),
    el('span', { texto: fatia.nome }),
    el('strong', { texto: `${fatia.percentual}%` }),
  ])));
  // A cor de cada ponto vem do banco; o estilo é aplicado pelo JavaScript (permitido pela política de segurança).
  lista.querySelectorAll('[data-cor-fundo]').forEach((ponto) => { ponto.style.background = ponto.dataset.corFundo; });
}

/** Linha da simulação de metas, com a linha tracejada do objetivo. */
export function graficoLinha(container, valores, { objetivo = null, altura = 180 } = {}) {
  const largura = Math.max(container.clientWidth || 480, 280);
  const margem = { topo: 12, direita: 10, base: 24, esquerda: 66 };
  const areaLargura = largura - margem.esquerda - margem.direita;
  const areaAltura = altura - margem.topo - margem.base;
  const maior = Math.max(objetivo ?? 0, ...valores, 1);
  const passo = passoDoEixo(maior, 3);
  const topo = passo * Math.ceil(maior / passo);
  const x = (indice) => margem.esquerda + (valores.length <= 1 ? 0 : (indice / (valores.length - 1)) * areaLargura);
  const y = (valor) => margem.topo + areaAltura - (valor / topo) * areaAltura;

  const grafico = svg('svg', { viewBox: `0 0 ${largura} ${altura}`, role: 'presentation' });
  for (let marca = 0; marca <= topo; marca += passo) {
    grafico.append(svg('line', { class: 'grade-linha', x1: margem.esquerda, x2: largura - margem.direita, y1: y(marca), y2: y(marca) }));
    grafico.append(svg('text', { class: 'eixo-texto', x: margem.esquerda - 10, y: y(marca) + 4, 'text-anchor': 'end' }, [formatarMoedaCurta(marca)]));
  }
  if (valores.length > 0) {
    const pontos = valores.map((valor, indice) => `${x(indice)},${y(valor)}`).join(' ');
    const base = margem.topo + areaAltura;
    grafico.append(svg('polygon', { points: `${x(0)},${base} ${pontos} ${x(valores.length - 1)},${base}`, fill: 'rgba(29, 112, 79, 0.10)' }));
    grafico.append(svg('polyline', { points: pontos, fill: 'none', stroke: CORES.receita, 'stroke-width': 2.5, 'stroke-linejoin': 'round' }));
    grafico.append(svg('circle', { cx: x(valores.length - 1), cy: y(valores.at(-1)), r: 4, fill: CORES.receita }));
    grafico.append(svg('text', { class: 'eixo-texto', x: x(0), y: altura - 6, 'text-anchor': 'start' }, ['Hoje']));
    grafico.append(svg('text', { class: 'eixo-texto', x: x(valores.length - 1), y: altura - 6, 'text-anchor': 'end' }, [`Mês ${valores.length}`]));
  }
  if (objetivo) {
    grafico.append(svg('line', { x1: margem.esquerda, x2: largura - margem.direita, y1: y(objetivo), y2: y(objetivo), stroke: CORES.despesa, 'stroke-dasharray': '5 5', 'stroke-width': 1.5 }));
    grafico.append(svg('text', { class: 'eixo-texto', x: largura - margem.direita, y: y(objetivo) - 6, 'text-anchor': 'end' }, ['Objetivo']));
  }
  container.replaceChildren(grafico);
}

const NIVEIS = ['#EDF1EC', '#D6EBC4', '#A8D08F', '#5E9F6E', '#1D704F'];

/**
 * Mapa de gastos no estilo do calendário do GitHub: uma coluna por semana, uma linha por dia da semana.
 * A cor de cada dia depende de quanto foi gasto em relação aos outros dias.
 * @returns {string[]} cores da escala, para a legenda "Menos ... Mais"
 */
export function mapaDeGastos(container, { dias, fim }) {
  const colunas = semanasAte(fim, 53);
  const tamanho = 12;
  const espaco = 3;
  const esquerda = 30;
  const topo = 18;
  const largura = esquerda + colunas.length * (tamanho + espaco);
  const altura = topo + 7 * (tamanho + espaco);

  // Limites dos níveis pelos quartis dos dias com gasto, para a escala se adaptar a cada pessoa.
  const valores = Object.values(dias).filter((valor) => valor > 0).sort((a, b) => a - b);
  const quartil = (p) => valores[Math.min(valores.length - 1, Math.floor(p * valores.length))] ?? 0;
  const limites = [quartil(0.25), quartil(0.5), quartil(0.75)];
  const nivel = (valor) => {
    if (!valor) return 0;
    if (valor <= limites[0]) return 1;
    if (valor <= limites[1]) return 2;
    if (valor <= limites[2]) return 3;
    return 4;
  };

  const grafico = svg('svg', { viewBox: `0 0 ${largura} ${altura}`, role: 'presentation' });
  ['Seg', 'Qua', 'Sex'].forEach((nome, indice) => {
    const linha = indice * 2 + 1;
    grafico.append(svg('text', { class: 'eixo-texto', x: 0, y: topo + linha * (tamanho + espaco) + 10 }, [nome]));
  });

  const dica = criarDica(container);
  let mesAnterior = -1;
  colunas.forEach((semana, coluna) => {
    const primeiroDia = semana[0];
    if (primeiroDia.getMonth() !== mesAnterior && primeiroDia.getDate() <= 7) {
      mesAnterior = primeiroDia.getMonth();
      grafico.append(svg('text', { class: 'eixo-texto', x: esquerda + coluna * (tamanho + espaco), y: 11 }, [MESES_CURTOS[mesAnterior]]));
    }
    semana.forEach((dia, linha) => {
      if (dia > fim) return;
      const valor = dias[paraISO(dia)] ?? 0;
      const quadrado = svg('rect', {
        class: 'dia',
        x: esquerda + coluna * (tamanho + espaco),
        y: topo + linha * (tamanho + espaco),
        width: tamanho, height: tamanho, rx: 2.5,
        fill: NIVEIS[nivel(valor)],
      });
      quadrado.addEventListener('mousemove', (evento) => {
        const data = `${dia.getDate()} ${MESES_CURTOS[dia.getMonth()]} ${dia.getFullYear()}`;
        dica.replaceChildren(el('strong', { texto: valor > 0 ? formatarMoeda(valor) : 'Sem gastos' }), el('span', { texto: data }));
        posicionarDica(dica, container, evento);
      });
      quadrado.addEventListener('mouseleave', () => { dica.hidden = true; });
      grafico.append(quadrado);
    });
  });

  container.replaceChildren(grafico, dica);
  return NIVEIS;
}
