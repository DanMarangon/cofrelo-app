# Tecnologias do Cofrelo

O que cada tecnologia usada no projeto é, em uma frase, e o que ela soma à qualidade do Cofrelo.

## Interface (o que a pessoa vê)

| Tecnologia | O que é | O que soma ao Cofrelo |
|---|---|---|
| **HTML5 semântico** | A estrutura das páginas, com marcações que dizem o que cada parte é (menu, conteúdo, formulário). | Leitores de tela e o teclado navegam pelo sistema sem esforço, e as páginas passam na auditoria de acessibilidade. |
| **CSS3 com variáveis, grid e flexbox** | A aparência das páginas, com as cores do protótipo guardadas em variáveis. | O visual fica igual ao protótipo do Figma, muda num lugar só e se adapta do celular ao computador. |
| **JavaScript em módulos, sem framework** | A parte que reage na tela, dividida em arquivos pequenos por página e por componente. | Modais, filtros, simulador e gráficos funcionam sem recarregar a página, e cada um dos oito usos pedidos pela disciplina aparece de forma clara no código. |
| **SVG** | Desenhos feitos de formas e linhas, que o navegador monta a partir do código. | Os gráficos e o mapa de gastos são desenhados pelo próprio sistema, ficam nítidos em qualquer tela e não dependem de bibliotecas externas. |
| **Figma** | Ferramenta de design onde o protótipo das telas foi desenhado. | A interface foi pensada antes de ser programada, e os comentários do protótipo viraram funcionalidades. |

## Servidor e dados

| Tecnologia | O que é | O que soma ao Cofrelo |
|---|---|---|
| **PHP 8.3** | A linguagem que roda no servidor, com tipos estritos e classes somente leitura. | Recebe os pedidos, aplica as regras de negócio e devolve as páginas e a API; os tipos estritos evitam erros silenciosos. |
| **Arquitetura em camadas** | Organização do código em controladores, serviços, repositórios e modelos. | Cada arquivo tem um papel só, e quem lê sabe onde procurar uma regra, uma consulta ou uma tela. |
| **API REST com JSON** | Endereços /api/... que recebem e devolvem dados, em vez de páginas. | As páginas e qualquer ferramenta (curl, Postman) usam as mesmas operações, e é isso que os testes de integração verificam. |
| **MySQL 8.4** | O banco de dados relacional onde tudo fica guardado. | Chaves estrangeiras impedem dados órfãos, DECIMAL guarda dinheiro sem erro de arredondamento e os índices deixam as consultas rápidas. |
| **PDO com consultas preparadas** | A forma do PHP falar com o banco separando o SQL dos valores. | Fecha a porta para SQL injection: nada que a pessoa digita vira comando no banco. |
| **Composer** | O gerenciador de dependências do PHP. | Instala as ferramentas de teste e de análise nas versões travadas do composer.lock e carrega as classes sozinho (PSR-4). |
| **Apache** | O servidor web dentro do container. | Entrega os arquivos estáticos direto e manda todo o resto para o PHP, rodando sem privilégios de administrador. |
| **APCu** | Uma memória rápida compartilhada entre as requisições do PHP. | Guarda os contadores das métricas e as tentativas de login sem precisar ir ao banco. |

## Qualidade

| Tecnologia | O que é | O que soma ao Cofrelo |
|---|---|---|
| **PHPUnit** | Framework de testes automatizados do PHP. | 65 testes conferem as regras de negócio e a API a cada alteração; um erro de cálculo é pego antes de chegar ao usuário. |
| **node --test** | O executor de testes que já vem no Node.js. | Testa os cálculos e formatações do JavaScript sem instalar nada a mais. |
| **PHPStan** | Analisa o código PHP sem executá-lo, procurando erros de tipo e chamadas erradas. | Encontra problemas que os testes podem não cobrir, como uma variável que pode estar vazia. |
| **axe-core** | Verificador automático das regras de acessibilidade WCAG. | Garante contraste, nomes nos botões e navegação pelo teclado em todas as páginas, no computador e no celular. |

## Segurança

| Tecnologia | O que é | O que soma ao Cofrelo |
|---|---|---|
| **bcrypt, CSRF, CSP e sessão protegida** | Proteções da aplicação: senhas com hash, token em cada alteração, política de conteúdo e cookie inacessível ao JavaScript. | Senhas vazadas não são legíveis, outro site não consegue agir em nome do usuário e scripts injetados não rodam. |
| **Limite de tentativas de login** | Bloqueio temporário depois de várias senhas erradas. | Torna inviável descobrir uma senha por tentativa e erro. |
| **Gitleaks** | Procura senhas e chaves esquecidas no histórico do Git. | Impede que uma credencial chegue ao repositório sem ninguém perceber. |
| **composer audit, Trivy e Dependabot** | Verificam vulnerabilidades conhecidas nas dependências e na imagem Docker, e sugerem atualizações. | Uma biblioteca vulnerável é avisada (ou bloqueia o pipeline) antes de ir para o ar. |
| **zizmor e hadolint** | Revisam a segurança dos workflows do GitHub Actions e as boas práticas do Dockerfile. | O próprio pipeline e a imagem também passam por revisão, não só o código. |

## Entrega e operação (DevOps)

| Tecnologia | O que é | O que soma ao Cofrelo |
|---|---|---|
| **Git e GitFlow** | Controle de versões, com branches para funcionalidades, versões e correções. | Cada mudança fica registrada e revisável, e a main só recebe versões entregues e marcadas com tag. |
| **GitHub e Pull Requests** | Onde o código fica e onde as mudanças são propostas e revisadas. | Nada entra na develop ou na main sem passar por um Pull Request com o pipeline verde. |
| **GitHub Actions** | Automação que roda a cada envio de código. | Valida, testa, verifica a segurança e constrói a imagem sozinho; uma falha deixa o pipeline vermelho e barra a publicação. |
| **Docker** | Empacota a aplicação com tudo de que ela precisa numa imagem. | O sistema roda igual em qualquer computador, inclusive no dia da apresentação, com um único comando. |
| **Docker Compose** | Sobe vários containers juntos a partir de um arquivo. | Aplicação, banco, Prometheus e Grafana sobem com docker compose up -d, já ligados entre si. |
| **GitHub Container Registry e Releases** | Onde as imagens versionadas e os pacotes de cada versão ficam guardados. | Cada versão tem uma imagem rastreável (versão e commit) e pode ser baixada e implantada a qualquer momento. |
| **Script de implantação com rollback** | Script que troca a versão em execução e confere se ela ficou saudável. | Se a versão nova falhar, o sistema volta sozinho para a anterior em menos de um minuto. |

## Monitoramento

| Tecnologia | O que é | O que soma ao Cofrelo |
|---|---|---|
| **/health e logs em JSON** | Um endereço que diz se a aplicação e o banco estão bem, e registros estruturados de cada evento. | Uma falha aparece em segundos e o log mostra o que aconteceu, com o id de cada requisição. |
| **Prometheus** | Coleta números da aplicação de tempos em tempos e dispara alertas. | Avisa quando o banco cai, quando há muitos erros ou quando o sistema fica lento. |
| **Grafana** | Painéis visuais montados a partir das métricas. | Mostra num relance a saúde do Cofrelo: banco, versão em execução, erros e tempo de resposta. |

## Documentação

| Tecnologia | O que é | O que soma ao Cofrelo |
|---|---|---|
| **Python, Word, PowerPoint e Edge** | Scripts que geram os documentos, os slides e as capturas a partir dos dados do projeto. | Toda a documentação pode ser refeita com um comando quando algo muda, sem montar nada à mão. |
