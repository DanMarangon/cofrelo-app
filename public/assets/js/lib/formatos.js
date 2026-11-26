/**
 * Formatação no padrão brasileiro: moeda, percentuais e datas.
 * São funções puras (não mexem na página), por isso também rodam nos testes do Node (tests/js).
 */

const formatoMoeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

function capitalizar(texto) {
  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/** 24580 vira "R$ 24.580,00". */
export function formatarMoeda(valor) {
  const numero = Number(valor) || 0;
  const texto = formatoMoeda.format(Math.abs(numero)).replace(/ /g, ' ');
  return numero < 0 ? `-${texto}` : texto;
}

/** Valor com sinal conforme o tipo: receita "+ R$ 7.500,00", despesa "− R$ 286,40". */
export function formatarValorTransacao(tipo, valor) {
  const texto = formatarMoeda(Math.abs(Number(valor) || 0));
  if (tipo === 'receita') return `+ ${texto}`;
  if (tipo === 'despesa') return `− ${texto}`;
  return texto;
}

/** 53.9 vira "53,9%". Sem valor (por exemplo, sem mês anterior para comparar) mostra um traço. */
export function formatarPercentual(valor, casas = 1) {
  if (valor === null || valor === undefined || Number.isNaN(Number(valor))) return '—';
  return `${Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas })}%`;
}

/** Valores grandes do eixo dos gráficos: 7500 vira "R$ 7,5 mil". */
export function formatarMoedaCurta(valor) {
  const numero = Number(valor) || 0;
  if (Math.abs(numero) >= 1000) {
    const mil = numero / 1000;
    return `R$ ${mil.toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mil`;
  }
  return `R$ ${numero.toLocaleString('pt-BR', { maximumFractionDigits: 0 })}`;
}

export function nomeDoMes(indice) {
  return capitalizar(MESES[indice] ?? '');
}

export function mesCurto(indice) {
  return capitalizar((MESES[indice] ?? '').slice(0, 3));
}

/** "Novembro de 2026". O mês começa em 0, como no Date do JavaScript. */
export function rotuloMes(ano, mesIndice) {
  return `${nomeDoMes(mesIndice)} de ${ano}`;
}

function lerISO(iso) {
  const partes = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso ?? '');
  return partes ? new Date(Number(partes[1]), Number(partes[2]) - 1, Number(partes[3])) : null;
}

/** Data da lista de transações: "Hoje", "Ontem" ou "18 nov". */
export function formatarData(iso, hoje = new Date()) {
  const data = lerISO(iso);
  if (!data) return '';
  const referencia = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate());
  const dias = Math.round((referencia - data) / 86400000);
  if (dias === 0) return 'Hoje';
  if (dias === 1) return 'Ontem';
  const texto = `${data.getDate()} ${MESES[data.getMonth()].slice(0, 3)}`;
  return data.getFullYear() === referencia.getFullYear() ? texto : `${texto} ${data.getFullYear()}`;
}

/** 2026-11-18 vira "18/11/2026". */
export function formatarDataCompleta(iso) {
  const data = lerISO(iso);
  if (!data) return '';
  const dia = String(data.getDate()).padStart(2, '0');
  const mes = String(data.getMonth() + 1).padStart(2, '0');
  return `${dia}/${mes}/${data.getFullYear()}`;
}

/** Lê "1.234,56", "R$ 1.234,56" ou "1234.56" e devolve o número. Texto vazio vira NaN. */
export function lerValor(texto) {
  if (typeof texto === 'number') return texto;
  const limpo = String(texto ?? '').replace(/[^\d,.-]/g, '');
  if (limpo === '') return Number.NaN;
  const normalizado = limpo.includes(',') ? limpo.replace(/\./g, '').replace(',', '.') : limpo;
  return Number(normalizado);
}

/**
 * Máscara do campo de valor: trata o que foi digitado como centavos.
 * Digitar 2, 8, 6, 4, 0 mostra 0,02 → 0,28 → 2,86 → 28,64 → 286,40.
 */
export function mascararMoeda(digitado) {
  const digitos = String(digitado ?? '').replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 12);
  const centavos = Number(digitos || '0');
  return (centavos / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
