/**
 * Seletores de data:
 *  - SeletorPeriodo: calendário com dois meses para escolher uma janela de tempo (comentário do
 *    protótipo na tela de Transações), com atalhos como "Últimos 3 meses";
 *  - SeletorMes: escolha rápida de um mês, usada na Visão geral.
 */

import { el, icone } from '../nucleo/dom.js';
import { nomeDoMes } from '../lib/formatos.js';
import {
  ATALHOS, periodoDoAtalho, rotuloPeriodo, semanasDoMes, inicioDoMes, adicionarMeses, mesmoDia, hoje, antesDe,
} from '../lib/datas.js';

const DIAS_DA_SEMANA = [['D', 'Domingo'], ['S', 'Segunda'], ['T', 'Terça'], ['Q', 'Quarta'], ['Q', 'Quinta'], ['S', 'Sexta'], ['S', 'Sábado']];

/** Fecha o painel quando clicam fora dele ou apertam Esc. */
function fecharAoClicarFora(painel, botao, fechar) {
  document.addEventListener('click', (evento) => {
    if (!painel.hidden && !painel.contains(evento.target) && !botao.contains(evento.target)) fechar();
  });
  document.addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape' && !painel.hidden) {
      fechar();
      botao.focus();
    }
  });
}

export class SeletorPeriodo {
  /**
   * @param {HTMLButtonElement} botao
   * @param {{inicio: Date, fim: Date, atalho?: string|null, aoMudar: (periodo: {inicio: Date, fim: Date, atalho: string|null}) => void}} opcoes
   */
  constructor(botao, { inicio, fim, atalho = null, aoMudar }) {
    this.botao = botao;
    this.inicio = inicio;
    this.fim = fim;
    this.atalho = atalho;
    this.aoMudar = aoMudar;
    this.rascunho = { inicio, fim };
    this.passando = null;
    this.mesEsquerdo = adicionarMeses(inicioDoMes(fim), -1);

    this.painel = el('div', { classe: 'suspenso calendario', atributos: { role: 'dialog', 'aria-label': 'Escolher período', hidden: true } });
    botao.parentElement.append(this.painel);
    botao.addEventListener('click', () => (this.painel.hidden ? this.abrir() : this.fechar()));
    fecharAoClicarFora(this.painel, botao, () => this.fechar());
    this.atualizarBotao();
  }

  abrir() {
    this.rascunho = { inicio: this.inicio, fim: this.fim };
    this.mesEsquerdo = adicionarMeses(inicioDoMes(this.fim), -1);
    this.desenhar();
    this.painel.hidden = false;
    this.botao.setAttribute('aria-expanded', 'true');
  }

  fechar() {
    this.painel.hidden = true;
    this.botao.setAttribute('aria-expanded', 'false');
  }

  desenhar() {
    const atalhos = el('div', { classe: 'calendario-atalhos' }, ATALHOS.map((atalho) => el('button', {
      texto: atalho.rotulo,
      atributos: { type: 'button', 'aria-pressed': String(this.atalho === atalho.id) },
      aoClicar: () => this.escolherAtalho(atalho.id),
    })));

    const meses = el('div', { classe: 'calendario-meses' }, [
      this.desenharMes(this.mesEsquerdo, 'esquerda'),
      this.desenharMes(adicionarMeses(this.mesEsquerdo, 1), 'direita'),
    ]);

    const { inicio, fim } = this.rascunho;
    const texto = fim ? rotuloPeriodo(inicio, fim) : 'Agora escolha o último dia';
    const rodape = el('div', { classe: 'calendario-rodape' }, [
      el('span', { texto }),
      el('div', { classe: 'botoes' }, [
        el('button', { classe: 'botao botao-claro botao-pequeno', texto: 'Cancelar', atributos: { type: 'button' }, aoClicar: () => this.fechar() }),
        el('button', { classe: 'botao botao-primario botao-pequeno', texto: 'Aplicar', atributos: { type: 'button' }, aoClicar: () => this.aplicar() }),
      ]),
    ]);

    this.painel.replaceChildren(atalhos, el('div', { classe: 'calendario-corpo' }, [meses, rodape]));
  }

