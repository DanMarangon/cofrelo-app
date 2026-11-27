/**
 * Transações: filtros (busca, categoria, conta e período no calendário), abas por tipo,
 * ordenação clicando no cabeçalho, paginação, edição, exclusão, exportação em CSV
 * e a aba Assinaturas (que substitui a antiga tela de assinaturas).
 */

import { api, carregarCategorias, carregarContas, ErroApi } from '../nucleo/api.js';
import { el, icone, definirTexto, adiar } from '../nucleo/dom.js';
import { formatarMoeda, formatarData } from '../lib/formatos.js';
import { periodoDoAtalho, paraISO, deISO } from '../lib/datas.js';
import { SeletorPeriodo } from '../componentes/calendario.js';
import { abrirModalTransacao } from '../componentes/modal-transacao.js';
import { abrirModal, fecharModal, confirmar } from '../componentes/modal.js';
import { mostrarErros, preencherSelect, lerDinheiro, paraCampo } from '../componentes/formulario.js';
import { avisar } from '../componentes/avisos.js';
import {
  celulaDescricao, celulaValor, categoriaComCor, etiquetaStatus, nomeDaConta,
} from '../componentes/transacao-visual.js';

const POR_PAGINA = 8;
const doMes = periodoDoAtalho('este-mes');
const parametrosDaUrl = new URLSearchParams(window.location.search);

// [DW 10.d] Objeto com o estado dos filtros da tela.
const filtro = {
  inicio: doMes.inicio,
  fim: doMes.fim,
  atalho: 'este-mes',
  tipo: '',
  categoria_id: '',
  conta_id: parametrosDaUrl.get('conta') ?? '',
  busca: '',
  ordenar: 'data',
  direcao: 'desc',
  pagina: 1,
};

const seletor = new SeletorPeriodo(document.getElementById('filtro-periodo'), {
  inicio: filtro.inicio,
  fim: filtro.fim,
  atalho: filtro.atalho,
  aoMudar: ({ inicio, fim, atalho }) => {
    Object.assign(filtro, { inicio, fim, atalho, pagina: 1 });
    carregar();
  },
});

async function prepararFiltros() {
  const [categorias, contas] = await Promise.all([carregarCategorias(), carregarContas()]);
  preencherSelect(document.getElementById('filtro-categoria'), categorias, {
    textoVazio: 'Todas as categorias',
    rotulo: (categoria) => `${categoria.nome}${categoria.tipo === 'receita' ? ' (receita)' : ''}`,
  });
  preencherSelect(document.getElementById('filtro-conta'), contas, { textoVazio: 'Todas as contas', valor: filtro.conta_id });
  const dias = document.getElementById('ma-dia');
  for (let dia = 1; dia <= 28; dia++) dias.append(new Option(`Todo dia ${dia}`, String(dia)));
}

function parametros() {
  return {
    inicio: paraISO(filtro.inicio),
    fim: paraISO(filtro.fim),
    tipo: filtro.tipo,
    categoria_id: filtro.categoria_id,
    conta_id: filtro.conta_id,
    busca: filtro.busca,
    ordenar: filtro.ordenar,
    direcao: filtro.direcao,
  };
}

async function carregar() {
  const assinaturas = filtro.tipo === 'assinatura';
  document.getElementById('painel-tabela').hidden = assinaturas;
  document.getElementById('painel-assinaturas').hidden = !assinaturas;
  if (assinaturas) {
    carregarAssinaturas();
  }
  try {
    const dados = await api.get('/api/transacoes', { ...parametros(), pagina: filtro.pagina, por_pagina: POR_PAGINA });
    desenharTotais(dados.totais);
    if (!assinaturas) {
      desenharTabela(dados);
      desenharPaginacao(dados);
    }
  } catch (erro) {
    avisar(erro.message, 'erro');
  }
}

