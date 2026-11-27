/** Configurações: medidor de força da senha, valor diário do orçamento e trava do botão de excluir conta. */

import { forcaDaSenha } from '../lib/senha.js';
import { formatarMoeda } from '../lib/formatos.js';
import { lerDinheiro } from '../componentes/formulario.js';

const novaSenha = document.getElementById('cfg-senha');
const barra = document.querySelector('#cfg-forca span');
const textoForca = document.getElementById('cfg-forca-texto');
const CORES = ['#C2410C', '#C98A0B', '#8CB79C', '#1D704F'];

// [DW 10.g] Interação: a barra mostra a força da senha enquanto a pessoa digita.
novaSenha.addEventListener('input', () => {
  const { nivel, rotulo, percentual } = forcaDaSenha(novaSenha.value);
  barra.style.width = `${percentual}%`;
  barra.style.background = CORES[nivel];
  textoForca.textContent = novaSenha.value ? `Força da senha: ${rotulo}` : 'Digite a nova senha para ver a força dela.';
});

// Campo calculado: quanto o orçamento mensal dá por dia.
const orcamento = document.getElementById('cfg-orcamento');
const dica = document.getElementById('cfg-orcamento-dica');
function atualizarDica() {
  const valor = lerDinheiro(orcamento.value);
  dica.textContent = valor > 0
    ? `Isso dá cerca de ${formatarMoeda(valor / 30)} por dia.`
    : 'Sugestão: até 70% da sua renda mensal.';
}
orcamento.addEventListener('input', atualizarDica);
atualizarDica();

// O botão de excluir só funciona depois de digitar EXCLUIR e a senha.
const confirmacao = document.getElementById('cfg-confirmar-exclusao');
const senhaExclusao = document.getElementById('cfg-senha-exclusao');
const botaoExcluir = document.getElementById('cfg-botao-excluir');
function liberarExclusao() {
  botaoExcluir.disabled = !(confirmacao.value.trim() === 'EXCLUIR' && senhaExclusao.value.length > 0);
}
confirmacao.addEventListener('input', liberarExclusao);
senhaExclusao.addEventListener('input', liberarExclusao);
