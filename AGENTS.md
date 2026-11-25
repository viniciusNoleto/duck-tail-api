# DuckTail API — instruções para agentes

API do DuckTail em **Laravel 13** (PHP 8.5): descoberta turística em que cada ponto turístico é um nó de um grafo. O domínio (lugares, localidades em árvore, categorias, temas, empresas de turismo e seus passeios, visitas, roteiros) fica no **Neo4j 5** e é servido por **GraphQL** (Lighthouse, `POST /graphql`); usuários e sessão ficam no **PostgreSQL 16**, por **REST** (`/api/auth/*`, `/api/users`, `/api/registration-requests`), no mesmo padrão do white-label-stock-api. O web (Next.js) é o projeto irmão `../duck-tail-web`: **`graphql/schema.graphql` e o JSON do REST são consumidos por ele sem adaptação** (o graphql-codegen lê o arquivo do schema). Não mude tipo, campo, argumento, formato, nome ou status sem alinhar o web.

O README é a documentação para pessoas (contrato, garantias e que teste prova cada uma, operação) e `docs/graph.md` explica o mecanismo difícil (modelo do grafo, GraphQL → Cypher em lote, sincronização Postgres ↔ Neo4j); este arquivo é o resumo das convenções para quem escreve código aqui. Em caso de divergência, o comportamento descrito no README e provado pelos testes vale; corrija o texto que estiver errado.

**Rules detalhadas** (regra, porquê, modelo de código e checklist) em [`.claude/rules/`](.claude/rules/README.md); o Claude Code carrega cada uma quando a tarefa toca os arquivos do `paths:` dela. Outros agentes: abram o arquivo do assunto antes de mexer.

| Rule | Quando abrir |
|---|---|
| [01 Arquitetura](.claude/rules/01-arquitetura-em-camadas.md) | camada nova, Controller/Resolver/Action/Query/Resource |
| [02 Respostas e erros](.claude/rules/02-respostas-e-erros.md) | status, mensagem, exceção, erro GraphQL |
| [03 Validação e dados](.claude/rules/03-validacao-e-dados.md) | FormRequest, DTO, `fromArgs`, paginação, busca |
| [04 Banco e migrations](.claude/rules/04-banco-e-migrations.md) | migration, constraint, SQLSTATE, `GraphSchema` |
| [05 Concorrência](.claude/rules/05-concorrencia-e-transacoes.md) | transação, lock, retry, `Graph::transaction` |
| [06 Grafo e GraphQL](.claude/rules/06-grafo-e-graphql.md) | schema, resolver, Cypher, lote (N+1), comandos do grafo, seeder |
| [07 Autenticação](.claude/rules/07-autenticacao-e-seguranca.md) | tokens, senha, rate limit, sessão no `/graphql` |
| [08 Testes](.claude/rules/08-testes.md) | todo teste novo |
| [09 Qualidade e CI](.claude/rules/09-qualidade-e-ci.md) | CI, Dockerfile, ferramentas |
| [10 Operação](.claude/rules/10-operacao-e-deploy.md) | Makefile, `scripts/`, deploy, logs, Neo4j |
| [11 Documentação](.claude/rules/11-documentacao.md) | README, AGENTS.md, `docs/` |

PHP/Composer só existem nos containers: rode tudo com `make` (ou `docker compose -f docker-compose.local.yml exec php-fpm …`). **Commit, push e branch só quando o dono pedir**: deixe as mudanças no working tree com `make ci` verde.

## Camadas (Actions + DTOs + Queries, sem Repository)

```
REST:    routes/api.php → Controller → FormRequest → DTO → Action → Model (Eloquent)        (leituras: Eloquent ou Query)
                              ↓
                        Resource + Response::successResponse / returnError

GraphQL: graphql/schema.graphql → {Tipo}\{Campo}Resolver → DTO::fromArgs → Action (Cypher de escrita) | Query (Cypher de leitura) → Graph
                              ↓
                        arrays (projeções de Cypher::…) + ApiErrorHandler
```

