import { test } from 'node:test';
import assert from 'node:assert/strict';
import { forcaDaSenha } from '../../public/assets/js/lib/senha.js';

test('senha fora da regra mínima é fraca', () => {
  assert.equal(forcaDaSenha('abc').nivel, 0);
  assert.equal(forcaDaSenha('somenteletras').nivel, 0);
  assert.equal(forcaDaSenha('12345678').nivel, 0);
});

test('senha melhora com tamanho, maiúsculas e símbolos', () => {
  assert.equal(forcaDaSenha('cofrelo123').nivel, 1);
  assert.equal(forcaDaSenha('Cofrelo123').nivel, 2);
  assert.equal(forcaDaSenha('Cofrelo#2026!').nivel, 3);
});
