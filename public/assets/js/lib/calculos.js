/**
 * Cálculos do lado do navegador: simulador de metas, variações e sugestão automática de categoria.
 * Funções puras, testadas em tests/js/calculos.test.mjs.
 */

const arredondar = (valor) => Math.round(valor * 100) / 100;

export function variacaoPercentual(atual, anterior) {
  if (!anterior) return null;
  return Math.round(((atual - anterior) / Math.abs(anterior)) * 1000) / 10;
}

/**
 * Quanto guardar por mês para chegar ao objetivo em `meses`, com rendimento mensal opcional (em %).
 * Mesma fórmula da classe PHP CalculadoraMeta: objetivo = atual·(1+i)^n + aporte·((1+i)^n − 1)/i.
 */
export function aporteNecessario(objetivo, atual, meses, rendimentoMensal = 0) {
  if (meses <= 0) return arredondar(Math.max(0, objetivo - atual));
  const taxa = rendimentoMensal / 100;
  if (taxa <= 0) return arredondar(Math.max(0, (objetivo - atual) / meses));
  const fator = (1 + taxa) ** meses;
  const falta = objetivo - atual * fator;
  return falta <= 0 ? 0 : arredondar((falta * taxa) / (fator - 1));
}

/**
 * Simula a meta mês a mês: o saldo rende e recebe o aporte.
 * Devolve os campos calculados do formulário e a evolução para desenhar a linha do gráfico.
 */
export function simularMeta({ objetivo, atual = 0, aporteMensal = 0, rendimentoMensal = 0, meses }) {
  const taxa = rendimentoMensal / 100;
  let saldo = atual;
  let mesAtingido = null;
  const evolucao = [];
  for (let mes = 1; mes <= meses; mes++) {
    saldo = saldo * (1 + taxa) + aporteMensal;
    evolucao.push(arredondar(saldo));
    if (mesAtingido === null && saldo >= objetivo) mesAtingido = mes;
  }
  const totalGuardado = atual + aporteMensal * meses;
  return {
    saldoFinal: arredondar(saldo),
    totalGuardado: arredondar(totalGuardado),
    rendimento: arredondar(saldo - totalGuardado),
    atingeObjetivo: saldo >= objetivo,
    mesAtingido,
    falta: arredondar(Math.max(0, objetivo - saldo)),
    aporteNecessario: aporteNecessario(objetivo, atual, meses, rendimentoMensal),
    evolucao,
  };
}

/** Em quantos meses o objetivo é atingido guardando `aporteMensal`. Devolve null se nunca chegar. */
export function mesesParaObjetivo(objetivo, atual, aporteMensal, rendimentoMensal = 0, limite = 600) {
  if (atual >= objetivo) return 0;
  const taxa = rendimentoMensal / 100;
  let saldo = atual;
  for (let mes = 1; mes <= limite; mes++) {
    saldo = saldo * (1 + taxa) + aporteMensal;
    if (saldo >= objetivo) return mes;
  }
  return null;
}

const PALAVRAS_CHAVE = {
  Alimentação: ['mercado', 'padaria', 'restaurante', 'ifood', 'lanche', 'feira', 'cafe', 'pizza', 'acougue', 'hortifruti'],
  Transporte: ['uber', 'combustivel', 'posto', 'gasolina', 'onibus', 'metro', 'estacionamento', 'pedagio', 'taxi'],
  Moradia: ['aluguel', 'condominio', 'energia', 'luz', 'agua', 'internet', 'gas', 'iptu'],
  Saúde: ['farmacia', 'plano de saude', 'consulta', 'dentista', 'exame', 'hospital'],
  Assinaturas: ['netflix', 'spotify', 'icloud', 'academia', 'prime video', 'disney', 'youtube', 'assinatura'],
  Lazer: ['cinema', 'show', 'viagem', 'bar ', 'ingresso'],
  Educação: ['curso', 'faculdade', 'livro', 'mensalidade escolar'],
  Salário: ['salario', 'pagamento da empresa'],
  Freelance: ['freela', 'freelance', 'projeto'],
  Rendimentos: ['rendimento', 'dividendo', 'juros'],
};

function normalizar(texto) {
  return String(texto ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

/**
 * Sugere a categoria pela descrição digitada ("Supermercado Extra" sugere Alimentação).
 * Só devolve categorias do tipo da transação que estão na lista recebida.
 */
export function sugerirCategoria(descricao, categorias) {
  const texto = normalizar(descricao);
  if (texto.trim().length < 3) return null;
  for (const [nome, palavras] of Object.entries(PALAVRAS_CHAVE)) {
    if (palavras.some((palavra) => texto.includes(palavra))) {
      const encontrada = categorias.find((categoria) => normalizar(categoria.nome) === normalizar(nome));
      if (encontrada) return encontrada;
    }
  }
  return null;
}
