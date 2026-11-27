/** Central de ajuda: busca nas perguntas frequentes sem recarregar a página. */

import { adiar } from '../nucleo/dom.js';

const busca = document.getElementById('busca-ajuda');
const perguntas = [...document.querySelectorAll('#lista-perguntas .pergunta')];
const contador = document.getElementById('ajuda-contador');

const normalizar = (texto) => texto.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

function filtrar() {
  const termo = normalizar(busca.value.trim());
  let encontradas = 0;
  // [DW 10.a] Laço e condicional: mostra só as perguntas que têm o termo buscado.
  for (const pergunta of perguntas) {
    const combina = termo === '' || normalizar(pergunta.textContent).includes(termo);
    pergunta.hidden = !combina;
    pergunta.open = combina && termo !== '';
    if (combina) encontradas++;
  }
  contador.textContent = termo === ''
    ? `${perguntas.length} perguntas`
    : `${encontradas} ${encontradas === 1 ? 'pergunta encontrada' : 'perguntas encontradas'}`;
}

busca.addEventListener('input', adiar(filtrar, 150));
filtrar();