function desenharTotais(totais) {
  if (!totais) return;
  const doMesInteiro = filtro.atalho === 'este-mes' || filtro.atalho === 'mes-passado';
  const sufixo = doMesInteiro ? 'do mês' : 'do período';
  definirTexto('rotulo-receitas', `Receitas ${sufixo}`);
  definirTexto('rotulo-despesas', `Despesas ${sufixo}`);
  definirTexto('rotulo-balanco', `Balanço ${sufixo}`);
  definirTexto('total-receitas', formatarMoeda(totais.receitas));
  definirTexto('total-despesas', formatarMoeda(totais.despesas));
  definirTexto('total-balanco', formatarMoeda(totais.balanco));
  definirTexto('qtd-receitas', `${totais.qtd_receitas} ${totais.qtd_receitas === 1 ? 'recebimento' : 'recebimentos'}`);
  definirTexto('qtd-despesas', `${totais.qtd_despesas} ${totais.qtd_despesas === 1 ? 'pagamento' : 'pagamentos'}`);
  definirTexto('frase-balanco', totais.balanco >= 0 ? 'Você está no positivo' : 'Atenção: os gastos passaram das receitas');
}

function desenharTabela(dados) {
  const corpo = document.getElementById('linhas-transacoes');
  const quantidade = dados.total;
  document.getElementById('contador-transacoes').textContent = `${quantidade} ${quantidade === 1 ? 'transação' : 'transações'} neste período`;

  if (dados.itens.length === 0) {
    corpo.replaceChildren(el('tr', {}, [el('td', { classe: 'vazio', atributos: { colspan: 7 } }, [
      el('strong', { texto: 'Nada por aqui' }),
      filtro.busca || filtro.categoria_id || filtro.conta_id ? 'Nenhuma transação com esses filtros. Tente limpar a busca.' : 'Nenhuma transação neste período.',
    ])]));
    return;
  }

  // [DW 10.e] Tabela: uma linha por transação, com botões de editar e excluir.
  corpo.replaceChildren(...dados.itens.map((transacao) => el('tr', {}, [
    el('td', {}, [celulaDescricao(transacao)]),
    el('td', {}, [categoriaComCor(transacao)]),
    el('td', { classe: 'texto-2', texto: nomeDaConta(transacao) }),
    el('td', { classe: 'texto-2', texto: formatarData(transacao.data) }),
    el('td', {}, [etiquetaStatus(transacao)]),
    celulaValor(transacao),
    el('td', {}, [el('div', { classe: 'acoes-linha' }, [
      el('button', {
        classe: 'botao-icone',
        atributos: { type: 'button', 'aria-label': `Editar ${transacao.descricao}`, title: 'Editar' },
        aoClicar: () => abrirModalTransacao({ transacao }),
      }, [icone('editar')]),
      el('button', {
        classe: 'botao-icone',
        atributos: { type: 'button', 'aria-label': `Excluir ${transacao.descricao}`, title: 'Excluir' },
        aoClicar: () => excluir(transacao),
      }, [icone('lixeira')]),
    ])]),
  ])));
}

function desenharPaginacao({ total, pagina, paginas }) {
  const primeiro = total === 0 ? 0 : (pagina - 1) * POR_PAGINA + 1;
  const ultimo = Math.min(total, pagina * POR_PAGINA);
  document.getElementById('texto-paginacao').textContent = `Mostrando ${primeiro}–${ultimo} de ${total} ${total === 1 ? 'transação' : 'transações'}`;

  const irPara = (numero) => () => { filtro.pagina = numero; carregar(); };
  const botoes = [el('button', { atributos: { type: 'button', disabled: pagina <= 1 }, aoClicar: irPara(pagina - 1) }, [icone('esquerda', 'icone icone-sm'), ' Anterior'])];
  const inicio = Math.max(1, Math.min(pagina - 2, paginas - 4));
  for (let numero = inicio; numero <= Math.min(paginas, inicio + 4); numero++) {
    botoes.push(el('button', {
      classe: numero === pagina ? 'atual' : '',
      texto: numero,
      atributos: { type: 'button', 'aria-current': numero === pagina ? 'page' : false, 'aria-label': `Página ${numero}` },
      aoClicar: irPara(numero),
    }));
  }
  botoes.push(el('button', { atributos: { type: 'button', disabled: pagina >= paginas, 'aria-label': 'Próxima página' }, aoClicar: irPara(pagina + 1) }, [icone('direita', 'icone icone-sm')]));
  document.getElementById('botoes-paginacao').replaceChildren(...botoes);
}

