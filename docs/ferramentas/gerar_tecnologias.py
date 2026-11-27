"""Gera o documento "Tecnologias do Cofrelo": o que é cada tecnologia, em uma frase, e o que ela soma ao projeto.

Uso: python docs/ferramentas/gerar_tecnologias.py
Saída: docs/TECNOLOGIAS.md e docs/entregas/Tecnologias do Cofrelo.pdf
"""

from __future__ import annotations

import html
import sys
import tempfile
from datetime import datetime
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import conteudo as c  # noqa: E402
from navegador import Navegador  # noqa: E402

# (área, [(tecnologia, o que é, o que soma ao Cofrelo)])
AREAS = [
    ("Interface (o que a pessoa vê)", [
        ("HTML5 semântico", "A estrutura das páginas, com marcações que dizem o que cada parte é (menu, conteúdo, formulário).",
         "Leitores de tela e o teclado navegam pelo sistema sem esforço, e as páginas passam na auditoria de acessibilidade."),
        ("CSS3 com variáveis, grid e flexbox", "A aparência das páginas, com as cores do protótipo guardadas em variáveis.",
         "O visual fica igual ao protótipo do Figma, muda num lugar só e se adapta do celular ao computador."),
        ("JavaScript em módulos, sem framework", "A parte que reage na tela, dividida em arquivos pequenos por página e por componente.",
         "Modais, filtros, simulador e gráficos funcionam sem recarregar a página, e cada um dos oito usos pedidos pela disciplina aparece de forma clara no código."),
        ("SVG", "Desenhos feitos de formas e linhas, que o navegador monta a partir do código.",
         "Os gráficos e o mapa de gastos são desenhados pelo próprio sistema, ficam nítidos em qualquer tela e não dependem de bibliotecas externas."),
        ("Figma", "Ferramenta de design onde o protótipo das telas foi desenhado.",
         "A interface foi pensada antes de ser programada, e os comentários do protótipo viraram funcionalidades."),
    ]),
    ("Servidor e dados", [
        ("PHP 8.3", "A linguagem que roda no servidor, com tipos estritos e classes somente leitura.",
         "Recebe os pedidos, aplica as regras de negócio e devolve as páginas e a API; os tipos estritos evitam erros silenciosos."),
        ("Arquitetura em camadas", "Organização do código em controladores, serviços, repositórios e modelos.",
         "Cada arquivo tem um papel só, e quem lê sabe onde procurar uma regra, uma consulta ou uma tela."),
        ("API REST com JSON", "Endereços /api/... que recebem e devolvem dados, em vez de páginas.",
         "As páginas e qualquer ferramenta (curl, Postman) usam as mesmas operações, e é isso que os testes de integração verificam."),
        ("MySQL 8.4", "O banco de dados relacional onde tudo fica guardado.",
         "Chaves estrangeiras impedem dados órfãos, DECIMAL guarda dinheiro sem erro de arredondamento e os índices deixam as consultas rápidas."),
        ("PDO com consultas preparadas", "A forma do PHP falar com o banco separando o SQL dos valores.",
         "Fecha a porta para SQL injection: nada que a pessoa digita vira comando no banco."),
        ("Composer", "O gerenciador de dependências do PHP.",
         "Instala as ferramentas de teste e de análise nas versões travadas do composer.lock e carrega as classes sozinho (PSR-4)."),
        ("Apache", "O servidor web dentro do container.",
         "Entrega os arquivos estáticos direto e manda todo o resto para o PHP, rodando sem privilégios de administrador."),
        ("APCu", "Uma memória rápida compartilhada entre as requisições do PHP.",
         "Guarda os contadores das métricas e as tentativas de login sem precisar ir ao banco."),
    ]),
    ("Qualidade", [
        ("PHPUnit", "Framework de testes automatizados do PHP.",
         "65 testes conferem as regras de negócio e a API a cada alteração; um erro de cálculo é pego antes de chegar ao usuário."),
        ("node --test", "O executor de testes que já vem no Node.js.",
         "Testa os cálculos e formatações do JavaScript sem instalar nada a mais."),
        ("PHPStan", "Analisa o código PHP sem executá-lo, procurando erros de tipo e chamadas erradas.",
         "Encontra problemas que os testes podem não cobrir, como uma variável que pode estar vazia."),
        ("axe-core", "Verificador automático das regras de acessibilidade WCAG.",
         "Garante contraste, nomes nos botões e navegação pelo teclado em todas as páginas, no computador e no celular."),
    ]),
    ("Segurança", [
        ("bcrypt, CSRF, CSP e sessão protegida", "Proteções da aplicação: senhas com hash, token em cada alteração, política de conteúdo e cookie inacessível ao JavaScript.",
         "Senhas vazadas não são legíveis, outro site não consegue agir em nome do usuário e scripts injetados não rodam."),
        ("Limite de tentativas de login", "Bloqueio temporário depois de várias senhas erradas.",
         "Torna inviável descobrir uma senha por tentativa e erro."),
        ("Gitleaks", "Procura senhas e chaves esquecidas no histórico do Git.",
         "Impede que uma credencial chegue ao repositório sem ninguém perceber."),
        ("composer audit, Trivy e Dependabot", "Verificam vulnerabilidades conhecidas nas dependências e na imagem Docker, e sugerem atualizações.",
         "Uma biblioteca vulnerável é avisada (ou bloqueia o pipeline) antes de ir para o ar."),
        ("zizmor e hadolint", "Revisam a segurança dos workflows do GitHub Actions e as boas práticas do Dockerfile.",
         "O próprio pipeline e a imagem também passam por revisão, não só o código."),
    ]),
    ("Entrega e operação (DevOps)", [
        ("Git e GitFlow", "Controle de versões, com branches para funcionalidades, versões e correções.",
         "Cada mudança fica registrada e revisável, e a main só recebe versões entregues e marcadas com tag."),
        ("GitHub e Pull Requests", "Onde o código fica e onde as mudanças são propostas e revisadas.",
         "Nada entra na develop ou na main sem passar por um Pull Request com o pipeline verde."),
        ("GitHub Actions", "Automação que roda a cada envio de código.",
         "Valida, testa, verifica a segurança e constrói a imagem sozinho; uma falha deixa o pipeline vermelho e barra a publicação."),
        ("Docker", "Empacota a aplicação com tudo de que ela precisa numa imagem.",
         "O sistema roda igual em qualquer computador, inclusive no dia da apresentação, com um único comando."),
        ("Docker Compose", "Sobe vários containers juntos a partir de um arquivo.",
         "Aplicação, banco, Prometheus e Grafana sobem com docker compose up -d, já ligados entre si."),
        ("GitHub Container Registry e Releases", "Onde as imagens versionadas e os pacotes de cada versão ficam guardados.",
         "Cada versão tem uma imagem rastreável (versão e commit) e pode ser baixada e implantada a qualquer momento."),
        ("Script de implantação com rollback", "Script que troca a versão em execução e confere se ela ficou saudável.",
         "Se a versão nova falhar, o sistema volta sozinho para a anterior em menos de um minuto."),
    ]),
    ("Monitoramento", [
        ("/health e logs em JSON", "Um endereço que diz se a aplicação e o banco estão bem, e registros estruturados de cada evento.",
         "Uma falha aparece em segundos e o log mostra o que aconteceu, com o id de cada requisição."),
        ("Prometheus", "Coleta números da aplicação de tempos em tempos e dispara alertas.",
         "Avisa quando o banco cai, quando há muitos erros ou quando o sistema fica lento."),
        ("Grafana", "Painéis visuais montados a partir das métricas.",
         "Mostra num relance a saúde do Cofrelo: banco, versão em execução, erros e tempo de resposta."),
    ]),
    ("Documentação", [
        ("Python, Word, PowerPoint e Edge", "Scripts que geram os documentos, os slides e as capturas a partir dos dados do projeto.",
         "Toda a documentação pode ser refeita com um comando quando algo muda, sem montar nada à mão."),
    ]),
]


