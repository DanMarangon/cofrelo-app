/**
 * Metas e objetivos: cartões das metas, criar/editar/excluir, guardar dinheiro e o simulador.
 * O simulador é o formulário com campos calculados em JavaScript pedido no projeto (item VI).
 */

import { api, ErroApi } from '../nucleo/api.js';
import { el, icone, definirTexto, adiar } from '../nucleo/dom.js';
import { formatarMoeda, formatarPercentual } from '../lib/formatos.js';
import { simularMeta, mesesParaObjetivo, aporteNecessario } from '../lib/calculos.js';
import { paraISO, hoje, adicionarMeses, deISO } from '../lib/datas.js';
import { graficoLinha } from '../componentes/graficos.js';
import { abrirModal, fecharModal, confirmar } from '../componentes/modal.js';
import { mostrarErros, limparErros, lerDinheiro, paraCampo, preencherSelect } from '../componentes/formulario.js';
import { abrirModalDeposito } from '../componentes/modal-deposito.js';
import { avisar } from '../componentes/avisos.js';

const modal = document.getElementById('modal-meta');
const formulario = document.getElementById('formulario-meta');
let metas = [];
let filtro = 'ativas';
let metaEditando = null;

async function carregar() {
  try {
    const dados = await api.get('/api/metas');
    metas = dados.metas;
    const { resumo } = dados;
    definirTexto('total-reservado', formatarMoeda(resumo.total_reservado));
    definirTexto('metas-ativas', `${resumo.ativas} ${resumo.ativas === 1 ? 'meta' : 'metas'}`);
    definirTexto('metas-detalhe', `${resumo.em_progresso} em andamento · ${resumo.prontas} ${resumo.prontas === 1 ? 'pronta' : 'prontas'} para começar`);
    definirTexto('proxima-conquista', resumo.proxima_conquista?.nome ?? 'Nenhuma meta ativa');
    definirTexto('proxima-detalhe', resumo.proxima_conquista ? `Você já chegou a ${formatarPercentual(resumo.proxima_conquista.percentual, 0)}` : 'Crie uma meta para começar');

    const [abaAtivas, abaConcluidas] = document.querySelectorAll('#abas-metas [data-filtro]');
    abaAtivas.textContent = `Ativas (${resumo.ativas})`;
    abaConcluidas.textContent = `Concluídas (${resumo.concluidas})`;
    desenharMetas();
    preencherSelect(document.getElementById('sim-meta'), metas.filter((meta) => meta.status !== 'concluida'), { textoVazio: 'Escolha uma meta' });
  } catch (erro) {
    avisar(erro.message, 'erro');
  }
}

function desenharMetas() {
  const lista = document.getElementById('lista-metas');
  // [DW 10.c] Métodos de array: filter para separar ativas e concluídas, map para criar os cartões.
  const visiveis = metas.filter((meta) => (filtro === 'concluidas' ? meta.status === 'concluida' : meta.status !== 'concluida'));
  if (visiveis.length === 0) {
    lista.replaceChildren(el('div', { classe: 'cartao vazio' }, [
      el('strong', { texto: filtro === 'concluidas' ? 'Nenhuma meta concluída ainda' : 'Nenhuma meta ativa' }),
      filtro === 'concluidas' ? 'Quando uma meta chegar a 100%, ela aparece aqui.' : 'Clique em "Criar nova meta" para começar.',
    ]));
    return;
  }
  lista.replaceChildren(...visiveis.map(cartaoDaMeta));
}

function cartaoDaMeta(meta) {
  const barra = el('div', { classe: 'progresso grosso' }, [el('span')]);
  // O preenchimento começa em zero e anima até o percentual.
  requestAnimationFrame(() => { barra.firstChild.style.width = `${meta.percentual}%`; });
  const emProgresso = meta.status === 'em_progresso';

  return el('article', { classe: 'cartao meta-cartao' }, [
    el('div', { classe: 'cartao-topo' }, [
      el('span', { classe: 'icone-caixa neutro' }, [icone(meta.icone)]),
      el('span', { classe: `status${meta.status === 'concluida' ? ' concluida' : ''}`, texto: meta.status_rotulo }),
    ]),
    el('div', {}, [el('h3', { texto: meta.nome }), el('span', { classe: 'texto-2', texto: meta.descricao ?? '' })]),
    el('div', { classe: 'meta-linha' }, [
      el('span', { classe: 'percentual', texto: formatarPercentual(meta.percentual, 0) }),
      el('span', { classe: 'texto-2', texto: meta.prazo_rotulo }),
    ]),
    barra,
    el('div', { classe: 'meta-linha' }, [
      el('strong', { texto: formatarMoeda(meta.valor_atual).replace(/,00$/, '') }),
      el('span', { classe: 'texto-2', texto: `de ${formatarMoeda(meta.valor_objetivo).replace(/,00$/, '')}` }),
    ]),
    meta.aporte_mensal_sugerido
      ? el('span', { classe: 'texto-2', texto: `Guarde ${formatarMoeda(meta.aporte_mensal_sugerido)} por mês para chegar no prazo.` })
      : null,
    // Previsão de conclusão no ritmo dos últimos 3 meses (vem calculada pela API).
    meta.previsao_rotulo ? el('span', { classe: 'texto-2 meta-previsao', texto: meta.previsao_rotulo }) : null,
    el('div', { classe: 'acoes' }, [
      el('button', {
        classe: `botao ${emProgresso ? 'botao-primario' : 'botao-secundario'}`,
        texto: 'Guardar dinheiro',
        atributos: { type: 'button' },
        aoClicar: () => abrirModalDeposito(meta),
      }),
      el('button', { classe: 'botao-icone', atributos: { type: 'button', 'aria-label': `Editar ${meta.nome}` }, aoClicar: () => abrirFormulario(meta) }, [icone('editar')]),
      el('button', { classe: 'botao-icone', atributos: { type: 'button', 'aria-label': `Excluir ${meta.nome}` }, aoClicar: () => excluir(meta) }, [icone('lixeira')]),
    ]),
  ]);
}

