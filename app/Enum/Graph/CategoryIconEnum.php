<?php

namespace App\Enum\Graph;

/**
 * Ícone de cada categoria (`Category.icon`): o nome de um ícone do Iconify (`conjunto:nome`), que o web
 * desenha direto com o `Icon` do `@iconify/react`. As categorias vêm do Wikidata em texto livre
 * ("igreja", "museu de arte", "estação ferroviária"), então o ícone sai por palavra-chave do nome em
 * pt-br (e do en-us, quando há), na ordem de `RULES` (a primeira regra que casa vence: a mais específica
 * vem antes). Sem nenhuma, `PLACE` (alfinete). Preferência pelo Tabler (traço, o mesmo desenho do resto
 * do web); estátua, escultura, museu, cachoeira e banco não existem nele.
 */
enum CategoryIconEnum: string {
    case MUSEUM = 'mdi:museum-outline';
    case LIBRARY = 'tabler:books';
    case CHURCH = 'tabler:building-church';
    case CROSS = 'tabler:cross';
    case COMMUNITY = 'tabler:building-community';
    case GRAVE = 'tabler:grave-2';
    case BUS = 'tabler:bus';
    case TRAIN = 'tabler:train';
    case BRIDGE = 'tabler:building-bridge';
    case TUNNEL = 'tabler:building-tunnel';
    case STATUE = 'temaki:statue';
    case SCULPTURE = 'temaki:sculpture';
    case MONUMENT = 'tabler:building-monument';
    case FOUNTAIN = 'tabler:fountain';
    case ART = 'tabler:palette';
    case THEATER = 'tabler:masks-theater';
    case CINEMA = 'tabler:movie';
    case PAVILION = 'tabler:building-pavilion';
    case TELESCOPE = 'tabler:telescope';
    case VIEWPOINT = 'tabler:binoculars';
    case WATERFALL = 'mdi:waterfall';
    case AQUARIUM = 'tabler:fish';
    case WATER = 'tabler:ripple';
    case BEACH = 'tabler:beach';
    case PORT = 'tabler:anchor';
    case BOAT = 'tabler:sailboat';
    case MOUNTAIN = 'tabler:mountain';
    case MINE = 'tabler:pick';
    case FOREST = 'tabler:trees';
    case TREE = 'tabler:tree';
    case ZOO = 'tabler:paw';
    case AMUSEMENT = 'tabler:building-carousel';
    case GARDEN = 'tabler:plant-2';
    case SQUARE = 'mdi:bench';
    case STADIUM = 'tabler:building-stadium';
    case SCHOOL = 'tabler:school';
    case HOSPITAL = 'tabler:building-hospital';
    case PRISON = 'tabler:prison';
    case COURT = 'tabler:scale';
    case CASTLE = 'tabler:building-castle';
    case FORTRESS = 'tabler:building-fortress';
    case WINDMILL = 'tabler:windmill';
    case FARM = 'tabler:tractor';
    case FACTORY = 'tabler:building-factory-2';
    case CRANE = 'tabler:crane';
    case HOTEL = 'tabler:bed';
    case CAFE = 'tabler:coffee';
    case BAR = 'tabler:beer';
    case BAKERY = 'tabler:bread';
    case RESTAURANT = 'tabler:tools-kitchen-2';
    case STORE = 'tabler:building-store';
    case MAIL = 'tabler:mail';
    case CLOCK = 'tabler:clock';
    case LAMP = 'tabler:bulb';
    case FURNITURE = 'tabler:armchair';
    case STAIRS = 'tabler:stairs';
    case FENCE = 'tabler:fence';
    case GATE = 'tabler:building-arch';
    case GOVERNMENT = 'tabler:building-bank';
    case MANOR = 'tabler:building-cottage';
    case HOUSE = 'tabler:home';
    case SKYSCRAPER = 'tabler:building-skyscraper';
    case ROAD = 'tabler:road';
    case DISTRICT = 'tabler:map-2';
    case EVENT = 'tabler:confetti';
    case HERITAGE = 'tabler:award';
    case BUILDING = 'tabler:building';
    case PLACE = 'tabler:map-pin';

