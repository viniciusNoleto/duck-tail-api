<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;

it('builds the api message in every locale', function () {
    expect(api_message('messages.places.not_found'))->toBe([
        'pt-br' => 'Lugar não encontrado.',
        'es-mx' => 'Lugar no encontrado.',
        'en-us' => 'Place not found.',
    ]);
});

it('interpolates the api message', function () {
    expect(api_message('messages.places.invalid_radius', ['max' => 20])['pt-br'])
        ->toBe('O raio deve ser maior que zero e de no máximo 20 km.');
});

it('has the same message keys in every locale', function () {
    $keys = fn(string $locale) => array_keys(Arr::dot(require lang_path("{$locale}/messages.php")));

    expect($keys('es_MX'))->toBe($keys('pt_BR'))
        ->and($keys('en'))->toBe($keys('pt_BR'));
});

it('parses only positive integer ids', function (mixed $value, ?int $expected) {
    expect(parse_id($value))->toBe($expected);
})->with([
    [5, 5],
    ['12', 12],
    [0, null],
    ['-1', null],
    ['abc', null],
    ['1.5', null],
    [null, null],
]);

it('hashes tokens with sha256', function () {
    $token = generate_opaque_token();

    expect($token)->toHaveLength(64)
        ->and(hash_token($token))->toBe(hash('sha256', $token))
        ->and(generate_opaque_token())->not->toBe($token);
});

it('takes "today" from the business timezone, not from UTC', function () {
    Carbon::setTestNow('2026-11-11 01:30:00');

    config(['app.business_timezone' => 'UTC']);
    expect(business_today())->toBe('2026-11-11');

    config(['app.business_timezone' => 'America/Sao_Paulo']);
    expect(business_today())->toBe('2026-11-10');

    Carbon::setTestNow();
});

it('detects concurrency failures by SQLSTATE', function (string $sqlstate, bool $expected) {
    $pdo = new PDOException('x');
    (new ReflectionProperty(Exception::class, 'code'))->setValue($pdo, $sqlstate);

    expect(is_concurrency_failure(new QueryException('pgsql', 'select 1', [], $pdo)))->toBe($expected);
})->with([
    ['40P01', true],
    ['40001', true],
    ['55P03', true],
    ['23505', false],
]);

it('escapes LIKE wildcards so a search matches them literally', function () {
    expect(escape_like('50%_off\\x'))->toBe('50\\%\\_off\\\\x');
});

it('round-trips pagination cursors and rejects foreign ones', function () {
    expect(decode_cursor(encode_cursor(40)))->toBe(40)
        ->and(decode_cursor(null))->toBeNull()
        ->and(decode_cursor(''))->toBeNull()
        ->and(decode_cursor('not base64 !'))->toBeFalse()
        ->and(decode_cursor(base64_encode('page:2')))->toBeFalse();
});

it('escapes the Lucene syntax so a search term is never a query', function (string $term, ?string $expected) {
    expect(lucene_prefix_query($term))->toBe($expected);
})->with([
    ['ouro pre', 'ouro* AND pre*'],
    ['  Igreja  ', 'igreja*'],
    ['igreja (matriz)', 'igreja* AND \\(matriz\\)*'],
    ['a:b "c" d*', 'a\\:b* AND \\"c\\"* AND d\\**'],
    ['AND OR', 'and* AND or*'],
    ['Pão de Açúcar', 'pao* AND de* AND acucar*'],
    ['SÃO JOÃO del-rei', 'sao* AND joao* AND del\\-rei*'],
    ['   ', null],
]);

it('boosts the name in the ranked Lucene query and keeps the same matches', function () {
    expect(lucene_ranked_query('Ouro Preto'))->toBe('name:"ouro preto"^8 OR name:(ouro* AND preto*)^3 OR (ouro* AND preto*)')
        ->and(lucene_ranked_query('a"b'))->toBe('name:"a\\"b"^8 OR name:(a\\"b*)^3 OR (a\\"b*)')
        ->and(lucene_ranked_query('  '))->toBeNull();
});

it('detects a graph uniqueness violation by its Neo4j code', function () {
    $violation = new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Schema.ConstraintValidationFailed', 'exists')]);
    $syntax = new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Statement.SyntaxError', 'oops')]);

    expect(is_graph_constraint_violation($violation))->toBeTrue()
        ->and(is_graph_constraint_violation($syntax))->toBeFalse()
        ->and(is_graph_constraint_violation(new RuntimeException('x')))->toBeFalse();
});
