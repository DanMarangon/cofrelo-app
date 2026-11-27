import { el, icone } from '../nucleo/dom.js';

/** Aviso rápido no canto da tela ("Transação salva"). */
export function avisar(texto, tipo = 'sucesso') {
  const area = document.getElementById('avisos');
  if (!area) return;
  const aviso = el('div', { classe: `aviso ${tipo}`, atributos: { role: tipo === 'erro' ? 'alert' : 'status' } }, [
    icone(tipo === 'erro' ? 'alerta' : 'check'),
    el('span', { texto }),
  ]);
  area.append(aviso);
  // [DW 10.h] Automatização: o aviso some sozinho depois de alguns segundos.
  setTimeout(() => {
    aviso.classList.add('saindo');
    setTimeout(() => aviso.remove(), 300);
  }, tipo === 'erro' ? 6000 : 4000);
}
