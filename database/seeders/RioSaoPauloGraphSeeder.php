<?php

namespace Database\Seeders;

use App\Actions\Graph\Category\AssignCategoryIdsAction;
use App\Actions\Graph\Category\NameCategoriesAction;
use App\Actions\Graph\Proximity\ComputePlaceProximityAction;
use App\Actions\Graph\Relate\RelatePlacesAction;
use App\Actions\Graph\Schema\ApplyGraphSchemaAction;
use App\Actions\Graph\Seed\AssignSeedIdsAction;
use App\Base\Graph\Graph;
use App\Enum\Graph\CompanyKindEnum;
use App\Enum\Graph\LocationKindEnum;
use App\Enum\Graph\RelationReasonEnum;
use Illuminate\Database\Seeder;

/**
 * Grafo de exemplo do Rio de Janeiro e de São Paulo: cidades, lugares reais com coordenadas aproximadas,
 * temas e empresas de turismo **fictícias** (domínios `.example`, telefones de mentira) com seus passeios.
 *
 * Diferente do `GraphSeeder`, todo nó daqui leva o `wikidataId` do lugar/localidade real, então convive
 * com o importador (`ducktail:import --area=BR-RJ|BR-SP`) sem duplicar: o `MERGE` é pelo `wikidataId`
 * (o importador faz o mesmo). Se o importador rodou antes, o seeder reaproveita o nó importado (id = QID)
 * e só acrescenta categorias, temas, empresas e passeios; se rodou depois, o importador atualiza o nó
 * `seed-…` com os dados do Wikidata. Nós `seed-…` do `GraphSeeder` que já existem sem `wikidataId`
 * (Brasil, estado e cidade do Rio, Cristo, Pão de Açúcar, Selarón) o adotam, se ninguém mais o tiver.
 *
 * Todas as referências nas constantes são chaves `seed-…`; na escrita viram o `wikidataId` correspondente
 * (ou, no id do nó, o UUID de `seed_id()`).
 * Idempotente. No fim calcula `NEAR` (3 km) e `RELATED_TO`, como o `GraphSeeder`.
 */
class RioSaoPauloGraphSeeder extends Seeder {

