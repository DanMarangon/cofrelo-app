/**
 * Modal de nova transação (e de edição). As abas mudam os campos mostrados:
 * Despesa, Receita, Transferência e Assinatura (cobrança mensal que o sistema lança sozinho).
 */

import { api, carregarCategorias, carregarContas, ErroApi } from '../nucleo/api.js';
import { adiar } from '../nucleo/dom.js';
import { abrirModal, fecharModal } from './modal.js';
import { mostrarErros, limparErros, preencherSelect, ligarMascaraMoeda, paraCampo } from './formulario.js';
import { lerValor, formatarMoeda } from '../lib/formatos.js';
import { sugerirCategoria } from '../lib/calculos.js';
import { paraISO, hoje, deISO } from '../lib/datas.js';
import { avisar } from './avisos.js';

const fundo = document.getElementById('modal-transacao');
const formulario = document.getElementById('formulario-transacao');

// [DW 10.d] Objeto que guarda o estado do formulário enquanto ele está aberto.
const estado = {
  tipo: 'despesa',
  status: 'pago',
  editandoId: null,
  categoriaEscolhidaManualmente: false,
  categorias: [],
};

const campo = (id) => document.getElementById(id);

const SUBTITULOS = {
  despesa: 'Registre uma saída de dinheiro.',
  receita: 'Registre uma entrada de dinheiro.',
  transferencia: 'Mova dinheiro entre as suas contas, sem mudar o saldo total.',
  assinatura: 'Uma cobrança que se repete todo mês, como streaming ou academia.',
};

if (formulario) {
  ligarMascaraMoeda(campo('mt-valor'));

  const dias = campo('mt-dia');
  for (let dia = 1; dia <= 28; dia++) dias.append(new Option(`Todo dia ${dia}`, String(dia)));

  formulario.querySelectorAll('#mt-abas [data-tipo]').forEach((aba) => {
    aba.addEventListener('click', () => definirTipo(aba.dataset.tipo));
  });

  formulario.querySelectorAll('[data-status]').forEach((botao) => {
    botao.addEventListener('click', () => definirStatus(botao.dataset.status));
  });

  // [DW 10.h] Automatização: sugere a categoria pela descrição enquanto a pessoa digita.
  campo('mt-descricao').addEventListener('input', adiar(sugerir, 250));
  campo('mt-categoria').addEventListener('change', () => {
    estado.categoriaEscolhidaManualmente = true;
    campo('mt-sugestao').hidden = true;
  });

  // Na assinatura, o dia da cobrança acompanha a data da primeira cobrança.
  campo('mt-data').addEventListener('change', () => {
    const data = deISO(campo('mt-data').value);
    if (data && estado.tipo === 'assinatura') campo('mt-dia').value = String(Math.min(28, data.getDate()));
  });

  formulario.addEventListener('submit', salvar);
}

/** Abre o modal vazio ou com uma transação para editar. */
export async function abrirModalTransacao({ transacao = null, tipo = 'despesa' } = {}) {
  const [categorias, contas] = await Promise.all([carregarCategorias(), carregarContas()]);
  estado.categorias = categorias;
  estado.editandoId = transacao?.id ?? null;
  estado.categoriaEscolhidaManualmente = Boolean(transacao);
  limparErros(formulario);

  preencherSelect(campo('mt-conta'), contas, { valor: transacao?.conta?.id ?? contas[0]?.id });
  preencherSelect(campo('mt-destino'), contas, { valor: transacao?.conta_destino?.id ?? contas[1]?.id ?? contas[0]?.id });

  campo('mt-valor').value = paraCampo(transacao?.valor ?? 0);
  campo('mt-descricao').value = transacao?.descricao ?? '';
  campo('mt-data').value = transacao?.data ?? paraISO(hoje());
  campo('mt-forma').value = transacao?.forma_pagamento && transacao.forma_pagamento !== 'transferencia' ? transacao.forma_pagamento : 'debito';
  campo('mt-observacao').value = transacao?.observacao ?? '';
  campo('mt-dia').value = String(Math.min(28, deISO(campo('mt-data').value)?.getDate() ?? 1));
  campo('mt-sugestao').hidden = true;

  campo('mt-titulo').textContent = transacao ? 'Editar transação' : 'Nova transação';
  // Ao editar, a aba Assinatura some: a cobrança mensal é editada na aba Assinaturas de Transações.
  formulario.querySelector('[data-tipo="assinatura"]').hidden = Boolean(transacao);
  campo('mt-aviso-assinatura').hidden = !transacao?.assinatura_id;

  definirTipo(transacao?.tipo ?? tipo, { manterCategoria: transacao?.categoria?.id });
  definirStatus(transacao?.status ?? 'pago');
  abrirModal(fundo);
}

