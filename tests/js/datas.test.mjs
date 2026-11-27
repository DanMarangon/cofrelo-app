import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  periodoDoAtalho, rotuloPeriodo, semanasAte, semanasDoMes, adicionarMeses, paraISO, deISO,
} from '../../public/assets/js/lib/datas.js';

const hoje = new Date(2026, 10, 26);

test('atalhos do calendário de período', () => {
  const mesPassado = periodoDoAtalho('mes-passado', hoje);
  assert.equal(paraISO(mesPassado.inicio), '2026-10-01');
  assert.equal(paraISO(mesPassado.fim), '2026-10-31');

  const seisMeses = periodoDoAtalho('ultimos-6-meses', hoje);
  assert.equal(paraISO(seisMeses.inicio), '2026-06-01');
  assert.equal(paraISO(seisMeses.fim), '2026-11-30');
});

test('rótulo do período escolhido', () => {
  assert.equal(rotuloPeriodo(deISO('2026-11-01'), deISO('2026-11-30')), 'Novembro de 2026');
  assert.equal(rotuloPeriodo(deISO('2026-06-01'), deISO('2026-11-30')), '1 jun – 30 nov 2026');
  assert.equal(rotuloPeriodo(deISO('2025-12-01'), deISO('2026-11-30')), '1 dez 2025 – 30 nov 2026');
});

test('somar meses não pula o fim do mês', () => {
  assert.equal(paraISO(adicionarMeses(deISO('2026-01-31'), 1)), '2026-02-28');
});

test('grade do calendário e do mapa de gastos', () => {
  const semanas = semanasDoMes(2026, 10);
  assert.equal(semanas.length, 6);
  assert.equal(paraISO(semanas[0][0]), '2026-11-01');

  const colunas = semanasAte(hoje);
  assert.equal(colunas.length, 53);
  assert.ok(colunas.every((coluna) => coluna.length === 7));
  assert.ok(colunas.at(-1).some((dia) => paraISO(dia) === '2026-11-26'));
});