async function excluir(transacao) {
  const certeza = await confirmar({
    titulo: 'Excluir transação?',
    mensagem: `"${transacao.descricao}" de ${formatarMoeda(transacao.valor)} será apagada. Não dá para desfazer.`,
  });
  if (!certeza) return;
  try {
    await api.excluir(`/api/transacoes/${transacao.id}`);
    avisar('Transação excluída.');
    document.dispatchEvent(new CustomEvent('cofrelo:dados-alterados', { detail: { origem: 'transacao' } }));
  } catch (erro) {
    avisar(erro.message, 'erro');
  }
}

// ---------------------------------------------------------------------------------------------
// Aba Assinaturas
// ---------------------------------------------------------------------------------------------

let assinaturaEditando = null;

async function carregarAssinaturas() {
  const lista = document.getElementById('lista-assinaturas');
  try {
    const { assinaturas, total_mensal: totalMensal } = await api.get('/api/assinaturas');
    const ativas = assinaturas.filter((assinatura) => assinatura.ativa).length;
    document.getElementById('resumo-assinaturas').textContent = ativas > 0
      ? `${ativas} ${ativas === 1 ? 'assinatura ativa soma' : 'assinaturas ativas somam'} ${formatarMoeda(totalMensal)} por mês.`
      : 'Nenhuma assinatura ativa. Crie uma pela aba Assinatura do botão "Nova transação".';
    document.getElementById('contador-transacoes').textContent = `${assinaturas.length} ${assinaturas.length === 1 ? 'assinatura' : 'assinaturas'}`;

    lista.replaceChildren(...assinaturas.map((assinatura) => {
      const quando = assinatura.dias_para_cobranca === 0 ? 'hoje' : assinatura.dias_para_cobranca === 1 ? 'amanhã' : `em ${assinatura.dias_para_cobranca} dias`;
      return el('li', {}, [
        el('span', { classe: 'icone-caixa' }, [icone('assinatura')]),
        el('div', { classe: 'lista-texto' }, [
          el('strong', { texto: assinatura.descricao }),
          el('span', {
            texto: assinatura.ativa
              ? `Todo dia ${assinatura.dia_cobranca} · ${assinatura.conta.nome} · próxima cobrança ${quando} (${formatarData(assinatura.proxima_cobranca)})`
              : `Pausada · cobrava todo dia ${assinatura.dia_cobranca}`,
          }),
        ]),
        el('span', { classe: assinatura.ativa ? 'etiqueta assinatura' : 'etiqueta neutra', texto: assinatura.ativa ? 'Ativa' : 'Pausada' }),
        el('strong', { texto: formatarMoeda(assinatura.valor) }),
        el('div', { classe: 'acoes-linha' }, [
          el('button', { classe: 'botao-icone', atributos: { type: 'button', 'aria-label': `Editar ${assinatura.descricao}` }, aoClicar: () => editarAssinatura(assinatura) }, [icone('editar')]),
          el('button', { classe: 'botao-icone', atributos: { type: 'button', 'aria-label': `Excluir ${assinatura.descricao}` }, aoClicar: () => excluirAssinatura(assinatura) }, [icone('lixeira')]),
        ]),
      ]);
    }));
  } catch (erro) {
    avisar(erro.message, 'erro');
  }
}

function editarAssinatura(assinatura) {
  assinaturaEditando = assinatura;
  document.getElementById('ma-descricao').value = assinatura.descricao;
  document.getElementById('ma-valor').value = paraCampo(assinatura.valor);
  document.getElementById('ma-dia').value = String(assinatura.dia_cobranca);
  document.getElementById('ma-ativa').checked = assinatura.ativa;
  abrirModal(document.getElementById('modal-assinatura'));
}