function abrirFormulario(meta = null) {
  metaEditando = meta;
  limparErros(formulario);
  document.getElementById('mm-titulo').textContent = meta ? 'Editar meta' : 'Criar nova meta';
  document.getElementById('mm-nome').value = meta?.nome ?? '';
  document.getElementById('mm-descricao').value = meta?.descricao ?? '';
  document.getElementById('mm-objetivo').value = meta ? paraCampo(meta.valor_objetivo) : '';
  document.getElementById('mm-atual').value = meta ? paraCampo(meta.valor_atual) : '';
  document.getElementById('mm-limite').value = meta?.data_limite ?? paraISO(adicionarMeses(hoje(), 12));
  document.getElementById('mm-limite').min = meta ? '' : paraISO(hoje());
  document.getElementById('mm-icone').value = meta?.icone ?? 'alvo';
  sugerirAporteNoFormulario();
  abrirModal(modal);
}

// Campo calculado no modal: quanto guardar por mês para chegar ao objetivo no prazo escolhido.
function sugerirAporteNoFormulario() {
  const objetivo = lerDinheiro(document.getElementById('mm-objetivo').value) ?? 0;
  const atual = lerDinheiro(document.getElementById('mm-atual').value) ?? 0;
  const limite = deISO(document.getElementById('mm-limite').value);
  const texto = document.getElementById('mm-sugestao');
  if (!(objetivo > 0) || !limite) {
    texto.textContent = '';
    return;
  }
  const meses = Math.max(1, (limite.getFullYear() - hoje().getFullYear()) * 12 + (limite.getMonth() - hoje().getMonth()));
  texto.textContent = `Para chegar lá até o prazo, guarde cerca de ${formatarMoeda(aporteNecessario(objetivo, atual, meses))} por mês (${meses} ${meses === 1 ? 'mês' : 'meses'}).`;
}

['mm-objetivo', 'mm-atual', 'mm-limite'].forEach((id) => document.getElementById(id).addEventListener('input', sugerirAporteNoFormulario));

formulario.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const dados = {
    nome: document.getElementById('mm-nome').value.trim(),
    descricao: document.getElementById('mm-descricao').value.trim(),
    valor_objetivo: lerDinheiro(document.getElementById('mm-objetivo').value),
    valor_atual: lerDinheiro(document.getElementById('mm-atual').value) ?? 0,
    data_limite: document.getElementById('mm-limite').value,
    icone: document.getElementById('mm-icone').value,
  };
  const erros = {};
  if (dados.nome.length < 2) erros.nome = 'Dê um nome para a meta.';
  if (!(dados.valor_objetivo > 0)) erros.valor_objetivo = 'Informe quanto você quer juntar.';
  if (Object.keys(erros).length) {
    mostrarErros(formulario, erros);
    return;
  }
  try {
    if (metaEditando) await api.put(`/api/metas/${metaEditando.id}`, dados);
    else await api.post('/api/metas', dados);
    fecharModal(modal);
    avisar(metaEditando ? 'Meta atualizada.' : 'Meta criada. Agora é só começar a guardar!');
    document.dispatchEvent(new CustomEvent('cofrelo:dados-alterados', { detail: { origem: 'meta' } }));
  } catch (erro) {
    if (erro instanceof ErroApi && erro.status === 422) mostrarErros(formulario, erro.campos);
    else avisar(erro.message, 'erro');
  }
});

async function excluir(meta) {
  const certeza = await confirmar({ titulo: 'Excluir meta?', mensagem: `A meta "${meta.nome}" e o histórico de depósitos serão apagados.` });
  if (!certeza) return;
  try {
    await api.excluir(`/api/metas/${meta.id}`);
    avisar('Meta excluída.');
    document.dispatchEvent(new CustomEvent('cofrelo:dados-alterados', { detail: { origem: 'meta' } }));
  } catch (erro) {
    avisar(erro.message, 'erro');
  }
}

