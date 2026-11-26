/**
 * Funções de datas usadas pelo calendário de período e pelo mapa de gastos dos relatórios.
 * Trabalham sempre com datas locais à meia-noite, sem horário.
 */

import { nomeDoMes } from './formatos.js';

const MESES_CURTOS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

export function hoje() {
  const agora = new Date();
  return new Date(agora.getFullYear(), agora.getMonth(), agora.getDate());
}

export function paraISO(data) {
  const mes = String(data.getMonth() + 1).padStart(2, '0');
  const dia = String(data.getDate()).padStart(2, '0');
  return `${data.getFullYear()}-${mes}-${dia}`;
}

export function deISO(texto) {
  const partes = /^(\d{4})-(\d{2})-(\d{2})$/.exec(texto ?? '');
  return partes ? new Date(Number(partes[1]), Number(partes[2]) - 1, Number(partes[3])) : null;
}

export function inicioDoMes(data) {
  return new Date(data.getFullYear(), data.getMonth(), 1);
}

export function fimDoMes(data) {
  return new Date(data.getFullYear(), data.getMonth() + 1, 0);
}

export function adicionarDias(data, dias) {
  const resultado = new Date(data);
  resultado.setDate(resultado.getDate() + dias);
  return resultado;
}

/** Soma meses sem "pular" o mês: 31/01 + 1 mês vira 28/02 (ou 29/02). */
export function adicionarMeses(data, meses) {
  const resultado = new Date(data.getFullYear(), data.getMonth() + meses, 1);
  resultado.setDate(Math.min(data.getDate(), fimDoMes(resultado).getDate()));
  return resultado;
}

export function mesmoDia(a, b) {
  return Boolean(a && b) && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

export function antesDe(a, b) {
  return a.getTime() < b.getTime();
}

/** Atalhos do calendário de período. */
export const ATALHOS = [
  { id: 'este-mes', rotulo: 'Este mês' },
  { id: 'mes-passado', rotulo: 'Mês passado' },
  { id: 'ultimos-30', rotulo: 'Últimos 30 dias' },
  { id: 'ultimos-3-meses', rotulo: 'Últimos 3 meses' },
  { id: 'ultimos-6-meses', rotulo: 'Últimos 6 meses' },
  { id: 'este-ano', rotulo: 'Este ano' },
  { id: 'ultimos-12-meses', rotulo: 'Últimos 12 meses' },
];

export function periodoDoAtalho(id, referencia = hoje()) {
  const mesAtual = inicioDoMes(referencia);
  switch (id) {
    case 'este-mes':
      return { inicio: mesAtual, fim: fimDoMes(referencia) };
    case 'mes-passado': {
      const anterior = adicionarMeses(mesAtual, -1);
      return { inicio: anterior, fim: fimDoMes(anterior) };
    }
    case 'ultimos-30':
      return { inicio: adicionarDias(referencia, -29), fim: referencia };
    case 'ultimos-3-meses':
      return { inicio: adicionarMeses(mesAtual, -2), fim: fimDoMes(referencia) };
    case 'ultimos-6-meses':
      return { inicio: adicionarMeses(mesAtual, -5), fim: fimDoMes(referencia) };
    case 'este-ano':
      return { inicio: new Date(referencia.getFullYear(), 0, 1), fim: new Date(referencia.getFullYear(), 11, 31) };
    case 'ultimos-12-meses':
      return { inicio: adicionarMeses(mesAtual, -11), fim: fimDoMes(referencia) };
    default:
      return null;
  }
}

/** Texto do botão do calendário: "Novembro de 2026", "1 jun – 30 nov 2026" ou com dois anos. */
export function rotuloPeriodo(inicio, fim) {
  const mesInteiro = inicio.getDate() === 1 && mesmoDia(fim, fimDoMes(inicio));
  if (mesInteiro) return `${nomeDoMes(inicio.getMonth())} de ${inicio.getFullYear()}`;
  const curto = (d) => `${d.getDate()} ${MESES_CURTOS[d.getMonth()]}`;
  if (inicio.getFullYear() === fim.getFullYear()) {
    return `${curto(inicio)} – ${curto(fim)} ${fim.getFullYear()}`;
  }
  return `${curto(inicio)} ${inicio.getFullYear()} – ${curto(fim)} ${fim.getFullYear()}`;
}

/**
 * Semanas de um mês para desenhar o calendário: começa no domingo e completa as pontas
 * com dias dos meses vizinhos, sempre em 6 linhas para o calendário não mudar de altura.
 */
export function semanasDoMes(ano, mesIndice) {
  const primeiro = new Date(ano, mesIndice, 1);
  const inicio = adicionarDias(primeiro, -primeiro.getDay());
  const semanas = [];
  for (let s = 0; s < 6; s++) {
    const dias = [];
    for (let d = 0; d < 7; d++) dias.push(adicionarDias(inicio, s * 7 + d));
    semanas.push(dias);
  }
  return semanas;
}

/**
 * Grade do mapa de gastos no estilo do calendário do GitHub: cada coluna é uma semana
 * (domingo a sábado) e a última coluna contém a data final.
 */
export function semanasAte(fim, quantidade = 53) {
  const ultimoSabado = adicionarDias(fim, 6 - fim.getDay());
  const primeiroDomingo = adicionarDias(ultimoSabado, -(quantidade * 7 - 1));
  const colunas = [];
  for (let s = 0; s < quantidade; s++) {
    const dias = [];
    for (let d = 0; d < 7; d++) dias.push(adicionarDias(primeiroDomingo, s * 7 + d));
    colunas.push(dias);
  }
  return colunas;
}
