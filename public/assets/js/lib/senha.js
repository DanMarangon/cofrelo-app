/**
 * Força da senha para o medidor dos formulários de cadastro e de troca de senha.
 * A regra mínima (RN01) é a mesma do PHP: 8 caracteres, com letras e números.
 */

export function forcaDaSenha(senha) {
  const texto = String(senha ?? '');
  const atendeMinimo = texto.length >= 8 && /[A-Za-z]/.test(texto) && /\d/.test(texto);
  let pontos = 0;
  if (texto.length >= 8) pontos++;
  if (texto.length >= 12) pontos++;
  if (/[a-z]/.test(texto) && /[A-Z]/.test(texto)) pontos++;
  if (/\d/.test(texto)) pontos++;
  if (/[^A-Za-z0-9]/.test(texto)) pontos++;

  if (!atendeMinimo) return { nivel: 0, rotulo: 'Fraca: use 8 caracteres ou mais, com letras e números', percentual: Math.min(25, texto.length * 3) };
  if (pontos <= 2) return { nivel: 1, rotulo: 'Razoável', percentual: 50 };
  if (pontos === 3) return { nivel: 2, rotulo: 'Boa', percentual: 75 };
  return { nivel: 3, rotulo: 'Forte', percentual: 100 };
}
