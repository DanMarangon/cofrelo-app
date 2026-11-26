import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  formatarMoeda, formatarValorTransacao, formatarPercentual, formatarData, lerValor, mascararMoeda, rotuloMes,
} from '../../public/assets/js/lib/formatos.js';

test('formata moeda no padrão brasileiro', () => {
  assert.equal(formatarMoeda(24580), 'R$ 24.580,00');
  assert.equal(formatarMoeda(-210), '-R$ 210,00');
});

test('mostra o sinal conforme o tipo da transação', () => {
  assert.equal(formatarValorTransacao('receita', 7500), '+ R$ 7.500,00');
  assert.equal(formatarValorTransacao('despesa', 286.4), '− R$ 286,40');
  assert.equal(formatarValorTransacao('transferencia', 1000), 'R$ 1.000,00');
});

test('formata percentual e mostra traço quando não há valor', () => {
  assert.equal(formatarPercentual(53.9), '53,9%');
  assert.equal(formatarPercentual(null), '—');
});

test('datas da lista ficam curtas e relativas a hoje', () => {
  const hoje = new Date(2026, 10, 26);
  assert.equal(formatarData('2026-11-26', hoje), 'Hoje');
  assert.equal(formatarData('2026-11-25', hoje), 'Ontem');
  assert.equal(formatarData('2026-11-18', hoje), '18 nov');
  assert.equal(formatarData('2025-12-03', hoje), '3 dez 2025');
  assert.equal(rotuloMes(2026, 10), 'Novembro de 2026');
});

test('lê valores digitados e aplica a máscara de centavos', () => {
  assert.equal(lerValor('1.234,56'), 1234.56);
  assert.equal(lerValor('R$ 286,40'), 286.4);
  assert.ok(Number.isNaN(lerValor('')));
  assert.equal(mascararMoeda('28640'), '286,40');
  assert.equal(mascararMoeda('123456789'), '1.234.567,89');
});
