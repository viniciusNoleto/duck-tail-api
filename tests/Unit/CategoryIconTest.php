<?php

use App\Enum\Graph\CategoryIconEnum;

/*
| `CategoryIconEnum::for`: o ícone de cada categoria sai de palavras-chave do nome, a regra mais específica
| primeiro.
*/

it('picks the icon of a category by keyword, the most specific rule first', function (array $names, CategoryIconEnum $icon) {
    expect(CategoryIconEnum::for(...$names))->toBe($icon);
})->with([
    'church' => [['igreja', 'church building'], CategoryIconEnum::CHURCH],
    'railway museum is a museum' => [['museu ferroviário', 'railway museum'], CategoryIconEnum::MUSEUM],
    'railway bridge is a bridge' => [['ponte ferroviária', 'railway bridge'], CategoryIconEnum::BRIDGE],
    'school building is a school' => [['edifício escolar', 'school building'], CategoryIconEnum::SCHOOL],
    'bus station is not a train' => [['estação de ônibus', 'bus station'], CategoryIconEnum::BUS],
    'power station is not a train' => [['usina hidroelétrica', 'hydroelectric power station'], CategoryIconEnum::FACTORY],
    'natural heritage is nature' => [['patrimônio natural', 'natural heritage'], CategoryIconEnum::FOREST],
    'heritage' => [['patrimônio histórico', 'historic site'], CategoryIconEnum::HERITAGE],
    'city walls are not a mural' => [['muralhas da cidade', 'city walls'], CategoryIconEnum::FORTRESS],
    'collegiate is not a yacht' => [['universidade descentralizada', 'collegiate university'], CategoryIconEnum::SCHOOL],
    'only pt-br' => [['chafariz', null], CategoryIconEnum::FOUNTAIN],
    'unknown' => [['objeto zamenhof-esperanto', 'zamenhof-esperanto object'], CategoryIconEnum::PLACE],
    'blank' => [[null, ''], CategoryIconEnum::PLACE],
]);

it('names an icon of an Iconify set in every case', function () {
    foreach (CategoryIconEnum::cases() as $icon) {
        expect($icon->value)->toMatch('/^[a-z0-9-]+:[a-z0-9-]+$/');
    }
});
