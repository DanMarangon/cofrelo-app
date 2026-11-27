/**
 * Comportamentos comuns a todas as páginas internas: menu no celular, menu do usuário,
 * notificações, botão "Nova transação" e atalhos de teclado.
 */

import './componentes/modal.js';
import { abrirModalTransacao } from './componentes/modal-transacao.js';
import { iniciarNotificacoes } from './componentes/notificacoes.js';

const app = document.getElementById('app');

// Menu lateral no celular
const botaoMenu = document.getElementById('botao-menu');
botaoMenu?.addEventListener('click', (evento) => {
  evento.stopPropagation();
  const aberto = app.classList.toggle('menu-aberto');
  botaoMenu.setAttribute('aria-expanded', String(aberto));
});
document.addEventListener('click', (evento) => {
  if (app?.classList.contains('menu-aberto') && !evento.target.closest('#menu-lateral')) {
    app.classList.remove('menu-aberto');
    botaoMenu?.setAttribute('aria-expanded', 'false');
  }
});

// Menu do usuário (Configurações, Ajuda, Sair)
const botaoUsuario = document.getElementById('botao-usuario');
const menuUsuario = document.getElementById('menu-usuario');
botaoUsuario?.addEventListener('click', () => {
  menuUsuario.hidden = !menuUsuario.hidden;
  botaoUsuario.setAttribute('aria-expanded', String(!menuUsuario.hidden));
});
document.addEventListener('click', (evento) => {
  if (menuUsuario && !menuUsuario.hidden && !menuUsuario.contains(evento.target) && !botaoUsuario.contains(evento.target)) {
    menuUsuario.hidden = true;
    botaoUsuario.setAttribute('aria-expanded', 'false');
  }
});

// Qualquer botão com data-nova-transacao abre o modal.
document.addEventListener('click', (evento) => {
  const botao = evento.target.closest('[data-nova-transacao]');
  if (botao) abrirModalTransacao({ tipo: botao.dataset.novaTransacao || 'despesa' });
});

// [DW 10.b] Atalhos de teclado: N abre uma nova transação, / vai para a busca, G e depois V abre a Visão geral.
let ultimaTecla = '';
document.addEventListener('keydown', (evento) => {
  const digitando = evento.target.closest('input, textarea, select, [contenteditable]');
  const modalAberto = document.querySelector('.fundo-modal:not([hidden])');
  if (digitando || modalAberto || evento.ctrlKey || evento.metaKey || evento.altKey) return;

  const tecla = evento.key.toLowerCase();
  if (tecla === 'n') {
    evento.preventDefault();
    abrirModalTransacao();
  } else if (tecla === '/') {
    const busca = document.getElementById('filtro-busca') ?? document.getElementById('busca-ajuda');
    if (busca) {
      evento.preventDefault();
      busca.focus();
    }
  } else if (tecla === 'v' && ultimaTecla === 'g') {
    window.location.href = `${document.querySelector('meta[name="base-url"]').content.replace(/\/$/, '')}/visao-geral`;
  }
  ultimaTecla = tecla;
});

iniciarNotificacoes();