function definirTipo(tipo, { manterCategoria = null } = {}) {
  estado.tipo = tipo;
  formulario.querySelectorAll('#mt-abas [data-tipo]').forEach((aba) => {
    aba.setAttribute('aria-selected', String(aba.dataset.tipo === tipo));
  });

  // [DW 10.a] Estruturas condicionais: cada tipo mostra e esconde campos diferentes.
  const transferencia = tipo === 'transferencia';
  const assinatura = tipo === 'assinatura';
  campo('mt-campo-categoria').hidden = transferencia;
  campo('mt-campo-destino').hidden = !transferencia;
  campo('mt-campo-forma').hidden = transferencia;
  campo('mt-campo-status').hidden = assinatura;
  campo('mt-caixa-assinatura').hidden = !assinatura;
  campo('mt-rotulo-conta').textContent = transferencia ? 'Da conta' : 'Conta';
  campo('mt-rotulo-data').textContent = assinatura ? 'Primeira cobrança' : 'Data';
  campo('mt-subtitulo').textContent = SUBTITULOS[tipo];
  campo('mt-salvar').textContent = assinatura ? 'Salvar assinatura' : 'Salvar transação';

  const [pago, pendente] = formulario.querySelectorAll('[data-status]');
  pago.textContent = tipo === 'receita' ? 'Recebido' : 'Pago';
  pendente.textContent = tipo === 'receita' ? 'A receber' : 'Pendente';

  if (!transferencia) {
    const tipoCategoria = tipo === 'receita' ? 'receita' : 'despesa';
    const lista = estado.categorias.filter((categoria) => categoria.tipo === tipoCategoria);
    const padrao = assinatura ? lista.find((categoria) => categoria.nome === 'Assinaturas')?.id : null;
    preencherSelect(campo('mt-categoria'), lista, { valor: manterCategoria ?? padrao, textoVazio: 'Selecione a categoria' });
    if (assinatura) campo('mt-forma').value = 'credito';
    sugerir();
  }
}

function definirStatus(status) {
  estado.status = status;
  formulario.querySelectorAll('[data-status]').forEach((botao) => {
    botao.setAttribute('aria-pressed', String(botao.dataset.status === status));
  });
}

function sugerir() {
  if (estado.categoriaEscolhidaManualmente || estado.tipo === 'transferencia') return;
  const tipoCategoria = estado.tipo === 'receita' ? 'receita' : 'despesa';
  const sugestao = sugerirCategoria(campo('mt-descricao').value, estado.categorias.filter((c) => c.tipo === tipoCategoria));
  const dica = campo('mt-sugestao');
  if (sugestao) {
    campo('mt-categoria').value = String(sugestao.id);
    dica.textContent = `Categoria sugerida pela descrição: ${sugestao.nome}`;
    dica.hidden = false;
  } else {
    dica.hidden = true;
  }
}

function montarDados() {
  const assinatura = estado.tipo === 'assinatura';
  const data = campo('mt-data').value;
  return {
    tipo: assinatura ? 'despesa' : estado.tipo,
    valor: lerValor(campo('mt-valor').value),
    descricao: campo('mt-descricao').value.trim(),
    categoria_id: estado.tipo === 'transferencia' ? null : Number(campo('mt-categoria').value) || null,
    conta_id: Number(campo('mt-conta').value) || null,
    conta_destino_id: estado.tipo === 'transferencia' ? Number(campo('mt-destino').value) || null : null,
    data,
    forma_pagamento: estado.tipo === 'transferencia' ? 'transferencia' : campo('mt-forma').value,
    // Na assinatura, a cobrança com data futura começa pendente.
    status: assinatura ? (data > paraISO(hoje()) ? 'pendente' : 'pago') : estado.status,
    assinatura,
    dia_cobranca: assinatura ? Number(campo('mt-dia').value) : null,
    aviso_dias: assinatura ? Number(campo('mt-aviso').value) : null,
    observacao: campo('mt-observacao').value.trim(),
  };
}

/** Validação no navegador, para avisar antes de enviar. O PHP valida de novo no servidor. */
function validarNoNavegador(dados) {
  const erros = {};
  if (!(dados.valor > 0)) erros.valor = 'Informe um valor maior que zero.';
  if (dados.descricao.length < 2) erros.descricao = 'Descreva a transação (pelo menos 2 letras).';
  if (estado.tipo !== 'transferencia' && !dados.categoria_id) erros.categoria_id = 'Escolha uma categoria.';
  if (!dados.data) erros.data = 'Informe a data.';
  if (estado.tipo === 'transferencia' && dados.conta_id === dados.conta_destino_id) {
    erros.conta_destino_id = 'Escolha uma conta de destino diferente da origem.';
  }
  return erros;
}

async function salvar(evento) {
  evento.preventDefault();
  const dados = montarDados();
  const erros = validarNoNavegador(dados);
  if (Object.keys(erros).length > 0) {
    mostrarErros(formulario, erros);
    return;
  }

  const botao = campo('mt-salvar');
  botao.disabled = true;
  try {
    const salva = estado.editandoId
      ? await api.put(`/api/transacoes/${estado.editandoId}`, dados)
      : await api.post('/api/transacoes', dados);
    fecharModal(fundo);
    const texto = estado.editandoId ? 'Transação atualizada.' : dados.assinatura
      ? `Assinatura criada: ${formatarMoeda(dados.valor)} todo dia ${dados.dia_cobranca}.`
      : 'Transação salva.';
    avisar(texto);
    document.dispatchEvent(new CustomEvent('cofrelo:dados-alterados', { detail: { origem: 'transacao', transacao: salva } }));
  } catch (erro) {
    if (erro instanceof ErroApi && erro.status === 422) {
      mostrarErros(formulario, erro.campos);
    } else {
      avisar(erro.message, 'erro');
    }
  } finally {
    botao.disabled = false;
  }
}
