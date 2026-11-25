---
paths:
  - "graphql/**"
  - "app/GraphQL/**"
  - "app/Base/Graph/**"
  - "app/Base/GraphQL/**"
  - "app/Queries/Graph/**"
  - "app/Actions/Graph/**"
  - "app/Data/Graph/**"
  - "config/lighthouse.php"
  - "config/neo4j.php"
  - "routes/console.php"
  - "database/seeders/**"
---

# 06. Grafo (Neo4j) e GraphQL (Lighthouse)

## Regras

### 6.1 O schema é o contrato e fica sem diretivas

- `graphql/schema.graphql` é lido pelo web com o graphql-codegen **direto do arquivo**. Ele é SDL puro: nada de `@field`, `@guard`, `@rules`, `@paginate`… (diretiva desconhecida quebra a geração dos tipos). Um teste (`SchemaTest`) confere.
- Mudou um tipo, campo, argumento ou nulabilidade? **Avise o web antes** (é o mesmo que mudar o JSON do REST) e atualize o comentário do topo do arquivo se o formato de erro mudar.
- Cada campo é ligado por convenção a `App\GraphQL\Resolvers\{Tipo}\{Campo}Resolver` (`App\Base\GraphQL\ResolverProvider`). Campo de `Query`/`Mutation` sem classe é erro de definição; campo de outro tipo sem classe lê a chave de mesmo nome do array do pai.

### 6.2 Camadas, como no REST

```
POST /graphql → OptionalAuthenticateMiddleware → Resolver → DTO::fromArgs (ArgsValidator) → Action | Query → Graph (Cypher)
```

| Camada | Onde | Regras |
|---|---|---|
| Resolver | `app/GraphQL/Resolvers/{Tipo}/{Campo}Resolver.php` | `final`, só `__invoke($root, $args, $context, $info)`. Confere sessão (`GraphAuth::user/require`), monta o DTO, chama **uma** Query ou Action e devolve arrays. **Nunca** Cypher, `Graph` ou o driver (o `ArchitectureTest` confere). |
| DTO | `app/Data/Graph/…` | `fromArgs(array $args)` valida com `ArgsValidator::validate($args, $rules, $messages com chaves, $replace)` (o `BaseRequest` do GraphQL) e normaliza (trim, minúsculas, km → m, cursor → offset). |
| Query | `app/Queries/Graph/{Assunto}/…Query.php` | `final`, `get()`, **só leitura** (`MATCH`/`RETURN`; o `ArchitectureTest` procura `CREATE`/`MERGE`/`SET`/`DELETE`). |
| Action | `app/Actions/Graph/{Assunto}/…Action.php` | `final`, `handle()`, toda escrita. Várias escritas → `Graph::transaction()`. Regra violada → `ApiException` (desfaz a transação). |
| Graph | `app/Base/Graph/Graph.php` | Único ponto que fala com o driver (`laudis/neo4j-php-client`): `read`, `write`, `transaction`, `ping`. Não conhece o domínio. **Não é repositório** e não ganha métodos de domínio. |

### 6.3 Cypher

- **Valores sempre como parâmetro** (`$id`, `$term`). Só se interpola: projeções de `App\Base\Graph\Cypher` (`Cypher::place('p')`), constantes e valores de enum (tipo de relação não pode ser parâmetro: `UserPlaceLinkEnum`). O `ArchitectureTest` procura `{$dto…}`/`{$args…}` dentro das Queries/Actions (heurística).
- O Cypher projeta **tipos primitivos** (`p {.id, .name, latitude: p.location.latitude}`, `toString(v.date)`), nunca nó ou ponto cru: o resultado vira array sem conversão e o formato de cada tipo do schema sai de um lugar só (`Cypher`).
- Busca textual: índice `place_search` (texto completo, `standard-folding`) com `lucene_prefix_query()`, que escapa a sintaxe do Lucene (o `escape_like` do grafo). Nunca concatene o termo do usuário numa consulta Lucene.
- Distância: `point.distance(a.location, b.location)` em metros, com índice de ponto. Todo raio vindo do cliente tem teto (`RadiusDTO::fromKm($km, $default, $max)`); toda lista tem `LIMIT`.
- Relações que o produto explica (`RELATED_TO`) guardam **tipo e valor** (`kind: 'author', value: 'Aleijadinho'`); o texto sai do `lang` na leitura (`RelationReasonEnum::describe`).

