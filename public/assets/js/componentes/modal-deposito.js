/** Modal "Guardar dinheiro" numa meta, usado na Visão geral e em Metas e objetivos. */

import { api, ErroApi } from '../nucleo/api.js';
import { el } from '../nucleo/dom.js';
import { abrirModal, fecharModal } from './modal.js';
import { mostrarErros, limparErros, ligarMascaraMoeda, paraCampo } from './formulario.js';
import { lerValor, formatarMoeda, formatarPercentual } from '../lib/formatos.js';
import { paraISO, hoje } from '../lib/datas.js';
import { avisar } from './avisos.js';

const fundo = document.getElementById('modal-deposito');
const formulario = document.getElementById('formulario-deposito');
let metaAtual = null;

if (formulario) {
  const campoValor = document.getElementById('md-valor');
  ligarMascaraMoeda(campoValor);
  campoValor.addEventListener('input', atualizarPrevisao);
  formulario.addEventListener('submit', guardar);
}

export function abrirModalDeposito(meta) {
  metaAtual = meta;
  limparErros(formulario);
  document.getElementById('md-titulo').textContent = `Guardar em "${meta.nome}"`;
  document.getElementById('md-subtitulo').textContent = meta.falta > 0
    ? `Faltam ${formatarMoeda(meta.falta)} para chegar ao objetivo.`
    : 'Esta meta já foi atingida. Ainda dá para guardar mais.';
  document.getElementById('md-valor').value = paraCampo(0);
  document.getElementById('md-data').value = paraISO(hoje());
  document.getElementById('md-data').max = paraISO(hoje());

  // Valores sugeridos: o aporte mensal calculado para o prazo e alguns valores redondos.
  const sugestoes = [100, 250, 500];
  if (meta.aporte_mensal_sugerido > 0) sugestoes.unshift(Math.ceil(meta.aporte_mensal_sugerido));
  const area = document.getElementById('md-sugestoes');
  area.replaceChildren(...[...new Set(sugestoes)].map((valor) => el('button', {
    texto: formatarMoeda(valor).replace(/,00$/, ''),
    atributos: { type: 'button', 'aria-pressed': 'false' },
    aoClicar: () => {
      document.getElementById('md-valor').value = paraCampo(valor);
      atualizarPrevisao();
    },
  })));
  atualizarPrevisao();
  abrirModal(fundo);
}

// [DW 10.f] Função que recalcula a previsão da meta com o valor digitado.
function atualizarPrevisao() {
  if (!metaAtual) return;
  const valor = lerValor(document.getElementById('md-valor').value) || 0;
  const novoTotal = metaAtual.valor_atual + valor;
  const percentual = Math.min(100, (novoTotal / metaAtual.valor_objetivo) * 100);
  document.getElementById('md-previsao').textContent = valor > 0
    ? `Com este valor, a meta chega a ${formatarPercentual(percentual)} (${formatarMoeda(novoTotal)} de ${formatarMoeda(metaAtual.valor_objetivo)}).`
    : '';
}

async function guardar(evento) {
  evento.preventDefault();
  const valor = lerValor(document.getElementById('md-valor').value);
  if (!(valor > 0)) {
    mostrarErros(formulario, { valor: 'Informe um valor maior que zero.' });
    return;
  }
  try {
    const meta = await api.post(`/api/metas/${metaAtual.id}/depositos`, { valor, data: document.getElementById('md-data').value });
    fecharModal(fundo);
    avisar(meta.status === 'concluida'
      ? `Parabéns! A meta "${meta.nome}" foi concluída.`
      : `${formatarMoeda(valor)} guardados. A meta está em ${formatarPercentual(meta.percentual)}.`);
    document.dispatchEvent(new CustomEvent('cofrelo:dados-alterados', { detail: { origem: 'meta', meta } }));
  } catch (erro) {
    if (erro instanceof ErroApi && erro.status === 422) mostrarErros(formulario, erro.campos);
    else avisar(erro.message, 'erro');
  }
}