| Camada | Onde | Regras |
|---|---|---|
| Controller | `app/Http/Controllers/{Domínio}/` | Estende `App\Base\Http\Controllers\BaseController`. Actions/Queries injetadas no método. Retorno nativo `: JsonResponse|Response`. Corpo em `try { return self::successResponse(...) } catch (Throwable $e) { return self::returnError($e, 'messages.{recurso}.{ação}_error'); }`. Só monta o DTO, chama a Action e devolve o Resource. |
| FormRequest | `app/Http/Requests/{Recurso}/` | Estende `BaseRequest`. `messages()` devolve **chaves** de `lang/*/messages.php`; a primeira falha vira a mensagem do 422. `*` nas chaves não atravessa pontos. |
| Resolver | `app/GraphQL/Resolvers/{Tipo}/{Campo}Resolver.php` | Ligado por convenção (`App\Base\GraphQL\ResolverProvider`; o schema **não tem diretivas**). `final`, só `__invoke($root, $args, $context, $info)`. Sessão por `GraphAuth::user()`/`require()`. Monta o DTO, chama **uma** Query/Action, devolve arrays. **Nunca** Cypher, `Graph` ou o driver (o `ArchitectureTest` confere). Campo aninhado de lista/objeto usa `BatchLoader` (N+1). |
| DTO | `app/Data/{Domínio}/…` | `Spatie\LaravelData\Data`. REST: `fromRequest()` lendo `validated()`. GraphQL: `fromArgs()` validando com `ArgsValidator` (mensagens por chave, mesma semântica do `BaseRequest`). Normalização (trim, minúsculas, km → m, cursor → offset, tetos) aqui; regra de negócio não. |
| Action | `app/Actions/{Domínio}/{Recurso}/{Ação}{Recurso}Action.php` (grafo: `app/Actions/Graph/{Assunto}/`) | `final class`, um único método público `handle()`. Recebe DTO/ids/Models, nunca `Request`; retorna Model, array, DTO ou `void`. Postgres: `DB::transaction(…, attempts: config('database.transaction_attempts'))` (o `ArchitectureTest` confere). Grafo: toda escrita; várias escritas numa `Graph::transaction()`; regra violada → `ApiException` (desfaz). |
| Query | `app/Queries/{Domínio}/` (grafo: `app/Queries/Graph/{Assunto}/`; externo: `app/Queries/Wikidata/`) | Leitura. `final`, sufixo `Query`, método `get()`. Cypher só de leitura (o `ArchitectureTest` procura `CREATE/MERGE/SET/DELETE`). Query de campo em lote recebe `list $ids`, faz `UNWIND $ids AS id …` e devolve `[id => valor]`. |
| Graph | `app/Base/Graph/` | `Graph` é o único que fala com o `laudis/neo4j-php-client` (`read`, `write`, `transaction`, `writeInBatches`, `writeUntilDone`, `ping`); `Cypher` tem as projeções de cada tipo; `GraphSchema` as constraints/índices. Não é repositório: nada de método de domínio. |
| Model | `app/Models/{Domínio}/` | Só Postgres (`User`, tokens, pedidos de cadastro). `$table`, `$fillable`, `casts()`, relações e scopes tipados. Busca por id da rota: `X::findOrFailByKey($id, 'messages.{recurso}.not_found')`. |
| Resource | `app/Http/Resources/{Domínio}/` | Shape exato do JSON REST (snake_case, ordem das chaves). |
| Enum | `app/Enum/{Domínio}/{Nome}Enum.php` | Todo conjunto fixo: `CompanyKindEnum` (agency, guide, lodging, transport), `RelationReasonEnum`, `UserPlaceLinkEnum`, `ApiLocaleEnum`. |
| Helpers | `app/Base/Helpers/*.php` | `api_message`, `parse_id`, `business_today`, `hash_token`, `escape_like`, `is_*_violation`, `is_concurrency_failure`, `encode_cursor`/`decode_cursor`, `escape_lucene`/`lucene_prefix_query`, `is_graph_constraint_violation`. |

## Grafo

