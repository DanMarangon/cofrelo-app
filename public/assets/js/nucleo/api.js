/**
 * Cliente da API REST do Cofrelo. Toda chamada leva o cookie da sessão e o token CSRF.
 * Erros viram ErroApi com a mensagem pronta para mostrar e os erros de cada campo.
 */

const lerMeta = (nome) => document.querySelector(`meta[name="${nome}"]`)?.content ?? '';
const base = () => lerMeta('base-url').replace(/\/$/, '');

export class ErroApi extends Error {
  constructor(mensagem, status, campos = {}) {
    super(mensagem);
    this.status = status;
    this.campos = campos;
  }
}

export function urlDaApi(caminho, parametros = {}) {
  const url = new URL(base() + caminho, window.location.origin);
  for (const [chave, valor] of Object.entries(parametros ?? {})) {
    if (valor !== '' && valor !== null && valor !== undefined) url.searchParams.set(chave, valor);
  }
  return url.toString();
}

async function requisitar(metodo, caminho, { parametros, corpo } = {}) {
  const cabecalhos = { Accept: 'application/json', 'X-CSRF-Token': lerMeta('csrf-token') };
  if (corpo !== undefined) cabecalhos['Content-Type'] = 'application/json';

  let resposta;
  try {
    resposta = await fetch(urlDaApi(caminho, parametros), {
      method: metodo,
      credentials: 'same-origin',
      headers: cabecalhos,
      body: corpo !== undefined ? JSON.stringify(corpo) : undefined,
    });
  } catch {
    throw new ErroApi('Sem conexão com o servidor. Confira a internet e tente de novo.', 0);
  }

  if (resposta.status === 401) {
    window.location.href = `${base()}/login`;
    throw new ErroApi('Sua sessão terminou. Entre de novo.', 401);
  }
  if (resposta.status === 204) return null;

  const dados = await resposta.json().catch(() => null);
  if (!resposta.ok) {
    throw new ErroApi(dados?.erro ?? 'Não foi possível concluir. Tente de novo.', resposta.status, dados?.campos ?? {});
  }
  return dados;
}

export const api = {
  get: (caminho, parametros) => requisitar('GET', caminho, { parametros }),
  post: (caminho, corpo = {}) => requisitar('POST', caminho, { corpo }),
  put: (caminho, corpo = {}) => requisitar('PUT', caminho, { corpo }),
  excluir: (caminho) => requisitar('DELETE', caminho),
  /** Arquivos (CSV): o navegador baixa pelo próprio link, já com o cookie da sessão. */
  baixar: (caminho, parametros) => { window.location.href = urlDaApi(caminho, parametros); },
};

// Categorias e contas quase não mudam: ficam guardadas para não buscar de novo a cada modal aberto.
let categoriasGuardadas = null;
let contasGuardadas = null;

export function carregarCategorias() {
  categoriasGuardadas ??= api.get('/api/categorias').then((resposta) => resposta.categorias);
  return categoriasGuardadas;
}

export function carregarContas({ atualizar = false } = {}) {
  if (atualizar || !contasGuardadas) {
    contasGuardadas = api.get('/api/contas').then((resposta) => resposta.contas);
  }
  return contasGuardadas;
}

document.addEventListener('cofrelo:dados-alterados', () => { contasGuardadas = null; });
