/** Sino de notificações no topo: cobranças chegando, contas atrasadas, orçamento e metas. */

import { api } from '../nucleo/api.js';
import { el, icone } from '../nucleo/dom.js';

const CHAVE_LIDAS = 'cofrelo:notificacoes-lidas';
const ICONES = { cobranca: 'calendario', atraso: 'alerta', orcamento: 'alerta', meta: 'meta' };
let notificacoes = [];

function lidas() {
  try {
    return new Set(JSON.parse(localStorage.getItem(CHAVE_LIDAS) ?? '[]'));
  } catch {
    return new Set();
  }
}

function guardarLidas(conjunto) {
  try {
    localStorage.setItem(CHAVE_LIDAS, JSON.stringify([...conjunto].slice(-200)));
  } catch {
    // Sem armazenamento local (aba anônima): as notificações só não ficam marcadas como lidas.
  }
}

function desenhar() {
  const lista = document.getElementById('lista-notificacoes');
  const selo = document.getElementById('selo-notificacoes');
  const jaLidas = lidas();
  const naoLidas = notificacoes.filter((aviso) => !jaLidas.has(aviso.id)).length;

  selo.hidden = naoLidas === 0;
  selo.textContent = naoLidas > 9 ? '9+' : String(naoLidas);

  if (notificacoes.length === 0) {
    lista.replaceChildren(el('li', { classe: 'vazio' }, [el('strong', { texto: 'Tudo em dia' }), 'Nenhuma cobrança ou alerta por enquanto.']));
    return;
  }
  lista.replaceChildren(...notificacoes.map((aviso) => el('li', { classe: `notificacao${jaLidas.has(aviso.id) ? ' lida' : ''}` }, [
    el('span', { classe: `icone-caixa ${aviso.tipo}` }, [icone(ICONES[aviso.tipo] ?? 'sino')]),
    el('div', {}, [el('strong', { texto: aviso.titulo }), el('span', { texto: aviso.mensagem })]),
  ])));
}

async function carregar() {
  try {
    const resposta = await api.get('/api/notificacoes');
    notificacoes = resposta.notificacoes;
    desenhar();
  } catch {
    // Falha temporária: tenta de novo no próximo ciclo.
  }
}

export function iniciarNotificacoes() {
  const botao = document.getElementById('botao-notificacoes');
  const painel = document.getElementById('painel-notificacoes');
  if (!botao || !painel) return;

  carregar();
  // [DW 10.h] Automatização: as notificações são atualizadas sozinhas a cada minuto.
  setInterval(carregar, 60_000);
  document.addEventListener('cofrelo:dados-alterados', carregar);

  botao.addEventListener('click', () => {
    painel.hidden = !painel.hidden;
    botao.setAttribute('aria-expanded', String(!painel.hidden));
  });
  document.addEventListener('click', (evento) => {
    if (!painel.hidden && !painel.contains(evento.target) && !botao.contains(evento.target)) {
      painel.hidden = true;
      botao.setAttribute('aria-expanded', 'false');
    }
  });
  document.getElementById('marcar-lidas').addEventListener('click', () => {
    const conjunto = lidas();
    notificacoes.forEach((aviso) => conjunto.add(aviso.id));
    guardarLidas(conjunto);
    desenhar();
  });
}