    /**
     * Da mais específica para a mais geral: "museu ferroviário" é museu, "ponte ferroviária" é ponte,
     * "edifício escolar" é escola, "patrimônio natural" é natureza. Expressões sem acento opcional usam
     * a classe (`[aá]`); o texto chega em minúsculas.
     */
    private const array RULES = [
        ['/museu|museo|museum|cinemateca/u', self::MUSEUM],
        ['/bibliotec|library|acervo|arquiv|archive|cole[cç][aã]o|collection|\blivro\b|livraria|\bbook|editor/u', self::LIBRARY],
        ['/terreiro|candombl|quilombo/u', self::COMMUNITY],
        ['/cruzeiro|via sacra|sacro monte|calvary|objeto religioso|ret[aá]bulo|nicho/u', self::CROSS],
        ['/catedral|cathedral|bas[ií]lica|igreja|church|capela|chapel|santu[aá]rio|orat[oó]rio|templo|temple|par[oó]quia|abadia|mosteiro|convento|semin[aá]rio|epis(c|copal)|loja ma[cç]/u', self::CHURCH],
        ['/cemit[eé]rio|cemetery|t[uú]mulo|grave/u', self::GRAVE],
        ['/[oô]nibus|\bbus\b/u', self::BUS],
        ['/ponte|bridge|viaduto|viaduct|aqueduto|aqueduct|passadi[cç]o/u', self::BRIDGE],
        ['/t[uú]nel|tunnel/u', self::TUNNEL],
        ['/usina|power station|bombeamento|pumping|abastecimento|filling station/u', self::FACTORY],
        ['/esta[cç][aã]o|station|ferrovi|railway|locomotiv|trem|train|bonde|tram|funicular|metr[oô]/u', self::TRAIN],
        ['/est[aá]tua|statue|busto|\bbust\b|herma|ef[ií]gie|effigy/u', self::STATUE],
        ['/escult|sculpt/u', self::SCULPTURE],
        ['/monumento|monument|marco|landmark|obelisco|memorial|mon[oó]lito|pedra angular|ponto de refer|pelourinho/u', self::MONUMENT],
        ['/bem tombado|bem cultural|cultural property/u', self::HERITAGE],
        ['/chafariz|fonte termal|hot spring/u', self::FOUNTAIN],
        ['/fonte|fountain|bebedouro|caixa-d|water tank|water trough/u', self::FOUNTAIN],
        ['/\bmural\b|mosaic|painel|pintura|painting|afresco|fresco|vitral|arte urbana|street art/u', self::ART],
        ['/cinema|movie/u', self::CINEMA],
        ['/teatro|theat|espet[aá]culo|casa de shows|concert|concha ac|dan[cç]a|cassino|casino|instrumento musical/u', self::THEATER],
        ['/coreto|bandstand|caramanch|gloriet|pavilh|pavilion/u', self::PAVILION],
        ['/observat[oó]rio/u', self::TELESCOPE],
        ['/mirante|viewpoint|belveder|\bvista\b|\bview\b/u', self::VIEWPOINT],
        ['/queda d|cachoeira|waterfall/u', self::WATERFALL],
        ['/aqu[aá]rio|aquarium/u', self::AQUARIUM],
        ['/praia|beach|duna|dune|fal[eé]sia|cliff|ilha|island/u', self::BEACH],
        ['/porto|\bport\b|p[ií]er|ancoradouro|wharf|deque|\bdeck\b/u', self::PORT],
        ['/\biate\b|yacht|galeota|barge/u', self::BOAT],
        ['/lago|lake|laguna|lagoon|reservat[oó]rio|reservoir|barragem|\bdam\b|canal|nascente|\bspring\b|parque aqu[aá]tico/u', self::WATER],
        ['/\bmina\b|\bmine\b/u', self::MINE],
        ['/montanha|mountain|cordilheira|morro|\bhill\b|\bserra\b|\bpico\b|inselberg|rochos|\brock|caverna|\bcave\b|gruta/u', self::MOUNTAIN],
        ['/zool[oó]gico|\bzoo\b/u', self::ZOO],
        ['/divers[aã]o|amusement|roda-gigante|ferris/u', self::AMUSEMENT],
        ['/floresta|forest|parque nacional|parque estadual|parque natural|national park|state park|nature park|reserva|reserve|protegid|protected|prote[cç][aã]o ambiental|patrim[oô]nio natural|natural heritage|percurso|trail/u', self::FOREST],
        ['/[aá]rvore|\btree\b|jequitib/u', self::TREE],
        ['/jardim|garden|parque|\bpark\b|[aá]rea verde/u', self::GARDEN],
        ['/pra[cç]a|square|\bbanco\b|bench/u', self::SQUARE],
        ['/est[aá]dio|stadium|arena|hip[oó]dromo|racing|futebol|football|esport|sport|samb[oó]dromo/u', self::STADIUM],
        ['/escola militar|military school/u', self::SCHOOL],
        ['/forte\b|\bfort\b|fortale|fortifica|muralha|city walls|blocausse|blockhouse|base militar|military base|quartel|barracks/u', self::FORTRESS],
        ['/escol|school|col[eé]gio|college|faculdade|faculty|universi|ensino|educa|acad[eê]mic|academic|conservat[oó]rio|ber[cç][aá]rio|nursery|dormit[oó]rio|institut/u', self::SCHOOL],
        ['/hospital|sanat[oó]rio|drogaria|drugstore/u', self::HOSPITAL],
        ['/pris[aã]o|prison|cadeia|tortura/u', self::PRISON],
        ['/f[oó]rum|courthouse|tribunal/u', self::COURT],
        ['/castelo|castle|pal[aá]cio|palace/u', self::CASTLE],
        ['/hotel|pousada/u', self::HOTEL],
        ['/moinho|windmill/u', self::WINDMILL],
        ['/fazenda|engenho|serraria|sawmill|matadouro|slaughter/u', self::FARM],
        ['/usina|power station|bombeamento|pumping|f[aá]brica|factory|industri|chamin[eé]|chimney|galp[aã]o|\bshed\b|hangar|casa da moeda|\bmint\b|fundi[cç][aã]o|foundry|\bgr[aá]fica|printing|abastecimento/u', self::FACTORY],
        ['/guindaste|crane/u', self::CRANE],
        ['/cafeteria|caf[eé]\b|lanchonete|snack/u', self::CAFE],
        ['/\bbar\b|taberna|tavern|cantina/u', self::BAR],
        ['/padaria|bakery|confeitaria|confection/u', self::BAKERY],
        ['/restaurante|restaurant|pizzaria|pizzeria/u', self::RESTAURANT],
        ['/correio|\bmail\b|post office/u', self::MAIL],
        ['/arranha-c[eé]u|skyscraper|edif[ií]cio de escrit/u', self::SKYSCRAPER],
        ['/mercado|market|feira|com[eé]rci|commercial|loja|store|neg[oó]cio|business|empresa|company|tabacaria|smoke shop|[oó]ptica|eyewear|livraria|escrit[oó]rio|office/u', self::STORE],
        ['/rel[oó]gio|clock|compasso/u', self::CLOCK],
        ['/poste|utility pole|l[aâ]mpada|\blamp\b|lustre|chandelier/u', self::LAMP],
        ['/mobili[aá]rio|furniture|vaso|vase/u', self::FURNITURE],
        ['/escada|stair|ladeira/u', self::STAIRS],
        ['/guarda-corpo|guard rail|amurada|balaustrada|balustrade|grade|grille|\bmuro\b|\bwall\b/u', self::FENCE],
        ['/port[aã]o|\bgate\b|portal|ru[ií]na|ruins|arqueol[oó]gic|archaeolog/u', self::GATE],
        ['/\brua\b|street|avenida|avenue|boulevard|via p[uú]blica|road|cal[cç]ada|sidewalk|logradouro|pavimento|floor|\bpiso\b|paver|estrada/u', self::ROAD],
        ['/prefeitura|city hall|c[aâ]mara|governo|government|governamental|administra[cç][aã]o p|p[uú]blic|tr[aâ]nsito|sede|headquarters|ag[eê]ncia|funda[cç][aã]o|foundation|associa[cç][aã]o|organiza[cç][aã]o|organization|clube|club|centro comunit|community center|centro cultural|cultural center|centro de conven|convention/u', self::GOVERNMENT],
        ['/solar|manor|mans[aã]o|mansion|h[oô]tel particulier|casa-grande|casa grande|villa|chal[eé]|chalet/u', self::MANOR],
        ['/casa|house|moradia|resid|sobrado|im[oó]vel|property|abrigo|shelter|home/u', self::HOUSE],
        ['/bairro|neighbo|distrito|district|cidade|town|favela|[aá]rea residencial|residential area|conjunto urbano|urban|prov[ií]ncia|prefecture|posi[cç][aã]o geo|geographic/u', self::DISTRICT],
        ['/evento|event|carnaval|carnival|movimento|movement|grupo de humanos|imaterial|intangible/u', self::EVENT],
        ['/edif|building|constru|estrutura|structure|conjunto|ensemble|complexo|complex|pr[eé]dio/u', self::BUILDING],
        ['/patrim[oô]nio|heritage|tombad|bem cultural|cultural property|hist[oó]ric|historic|estate/u', self::HERITAGE],
    ];

    /**
     * @param string|null ...$names o nome da categoria em cada idioma (o pt-br primeiro)
     * @return self
     */
    public static function for(?string ...$names): self {
        $text = mb_strtolower(implode(' | ', array_filter($names, fn(?string $name) => $name !== null && $name !== '')));

        if ($text === '') {
            return self::PLACE;
        }

        foreach (self::RULES as [$pattern, $icon]) {
            if (preg_match($pattern, $text) === 1) {
                return $icon;
            }
        }

        return self::PLACE;
    }
}
