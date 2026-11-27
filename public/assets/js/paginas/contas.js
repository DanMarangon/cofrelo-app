/** Minhas contas: patrimônio, cartões das contas (criar, editar, excluir) e transferências. */

import { api, ErroApi } from '../nucleo/api.js';
import { el, icone, definirTexto } from '../nucleo/dom.js';
import { formatarMoeda, formatarData } from '../lib/formatos.js';
import { abrirModal, fecharModal, confirmar } from '../componentes/modal.js';
import { mostrarErros, limparErros, lerDinheiro, paraCampo } from '../componentes/formulario.js';
import { abrirModalTransacao } from '../componentes/modal-transacao.js';
import { avisar } from '../componentes/avisos.js';

const base = document.querySelector('meta[name="base-url"]').content.replace(/\/$/, '');
const modal = document.getElementById('modal-conta');
const formulario = document.getElementById('formulario-conta');
let contaEditando = null;
let menuAberto = null;

const ICONE_POR_TIPO = { corrente: 'carteira', pagamento: 'carteira', investimento: 'meta', dinheiro: 'carteira' };

async function carregar() {
  try {
    const dados = await api.get('/api/contas');
    definirTexto('patrimonio-total', formatarMoeda(dados.patrimonio));
    definirTexto('patrimonio-contas', `Distribuído em ${dados.quantidade} ${dados.quantidade === 1 ? 'conta' : 'contas'}`);
    definirTexto('contas-ativas', `${dados.quantidade} ${dados.quantidade === 1 ? 'conta ativa' : 'contas ativas'}`);

    const variacao = document.getElementById('patrimonio-variacao');
    variacao.replaceChildren();
    if (dados.patrimonio_variacao !== null) {
      const subiu = dados.patrimonio_variacao >= 0;
      variacao.append(`${subiu ? '↑' : '↓'} ${Math.abs(dados.patrimonio_variacao).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}% em relação ao mês anterior`);
    }

    desenharContas(dados.contas);
    desenharTransferencias(dados.transferencias);
  } catch (erro) {
    avisar(erro.message, 'erro');
  }
}

function desenharContas(contas) {
  const lista = document.getElementById('lista-contas');
  lista.replaceChildren(...contas.map((conta) => {
    const menu = el('div', { classe: 'suspenso', atributos: { hidden: true } }, [
      el('button', { atributos: { type: 'button' }, aoClicar: () => abrirFormulario(conta) }, [icone('editar'), 'Editar']),
      el('button', { classe: 'perigo', atributos: { type: 'button' }, aoClicar: () => excluir(conta) }, [icone('lixeira'), 'Excluir']),
    ]);
    const botaoMenu = el('button', {
      classe: 'botao-icone',
      atributos: { type: 'button', 'aria-label': `Opções da conta ${conta.nome}`, 'aria-haspopup': 'true' },
      aoClicar: (evento) => {
        evento.stopPropagation();
        if (menuAberto && menuAberto !== menu) menuAberto.hidden = true;
        menu.hidden = !menu.hidden;
        menuAberto = menu.hidden ? null : menu;
      },
    }, [icone('opcoes')]);

    return el('article', { classe: 'cartao conta-cartao' }, [
      el('div', { classe: 'cartao-topo' }, [
        el('span', { classe: 'icone-caixa' }, [icone(ICONE_POR_TIPO[conta.tipo] ?? 'carteira')]),
        el('div', { classe: 'ancora' }, [botaoMenu, menu]),
      ]),
      el('h3', { texto: conta.nome }),
      el('span', { classe: 'texto-2', texto: conta.tipo_rotulo }),
      el('div', { classe: 'saldo', texto: formatarMoeda(conta.saldo_atual) }),
      el('footer', {}, [
        el('span', { texto: `${conta.percentual_do_total}% do saldo total` }),
        el('a', { classe: 'botao botao-texto', texto: 'Ver detalhes →', atributos: { href: `${base}/transacoes?conta=${conta.id}` } }),
      ]),
    ]);
  }));
}

function desenharTransferencias(transferencias) {
  const lista = document.getElementById('lista-transferencias');
  if (transferencias.length === 0) {
    lista.replaceChildren(el('li', { classe: 'vazio', texto: 'Nenhuma transferência entre contas ainda.' }));
    return;
  }
  lista.replaceChildren(...transferencias.map((transferencia) => el('li', {}, [
    icone('transacoes', 'icone icone-lg'),
    el('div', { classe: 'lista-texto' }, [
      el('strong', { texto: `${transferencia.conta.nome} → ${transferencia.conta_destino?.nome ?? ''}` }),
      el('span', { texto: `${formatarData(transferencia.data)} · ${transferencia.status === 'pendente' ? 'Transferência agendada' : 'Transferência concluída'}` }),
    ]),
    el('strong', { texto: formatarMoeda(transferencia.valor) }),
  ])));
}

function abrirFormulario(conta = null) {
  contaEditando = conta;
  limparErros(formulario);
  if (menuAberto) menuAberto.hidden = true;
  document.getElementById('mco-titulo').textContent = conta ? 'Editar conta' : 'Adicionar conta';
  document.getElementById('mco-nome').value = conta?.nome ?? '';
  document.getElementById('mco-tipo').value = conta?.tipo ?? 'corrente';
  document.getElementById('mco-saldo').value = conta ? paraCampo(conta.saldo_inicial) : '';
  document.getElementById('mco-descricao').value = conta?.descricao ?? '';
  abrirModal(modal);
}

formulario.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const dados = {
    nome: document.getElementById('mco-nome').value.trim(),
    tipo: document.getElementById('mco-tipo').value,
    saldo_inicial: lerDinheiro(document.getElementById('mco-saldo').value) ?? 0,
    descricao: document.getElementById('mco-descricao').value.trim(),
  };
  if (dados.nome.length < 2) {
    mostrarErros(formulario, { nome: 'Dê um nome para a conta.' });
    return;
  }
  try {
    if (contaEditando) await api.put(`/api/contas/${contaEditando.id}`, dados);
    else await api.post('/api/contas', dados);
    fecharModal(modal);
    avisar(contaEditando ? 'Conta atualizada.' : 'Conta criada.');
    document.dispatchEvent(new CustomEvent('cofrelo:dados-alterados', { detail: { origem: 'conta' } }));
  } catch (erro) {
    if (erro instanceof ErroApi && erro.status === 422) mostrarErros(formulario, erro.campos);
    else avisar(erro.message, 'erro');
  }
});

async function excluir(conta) {
  if (menuAberto) menuAberto.hidden = true;
  const certeza = await confirmar({ titulo: 'Excluir conta?', mensagem: `A conta "${conta.nome}" será apagada.` });
  if (!certeza) return;
  try {
    await api.excluir(`/api/contas/${conta.id}`);
    avisar('Conta excluída.');
    document.dispatchEvent(new CustomEvent('cofrelo:dados-alterados', { detail: { origem: 'conta' } }));
  } catch (erro) {
    avisar(erro.message, 'erro');
  }
}

document.addEventListener('click', () => {
  if (menuAberto) {
    menuAberto.hidden = true;
    menuAberto = null;
  }
});
document.getElementById('adicionar-conta').addEventListener('click', () => abrirFormulario());
document.getElementById('nova-transferencia').addEventListener('click', () => abrirModalTransacao({ tipo: 'transferencia' }));
document.addEventListener('cofrelo:dados-alterados', carregar);

carregar();
