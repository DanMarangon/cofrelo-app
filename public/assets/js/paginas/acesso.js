/** Telas de entrar e criar conta: mostrar senha, conta de demonstração e validação antes de enviar. */

import { forcaDaSenha } from '../lib/senha.js';

const mostrarSenha = document.getElementById('mostrar-senha');
mostrarSenha?.addEventListener('change', () => {
  document.getElementById('acesso-senha').type = mostrarSenha.checked ? 'text' : 'password';
});

document.getElementById('usar-demo')?.addEventListener('click', () => {
  document.getElementById('acesso-email').value = 'mariana@cofrelo.app';
  document.getElementById('acesso-senha').value = 'cofrelo123';
  document.getElementById('acesso-senha').focus();
});

function marcarErro(formulario, campo, mensagem) {
  const bloco = formulario.querySelector(`[data-campo="${campo}"]`);
  bloco?.classList.toggle('com-erro', Boolean(mensagem));
  const alvo = formulario.querySelector(`[data-erro="${campo}"]`);
  if (alvo) alvo.textContent = mensagem;
}

// Entrar: confere os campos antes de enviar ao servidor.
document.getElementById('formulario-acesso')?.addEventListener('submit', (evento) => {
  const formulario = evento.currentTarget;
  const email = formulario.email.value.trim();
  const erroEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? '' : 'Informe um e-mail válido.';
  const erroSenha = formulario.senha.value ? '' : 'Informe a senha.';
  marcarErro(formulario, 'email', erroEmail);
  marcarErro(formulario, 'senha', erroSenha);
  if (erroEmail || erroSenha) evento.preventDefault();
});

// Criar conta: medidor de força e conferência das regras (as mesmas que o PHP valida).
const cadastro = document.getElementById('formulario-cadastro');
if (cadastro) {
  const barra = document.querySelector('#forca-senha span');
  const texto = document.getElementById('forca-senha-texto');
  const cores = ['#C2410C', '#C98A0B', '#8CB79C', '#1D704F'];
  cadastro.senha.addEventListener('input', () => {
    const { nivel, rotulo, percentual } = forcaDaSenha(cadastro.senha.value);
    barra.style.width = `${percentual}%`;
    barra.style.background = cores[nivel];
    texto.textContent = cadastro.senha.value ? `Força da senha: ${rotulo}` : 'Use pelo menos 8 caracteres, com letras e números.';
  });

  cadastro.addEventListener('submit', (evento) => {
    const erros = {
      nome: cadastro.nome.value.trim().length >= 2 ? '' : 'Informe seu nome.',
      email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cadastro.email.value.trim()) ? '' : 'Informe um e-mail válido.',
      senha: forcaDaSenha(cadastro.senha.value).nivel > 0 ? '' : 'A senha precisa ter pelo menos 8 caracteres, com letras e números.',
      confirmacao: cadastro.senha.value === cadastro.confirmacao.value ? '' : 'A confirmação não é igual à senha.',
    };
    Object.entries(erros).forEach(([campo, mensagem]) => marcarErro(cadastro, campo, mensagem));
    if (Object.values(erros).some(Boolean)) evento.preventDefault();
  });
}