- Nós: `Place {id, name, description, location: point, image, wikidataId, authors[], styles[]}`, `Location {id, name, kind (country|state|region|city), location?, wikidataId}`, `Tour {id, name, description, durationMinutes, price, currency}`, `Category {id (UUID opaco, nunca o nome), name (pt-br), nameEsMx?, nameEnUs?, image?}` (o GraphQL expõe `names { ptBr esMx enUs }`, com fallback no pt-br), `Theme {name, description}`, `Company {id, name, description, website, phone, kind}`, `User {id, name}` (espelho do Postgres, mesmo `id` inteiro), `Itinerary {id, title, isPublic, createdAt}`.
- Relações: `LOCATED_IN` (Place → Location mais específica), `WITHIN` (Location → Location pai), `HAS_CATEGORY`, `PART_OF`, `RELATED_TO {kind, value}` (author/style automáticas, curated à mão), `NEAR {distanceM}` (Place↔Place), `BASED_IN`/`SERVES` (Company → Location), `OPERATES_AT` (Company → Place), `OFFERS` (Company → Tour), `STOPS_AT {order}` (Tour → Place), `VISITED {date, rating}`, `WANTS_TO_VISIT {since}`, `FOLLOWS {since}`, `CREATED`, `INCLUDES {day, order}`.
- Cypher: **valores sempre como parâmetro**; interpola-se só projeção de `Cypher::…`, constante ou valor de enum. Projete primitivos (`p {.id, latitude: p.location.latitude}`, `toString(v.date)`), nunca nó/ponto cru. Toda lista tem `LIMIT`; todo raio do cliente tem teto (`RadiusDTO`). Busca textual só por `lucene_prefix_query()` no índice `place_search` (`standard-folding`: sem acento).
- O motivo de `RELATED_TO` é guardado como tipo + valor; o texto sai de `messages.related.*` na leitura.
- "Hoje" de uma visita é `business_today()` (fuso `BUSINESS_TIMEZONE`).
- Categoria nasce no `MERGE` por nome da importação/seeders com `ON CREATE SET k.id = randomUUID()`; `AssignCategoryIdsAction` dá UUID às que ficaram sem id ou ainda têm o slug antigo (rodado por `ducktail:import`, `ducktail:graph-migrate` e os seeders). Leituras usam `Cypher::category()` (id cai no nome se faltar).
- Constraints/índices: `GraphSchema` (só acrescentar), aplicados por `php artisan ducktail:graph-migrate` (`make migrate` e deploy).
- Dados: Wikidata (CC0) por `ducktail:import --area=BR-MG` (qualquer código ISO 3166-2) (`MERGE` por `wikidataId`, id público = QID), `ducktail:proximity --radius=3000` e `ducktail:relate` (idempotentes, em lotes). `GraphSeeder`: grafo de exemplo com chaves `seed-…` nas constantes, gravadas como id opaco `seed_id()` (UUID v5; `AssignSeedIdsAction` converte grafo antigo no `graph-migrate`) — o web põe o id na URL, nunca um slug — e empresas **fictícias** (`.example`).
- Sincronização: `UserObserver` (depois do commit) cria/renomeia/apaga o nó `User`; falha no Neo4j não derruba o Postgres (`Log::warning('graph.user_sync_failed')`); as mutations fazem `MERGE` do nó; `ducktail:sync-users` (diário) reconcilia.

## Respostas

