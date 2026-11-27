import { mascararMoeda } from '../lib/formatos.js';

/** Mostra a mensagem de erro embaixo de cada campo e coloca o foco no primeiro campo com problema. */
export function mostrarErros(formulario, campos = {}) {
  limparErros(formulario);
  let primeiro = null;
  for (const [campo, mensagem] of Object.entries(campos)) {
    const bloco = formulario.querySelector(`[data-campo="${campo}"]`);
    const alvo = formulario.querySelector(`[data-erro="${campo}"]`);
    bloco?.classList.add('com-erro');
    if (alvo) alvo.textContent = mensagem;
    primeiro ??= bloco?.querySelector('input, select, textarea');
  }
  primeiro?.focus();
}

export function limparErros(formulario) {
  formulario.querySelectorAll('.com-erro').forEach((bloco) => bloco.classList.remove('com-erro'));
  formulario.querySelectorAll('[data-erro]').forEach((alvo) => { alvo.textContent = ''; });
}

/** Campo de valor com máscara: os dígitos digitados entram como centavos (2 8 6 4 0 vira 286,40). */
export function ligarMascaraMoeda(campo) {
  // [DW 10.b] Evento "input": dispara a cada tecla digitada.
  campo.addEventListener('input', () => {
    campo.value = mascararMoeda(campo.value);
  });
  campo.addEventListener('focus', () => campo.select());
}

/** Preenche um <select> com uma lista de objetos. */
export function preencherSelect(select, itens, { valor = null, textoVazio = null, rotulo = (item) => item.nome } = {}) {
  select.replaceChildren();
  if (textoVazio !== null) select.append(new Option(textoVazio, ''));
  for (const item of itens) select.append(new Option(rotulo(item), item.id));
  if (valor !== null && valor !== undefined) select.value = String(valor);
}

/** Lê um campo de dinheiro digitado livremente ("1.234,56" ou "1234.56"). */
export function lerDinheiro(texto) {
  const limpo = String(texto ?? '').trim();
  if (limpo === '') return null;
  const normalizado = limpo.includes(',') ? limpo.replace(/\./g, '').replace(',', '.') : limpo;
  const numero = Number(normalizado.replace(/[^\d.-]/g, ''));
  return Number.isFinite(numero) ? numero : null;
}

/** Número no formato brasileiro para colocar de volta num campo de texto: 1234.5 vira "1.234,50". */
export function paraCampo(numero) {
  return Number(numero ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
