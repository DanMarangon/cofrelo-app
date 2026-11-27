/**
 * Funções para criar e mexer em elementos da página.
 * Todo texto entra com textContent, nunca como HTML, para que nada digitado vire código na página (XSS).
 */

const SVG = 'http://www.w3.org/2000/svg';

/** Cria um elemento: el('td', { classe: 'direita', texto: 'R$ 10,00' }, [filhos]) */
export function el(tag, opcoes = {}, filhos = []) {
  const elemento = document.createElement(tag);
  aplicar(elemento, opcoes);
  adicionarFilhos(elemento, filhos);
  return elemento;
}

/** Mesma ideia para elementos SVG dos gráficos. */
export function svg(tag, atributos = {}, filhos = []) {
  const elemento = document.createElementNS(SVG, tag);
  for (const [nome, valor] of Object.entries(atributos)) {
    if (valor !== null && valor !== undefined) elemento.setAttribute(nome, String(valor));
  }
  adicionarFilhos(elemento, filhos);
  return elemento;
}

export function icone(nome, classe = 'icone') {
  const uso = svg('use', { href: `#i-${nome}` });
  return svg('svg', { class: classe, 'aria-hidden': 'true' }, [uso]);
}

function aplicar(elemento, { classe, texto, atributos, dados, aoClicar } = {}) {
  if (classe) elemento.className = classe;
  if (texto !== undefined && texto !== null) elemento.textContent = String(texto);
  for (const [nome, valor] of Object.entries(atributos ?? {})) {
    if (valor === false || valor === null || valor === undefined) continue;
    elemento.setAttribute(nome, valor === true ? '' : String(valor));
  }
  for (const [chave, valor] of Object.entries(dados ?? {})) elemento.dataset[chave] = String(valor);
  if (aoClicar) elemento.addEventListener('click', aoClicar);
}

function adicionarFilhos(elemento, filhos) {
  for (const filho of [].concat(filhos)) {
    if (filho === null || filho === undefined || filho === false) continue;
    elemento.append(filho instanceof Node ? filho : String(filho));
  }
}

export const $ = (seletor, raiz = document) => raiz.querySelector(seletor);
export const $$ = (seletor, raiz = document) => [...raiz.querySelectorAll(seletor)];

/** Troca o texto de um elemento pelo id e tira o efeito de "carregando". */
export function definirTexto(id, texto) {
  const elemento = document.getElementById(id);
  if (!elemento) return;
  elemento.textContent = texto;
  elemento.classList.remove('esqueleto');
}

/** Espera o usuário parar de digitar antes de executar (evita uma busca por tecla). */
export function adiar(funcao, espera = 300) {
  let temporizador;
  return (...argumentos) => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => funcao(...argumentos), espera);
  };
}

/** Variação com seta: "↑ 8,1%". Para despesas, subir é ruim (vermelho). */
export function preencherVariacao(id, valor, { despesa = false } = {}) {
  const elemento = document.getElementById(id);
  if (!elemento) return;
  elemento.replaceChildren();
  elemento.className = 'variacao';
  if (valor === null || valor === undefined) {
    elemento.textContent = 'sem comparação';
    return;
  }
  const subiu = valor > 0;
  const ruim = despesa ? subiu : !subiu && valor !== 0;
  elemento.classList.add(ruim ? 'ruim' : 'sobe');
  elemento.append(subiu ? '↑ ' : valor < 0 ? '↓ ' : '');
  elemento.append(`${Math.abs(valor).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`);
}