  desenharMes(mes, lado) {
    const navegacao = el('div', { classe: 'calendario-navegacao' }, [
      lado === 'esquerda'
        ? el('button', { classe: 'botao-icone', atributos: { type: 'button', 'aria-label': 'Mês anterior' }, aoClicar: () => this.mudarMes(-1) }, [icone('anterior')])
        : el('span'),
      el('span', { texto: `${nomeDoMes(mes.getMonth())} ${mes.getFullYear()}` }),
      lado === 'direita'
        ? el('button', { classe: 'botao-icone', atributos: { type: 'button', 'aria-label': 'Próximo mês' }, aoClicar: () => this.mudarMes(1) }, [icone('proximo')])
        : el('span'),
    ]);

    const grade = el('div', { classe: 'calendario-grade' }, DIAS_DA_SEMANA.map(([letra, nome]) => el('abbr', { texto: letra, atributos: { title: nome } })));
    const { inicio } = this.rascunho;
    const fim = this.rascunho.fim ?? this.passando;
    const dataDeHoje = hoje();

    for (const semana of semanasDoMes(mes.getFullYear(), mes.getMonth())) {
      for (const dia of semana) {
        const classes = [];
        // [DW 10.a] Estruturas condicionais: cada dia ganha a aparência de acordo com o intervalo.
        if (dia.getMonth() !== mes.getMonth()) classes.push('outro-mes');
        if (mesmoDia(dia, dataDeHoje)) classes.push('hoje');
        if (inicio && fim && !antesDe(dia, inicio) && !antesDe(fim, dia)) classes.push('no-intervalo');
        if (mesmoDia(dia, inicio)) classes.push('inicio');
        if (fim && mesmoDia(dia, fim)) classes.push('fim');

        const botao = el('button', {
          classe: classes.join(' '),
          texto: dia.getDate(),
          atributos: { type: 'button', 'aria-label': dia.toLocaleDateString('pt-BR', { dateStyle: 'full' }) },
          aoClicar: () => this.escolherDia(dia),
        });
        botao.addEventListener('mouseenter', () => {
          if (this.rascunho.inicio && !this.rascunho.fim && !antesDe(dia, this.rascunho.inicio)) {
            this.passando = dia;
            this.desenhar();
          }
        });
        grade.append(botao);
      }
    }
    return el('div', {}, [navegacao, grade]);
  }

  escolherDia(dia) {
    const { inicio, fim } = this.rascunho;
    if (!inicio || fim) {
      this.rascunho = { inicio: dia, fim: null };
    } else if (antesDe(dia, inicio)) {
      this.rascunho = { inicio: dia, fim: null };
    } else {
      this.rascunho = { inicio, fim: dia };
    }
    this.atalho = null;
    this.passando = null;
    this.desenhar();
  }

  escolherAtalho(id) {
    const periodo = periodoDoAtalho(id);
    this.rascunho = periodo;
    this.atalho = id;
    this.aplicar();
  }

  mudarMes(passo) {
    this.mesEsquerdo = adicionarMeses(this.mesEsquerdo, passo);
    this.desenhar();
  }

  aplicar() {
    const { inicio } = this.rascunho;
    const fim = this.rascunho.fim ?? inicio;
    this.inicio = inicio;
    this.fim = fim;
    this.atualizarBotao();
    this.fechar();
    this.aoMudar({ inicio, fim, atalho: this.atalho });
  }

  atualizarBotao() {
    const atalho = ATALHOS.find((item) => item.id === this.atalho);
    this.botao.querySelector('span').textContent = atalho ? atalho.rotulo : rotuloPeriodo(this.inicio, this.fim);
  }
}

export class SeletorMes {
  /** @param {HTMLButtonElement} botao @param {{mes: Date, aoMudar: (mes: Date) => void}} opcoes */
  constructor(botao, { mes, aoMudar }) {
    this.botao = botao;
    this.mes = inicioDoMes(mes);
    this.ano = this.mes.getFullYear();
    this.aoMudar = aoMudar;
    this.painel = el('div', { classe: 'suspenso seletor-mes', atributos: { role: 'dialog', 'aria-label': 'Escolher mês', hidden: true } });
    botao.parentElement.append(this.painel);
    botao.addEventListener('click', () => (this.painel.hidden ? this.abrir() : this.fechar()));
    fecharAoClicarFora(this.painel, botao, () => this.fechar());
    this.atualizarBotao();
  }

  abrir() {
    this.ano = this.mes.getFullYear();
    this.desenhar();
    this.painel.hidden = false;
    this.botao.setAttribute('aria-expanded', 'true');
  }

  fechar() {
    this.painel.hidden = true;
    this.botao.setAttribute('aria-expanded', 'false');
  }

  desenhar() {
    const atual = inicioDoMes(hoje());
    const navegacao = el('div', { classe: 'calendario-navegacao' }, [
      el('button', { classe: 'botao-icone', atributos: { type: 'button', 'aria-label': 'Ano anterior' }, aoClicar: () => { this.ano -= 1; this.desenhar(); } }, [icone('anterior')]),
      el('span', { texto: String(this.ano) }),
      el('button', {
        classe: 'botao-icone',
        atributos: { type: 'button', 'aria-label': 'Próximo ano', disabled: this.ano >= atual.getFullYear() },
        aoClicar: () => { this.ano += 1; this.desenhar(); },
      }, [icone('proximo')]),
    ]);
    const meses = el('div', { classe: 'meses' });
    for (let indice = 0; indice < 12; indice++) {
      const mes = new Date(this.ano, indice, 1);
      meses.append(el('button', {
        texto: nomeDoMes(indice).slice(0, 3),
        atributos: {
          type: 'button',
          'aria-pressed': String(mes.getTime() === this.mes.getTime()),
          disabled: antesDe(atual, mes),
        },
        aoClicar: () => this.escolher(mes),
      }));
    }
    this.painel.replaceChildren(navegacao, meses);
  }

  escolher(mes) {
    this.mes = mes;
    this.atualizarBotao();
    this.fechar();
    this.aoMudar(mes);
  }

  atualizarBotao() {
    this.botao.querySelector('span').textContent = `${nomeDoMes(this.mes.getMonth())} de ${this.mes.getFullYear()}`;
  }
}
