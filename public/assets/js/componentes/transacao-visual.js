/** Peças visuais de uma transação, usadas na Visão geral e na página de Transações. */

import { el, icone } from '../nucleo/dom.js';
import { formatarValorTransacao } from '../lib/formatos.js';

export function iconeDaTransacao(transacao) {
  // [DW 10.a] switch: cada tipo de transação tem um ícone e uma cor.
  switch (transacao.tipo) {
    case 'receita':
      return el('span', { classe: 'icone-caixa' }, [icone('entrada', 'icone icone-sm')]);
    case 'despesa':
      return el('span', { classe: 'icone-caixa saida' }, [icone('despesa', 'icone icone-sm')]);
    default:
      return el('span', { classe: 'icone-caixa neutro' }, [icone('transacoes', 'icone icone-sm')]);
  }
}

export function celulaDescricao(transacao) {
  return el('div', { classe: 'descricao-celula' }, [
    iconeDaTransacao(transacao),
    el('div', {}, [
      el('strong', { texto: transacao.descricao }),
      el('span', { texto: transacao.detalhe }),
    ]),
  ]);
}

export function celulaValor(transacao) {
  return el('td', {
    classe: `direita ${transacao.tipo === 'receita' ? 'valor-receita' : 'valor-despesa'}`,
    texto: formatarValorTransacao(transacao.tipo, transacao.valor),
  });
}

export function etiquetaStatus(transacao) {
  const classe = transacao.status === 'pendente' ? 'etiqueta pendente' : 'etiqueta';
  return el('span', { classe, texto: transacao.status_rotulo });
}

/** Nome da categoria com o pontinho colorido. Transferências mostram "Transferência". */
export function categoriaComCor(transacao) {
  if (!transacao.categoria) return el('span', { classe: 'texto-2', texto: 'Transferência' });
  const ponto = el('i', { classe: 'ponto redondo' });
  ponto.style.background = transacao.categoria.cor;
  return el('span', { classe: 'categoria-ponto' }, [ponto, el('span', { texto: transacao.categoria.nome })]);
}

export function nomeDaConta(transacao) {
  return transacao.conta_destino ? `${transacao.conta.nome} → ${transacao.conta_destino.nome}` : transacao.conta.nome;
}