### 6.4 N+1: lote por campo

Campo de lista/objeto dentro de outro tipo (`Place.location`, `Place.related`, `User.visited`…) **nunca** consulta um por um: o resolver devolve `BatchLoader::for($info, $args, fn(array $keys) => $query->get($keys…))->load($root['id'], $default)`, e a Query faz `UNWIND $ids AS id …` devolvendo `[id => valor]`. Uma instância por campo do documento e por argumentos. Exceção documentada: conexão paginada por pai (`Location.places`), uma consulta por localidade.

### 6.5 Erros

- Regra/validação/não encontrado: `throw new ApiException('messages.x.y', $status)` no DTO, Action ou resolver. O `ApiErrorHandler` devolve `message` traduzida e `extensions { code, category, status, key, messages }` (mesmo `messages` do envelope REST).
- `Query.place/location/company/tour` com id inexistente devolvem `null` (tipo anulável); campos que recebem um id para agir (`markVisited`, `suggestedItinerary`) respondem `NOT_FOUND`; busca por ids (`toursThrough`) ignora os desconhecidos.
- Sessão: sem token → anônimo; token inválido → **401 no envelope REST** (o web renova e repete). Campo que exige sessão chama `GraphAuth::require($context)` (`UNAUTHENTICATED`).
- Inesperado (Neo4j fora, bug): `INTERNAL` com `messages.server_error`, logado com `request_id`/`user_id`; nunca trace nem mensagem interna na resposta (`debug = NONE`).
- Os middlewares globais `TrimStrings`/`ConvertEmptyStringsToNull` não valem para `/graphql` (`bootstrap/app.php`) e o Lighthouse roda sem `field_middleware`: `"   "` chega ao DTO e vira a mensagem do campo, não um erro de tipo.

### 6.6 Transações no grafo

- `Graph::transaction(fn(GraphTransaction $t) => …)`: exceção no callback faz **rollback explícito**; erro transitório do Neo4j (`TransientError`) repete até `neo4j.transaction_attempts`. O callback não tem efeito externo.
- Unicidade é constraint do grafo (`GraphSchema::CONSTRAINTS`) + `MERGE`; violação → `is_graph_constraint_violation()` (o `23505` do grafo).
- Não há transação distribuída Postgres ↔ Neo4j. O espelho `User` é sincronizado **depois do commit** (`UserObserver`), falha vira `Log::warning('graph.user_sync_failed')`, as mutations recriam o nó com `MERGE`, e `ducktail:sync-users` (diário) reconcilia.

### 6.7 Schema do grafo ("migrations")

`App\Base\Graph\GraphSchema` lista constraints e índices, todos `IF NOT EXISTS`, aplicados por `php artisan ducktail:graph-migrate` (no `make migrate` e no deploy), que espera os índices ficarem online. Só **acrescente**; remover/renomear é `DROP … IF EXISTS` numa release posterior (expand/contract, rule 04).

### 6.8 Dados

- Lugares reais vêm do Wikidata (CC0) por comando (`ducktail:import`, `MERGE` por `wikidataId`, id público = QID), nunca cadastrados à mão. `ducktail:proximity` e `ducktail:relate` recalculam as arestas derivadas e são idempotentes.
- O `GraphSeeder` é dado de **exemplo** (chaves `seed-…` nas constantes, gravadas como `seed_id()`: id de URL é sempre opaco, nunca slug; empresas fictícias com domínio `.example`). Nada de empresa, pessoa ou marca real em seed.

## Checklist

- [ ] Schema sem diretivas; web avisado de qualquer mudança no contrato.
- [ ] Resolver fino (sem Cypher), DTO com `fromArgs` e mensagens por chave nos três idiomas.
- [ ] Cypher parametrizado, projeção de `Cypher`, `LIMIT` e teto de raio.
- [ ] Campo aninhado com `BatchLoader` + Query com `UNWIND $ids`.
- [ ] Várias escritas numa `Graph::transaction`; regra violada desfaz tudo (teste).
- [ ] Constraint/índice novo em `GraphSchema` (só acrescentar).
- [ ] Teste de Feature contra o Neo4j de teste: formato, validação, `NOT_FOUND`, sessão.