// ---------------------------------------------------------------------------------------------
// Simulador de metas: os resultados são recalculados a cada tecla.
// ---------------------------------------------------------------------------------------------

const campoSimulador = (id) => document.getElementById(id);

function lerSimulador() {
  return {
    objetivo: lerDinheiro(campoSimulador('sim-objetivo').value) ?? 0,
    atual: lerDinheiro(campoSimulador('sim-atual').value) ?? 0,
    meses: Math.min(600, Math.max(0, parseInt(campoSimulador('sim-meses').value, 10) || 0)),
    rendimento: lerDinheiro(campoSimulador('sim-rendimento').value) ?? 0,
    aporte: lerDinheiro(campoSimulador('sim-aporte').value),
  };
}

// [DW 10.f] Função que lê o formulário, calcula e escreve os resultados (campos calculados).
function recalcular() {
  const { objetivo, atual, meses, rendimento, aporte } = lerSimulador();
  const frase = campoSimulador('frase-simulacao');
  const saidas = ['res-aporte-necessario', 'res-saldo-final', 'res-rendimento', 'res-total', 'res-meses'];

  if (!(objetivo > 0) || meses < 1 || rendimento < 0 || rendimento > 10) {
    saidas.forEach((id) => { campoSimulador(id).textContent = '—'; });
    frase.textContent = 'Preencha o objetivo, o prazo em meses e um rendimento entre 0% e 10% ao mês.';
    campoSimulador('grafico-simulacao').replaceChildren();
    return;
  }

  const necessario = aporteNecessario(objetivo, atual, meses, rendimento);
  const aporteUsado = aporte ?? necessario;
  const resultado = simularMeta({ objetivo, atual, aporteMensal: aporteUsado, rendimentoMensal: rendimento, meses });
  const mesesAteLa = mesesParaObjetivo(objetivo, atual, aporteUsado, rendimento);

  campoSimulador('res-aporte-necessario').textContent = formatarMoeda(necessario);
  campoSimulador('res-saldo-final').textContent = formatarMoeda(resultado.saldoFinal);
  campoSimulador('res-rendimento').textContent = formatarMoeda(resultado.rendimento);
  campoSimulador('res-total').textContent = formatarMoeda(resultado.totalGuardado);
  campoSimulador('res-meses').textContent = mesesAteLa === null ? 'Não chega lá' : `${mesesAteLa} ${mesesAteLa === 1 ? 'mês' : 'meses'}`;

  if (aporte === null) {
    frase.textContent = `Para juntar ${formatarMoeda(objetivo)} em ${meses} meses, guarde ${formatarMoeda(necessario)} por mês.`;
  } else if (resultado.atingeObjetivo) {
    frase.textContent = `Guardando ${formatarMoeda(aporte)} por mês, você chega lá em ${resultado.mesAtingido} ${resultado.mesAtingido === 1 ? 'mês' : 'meses'}.`;
  } else {
    frase.textContent = `Guardando ${formatarMoeda(aporte)} por mês, ainda faltariam ${formatarMoeda(resultado.falta)} no fim do prazo.`;
  }
  graficoLinha(campoSimulador('grafico-simulacao'), resultado.evolucao, { objetivo });
}

['sim-objetivo', 'sim-atual', 'sim-meses', 'sim-rendimento', 'sim-aporte'].forEach((id) => {
  campoSimulador(id).addEventListener('input', recalcular);
});

campoSimulador('sim-usar-meta').addEventListener('change', (evento) => {
  campoSimulador('sim-meta').hidden = !evento.target.checked;
});

campoSimulador('sim-meta').addEventListener('change', (evento) => {
  const meta = metas.find((item) => String(item.id) === evento.target.value);
  if (!meta) return;
  campoSimulador('sim-objetivo').value = paraCampo(meta.valor_objetivo);
  campoSimulador('sim-atual').value = paraCampo(meta.valor_atual);
  campoSimulador('sim-meses').value = String(meta.meses_restantes ?? 12);
  campoSimulador('sim-aporte').value = '';
  recalcular();
});

document.querySelectorAll('#abas-metas [data-filtro]').forEach((aba) => {
  aba.addEventListener('click', () => {
    document.querySelectorAll('#abas-metas [data-filtro]').forEach((outra) => outra.setAttribute('aria-selected', String(outra === aba)));
    filtro = aba.dataset.filtro;
    desenharMetas();
  });
});

document.getElementById('criar-meta').addEventListener('click', () => abrirFormulario());
document.addEventListener('cofrelo:dados-alterados', carregar);
window.addEventListener('resize', adiar(recalcular, 200));

carregar();
recalcular();