document.getElementById('formulario-assinatura').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const formulario = evento.currentTarget;
  try {
    await api.put(`/api/assinaturas/${assinaturaEditando.id}`, {
      descricao: document.getElementById('ma-descricao').value,
      valor: lerDinheiro(document.getElementById('ma-valor').value),
      dia_cobranca: Number(document.getElementById('ma-dia').value),
      aviso_dias: assinaturaEditando.aviso_dias,
      ativa: document.getElementById('ma-ativa').checked,
    });
    fecharModal(document.getElementById('modal-assinatura'));
    avisar('Assinatura atualizada. Vale para as próximas cobranças.');
    carregarAssinaturas();
  } catch (erro) {
    if (erro instanceof ErroApi && erro.status === 422) mostrarErros(formulario, erro.campos);
    else avisar(erro.message, 'erro');
  }
});

async function excluirAssinatura(assinatura) {
  const certeza = await confirmar({
    titulo: 'Excluir assinatura?',
    mensagem: `O Cofrelo vai parar de lançar "${assinatura.descricao}". As cobranças que já aconteceram continuam no histórico.`,
  });
  if (!certeza) return;
  try {
    await api.excluir(`/api/assinaturas/${assinatura.id}`);
    avisar('Assinatura excluída.');
    carregarAssinaturas();
  } catch (erro) {
    avisar(erro.message, 'erro');
  }
}

// ---------------------------------------------------------------------------------------------
// Eventos dos filtros
// ---------------------------------------------------------------------------------------------

// [DW 10.b] Evento "input" com espera: a busca só roda quando a pessoa para de digitar.
document.getElementById('filtro-busca').addEventListener('input', adiar((evento) => {
  filtro.busca = evento.target.value.trim();
  filtro.pagina = 1;
  carregar();
}, 350));

document.getElementById('filtro-categoria').addEventListener('change', (evento) => {
  filtro.categoria_id = evento.target.value;
  filtro.pagina = 1;
  carregar();
});

document.getElementById('filtro-conta').addEventListener('change', (evento) => {
  filtro.conta_id = evento.target.value;
  filtro.pagina = 1;
  carregar();
});

document.querySelectorAll('#abas-tipo [data-tipo]').forEach((aba) => {
  aba.addEventListener('click', () => {
    document.querySelectorAll('#abas-tipo [data-tipo]').forEach((outra) => outra.setAttribute('aria-selected', String(outra === aba)));
    filtro.tipo = aba.dataset.tipo;
    filtro.pagina = 1;
    carregar();
  });
});

// Ordenação: clicar no cabeçalho ordena; clicar de novo inverte.
document.querySelectorAll('#tabela-transacoes th[data-ordenar]').forEach((cabecalho) => {
  cabecalho.addEventListener('click', () => {
    const coluna = cabecalho.dataset.ordenar;
    filtro.direcao = filtro.ordenar === coluna && filtro.direcao === 'desc' ? 'asc' : 'desc';
    filtro.ordenar = coluna;
    document.querySelectorAll('#tabela-transacoes th[data-ordenar]').forEach((outro) => {
      outro.setAttribute('aria-sort', outro === cabecalho ? (filtro.direcao === 'asc' ? 'ascending' : 'descending') : 'none');
    });
    carregar();
  });
});

document.getElementById('exportar-csv').addEventListener('click', () => {
  api.baixar('/api/transacoes/exportar', parametros());
  avisar('Gerando o arquivo CSV com as transações do filtro atual.');
});

document.addEventListener('cofrelo:dados-alterados', carregar);

if (parametrosDaUrl.get('inicio') && parametrosDaUrl.get('fim')) {
  const inicio = deISO(parametrosDaUrl.get('inicio'));
  const fim = deISO(parametrosDaUrl.get('fim'));
  if (inicio && fim) {
    Object.assign(filtro, { inicio, fim, atalho: null });
    Object.assign(seletor, { inicio, fim, atalho: null });
    seletor.atualizarBotao();
  }
}

prepararFiltros().then(carregar);