    /** Na ordem: o pai sempre antes do filho. */
    public const LOCATIONS = [
        ['id' => 'seed-brasil', 'wikidata' => 'Q155', 'name' => 'Brasil', 'kind' => LocationKindEnum::COUNTRY, 'parent' => null, 'lat' => -14.2350, 'lng' => -51.9253],
        ['id' => 'seed-estado-rio-de-janeiro', 'wikidata' => 'Q41428', 'name' => 'Rio de Janeiro', 'kind' => LocationKindEnum::STATE, 'parent' => 'seed-brasil', 'lat' => -22.2500, 'lng' => -42.6600],
        ['id' => 'seed-estado-sao-paulo', 'wikidata' => 'Q175', 'name' => 'São Paulo', 'kind' => LocationKindEnum::STATE, 'parent' => 'seed-brasil', 'lat' => -22.1900, 'lng' => -48.7900],
        ['id' => 'seed-rio-de-janeiro', 'wikidata' => 'Q8678', 'name' => 'Rio de Janeiro', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-estado-rio-de-janeiro', 'lat' => -22.9068, 'lng' => -43.1729],
        ['id' => 'seed-niteroi', 'wikidata' => 'Q178725', 'name' => 'Niterói', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-estado-rio-de-janeiro', 'lat' => -22.8832, 'lng' => -43.1034],
        ['id' => 'seed-petropolis', 'wikidata' => 'Q189043', 'name' => 'Petrópolis', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-estado-rio-de-janeiro', 'lat' => -22.5050, 'lng' => -43.1786],
        ['id' => 'seed-paraty', 'wikidata' => 'Q926729', 'name' => 'Paraty', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-estado-rio-de-janeiro', 'lat' => -23.2178, 'lng' => -44.7131],
        ['id' => 'seed-sao-paulo', 'wikidata' => 'Q174', 'name' => 'São Paulo', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-estado-sao-paulo', 'lat' => -23.5505, 'lng' => -46.6333],
        ['id' => 'seed-santos', 'wikidata' => 'Q81882', 'name' => 'Santos', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-estado-sao-paulo', 'lat' => -23.9608, 'lng' => -46.3336],
        ['id' => 'seed-campos-do-jordao', 'wikidata' => 'Q851253', 'name' => 'Campos do Jordão', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-estado-sao-paulo', 'lat' => -22.7394, 'lng' => -45.5914],
    ];

    /** Os três primeiros já existem no `GraphSeeder` (mesmo texto); os outros são novos. */
    public const THEMES = [
        ['name' => 'Estrada Real', 'description' => 'O caminho oficial da Coroa entre as minas e os portos do Rio de Janeiro e de Paraty.'],
        ['name' => 'Modernismo', 'description' => 'A arquitetura moderna brasileira, de Niemeyer a Burle Marx.'],
        ['name' => 'Cartões-postais do Rio', 'description' => 'As vistas que todo mundo reconhece da cidade.'],
        ['name' => 'Centro do Rio', 'description' => 'Mosteiros, teatros, bibliotecas e arcos do centro antigo carioca.'],
        ['name' => 'Brasil Império', 'description' => 'Lugares do Império, da Independência em 1822 ao último baile da monarquia em 1889.'],
        ['name' => 'Arte em São Paulo', 'description' => 'Museus, teatros e arte de rua da maior cidade do país.'],
        ['name' => 'Rota do Café', 'description' => 'A riqueza do café que desceu a serra até o porto de Santos.'],
        ['name' => 'Futebol', 'description' => 'Estádios e museus da paixão nacional.'],
    ];

    public const PLACES = [
        // Rio de Janeiro (os três primeiros já existem no GraphSeeder: aqui só ganham o wikidataId)
        ['id' => 'seed-cristo-redentor', 'wikidata' => 'Q79961', 'name' => 'Cristo Redentor', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9519, 'lng' => -43.2105,
            'description' => 'Estátua art déco de 1931 no alto do Corcovado, com vista para a baía e as praias.',
            'categories' => ['monumento'], 'themes' => ['Cartões-postais do Rio'], 'authors' => ['Paul Landowski'], 'styles' => ['art déco']],
        ['id' => 'seed-pao-de-acucar', 'wikidata' => 'Q210722', 'name' => 'Pão de Açúcar', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9492, 'lng' => -43.1546,
            'description' => 'Morro na entrada da Baía de Guanabara, com bondinho desde 1912.',
            'categories' => ['mirante'], 'themes' => ['Cartões-postais do Rio'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-escadaria-selaron', 'wikidata' => 'Q2200939', 'name' => 'Escadaria Selarón', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9153, 'lng' => -43.1794,
            'description' => 'Escada entre a Lapa e Santa Teresa coberta por azulejos de mais de 60 países.',
            'categories' => ['arte urbana'], 'themes' => ['Cartões-postais do Rio', 'Centro do Rio'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-maracana', 'wikidata' => 'Q155174', 'name' => 'Estádio do Maracanã', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9122, 'lng' => -43.2302,
            'description' => 'Estádio de 1950, palco de duas finais de Copa do Mundo.',
            'categories' => ['estádio'], 'themes' => ['Futebol', 'Cartões-postais do Rio'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-theatro-municipal-rio', 'wikidata' => 'Q1968538', 'name' => 'Theatro Municipal do Rio de Janeiro', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9090, 'lng' => -43.1765,
            'description' => 'Teatro de 1909 na Cinelândia, inspirado na Ópera de Paris.',
            'categories' => ['teatro'], 'themes' => ['Centro do Rio'], 'authors' => ['Francisco de Oliveira Passos'], 'styles' => ['eclético']],
        ['id' => 'seed-museu-do-amanha', 'wikidata' => 'Q10333874', 'name' => 'Museu do Amanhã', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.8940, 'lng' => -43.1797,
            'description' => 'Museu de ciências na Praça Mauá, projetado por Santiago Calatrava.',
            'categories' => ['museu'], 'themes' => ['Cartões-postais do Rio'], 'authors' => ['Santiago Calatrava'], 'styles' => []],
        ['id' => 'seed-arcos-da-lapa', 'wikidata' => 'Q1579892', 'name' => 'Arcos da Lapa', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9130, 'lng' => -43.1800,
            'description' => 'Aqueduto colonial do século XVIII por onde hoje passa o bonde de Santa Teresa.',
            'categories' => ['aqueduto'], 'themes' => ['Centro do Rio'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-jardim-botanico-rio', 'wikidata' => 'Q45242', 'name' => 'Jardim Botânico do Rio de Janeiro', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9673, 'lng' => -43.2236,
            'description' => 'Jardim criado por Dom João VI em 1808, com a aleia de palmeiras imperiais.',
            'categories' => ['jardim botânico'], 'themes' => ['Brasil Império', 'Cartões-postais do Rio'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-real-gabinete', 'wikidata' => 'Q5411113', 'name' => 'Real Gabinete Português de Leitura', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9050, 'lng' => -43.1820,
            'description' => 'Biblioteca neomanuelina com estantes de madeira até o teto de vitral.',
            'categories' => ['biblioteca'], 'themes' => ['Centro do Rio'], 'authors' => [], 'styles' => ['neomanuelino']],
        ['id' => 'seed-forte-copacabana', 'wikidata' => 'Q5470982', 'name' => 'Forte de Copacabana', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9862, 'lng' => -43.1872,
            'description' => 'Forte de 1914 na ponta da praia, com museu e café de frente para o mar.',
            'categories' => ['forte'], 'themes' => ['Cartões-postais do Rio'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-catedral-rio', 'wikidata' => 'Q2411716', 'name' => 'Catedral de São Sebastião do Rio de Janeiro', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9107, 'lng' => -43.1810,
            'description' => 'Catedral cônica de concreto com vitrais de 64 metros de altura.',
            'categories' => ['igreja'], 'themes' => ['Modernismo', 'Centro do Rio'], 'authors' => [], 'styles' => ['modernismo']],
        ['id' => 'seed-ilha-fiscal', 'wikidata' => 'Q985128', 'name' => 'Ilha Fiscal', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.8970, 'lng' => -43.1667,
            'description' => 'Castelinho neogótico na baía, onde aconteceu o último baile do Império.',
            'categories' => ['ilha'], 'themes' => ['Brasil Império'], 'authors' => [], 'styles' => ['neogótico']],
        ['id' => 'seed-mosteiro-sao-bento-rio', 'wikidata' => 'Q2820360', 'name' => 'Mosteiro de São Bento (Rio de Janeiro)', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.8970, 'lng' => -43.1780,
            'description' => 'Mosteiro do século XVII com igreja inteiramente revestida de talha dourada.',
            'categories' => ['mosteiro'], 'themes' => ['Centro do Rio'], 'authors' => [], 'styles' => ['barroco']],
        // Niterói
        ['id' => 'seed-mac-niteroi', 'wikidata' => 'Q1573239', 'name' => 'Museu de Arte Contemporânea de Niterói', 'location' => 'seed-niteroi', 'lat' => -22.9078, 'lng' => -43.1258,
            'description' => 'O disco voador de Niemeyer debruçado sobre a Baía de Guanabara.',
            'categories' => ['museu'], 'themes' => ['Modernismo'], 'authors' => ['Oscar Niemeyer'], 'styles' => ['modernismo']],
        ['id' => 'seed-caminho-niemeyer', 'wikidata' => 'Q9675449', 'name' => 'Caminho Niemeyer', 'location' => 'seed-niteroi', 'lat' => -22.8935, 'lng' => -43.1263,
            'description' => 'Conjunto de edifícios de Niemeyer na orla do centro de Niterói.',
            'categories' => ['conjunto arquitetônico'], 'themes' => ['Modernismo'], 'authors' => ['Oscar Niemeyer'], 'styles' => ['modernismo']],
        ['id' => 'seed-fortaleza-santa-cruz', 'wikidata' => 'Q10283631', 'name' => 'Fortaleza de Santa Cruz da Barra', 'location' => 'seed-niteroi', 'lat' => -22.9381, 'lng' => -43.1340,
            'description' => 'Fortaleza do século XVI que guardava a entrada da baía, de frente para o Pão de Açúcar.',
            'categories' => ['forte'], 'themes' => [], 'authors' => [], 'styles' => []],
        // Petrópolis
        ['id' => 'seed-museu-imperial', 'wikidata' => 'Q1887049', 'name' => 'Museu Imperial', 'location' => 'seed-petropolis', 'lat' => -22.5070, 'lng' => -43.1789,
            'description' => 'Palácio de verão de Dom Pedro II, com a coroa imperial e as joias da família.',
            'categories' => ['museu', 'palácio'], 'themes' => ['Brasil Império'], 'authors' => [], 'styles' => ['neoclássico']],
        ['id' => 'seed-catedral-petropolis', 'wikidata' => 'Q2407257', 'name' => 'Catedral de Petrópolis', 'location' => 'seed-petropolis', 'lat' => -22.5060, 'lng' => -43.1760,
            'description' => 'Catedral neogótica onde estão os túmulos de Dom Pedro II e da imperatriz.',
            'categories' => ['igreja'], 'themes' => ['Brasil Império'], 'authors' => [], 'styles' => ['neogótico']],
        ['id' => 'seed-palacio-quitandinha', 'wikidata' => 'Q3533704', 'name' => 'Palácio Quitandinha', 'location' => 'seed-petropolis', 'lat' => -22.5260, 'lng' => -43.2180,
            'description' => 'Antigo hotel-cassino dos anos 1940, com salões cenográficos e um lago em forma de mapa do Brasil.',
            'categories' => ['palácio'], 'themes' => [], 'authors' => [], 'styles' => []],
        // Paraty
        ['id' => 'seed-igreja-santa-rita-paraty', 'wikidata' => 'Q10300659', 'name' => 'Igreja de Santa Rita de Cássia', 'location' => 'seed-paraty', 'lat' => -23.2210, 'lng' => -44.7130,
            'description' => 'Igreja de 1722 à beira do cais, o cartão-postal do centro histórico de Paraty.',
            'categories' => ['igreja'], 'themes' => ['Estrada Real'], 'authors' => [], 'styles' => ['barroco']],
        // São Paulo
        ['id' => 'seed-masp', 'wikidata' => 'Q82941', 'name' => 'Museu de Arte de São Paulo', 'location' => 'seed-sao-paulo', 'lat' => -23.5614, 'lng' => -46.6559,
            'description' => 'O vão livre de Lina Bo Bardi na Paulista e a coleção nos cavaletes de vidro.',
            'categories' => ['museu'], 'themes' => ['Arte em São Paulo', 'Modernismo'], 'authors' => ['Lina Bo Bardi'], 'styles' => ['modernismo']],
        ['id' => 'seed-pinacoteca', 'wikidata' => 'Q2095209', 'name' => 'Pinacoteca de São Paulo', 'location' => 'seed-sao-paulo', 'lat' => -23.5343, 'lng' => -46.6339,
            'description' => 'O museu de arte mais antigo da cidade, num prédio de tijolos aparentes no Jardim da Luz.',
            'categories' => ['museu'], 'themes' => ['Arte em São Paulo'], 'authors' => ['Ramos de Azevedo'], 'styles' => ['eclético']],
        ['id' => 'seed-theatro-municipal-sp', 'wikidata' => 'Q2297627', 'name' => 'Theatro Municipal de São Paulo', 'location' => 'seed-sao-paulo', 'lat' => -23.5452, 'lng' => -46.6386,
            'description' => 'Teatro de 1911, palco da Semana de Arte Moderna de 1922.',
            'categories' => ['teatro'], 'themes' => ['Arte em São Paulo', 'Modernismo'], 'authors' => ['Ramos de Azevedo'], 'styles' => ['eclético']],
        ['id' => 'seed-parque-ibirapuera', 'wikidata' => 'Q212248', 'name' => 'Parque Ibirapuera', 'location' => 'seed-sao-paulo', 'lat' => -23.5874, 'lng' => -46.6576,
            'description' => 'O grande parque da cidade, com a marquise e os pavilhões de Niemeyer.',
            'categories' => ['parque'], 'themes' => ['Modernismo'], 'authors' => ['Oscar Niemeyer'], 'styles' => ['modernismo']],
        ['id' => 'seed-mercadao-sp', 'wikidata' => 'Q4120323', 'name' => 'Mercado Municipal de São Paulo', 'location' => 'seed-sao-paulo', 'lat' => -23.5417, 'lng' => -46.6293,
            'description' => 'Mercadão de 1933, famoso pelos vitrais e pelo sanduíche de mortadela.',
            'categories' => ['mercado'], 'themes' => [], 'authors' => ['Ramos de Azevedo'], 'styles' => []],
        ['id' => 'seed-catedral-se-sp', 'wikidata' => 'Q1050668', 'name' => 'Catedral Metropolitana de São Paulo', 'location' => 'seed-sao-paulo', 'lat' => -23.5509, 'lng' => -46.6343,
            'description' => 'Catedral neogótica na Praça da Sé, marco zero da cidade.',
            'categories' => ['igreja'], 'themes' => [], 'authors' => [], 'styles' => ['neogótico']],
        ['id' => 'seed-museu-ipiranga', 'wikidata' => 'Q371803', 'name' => 'Museu do Ipiranga', 'location' => 'seed-sao-paulo', 'lat' => -23.5856, 'lng' => -46.6097,
            'description' => 'Museu às margens do riacho onde foi proclamada a Independência.',
            'categories' => ['museu'], 'themes' => ['Brasil Império'], 'authors' => [], 'styles' => ['eclético']],
        ['id' => 'seed-edificio-copan', 'wikidata' => 'Q632566', 'name' => 'Edifício Copan', 'location' => 'seed-sao-paulo', 'lat' => -23.5465, 'lng' => -46.6447,
            'description' => 'O prédio em onda de Niemeyer, com mais de mil apartamentos no centro.',
            'categories' => ['edifício'], 'themes' => ['Modernismo'], 'authors' => ['Oscar Niemeyer'], 'styles' => ['modernismo']],
        ['id' => 'seed-museu-do-futebol', 'wikidata' => 'Q2356412', 'name' => 'Museu do Futebol', 'location' => 'seed-sao-paulo', 'lat' => -23.5475, 'lng' => -46.6650,
            'description' => 'Museu interativo sob as arquibancadas do Estádio do Pacaembu.',
            'categories' => ['museu'], 'themes' => ['Futebol'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-patio-do-colegio', 'wikidata' => 'Q2914788', 'name' => 'Pátio do Colégio', 'location' => 'seed-sao-paulo', 'lat' => -23.5480, 'lng' => -46.6325,
            'description' => 'Onde os jesuítas fundaram São Paulo em 1554.',
            'categories' => ['marco histórico'], 'themes' => [], 'authors' => [], 'styles' => []],
        ['id' => 'seed-avenida-paulista', 'wikidata' => 'Q41068', 'name' => 'Avenida Paulista', 'location' => 'seed-sao-paulo', 'lat' => -23.5640, 'lng' => -46.6527,
            'description' => 'A avenida-símbolo da cidade, fechada para carros aos domingos.',
            'categories' => ['avenida'], 'themes' => ['Arte em São Paulo'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-beco-do-batman', 'wikidata' => 'Q18932366', 'name' => 'Beco do Batman', 'location' => 'seed-sao-paulo', 'lat' => -23.5560, 'lng' => -46.6870,
            'description' => 'Travessa da Vila Madalena coberta de grafites que mudam o tempo todo.',
            'categories' => ['arte urbana'], 'themes' => ['Arte em São Paulo'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-mosteiro-sao-bento-sp', 'wikidata' => 'Q2820368', 'name' => 'Mosteiro de São Bento (São Paulo)', 'location' => 'seed-sao-paulo', 'lat' => -23.5440, 'lng' => -46.6343,
            'description' => 'Mosteiro beneditino com canto gregoriano nas missas de domingo.',
            'categories' => ['mosteiro'], 'themes' => [], 'authors' => [], 'styles' => []],
        // Santos
        ['id' => 'seed-museu-do-cafe', 'wikidata' => 'Q9660690', 'name' => 'Museu do Café', 'location' => 'seed-santos', 'lat' => -23.9340, 'lng' => -46.3290,
            'description' => 'A antiga Bolsa Oficial de Café, com o salão do pregão e vitrais de Benedito Calixto.',
            'categories' => ['museu'], 'themes' => ['Rota do Café'], 'authors' => [], 'styles' => ['eclético']],
        ['id' => 'seed-museu-pele', 'wikidata' => 'Q18278306', 'name' => 'Museu Pelé', 'location' => 'seed-santos', 'lat' => -23.9355, 'lng' => -46.3260,
            'description' => 'A carreira do Rei do Futebol num casarão restaurado do centro de Santos.',
            'categories' => ['museu'], 'themes' => ['Futebol'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-aquario-santos', 'wikidata' => 'Q524626', 'name' => 'Aquário Municipal de Santos', 'location' => 'seed-santos', 'lat' => -23.9870, 'lng' => -46.3070,
            'description' => 'Aquário de 1945 na orla da Ponta da Praia.',
            'categories' => ['aquário'], 'themes' => [], 'authors' => [], 'styles' => []],
        // Campos do Jordão
        ['id' => 'seed-palacio-boa-vista', 'wikidata' => 'Q10343927', 'name' => 'Palácio Boa Vista', 'location' => 'seed-campos-do-jordao', 'lat' => -22.7120, 'lng' => -45.5550,
            'description' => 'Residência de inverno do governo paulista, aberta como museu.',
            'categories' => ['palácio', 'museu'], 'themes' => [], 'authors' => [], 'styles' => []],
    ];

    /** Fictícias: nenhuma empresa real é representada. */
    public const COMPANIES = [
        ['id' => 'seed-bonde-e-baia', 'name' => 'Bonde & Baía Turismo', 'kind' => CompanyKindEnum::AGENCY, 'base' => 'seed-rio-de-janeiro',
            'description' => 'Roteiros pelo centro antigo do Rio e travessias da baía até Niterói.', 'website' => 'https://bondeebaia.example', 'phone' => '+55 21 0000-0007',
            'serves' => ['seed-rio-de-janeiro', 'seed-niteroi'],
            'operates' => ['seed-ilha-fiscal', 'seed-museu-do-amanha', 'seed-real-gabinete', 'seed-mac-niteroi']],
        ['id' => 'seed-serra-imperial', 'name' => 'Serra Imperial Guias', 'kind' => CompanyKindEnum::GUIDE, 'base' => 'seed-petropolis',
            'description' => 'Guias de turismo especializados na Petrópolis de Dom Pedro II.', 'website' => 'https://serraimperial.example', 'phone' => '+55 24 0000-0008',
            'serves' => ['seed-petropolis', 'seed-rio-de-janeiro'],
            'operates' => ['seed-museu-imperial', 'seed-catedral-petropolis', 'seed-palacio-quitandinha']],
        ['id' => 'seed-pousada-mare-mansa', 'name' => 'Pousada Maré Mansa', 'kind' => CompanyKindEnum::LODGING, 'base' => 'seed-paraty',
            'description' => 'Casarão colonial no centro histórico de Paraty, a uma quadra do cais.', 'website' => 'https://pousadamaremansa.example', 'phone' => '+55 24 0000-0009',
            'serves' => ['seed-paraty'], 'operates' => ['seed-igreja-santa-rita-paraty']],
        ['id' => 'seed-costa-verde-transfers', 'name' => 'Costa Verde Transfers', 'kind' => CompanyKindEnum::TRANSPORT, 'base' => 'seed-rio-de-janeiro',
            'description' => 'Vans com motorista entre o Rio, a serra e o litoral sul fluminense.', 'website' => 'https://costaverdetransfers.example', 'phone' => '+55 21 0000-0010',
            'serves' => ['seed-rio-de-janeiro', 'seed-niteroi', 'seed-petropolis', 'seed-paraty'],
            'operates' => ['seed-cristo-redentor', 'seed-maracana']],
        ['id' => 'seed-sampa-cultural', 'name' => 'Sampa Cultural', 'kind' => CompanyKindEnum::AGENCY, 'base' => 'seed-sao-paulo',
            'description' => 'Roteiros de museus e teatros com ingresso incluído.', 'website' => 'https://sampacultural.example', 'phone' => '+55 11 0000-0011',
            'serves' => ['seed-sao-paulo'], 'operates' => ['seed-masp', 'seed-pinacoteca', 'seed-theatro-municipal-sp']],
        ['id' => 'seed-guia-paulistano', 'name' => 'Guia Paulistano', 'kind' => CompanyKindEnum::GUIDE, 'base' => 'seed-sao-paulo',
            'description' => 'Caminhadas pelo centro velho, pela arquitetura moderna e pela arte de rua.', 'website' => 'https://guiapaulistano.example', 'phone' => '+55 11 0000-0012',
            'serves' => ['seed-sao-paulo'],
            'operates' => ['seed-patio-do-colegio', 'seed-catedral-se-sp', 'seed-mercadao-sp', 'seed-edificio-copan', 'seed-beco-do-batman']],
        ['id' => 'seed-hostel-vila-paulista', 'name' => 'Hostel Vila Paulista', 'kind' => CompanyKindEnum::LODGING, 'base' => 'seed-sao-paulo',
            'description' => 'Hostel a cinco minutos a pé da Avenida Paulista e do MASP.', 'website' => 'https://hostelvilapaulista.example', 'phone' => '+55 11 0000-0013',
            'serves' => ['seed-sao-paulo'], 'operates' => ['seed-avenida-paulista']],
        ['id' => 'seed-serra-e-litoral', 'name' => 'Serra & Litoral Transportes', 'kind' => CompanyKindEnum::TRANSPORT, 'base' => 'seed-sao-paulo',
            'description' => 'Bate-volta de São Paulo para Santos e Campos do Jordão.', 'website' => 'https://serraelitoral.example', 'phone' => '+55 11 0000-0014',
            'serves' => ['seed-sao-paulo', 'seed-santos', 'seed-campos-do-jordao'], 'operates' => ['seed-museu-do-cafe', 'seed-palacio-boa-vista']],
        ['id' => 'seed-chales-bruma-da-serra', 'name' => 'Chalés Bruma da Serra', 'kind' => CompanyKindEnum::LODGING, 'base' => 'seed-campos-do-jordao',
            'description' => 'Chalés com lareira no alto da serra, perto do Palácio Boa Vista.', 'website' => 'https://chalesbrumadaserra.example', 'phone' => '+55 12 0000-0015',
            'serves' => ['seed-campos-do-jordao'], 'operates' => ['seed-palacio-boa-vista']],
    ];

    /** Fictícios, como as empresas. `stops` na ordem do passeio. */
    public const TOURS = [
        ['id' => 'seed-tour-centro-antigo-rio', 'company' => 'seed-bonde-e-baia', 'name' => 'Centro antigo do Rio a pé',
            'description' => 'Do Mosteiro de São Bento à Escadaria Selarón, passando pelo Real Gabinete e pela Cinelândia.', 'minutes' => 240, 'price' => 150.0, 'currency' => 'BRL',
            'stops' => ['seed-mosteiro-sao-bento-rio', 'seed-real-gabinete', 'seed-theatro-municipal-rio', 'seed-catedral-rio', 'seed-arcos-da-lapa', 'seed-escadaria-selaron']],
        ['id' => 'seed-tour-baia-de-guanabara', 'company' => 'seed-bonde-e-baia', 'name' => 'Baía de Guanabara: Ilha Fiscal e Niterói',
            'description' => 'Praça Mauá, o castelinho da Ilha Fiscal e a travessia até as obras de Niemeyer.', 'minutes' => 360, 'price' => 260.0, 'currency' => 'BRL',
            'stops' => ['seed-museu-do-amanha', 'seed-ilha-fiscal', 'seed-caminho-niemeyer', 'seed-mac-niteroi', 'seed-fortaleza-santa-cruz']],
        ['id' => 'seed-tour-petropolis-imperial', 'company' => 'seed-serra-imperial', 'name' => 'Petrópolis imperial',
            'description' => 'Um dia na serra: o palácio de Dom Pedro II, a catedral e o Quitandinha.', 'minutes' => 480, 'price' => 320.0, 'currency' => 'BRL',
            'stops' => ['seed-museu-imperial', 'seed-catedral-petropolis', 'seed-palacio-quitandinha']],
        ['id' => 'seed-tour-rio-imperial', 'company' => 'seed-serra-imperial', 'name' => 'Rio imperial',
            'description' => 'O jardim de Dom João VI, a ilha do último baile e o palácio de verão na serra.', 'minutes' => 600, 'price' => 380.0, 'currency' => 'BRL',
            'stops' => ['seed-jardim-botanico-rio', 'seed-ilha-fiscal', 'seed-museu-imperial']],
        ['id' => 'seed-tour-zona-sul-maracana', 'company' => 'seed-costa-verde-transfers', 'name' => 'Corcovado, Zona Sul e Maracanã',
            'description' => 'Van com motorista do Cristo ao forte de Copacabana, com parada no Maracanã.', 'minutes' => 420, 'price' => 290.0, 'currency' => 'BRL',
            'stops' => ['seed-cristo-redentor', 'seed-jardim-botanico-rio', 'seed-forte-copacabana', 'seed-pao-de-acucar', 'seed-maracana']],
        ['id' => 'seed-tour-paraty', 'company' => 'seed-costa-verde-transfers', 'name' => 'Paraty pela Costa Verde',
            'description' => 'Bate-volta pela Rio-Santos até o centro histórico de Paraty.', 'minutes' => 720, 'price' => 410.0, 'currency' => 'BRL',
            'stops' => ['seed-igreja-santa-rita-paraty']],
        ['id' => 'seed-tour-museus-sp', 'company' => 'seed-sampa-cultural', 'name' => 'Museus da Paulista à Luz',
            'description' => 'MASP, Theatro Municipal e Pinacoteca, com ingressos e metrô incluídos.', 'minutes' => 360, 'price' => 180.0, 'currency' => 'BRL',
            'stops' => ['seed-masp', 'seed-theatro-municipal-sp', 'seed-pinacoteca']],
        ['id' => 'seed-tour-centro-historico-sp', 'company' => 'seed-guia-paulistano', 'name' => 'Centro histórico de São Paulo',
            'description' => 'Do Pátio do Colégio ao Mercadão, com o canto gregoriano de São Bento no caminho.', 'minutes' => 210, 'price' => 110.0, 'currency' => 'BRL',
            'stops' => ['seed-patio-do-colegio', 'seed-catedral-se-sp', 'seed-mosteiro-sao-bento-sp', 'seed-mercadao-sp']],
        ['id' => 'seed-tour-sp-moderna', 'company' => 'seed-guia-paulistano', 'name' => 'São Paulo moderna',
            'description' => 'Copan, Paulista, o vão do MASP e a marquise do Ibirapuera.', 'minutes' => 300, 'price' => 160.0, 'currency' => 'BRL',
            'stops' => ['seed-edificio-copan', 'seed-avenida-paulista', 'seed-masp', 'seed-parque-ibirapuera']],
        ['id' => 'seed-tour-arte-de-rua-futebol', 'company' => 'seed-guia-paulistano', 'name' => 'Grafite e futebol',
            'description' => 'Os muros do Beco do Batman e o museu sob as arquibancadas do Pacaembu.', 'minutes' => 240, 'price' => 130.0, 'currency' => 'BRL',
            'stops' => ['seed-beco-do-batman', 'seed-museu-do-futebol']],
        ['id' => 'seed-tour-santos', 'company' => 'seed-serra-e-litoral', 'name' => 'Santos: café, Pelé e aquário',
            'description' => 'Descida da serra até a Bolsa do Café, o Museu Pelé e a orla da Ponta da Praia.', 'minutes' => 600, 'price' => 240.0, 'currency' => 'BRL',
            'stops' => ['seed-museu-do-cafe', 'seed-museu-pele', 'seed-aquario-santos']],
        ['id' => 'seed-tour-independencia-e-cafe', 'company' => 'seed-serra-e-litoral', 'name' => 'Da Independência ao café',
            'description' => 'O Museu do Ipiranga de manhã e a Bolsa do Café em Santos à tarde.', 'minutes' => 540, 'price' => 230.0, 'currency' => 'BRL',
            'stops' => ['seed-museu-ipiranga', 'seed-museu-do-cafe']],
        ['id' => 'seed-tour-campos-do-jordao', 'company' => 'seed-serra-e-litoral', 'name' => 'Campos do Jordão no inverno',
            'description' => 'Bate-volta à Mantiqueira com visita ao Palácio Boa Vista.', 'minutes' => 720, 'price' => 280.0, 'currency' => 'BRL',
            'stops' => ['seed-palacio-boa-vista']],
    ];

    /** Curadoria: ligações entre os dois estados que nenhum cálculo automático encontraria. */
    public const CURATED = [
        ['seed-museu-ipiranga', 'seed-museu-imperial', 'Da Independência ao Segundo Reinado'],
        ['seed-museu-pele', 'seed-maracana', 'Do Rei do Futebol ao templo do futebol'],
        ['seed-escadaria-selaron', 'seed-beco-do-batman', 'Arte urbana no Rio e em São Paulo'],
    ];

    public function run(
        Graph                       $graph,
        ApplyGraphSchemaAction      $applySchema,
        ComputePlaceProximityAction $placeProximity,
        RelatePlacesAction          $relatePlaces,
        AssignCategoryIdsAction     $assignCategoryIds,
        NameCategoriesAction        $nameCategories,
        AssignSeedIdsAction         $assignSeedIds,
    ): void {
        $applySchema->handle();
        $assignSeedIds->handle();

        $wikidata = array_column([...self::LOCATIONS, ...self::PLACES], 'wikidata', 'id');
        $qid = fn(?string $id) => $id === null ? null : $wikidata[$id];
        $qids = fn(array $ids) => array_map($qid, $ids);

        $locations = array_map(fn(array $location) => ['id' => seed_id($location['id']), 'kind' => $location['kind']->value, 'parent' => $qid($location['parent'])] + $location, self::LOCATIONS);
        $places = array_map(fn(array $place) => ['id' => seed_id($place['id']), 'location' => $qid($place['location'])] + $place, self::PLACES);

        // Nós `seed-…` sem wikidataId adotam o do lugar real, desde que nenhum nó (importado) já o tenha.
        foreach (['Location' => $locations, 'Place' => $places] as $label => $rows) {
            $graph->write(
                "UNWIND \$rows AS row
                 MATCH (n:{$label} {id: row.id}) WHERE n.wikidataId IS NULL
                   AND NOT EXISTS { MATCH (other:{$label} {wikidataId: row.wikidata}) }
                 SET n.wikidataId = row.wikidata",
                ['rows' => $rows]
            );
        }

        $graph->write(
            'UNWIND $locations AS row
             MERGE (l:Location {wikidataId: row.wikidata})
               ON CREATE SET l.id = row.id, l.name = row.name, l.kind = row.kind
             SET l.location = coalesce(l.location, point({latitude: row.lat, longitude: row.lng}))
             WITH l, row WHERE row.parent IS NOT NULL
             MATCH (parent:Location {wikidataId: row.parent})
             MERGE (l)-[:WITHIN]->(parent)',
            ['locations' => $locations]
        );

        $graph->write(
            'UNWIND $themes AS row MERGE (t:Theme {name: row.name}) ON CREATE SET t.description = row.description',
            ['themes' => self::THEMES]
        );

        // Nome, descrição e coordenadas só na criação: num nó importado, quem manda é o Wikidata.
        $graph->write(
            'UNWIND $places AS row
             MERGE (p:Place {wikidataId: row.wikidata})
               ON CREATE SET p.id = row.id, p.name = row.name, p.description = row.description, p.image = null,
                             p.location = point({latitude: row.lat, longitude: row.lng}), p.authors = row.authors, p.styles = row.styles
             WITH p, row
             CALL (p, row) {
                 WITH p, row WHERE NOT (p)-[:LOCATED_IN]->(:Location)
                 MATCH (l:Location {wikidataId: row.location})
                 MERGE (p)-[:LOCATED_IN]->(l)
             }
             CALL (p, row) { UNWIND row.categories AS name MERGE (k:Category {name: name}) ON CREATE SET k.id = randomUUID() MERGE (p)-[:HAS_CATEGORY]->(k) }
             CALL (p, row) { UNWIND row.themes AS name MATCH (t:Theme {name: name}) MERGE (p)-[:PART_OF]->(t) }',
            ['places' => $places]
        );

        $graph->write(
            'UNWIND $companies AS row
             MATCH (base:Location {wikidataId: row.base})
             MERGE (co:Company {id: row.id})
             SET co.name = row.name, co.kind = row.kind, co.description = row.description, co.website = row.website, co.phone = row.phone
             MERGE (co)-[:BASED_IN]->(base)
             WITH co, row
             CALL (co, row) { UNWIND row.serves AS qid MATCH (l:Location {wikidataId: qid}) MERGE (co)-[:SERVES]->(l) }
             CALL (co, row) { UNWIND row.operates AS qid MATCH (p:Place {wikidataId: qid}) MERGE (co)-[:OPERATES_AT]->(p) }',
            ['companies' => array_map(fn(array $company) => [
                'id' => seed_id($company['id']),
                'kind' => $company['kind']->value,
                'base' => $qid($company['base']),
                'serves' => $qids($company['serves']),
                'operates' => $qids($company['operates']),
            ] + $company, self::COMPANIES)]
        );

        $graph->write(
            'UNWIND $tours AS row
             MATCH (co:Company {id: row.company})
             MERGE (t:Tour {id: row.id})
             SET t.name = row.name, t.description = row.description, t.durationMinutes = row.minutes, t.price = row.price, t.currency = row.currency
             MERGE (co)-[:OFFERS]->(t)
             WITH t, row
             CALL (t) { MATCH (t)-[old:STOPS_AT]->() DELETE old }
             WITH t, row
             UNWIND range(0, size(row.stops) - 1) AS index
             MATCH (p:Place {wikidataId: row.stops[index]})
             CREATE (t)-[:STOPS_AT {order: index + 1}]->(p)',
            ['tours' => array_map(fn(array $tour) => ['id' => seed_id($tour['id']), 'company' => seed_id($tour['company']), 'stops' => $qids($tour['stops'])] + $tour, self::TOURS)]
        );

        $graph->write(
            'UNWIND $pairs AS pair
             MATCH (a:Place {wikidataId: pair[0]}), (b:Place {wikidataId: pair[1]})
             MERGE (a)-[r:RELATED_TO {kind: $curated}]->(b)
             SET r.value = pair[2]',
            ['pairs' => array_map(fn(array $pair) => [$qid($pair[0]), $qid($pair[1]), $pair[2]], self::CURATED), 'curated' => RelationReasonEnum::CURATED->value]
        );

        $assignCategoryIds->handle();
        $nameCategories->handle();
        $near = $placeProximity->handle(3000);
        $related = $relatePlaces->handle();

        $this->command?->info(sprintf(
            'Grafo de exemplo RJ/SP: %d localidades, %d lugares, %d empresas, %d passeios; %d NEAR, %d RELATED_TO.',
            count(self::LOCATIONS), count(self::PLACES), count(self::COMPANIES), count(self::TOURS), $near, $related
        ));
    }
}
