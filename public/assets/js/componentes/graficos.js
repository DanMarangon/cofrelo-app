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
  const area = areaDoGrafico(largura, altura);
  const escala = escalaVertical(series, area);
  const grafico = svg('svg', { viewBox: `0 0 ${largura} ${altura}`, role: 'presentation' });
  const dica = criarDica(container);

  desenharGrade(grafico, escala, area, largura);
  const larguraDoGrupo = area.largura / rotulos.length;
  const todasAsBarras = [];
  rotulos.forEach((rotulo, indice) => {
    const xDoGrupo = area.esquerda + larguraDoGrupo * indice;
    const barras = desenharBarrasDoMes(grafico, series, indice, xDoGrupo, larguraDoGrupo, escala, area);
    todasAsBarras.push(...barras);
    grafico.append(svg('text', {
      class: `eixo-texto${indice === destaque ? ' destaque' : ''}`,
      x: xDoGrupo + larguraDoGrupo / 2,
      y: altura - 8,
      'text-anchor': 'middle',
    }, [rotulo]));
    grafico.append(alvoDoMouse({ container, dica, rotulo, series, indice, barras, todasAsBarras, x: xDoGrupo, largura: larguraDoGrupo, area }));
  });

  container.replaceChildren(grafico, dica);
}

/** Espaço do gráfico sem as margens (a da esquerda guarda os valores do eixo). */
function areaDoGrafico(largura, altura) {
  const margem = { topo: 10, direita: 6, base: 30, esquerda: 66 };
  return {
    topo: margem.topo,
    esquerda: margem.esquerda,
    direita: margem.direita,
    largura: largura - margem.esquerda - margem.direita,
    altura: altura - margem.topo - margem.base,
  };
}

/** Converte um valor em reais na altura (y) do desenho; o eixo vai de 0 até um número "redondo". */
function escalaVertical(series, area) {
  const maior = Math.max(0, ...series.flatMap((serie) => serie.valores));
  const passo = passoDoEixo(maior);
  const topo = passo * Math.max(4, Math.ceil(maior / passo));
  return { passo, topo, y: (valor) => area.topo + area.altura - (valor / topo) * area.altura };
}

function desenharGrade(grafico, escala, area, largura) {
  // [DW 10.a] Laço de repetição: uma linha de grade para cada marca do eixo.
  for (let marca = 0; marca <= escala.topo; marca += escala.passo) {
    const y = escala.y(marca);
    grafico.append(svg('line', { class: 'grade-linha', x1: area.esquerda, x2: largura - area.direita, y1: y, y2: y }));
    grafico.append(svg('text', { class: 'eixo-texto', x: area.esquerda - 10, y: y + 4, 'text-anchor': 'end' }, [formatarMoedaCurta(marca)]));
  }
}

/** Uma barra por série (receitas e despesas) lado a lado, centralizadas no espaço do mês. */
function desenharBarrasDoMes(grafico, series, indice, xDoGrupo, larguraDoGrupo, escala, area) {
  const larguraBarra = Math.min(26, larguraDoGrupo * 0.26);
  const espaco = Math.min(8, larguraDoGrupo * 0.08);
  const larguraDoPar = larguraBarra * series.length + espaco * (series.length - 1);
  const inicio = xDoGrupo + (larguraDoGrupo - larguraDoPar) / 2;
  const base = area.topo + area.altura;
  return series.map((serie, s) => {
    const y = escala.y(serie.valores[indice] ?? 0);
    const barra = svg('path', {
      class: 'barra',
      d: caminhoBarra(inicio + s * (larguraBarra + espaco), y, larguraBarra, base - y),
      fill: serie.cor,
    });
    grafico.append(barra);
    return barra;
  });
}

/** Área invisível do tamanho do mês inteiro: mais fácil de acertar com o mouse do que a barra fina. */
function alvoDoMouse({ container, dica, rotulo, series, indice, barras, todasAsBarras, x, largura, area }) {
  const alvo = svg('rect', { x, y: area.topo, width: largura, height: area.altura, fill: 'transparent' });
  // [DW 10.g] Interação: ao passar o mouse, destaca o mês e mostra os valores.
  alvo.addEventListener('mousemove', (evento) => {
    container.classList.add('tem-foco');
    todasAsBarras.forEach((b) => b.classList.remove('em-foco'));
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
  return alvo;
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
  const colunas = semanasAte(fim, 53); // uma coluna por semana, uma linha por dia da semana
  const grade = { tamanho: 12, espaco: 3, esquerda: 30, topo: 18 };
  const passo = grade.tamanho + grade.espaco;
  const largura = grade.esquerda + colunas.length * passo;
  const altura = grade.topo + 7 * passo;
  const nivelDoDia = calcularNiveis(dias);
  const grafico = svg('svg', { viewBox: `0 0 ${largura} ${altura}`, role: 'presentation' });
  const dica = criarDica(container);

  ['Seg', 'Qua', 'Sex'].forEach((nome, indice) => {
    const linha = indice * 2 + 1;
    grafico.append(svg('text', { class: 'eixo-texto', x: 0, y: grade.topo + linha * passo + 10 }, [nome]));
  });

  let mesAnterior = -1;
  colunas.forEach((semana, coluna) => {
    // O nome do mês aparece em cima da primeira semana dele.
    const primeiroDia = semana[0];
    if (primeiroDia.getMonth() !== mesAnterior && primeiroDia.getDate() <= 7) {
      mesAnterior = primeiroDia.getMonth();
      grafico.append(svg('text', { class: 'eixo-texto', x: grade.esquerda + coluna * passo, y: 11 }, [MESES_CURTOS[mesAnterior]]));
    }
    semana.forEach((dia, linha) => {
      if (dia > fim) return; // dias depois de hoje ficam em branco
      const valor = dias[paraISO(dia)] ?? 0;
      const x = grade.esquerda + coluna * passo;
      const y = grade.topo + linha * passo;
      grafico.append(quadradoDoDia({ dia, valor, x, y, tamanho: grade.tamanho, cor: NIVEIS[nivelDoDia(valor)], dica, container }));
    });
  });

  container.replaceChildren(grafico, dica);
  return NIVEIS;
}

/**
 * Tom de verde de cada dia (0 = sem gasto, 4 = gastou muito). Os limites vêm dos quartis dos dias com gasto,
 * então a escala se adapta a quem gasta pouco e a quem gasta muito.
 */
function calcularNiveis(dias) {
  const valores = Object.values(dias).filter((valor) => valor > 0).sort((a, b) => a - b);
  const quartil = (p) => valores[Math.min(valores.length - 1, Math.floor(p * valores.length))] ?? 0;
  const limites = [quartil(0.25), quartil(0.5), quartil(0.75)];
  return (valor) => {
    if (!valor) return 0;
    if (valor <= limites[0]) return 1;
    if (valor <= limites[1]) return 2;
    if (valor <= limites[2]) return 3;
    return 4;
  };
}

function quadradoDoDia({ dia, valor, x, y, tamanho, cor, dica, container }) {
  const quadrado = svg('rect', { class: 'dia', x, y, width: tamanho, height: tamanho, rx: 2.5, fill: cor });
  quadrado.addEventListener('mousemove', (evento) => {
    const data = `${dia.getDate()} ${MESES_CURTOS[dia.getMonth()]} ${dia.getFullYear()}`;
    dica.replaceChildren(el('strong', { texto: valor > 0 ? formatarMoeda(valor) : 'Sem gastos' }), el('span', { texto: data }));
    posicionarDica(dica, container, evento);
  });
  quadrado.addEventListener('mouseleave', () => { dica.hidden = true; });
  return quadrado;
}
