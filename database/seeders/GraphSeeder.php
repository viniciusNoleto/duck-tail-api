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
 * Grafo de exemplo para o web ter dados sem rodar o importador do Wikidata: localidades em árvore
 * (país → estado → cidade, ou país → cidade), lugares com coordenadas reais aproximadas, categorias,
 * temas, empresas de turismo **fictícias** (domínios `.example`, telefones de mentira) e os passeios
 * delas. Idempotente (`MERGE`): rodar de novo atualiza. No fim calcula `NEAR` (3 km) e `RELATED_TO`
 * como os comandos fazem. Os ids são slugs (`seed-…`); os importados do Wikidata usam o QID, então os
 * dois convivem.
 */
class GraphSeeder extends Seeder {

    /** Na ordem: o pai sempre antes do filho. */
    public const LOCATIONS = [
        ['id' => 'seed-brasil', 'name' => 'Brasil', 'kind' => LocationKindEnum::COUNTRY, 'parent' => null, 'lat' => -14.2350, 'lng' => -51.9253],
        ['id' => 'seed-minas-gerais', 'name' => 'Minas Gerais', 'kind' => LocationKindEnum::STATE, 'parent' => 'seed-brasil', 'lat' => -18.5122, 'lng' => -44.5550],
        ['id' => 'seed-estado-rio-de-janeiro', 'name' => 'Rio de Janeiro', 'kind' => LocationKindEnum::STATE, 'parent' => 'seed-brasil', 'lat' => -22.2500, 'lng' => -42.6600],
        ['id' => 'seed-portugal', 'name' => 'Portugal', 'kind' => LocationKindEnum::COUNTRY, 'parent' => null, 'lat' => 39.3999, 'lng' => -8.2245],
        ['id' => 'seed-ouro-preto', 'name' => 'Ouro Preto', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-minas-gerais', 'lat' => -20.3856, 'lng' => -43.5035],
        ['id' => 'seed-mariana', 'name' => 'Mariana', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-minas-gerais', 'lat' => -20.3778, 'lng' => -43.4169],
        ['id' => 'seed-congonhas', 'name' => 'Congonhas', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-minas-gerais', 'lat' => -20.4996, 'lng' => -43.8615],
        ['id' => 'seed-tiradentes', 'name' => 'Tiradentes', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-minas-gerais', 'lat' => -21.1100, 'lng' => -44.1781],
        ['id' => 'seed-sao-joao-del-rei', 'name' => 'São João del-Rei', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-minas-gerais', 'lat' => -21.1356, 'lng' => -44.2617],
        ['id' => 'seed-belo-horizonte', 'name' => 'Belo Horizonte', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-minas-gerais', 'lat' => -19.9167, 'lng' => -43.9345],
        ['id' => 'seed-rio-de-janeiro', 'name' => 'Rio de Janeiro', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-estado-rio-de-janeiro', 'lat' => -22.9068, 'lng' => -43.1729],
        ['id' => 'seed-lisboa', 'name' => 'Lisboa', 'kind' => LocationKindEnum::CITY, 'parent' => 'seed-portugal', 'lat' => 38.7223, 'lng' => -9.1393],
    ];

    public const THEMES = [
        ['name' => 'Ciclo do Ouro', 'description' => 'Cidades e minas da corrida do ouro em Minas Gerais, nos séculos XVII e XVIII.'],
        ['name' => 'Arte Barroca', 'description' => 'Igrejas e obras do barroco mineiro, de Aleijadinho a Mestre Ataíde.'],
        ['name' => 'Estrada Real', 'description' => 'O caminho oficial da Coroa entre as minas e os portos do Rio de Janeiro e de Paraty.'],
        ['name' => 'Modernismo', 'description' => 'A arquitetura moderna brasileira, de Niemeyer a Burle Marx.'],
        ['name' => 'Cartões-postais do Rio', 'description' => 'As vistas que todo mundo reconhece da cidade.'],
        ['name' => 'Era dos Descobrimentos', 'description' => 'Monumentos das navegações portuguesas dos séculos XV e XVI.'],
    ];

    public const PLACES = [
        ['id' => 'seed-igreja-sao-francisco-ouro-preto', 'name' => 'Igreja de São Francisco de Assis', 'location' => 'seed-ouro-preto', 'lat' => -20.3866, 'lng' => -43.5034,
            'description' => 'Igreja do século XVIII com projeto, portada e púlpitos de Aleijadinho e forro pintado por Mestre Ataíde.',
            'categories' => ['igreja'], 'themes' => ['Arte Barroca', 'Ciclo do Ouro'], 'authors' => ['Aleijadinho', 'Mestre Ataíde'], 'styles' => ['barroco']],
        ['id' => 'seed-matriz-pilar-ouro-preto', 'name' => 'Matriz de Nossa Senhora do Pilar', 'location' => 'seed-ouro-preto', 'lat' => -20.3883, 'lng' => -43.5092,
            'description' => 'Uma das igrejas mais ricas do barroco brasileiro, com talha coberta de ouro.',
            'categories' => ['igreja'], 'themes' => ['Arte Barroca', 'Ciclo do Ouro'], 'authors' => [], 'styles' => ['barroco']],
        ['id' => 'seed-museu-inconfidencia', 'name' => 'Museu da Inconfidência', 'location' => 'seed-ouro-preto', 'lat' => -20.3856, 'lng' => -43.5036,
            'description' => 'Antiga Casa de Câmara e Cadeia, guarda a memória da Inconfidência Mineira.',
            'categories' => ['museu'], 'themes' => ['Ciclo do Ouro', 'Estrada Real'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-mina-passagem', 'name' => 'Mina da Passagem', 'location' => 'seed-mariana', 'lat' => -20.3880, 'lng' => -43.4500,
            'description' => 'Mina de ouro do século XVIII aberta à visitação, com descida em trole.',
            'categories' => ['mina'], 'themes' => ['Ciclo do Ouro'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-catedral-se-mariana', 'name' => 'Catedral da Sé de Mariana', 'location' => 'seed-mariana', 'lat' => -20.3779, 'lng' => -43.4163,
            'description' => 'Catedral com órgão Arp Schnitger de 1701, ainda em uso em concertos.',
            'categories' => ['igreja'], 'themes' => ['Arte Barroca', 'Estrada Real'], 'authors' => ['Mestre Ataíde'], 'styles' => ['barroco']],
        ['id' => 'seed-santuario-bom-jesus-congonhas', 'name' => 'Santuário do Bom Jesus de Matosinhos', 'location' => 'seed-congonhas', 'lat' => -20.4930, 'lng' => -43.8565,
            'description' => 'Patrimônio Mundial: os doze profetas em pedra-sabão e os passos da Paixão de Aleijadinho.',
            'categories' => ['igreja', 'santuário'], 'themes' => ['Arte Barroca'], 'authors' => ['Aleijadinho'], 'styles' => ['barroco']],
        ['id' => 'seed-matriz-santo-antonio-tiradentes', 'name' => 'Matriz de Santo Antônio', 'location' => 'seed-tiradentes', 'lat' => -21.1103, 'lng' => -44.1777,
            'description' => 'Matriz com fachada atribuída a Aleijadinho e interior revestido de ouro.',
            'categories' => ['igreja'], 'themes' => ['Arte Barroca', 'Estrada Real'], 'authors' => ['Aleijadinho'], 'styles' => ['barroco']],
        ['id' => 'seed-igreja-sao-francisco-sjdr', 'name' => 'Igreja de São Francisco de Assis (São João del-Rei)', 'location' => 'seed-sao-joao-del-rei', 'lat' => -21.1365, 'lng' => -44.2617,
            'description' => 'Igreja com risco original de Aleijadinho, famosa pelas palmeiras imperiais do adro.',
            'categories' => ['igreja'], 'themes' => ['Arte Barroca', 'Estrada Real'], 'authors' => ['Aleijadinho'], 'styles' => ['barroco']],
        ['id' => 'seed-estacao-maria-fumaca', 'name' => 'Estação Ferroviária de São João del-Rei', 'location' => 'seed-sao-joao-del-rei', 'lat' => -21.1323, 'lng' => -44.2563,
            'description' => 'Ponto de partida do trem a vapor que liga São João del-Rei a Tiradentes.',
            'categories' => ['trem turístico'], 'themes' => ['Estrada Real'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-igreja-pampulha', 'name' => 'Igreja de São Francisco de Assis da Pampulha', 'location' => 'seed-belo-horizonte', 'lat' => -19.8582, 'lng' => -43.9786,
            'description' => 'Igreja modernista de Oscar Niemeyer, com painéis de Portinari e jardins de Burle Marx.',
            'categories' => ['igreja'], 'themes' => ['Modernismo'], 'authors' => ['Oscar Niemeyer', 'Candido Portinari'], 'styles' => ['modernismo']],
        ['id' => 'seed-mercado-central-bh', 'name' => 'Mercado Central de Belo Horizonte', 'location' => 'seed-belo-horizonte', 'lat' => -19.9228, 'lng' => -43.9437,
            'description' => 'Mercado de 1929 com queijos, cachaças e artesanato mineiro.',
            'categories' => ['mercado'], 'themes' => [], 'authors' => [], 'styles' => []],
        ['id' => 'seed-praca-liberdade-bh', 'name' => 'Praça da Liberdade', 'location' => 'seed-belo-horizonte', 'lat' => -19.9321, 'lng' => -43.9381,
            'description' => 'Antigo centro do governo mineiro, hoje cercado de museus do Circuito Liberdade.',
            'categories' => ['praça'], 'themes' => ['Modernismo'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-cristo-redentor', 'name' => 'Cristo Redentor', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9519, 'lng' => -43.2105,
            'description' => 'Estátua art déco de 1931 no alto do Corcovado, com vista para a baía e as praias.',
            'categories' => ['monumento'], 'themes' => ['Cartões-postais do Rio'], 'authors' => ['Paul Landowski'], 'styles' => ['art déco']],
        ['id' => 'seed-pao-de-acucar', 'name' => 'Pão de Açúcar', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9492, 'lng' => -43.1546,
            'description' => 'Morro na entrada da Baía de Guanabara, com bondinho desde 1912.',
            'categories' => ['mirante'], 'themes' => ['Cartões-postais do Rio'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-escadaria-selaron', 'name' => 'Escadaria Selarón', 'location' => 'seed-rio-de-janeiro', 'lat' => -22.9153, 'lng' => -43.1794,
            'description' => 'Escada entre a Lapa e Santa Teresa coberta por azulejos de mais de 60 países.',
            'categories' => ['arte urbana'], 'themes' => ['Cartões-postais do Rio'], 'authors' => [], 'styles' => []],
        ['id' => 'seed-torre-de-belem', 'name' => 'Torre de Belém', 'location' => 'seed-lisboa', 'lat' => 38.6916, 'lng' => -9.2160,
            'description' => 'Fortaleza do século XVI na margem do Tejo, de onde partiam as naus.',
            'categories' => ['monumento'], 'themes' => ['Era dos Descobrimentos'], 'authors' => [], 'styles' => ['manuelino']],
        ['id' => 'seed-mosteiro-jeronimos', 'name' => 'Mosteiro dos Jerónimos', 'location' => 'seed-lisboa', 'lat' => 38.6979, 'lng' => -9.2068,
            'description' => 'Mosteiro manuelino de 1501, com o claustro e o túmulo de Vasco da Gama.',
            'categories' => ['mosteiro'], 'themes' => ['Era dos Descobrimentos'], 'authors' => [], 'styles' => ['manuelino']],
        ['id' => 'seed-padrao-descobrimentos', 'name' => 'Padrão dos Descobrimentos', 'location' => 'seed-lisboa', 'lat' => 38.6936, 'lng' => -9.2057,
            'description' => 'Monumento de 1960 em forma de proa, com o mapa-múndi em mármore no chão.',
            'categories' => ['monumento'], 'themes' => ['Era dos Descobrimentos'], 'authors' => [], 'styles' => []],
    ];

    /** Fictícias: nenhuma empresa real é representada. */
    public const COMPANIES = [
        ['id' => 'seed-trilhas-do-ouro', 'name' => 'Trilhas do Ouro Turismo', 'kind' => CompanyKindEnum::AGENCY, 'base' => 'seed-ouro-preto',
            'description' => 'Passeios a pé pelo centro histórico e roteiros de um dia até Mariana.', 'website' => 'https://trilhasdoouro.example', 'phone' => '+55 31 0000-0001',
            'serves' => ['seed-ouro-preto', 'seed-mariana'], 'operates' => ['seed-igreja-sao-francisco-ouro-preto', 'seed-matriz-pilar-ouro-preto', 'seed-museu-inconfidencia', 'seed-mina-passagem']],
        ['id' => 'seed-guia-barroco', 'name' => 'Guia Barroco Mineiro', 'kind' => CompanyKindEnum::GUIDE, 'base' => 'seed-congonhas',
            'description' => 'Guias credenciados especializados na obra de Aleijadinho.', 'website' => 'https://guiabarroco.example', 'phone' => '+55 31 0000-0002',
            'serves' => ['seed-congonhas', 'seed-ouro-preto', 'seed-tiradentes', 'seed-sao-joao-del-rei'],
            'operates' => ['seed-santuario-bom-jesus-congonhas', 'seed-igreja-sao-francisco-ouro-preto', 'seed-matriz-santo-antonio-tiradentes', 'seed-igreja-sao-francisco-sjdr']],
        ['id' => 'seed-pousada-caminho-real', 'name' => 'Pousada Caminho Real', 'kind' => CompanyKindEnum::LODGING, 'base' => 'seed-tiradentes',
            'description' => 'Pousada no centro de Tiradentes, a duas quadras da Matriz.', 'website' => 'https://pousadacaminhoreal.example', 'phone' => '+55 32 0000-0003',
            'serves' => ['seed-tiradentes'], 'operates' => ['seed-matriz-santo-antonio-tiradentes']],
        ['id' => 'seed-expresso-estrada-real', 'name' => 'Expresso Estrada Real', 'kind' => CompanyKindEnum::TRANSPORT, 'base' => 'seed-belo-horizonte',
            'description' => 'Traslados entre Belo Horizonte e as cidades históricas.', 'website' => 'https://expressoestradareal.example', 'phone' => '+55 31 0000-0004',
            'serves' => ['seed-belo-horizonte', 'seed-ouro-preto', 'seed-mariana', 'seed-congonhas', 'seed-tiradentes', 'seed-sao-joao-del-rei'],
            'operates' => ['seed-estacao-maria-fumaca']],
        ['id' => 'seed-carioca-a-pe', 'name' => 'Carioca a Pé', 'kind' => CompanyKindEnum::GUIDE, 'base' => 'seed-rio-de-janeiro',
            'description' => 'Guias locais para os clássicos do Rio, em grupos pequenos.', 'website' => 'https://cariocaape.example', 'phone' => '+55 21 0000-0005',
            'serves' => ['seed-rio-de-janeiro'], 'operates' => []],
        ['id' => 'seed-lisboa-ribeirinha', 'name' => 'Lisboa Ribeirinha', 'kind' => CompanyKindEnum::AGENCY, 'base' => 'seed-lisboa',
            'description' => 'Passeios pela margem do Tejo, de Belém ao Cais do Sodré.', 'website' => 'https://lisboaribeirinha.example', 'phone' => '+351 21 000 0006',
            'serves' => ['seed-lisboa'], 'operates' => []],
    ];

    /** Fictícios, como as empresas. `stops` na ordem do passeio. */
    public const TOURS = [
        ['id' => 'seed-tour-centro-ouro-preto', 'company' => 'seed-trilhas-do-ouro', 'name' => 'Centro histórico de Ouro Preto a pé',
            'description' => 'Ladeiras, igrejas e o museu da Inconfidência numa caminhada de uma manhã.', 'minutes' => 180, 'price' => 120.0, 'currency' => 'BRL',
            'stops' => ['seed-museu-inconfidencia', 'seed-igreja-sao-francisco-ouro-preto', 'seed-matriz-pilar-ouro-preto']],
        ['id' => 'seed-tour-ouro-preto-mariana', 'company' => 'seed-trilhas-do-ouro', 'name' => 'Ouro Preto e Mariana em um dia',
            'description' => 'As duas primeiras vilas do ouro, com descida na mina e o órgão da Sé.', 'minutes' => 480, 'price' => 290.0, 'currency' => 'BRL',
            'stops' => ['seed-igreja-sao-francisco-ouro-preto', 'seed-museu-inconfidencia', 'seed-mina-passagem', 'seed-catedral-se-mariana']],
        ['id' => 'seed-tour-aleijadinho', 'company' => 'seed-guia-barroco', 'name' => 'Aleijadinho: de Congonhas a Ouro Preto',
            'description' => 'Os profetas de Congonhas e a igreja que ele projetou em Ouro Preto.', 'minutes' => 600, 'price' => 350.0, 'currency' => 'BRL',
            'stops' => ['seed-santuario-bom-jesus-congonhas', 'seed-igreja-sao-francisco-ouro-preto']],
        ['id' => 'seed-tour-campo-das-vertentes', 'company' => 'seed-guia-barroco', 'name' => 'Barroco de São João del-Rei e Tiradentes',
            'description' => 'Duas igrejas com risco de Aleijadinho e a estação do trem a vapor.', 'minutes' => 360, 'price' => 220.0, 'currency' => 'BRL',
            'stops' => ['seed-igreja-sao-francisco-sjdr', 'seed-estacao-maria-fumaca', 'seed-matriz-santo-antonio-tiradentes']],
        ['id' => 'seed-tour-maria-fumaca', 'company' => 'seed-expresso-estrada-real', 'name' => 'Maria Fumaça até Tiradentes',
            'description' => 'Trem a vapor entre as duas cidades, com tempo livre na Matriz.', 'minutes' => 240, 'price' => 95.0, 'currency' => 'BRL',
            'stops' => ['seed-estacao-maria-fumaca', 'seed-matriz-santo-antonio-tiradentes']],
        ['id' => 'seed-tour-bh-moderna', 'company' => 'seed-expresso-estrada-real', 'name' => 'Belo Horizonte moderna',
            'description' => 'Da Praça da Liberdade à Pampulha, com parada no Mercado Central.', 'minutes' => 300, 'price' => 140.0, 'currency' => 'BRL',
            'stops' => ['seed-praca-liberdade-bh', 'seed-mercado-central-bh', 'seed-igreja-pampulha']],
        ['id' => 'seed-tour-rio-classico', 'company' => 'seed-carioca-a-pe', 'name' => 'Rio clássico',
            'description' => 'Corcovado de manhã, Lapa no almoço e o bondinho no fim da tarde.', 'minutes' => 480, 'price' => 380.0, 'currency' => 'BRL',
            'stops' => ['seed-cristo-redentor', 'seed-escadaria-selaron', 'seed-pao-de-acucar']],
        ['id' => 'seed-tour-por-do-sol-urca', 'company' => 'seed-carioca-a-pe', 'name' => 'Pão de Açúcar ao pôr do sol',
            'description' => 'Subida de bondinho para ver o sol se pôr atrás do Corcovado.', 'minutes' => 180, 'price' => 210.0, 'currency' => 'BRL',
            'stops' => ['seed-pao-de-acucar']],
        ['id' => 'seed-tour-belem', 'company' => 'seed-lisboa-ribeirinha', 'name' => 'Belém numa manhã',
            'description' => 'O mosteiro, o padrão e a torre, a pé pela margem do rio.', 'minutes' => 210, 'price' => 45.0, 'currency' => 'EUR',
            'stops' => ['seed-mosteiro-jeronimos', 'seed-padrao-descobrimentos', 'seed-torre-de-belem']],
    ];

    /** Curadoria: a ligação que motivou o produto. */
    public const CURATED = [
        ['seed-igreja-sao-francisco-ouro-preto', 'seed-santuario-bom-jesus-congonhas', 'Obras-primas de Aleijadinho no ciclo do ouro'],
        ['seed-estacao-maria-fumaca', 'seed-matriz-santo-antonio-tiradentes', 'O trem a vapor leva até Tiradentes'],
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

        // As chaves `seed-…` das constantes viram o id opaco (`seed_id`) na escrita.
        $id = fn(?string $key) => $key === null ? null : seed_id($key);
        $ids = fn(array $keys) => array_map($id, $keys);

        $graph->write(
            'UNWIND $locations AS row
             MERGE (l:Location {id: row.id})
             SET l.name = row.name, l.kind = row.kind, l.location = point({latitude: row.lat, longitude: row.lng})
             WITH l, row WHERE row.parent IS NOT NULL
             MATCH (parent:Location {id: row.parent})
             MERGE (l)-[:WITHIN]->(parent)',
            ['locations' => array_map(fn(array $location) => ['id' => $id($location['id']), 'parent' => $id($location['parent']), 'kind' => $location['kind']->value] + $location, self::LOCATIONS)]
        );

        $graph->write('UNWIND $themes AS row MERGE (t:Theme {name: row.name}) SET t.description = row.description', ['themes' => self::THEMES]);

        $graph->write(
            'UNWIND $places AS row
             MATCH (l:Location {id: row.location})
             MERGE (p:Place {id: row.id})
             SET p.name = row.name, p.description = row.description, p.image = null,
                 p.location = point({latitude: row.lat, longitude: row.lng}), p.authors = row.authors, p.styles = row.styles
             MERGE (p)-[:LOCATED_IN]->(l)
             WITH p, row
             CALL (p, row) { UNWIND row.categories AS name MERGE (k:Category {name: name}) ON CREATE SET k.id = randomUUID() MERGE (p)-[:HAS_CATEGORY]->(k) }
             CALL (p, row) { UNWIND row.themes AS name MATCH (t:Theme {name: name}) MERGE (p)-[:PART_OF]->(t) }',
            ['places' => array_map(fn(array $place) => ['id' => $id($place['id']), 'location' => $id($place['location'])] + $place, self::PLACES)]
        );

        $graph->write(
            'UNWIND $companies AS row
             MATCH (base:Location {id: row.base})
             MERGE (co:Company {id: row.id})
             SET co.name = row.name, co.kind = row.kind, co.description = row.description, co.website = row.website, co.phone = row.phone
             MERGE (co)-[:BASED_IN]->(base)
             WITH co, row
             CALL (co, row) { UNWIND row.serves AS id MATCH (l:Location {id: id}) MERGE (co)-[:SERVES]->(l) }
             CALL (co, row) { UNWIND row.operates AS id MATCH (p:Place {id: id}) MERGE (co)-[:OPERATES_AT]->(p) }',
            ['companies' => array_map(fn(array $company) => [
                'id' => $id($company['id']),
                'kind' => $company['kind']->value,
                'base' => $id($company['base']),
                'serves' => $ids($company['serves']),
                'operates' => $ids($company['operates']),
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
             MATCH (p:Place {id: row.stops[index]})
             CREATE (t)-[:STOPS_AT {order: index + 1}]->(p)',
            ['tours' => array_map(fn(array $tour) => ['id' => $id($tour['id']), 'company' => $id($tour['company']), 'stops' => $ids($tour['stops'])] + $tour, self::TOURS)]
        );

        $graph->write(
            'UNWIND $pairs AS pair
             MATCH (a:Place {id: pair[0]}), (b:Place {id: pair[1]})
             MERGE (a)-[r:RELATED_TO {kind: $curated}]->(b)
             SET r.value = pair[2]',
            ['pairs' => array_map(fn(array $pair) => [$id($pair[0]), $id($pair[1]), $pair[2]], self::CURATED), 'curated' => RelationReasonEnum::CURATED->value]
        );

        $assignCategoryIds->handle();
        $nameCategories->handle();
        $near = $placeProximity->handle(3000);
        $related = $relatePlaces->handle();

        $this->command?->info(sprintf(
            'Grafo de exemplo: %d localidades, %d lugares, %d empresas, %d passeios; %d NEAR, %d RELATED_TO.',
            count(self::LOCATIONS), count(self::PLACES), count(self::COMPANIES), count(self::TOURS), $near, $related
        ));
    }
}