- REST: envelope sempre `{ success, message, data }`; `message` = `api_message($key)` → `{ "pt-br", "es-mx", "en-us" }`. Mensagem nova entra nos **três** `lang/{pt_BR,es_MX,en}/messages.php` (há teste que compara as chaves).
- REST, erros sempre com `data: null`. Regra de negócio: `throw new ApiException('messages.x.y', $status, $replace)`. Status: 400 JSON malformado · 401 sem sessão · 403 sem permissão/cadastro pendente · 404 · 409 conflito · 422 validação/regra · 429 rate limit · 500 · 503 manutenção.
- GraphQL: `ApiErrorHandler` → `message` traduzida + `extensions { code, category, status, key, messages }`; `code`: `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `CONFLICT`, `VALIDATION`, `INTERNAL`. `place/location/company/tour(id)` inexistente → `null`; `toursThrough` ignora ids desconhecidos; id para agir inexistente → `NOT_FOUND`. 500 vira `INTERNAL` logado, sem trace.
- Toda resposta leva `X-Request-Id`. `ApiException` não é logada.

## Sessão

- Tokens opacos (`generate_opaque_token`), só o `hash_token` no banco. Login e refresh devolvem a sessão no corpo (`SessionResource`: `user {id, name, email, is_admin}`, `access_token`, `refresh_token`, `expires_in`, `refresh_expires_in`). A API não lê nem grava cookies.
- REST protegido: `Route::middleware('auth')`. `/graphql`: `OptionalAuthenticateMiddleware` (sem header → anônimo; token inválido → **401 no envelope**; válido → usuário). Campos que exigem sessão: `recommendations` e todas as mutations (`GraphAuth::require`); `me` sem sessão é `null`.
- Admin: `users.is_admin`, grupo `Route::middleware('admin')`. Cadastro público (`POST /api/auth/register`) cria pedido; admin aprova (vira `User` comum; o observer cria o nó) ou recusa. Login com pedido pendente → 403.
- Refresh (`POST /api/auth/token/refresh`) rotaciona com detecção de reuso e dedupe de 10 s; rotação e access token na mesma transação. Senha: `SetUserPasswordAction` encerra todas as sessões. Rate limits: `throttle:login|register|refresh|password|graphql`.

## Migrations

- Postgres: só expandem; nunca editar migration já executada; valores de `CHECK` escritos na migration; FK nova ganha índice.
- Neo4j: `GraphSchema` só acrescenta; remover é `DROP … IF EXISTS` numa release posterior.

## Qualidade

- `make ci` = `make lint` (Pint, `pint.json`) + `make analyse` (Larastan nível 5, sem baseline) + `make audit` + `make test`. O GitHub Actions roda o mesmo (`.github/workflows/ci.yml`), com Postgres e Neo4j como serviços e os testes **dentro da imagem de produção** (pcov), cobertura mínima de 95%.
- `Model::preventLazyLoading()` fora de produção.
- Versões fixas: imagens por tag exata (`neo4j:5.26.33-community`, `postgres:16.14-alpine`, `php:8.5.12-fpm`), Actions por SHA, `composer.lock`. Dependabot atualiza por PR.

## Operação

- `make deploy` (ou `REF=<tag>`): manutenção → backup (Postgres) → código → build → composer → migrate → `ducktail:graph-migrate` → caches (`config`, `route`, `lighthouse:cache`) → restart do php-fpm → no ar → tag `deploy-*`. Não mude a ordem.
- Scripts em `scripts/*.sh` (`set -euo pipefail`, `scripts/lib.sh`, shellcheck limpo); o Makefile só chama.
- `make graph-import area=BR-MG radius=3000` importa e recalcula; `make graph-sync-users`; `make cypher-shell`.
- `/up` consulta Postgres e Neo4j. Logs com `request_id` e `user_id`.
- Backup automático cobre só o Postgres (o grafo de lugares é reconstruível do Wikidata; visitas/roteiros não: limitação documentada no README).

## Testes (Pest)

- `tests/Feature/**` contra o Postgres `${DB_DATABASE}_testing` (`RefreshDatabase`) **e** o Neo4j de teste (`NEO4J_TESTING_URI`, container `neo4j-testing`), esvaziado antes de cada teste. `tests/TestCase.php` recusa rodar se o Neo4j de teste faltar ou for o de desenvolvimento.
- Helpers em `tests/Pest.php`: `accessToken()`, `failTable()`, `graphql()`, `graph()`, `graphRow()`, `graphPlace()`, `runConcurrently()`, `withoutTransactionRetries()`. Dados realistas: `$this->seed(GraphSeeder::class)`.
- `tests/Unit/ArchitectureTest.php`: Actions/Queries/Resolvers `final` com sufixo; resolvers sem Cypher/driver; driver só em `App\Base\Graph`; Queries sem escrita; Cypher sem `$dto`/`$args` interpolado; nenhum `Repository`; `attempts` em toda `DB::transaction`; sem `dd`/`env()` fora de config (as varreduras de código são heurísticas).
- `tests/Feature/GraphQL/SchemaTest.php`: schema válido, sem diretivas, com resolver para todo campo de `Query`/`Mutation`.
- Todo campo/endpoint/regra nova com teste. Rodar: `make test`.
