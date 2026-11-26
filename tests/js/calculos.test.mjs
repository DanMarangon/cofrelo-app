import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  aporteNecessario, simularMeta, mesesParaObjetivo, variacaoPercentual, sugerirCategoria,
} from '../../public/assets/js/lib/calculos.js';

test('aporte mensal igual ao calculado pelo PHP', () => {
  assert.equal(aporteNecessario(20000, 12000, 13), 615.38);
  assert.equal(aporteNecessario(8000, 0, 20, 0.5), 381.33);
});

test('simulação da meta com rendimento mês a mês', () => {
  const resultado = simularMeta({ objetivo: 20000, atual: 12000, aporteMensal: 615.38, rendimentoMensal: 0.5, meses: 13 });
  assert.equal(resultado.saldoFinal, 21048.23);
  assert.equal(resultado.atingeObjetivo, true);
  assert.equal(resultado.totalGuardado, 19999.94);
  assert.equal(resultado.evolucao.length, 13);
  assert.ok(resultado.mesAtingido <= 13);
});

test('meses para atingir o objetivo', () => {
  assert.equal(mesesParaObjetivo(20000, 12000, 1000), 8);
  assert.equal(mesesParaObjetivo(20000, 20000, 0), 0);
  assert.equal(mesesParaObjetivo(20000, 0, 0), null);
});

test('variação em relação ao mês anterior', () => {
  assert.equal(variacaoPercentual(3920, 4090), -4.2);
  assert.equal(variacaoPercentual(100, 0), null);
});

test('sugere a categoria pela descrição', () => {
  const categorias = [{ id: 1, nome: 'Alimentação' }, { id: 2, nome: 'Transporte' }, { id: 3, nome: 'Assinaturas' }];
  assert.equal(sugerirCategoria('Supermercado Extra', categorias)?.id, 1);
  assert.equal(sugerirCategoria('Uber para o trabalho', categorias)?.id, 2);
  assert.equal(sugerirCategoria('Netflix', categorias)?.id, 3);
  assert.equal(sugerirCategoria('Presente', categorias), null);
});
