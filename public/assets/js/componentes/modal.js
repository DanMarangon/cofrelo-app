/**
 * Janelas modais: abre, fecha com Esc ou clique fora, e mantém o foco do teclado dentro da janela.
 */

let aberto = null;
let focoAnterior = null;

const FOCAVEIS = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])';

export function abrirModal(fundo) {
  if (aberto && aberto !== fundo) fecharModal(aberto);
  focoAnterior = document.activeElement;
  fundo.hidden = false;
  aberto = fundo;
  const primeiro = [...fundo.querySelectorAll(FOCAVEIS)].find((elemento) => !elemento.closest('[hidden]') && !elemento.matches('[data-fechar-modal]'));
  setTimeout(() => primeiro?.focus(), 30);
}

export function fecharModal(fundo = aberto) {
  if (!fundo || fundo.hidden) return;
  fundo.hidden = true;
  if (fundo === aberto) aberto = null;
  fundo.dispatchEvent(new CustomEvent('modal:fechado'));
  if (focoAnterior && document.contains(focoAnterior)) focoAnterior.focus();
}

// [DW 10.b] Eventos de teclado: Esc fecha e Tab circula só pelos campos da janela.
document.addEventListener('keydown', (evento) => {
  if (!aberto) return;
  if (evento.key === 'Escape') {
    evento.preventDefault();
    fecharModal();
    return;
  }
  if (evento.key === 'Tab') {
    const focaveis = [...aberto.querySelectorAll(FOCAVEIS)].filter((elemento) => !elemento.closest('[hidden]'));
    if (focaveis.length === 0) return;
    const primeiro = focaveis[0];
    const ultimo = focaveis[focaveis.length - 1];
    if (evento.shiftKey && document.activeElement === primeiro) {
      evento.preventDefault();
      ultimo.focus();
    } else if (!evento.shiftKey && document.activeElement === ultimo) {
      evento.preventDefault();
      primeiro.focus();
    }
  }
});

document.addEventListener('mousedown', (evento) => {
  if (aberto && evento.target === aberto) fecharModal();
});

document.addEventListener('click', (evento) => {
  if (aberto && evento.target.closest('[data-fechar-modal]')) fecharModal();
});

/** Pergunta de confirmação com botões da própria página. Devolve uma Promise com true ou false. */
export function confirmar({ titulo = 'Tem certeza?', mensagem = '', botao = 'Excluir' } = {}) {
  const fundo = document.getElementById('modal-confirmar');
  document.getElementById('mc-titulo').textContent = titulo;
  document.getElementById('mc-mensagem').textContent = mensagem;
  const sim = document.getElementById('mc-confirmar');
  const nao = document.getElementById('mc-cancelar');
  sim.textContent = botao;

  return new Promise((resolver) => {
    let respondeu = false;
    const responder = (resposta) => {
      if (respondeu) return;
      respondeu = true;
      sim.removeEventListener('click', aoConfirmar);
      nao.removeEventListener('click', aoCancelar);
      fundo.removeEventListener('modal:fechado', aoCancelar);
      fecharModal(fundo);
      resolver(resposta);
    };
    const aoConfirmar = () => responder(true);
    const aoCancelar = () => responder(false);
    sim.addEventListener('click', aoConfirmar);
    nao.addEventListener('click', aoCancelar);
    fundo.addEventListener('modal:fechado', aoCancelar);
    abrirModal(fundo);
  });
}