def gerar_markdown() -> Path:
    linhas = ["# Tecnologias do Cofrelo", "",
              "O que cada tecnologia usada no projeto é, em uma frase, e o que ela soma à qualidade do Cofrelo.", ""]
    for area, itens in AREAS:
        linhas += [f"## {area}", "", "| Tecnologia | O que é | O que soma ao Cofrelo |", "|---|---|---|"]
        linhas += [f"| **{nome}** | {oque} | {soma} |" for nome, oque, soma in itens]
        linhas.append("")
    arquivo = c.DOCS / "TECNOLOGIAS.md"
    arquivo.write_bytes("\n".join(linhas).encode("utf-8"))
    return arquivo


def gerar_pdf() -> Path:
    p = c.projeto()
    e = html.escape
    secoes = "".join(
        f"<h2>{e(area)}</h2>" + "".join(
            f'<div class="item"><h3>{e(nome)}</h3><p class="oque">{e(oque)}</p><p class="soma"><b>No Cofrelo:</b> {e(soma)}</p></div>'
            for nome, oque, soma in itens)
        for area, itens in AREAS)
    pagina_html = f"""<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Tecnologias do {e(p['projeto'])}</title><style>
@page {{ size: A4; margin: 16mm 16mm 18mm; }}
body {{ font-family: Inter, "Segoe UI", Arial, sans-serif; color: #182D26; font-size: 10.5pt; line-height: 1.45; margin: 0; }}
.topo {{ background: #123F2E; color: #fff; border-radius: 12px; padding: 22px 24px; margin-bottom: 16px; }}
.topo h1 {{ margin: 0 0 4px; font-size: 22pt; }} .topo p {{ margin: 0; color: #DBEEA3; }}
h2 {{ color: #1D704F; font-size: 13.5pt; margin: 18px 0 8px; break-after: avoid; }}
.item {{ background: #F4F8F2; border: 1px solid #DDE7DA; border-radius: 8px; padding: 9px 12px; margin: 0 0 8px; break-inside: avoid; }}
h3 {{ margin: 0 0 3px; font-size: 11pt; }} p {{ margin: 0 0 3px; }} .oque {{ color: #4E5953; }}
</style></head><body>
<div class="topo"><h1>Tecnologias do {e(p['projeto'])}</h1><p>O que cada tecnologia é, em uma frase, e o que ela soma à qualidade do projeto</p></div>
{secoes}</body></html>"""
    temporario = Path(tempfile.gettempdir()) / "cofrelo-tecnologias.html"
    temporario.write_text(pagina_html, encoding="utf-8")
    pdf = c.ENTREGAS / f"Tecnologias do {p['projeto']}.pdf"
    with Navegador(1200, 900) as nav:
        nav.abrir(temporario.resolve().as_uri(), pausa=1.0)
        nav.pdf(pdf, displayHeaderFooter=True, headerTemplate="<span></span>",
                footerTemplate=('<div style="font-size:8px;width:100%;text-align:center;color:#5B655F;font-family:Arial">'
                                f'{e(p["projeto"])} · Tecnologias · {datetime.now():%d/%m/%Y} · página <span class="pageNumber"></span>'
                                ' de <span class="totalPages"></span></div>'))
    return pdf


if __name__ == "__main__":
    print(gerar_markdown())
    print(gerar_pdf())
