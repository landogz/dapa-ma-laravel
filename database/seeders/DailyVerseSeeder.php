<?php

namespace Database\Seeders;

use App\Models\DailyVerse;
use Illuminate\Database\Seeder;

class DailyVerseSeeder extends Seeder
{
    public function run(): void
    {
        $verses = [
            [
                'reference' => 'Juan 3:16',
                'verse_text' => 'Sapagkat gayon na lamang ang pag-ibig ng Dios sa sanglibutan, na ibinigay niya ang kaniyang bugtong na Anak, upang ang sinumang sa kaniya ay sumampalataya ay huwag mapahamak, kundi magkaroon ng buhay na walang hanggan.',
                'book' => 'John', 'chapter' => 3, 'verse_start' => 16, 'verse_end' => 16,
            ],
            [
                'reference' => 'Filipos 4:13',
                'verse_text' => 'Lahat ng mga bagay ay magagawa ko sa pamamagitan niya na nagpapalakas sa akin.',
                'book' => 'Philippians', 'chapter' => 4, 'verse_start' => 13, 'verse_end' => 13,
            ],
            [
                'reference' => 'Awit 23:1',
                'verse_text' => 'Ang Panginoon ay aking pastor; hindi ako mangangailangan.',
                'book' => 'Psalms', 'chapter' => 23, 'verse_start' => 1, 'verse_end' => 1,
            ],
            [
                'reference' => 'Kawikaan 3:5-6',
                'verse_text' => 'Tumiwala ka sa Panginoon ng buong puso mo, at huwag kang manalig sa sarili mong kaunawaan. Sa lahat ng iyong mga lakad ay kilalanin mo siya, at kaniyang itutuwid ang iyong mga landas.',
                'book' => 'Proverbs', 'chapter' => 3, 'verse_start' => 5, 'verse_end' => 6,
            ],
            [
                'reference' => 'Isaias 41:10',
                'verse_text' => 'Huwag kang matakot, sapagkat ako ay sumasaiyo; huwag kang manghina, sapagkat ako ang iyong Dios; aking palalakasin ka, oo, aking tutulungan ka.',
                'book' => 'Isaiah', 'chapter' => 41, 'verse_start' => 10, 'verse_end' => 10,
            ],
            [
                'reference' => 'Mateo 11:28',
                'verse_text' => 'Magsiparito kayo sa akin, kayong lahat na nangapapagal at nangabibigatang lubha, at kayo ay bibigyan ko ng kapahingahan.',
                'book' => 'Matthew', 'chapter' => 11, 'verse_start' => 28, 'verse_end' => 28,
            ],
            [
                'reference' => 'Roma 8:28',
                'verse_text' => 'At nalalaman namin na sa mga umiibig sa Dios ay nagkakalakip sa mabubuti ang lahat ng mga bagay.',
                'book' => 'Romans', 'chapter' => 8, 'verse_start' => 28, 'verse_end' => 28,
            ],
            [
                'reference' => 'Jeremias 29:11',
                'verse_text' => 'Sapagkat talastas ko ang mga katha na aking iniisip tungkol sa inyo, sabi ng Panginoon, mga katha ng kapayapaan, at hindi ng kasamaan, upang bigyan kayo ng pag-asa sa inyong huling wakas.',
                'book' => 'Jeremiah', 'chapter' => 29, 'verse_start' => 11, 'verse_end' => 11,
            ],
            [
                'reference' => '1 Corinto 10:13',
                'verse_text' => 'Walang tukso na dumating sa inyo na hindi likas sa tao; datapuwa’t tapat ang Dios, na hindi niya papahintulutang kayo ay tuksuhin na higit sa inyong makakaya.',
                'book' => '1 Corinthians', 'chapter' => 10, 'verse_start' => 13, 'verse_end' => 13,
            ],
            [
                'reference' => '2 Timoteo 1:7',
                'verse_text' => 'Sapagkat hindi tayo binigyan ng Dios ng espiritu ng takot; kundi ng kapangyarihan at ng pag-ibig at ng pagpipigil sa sarili.',
                'book' => '2 Timothy', 'chapter' => 1, 'verse_start' => 7, 'verse_end' => 7,
            ],
            [
                'reference' => 'Santiago 1:5',
                'verse_text' => 'Kung ang sinoman sa inyo ay kulang sa karunungan, humingi siya sa Dios, na nagbibigay sa lahat ng mga tao na walang pagkutya, at ibibigay sa kaniya.',
                'book' => 'James', 'chapter' => 1, 'verse_start' => 5, 'verse_end' => 5,
            ],
            [
                'reference' => '1 Pedro 5:7',
                'verse_text' => 'Ihulog ninyo sa kaniya ang lahat ng inyong kabalisahan, sapagkat siya ay nagmamalasakit sa inyo.',
                'book' => '1 Peter', 'chapter' => 5, 'verse_start' => 7, 'verse_end' => 7,
            ],
            [
                'reference' => '1 Juan 4:19',
                'verse_text' => 'Tayo ay nagsisiibig sa kaniya, sapagkat siya ang unang umibig sa atin.',
                'book' => '1 John', 'chapter' => 4, 'verse_start' => 19, 'verse_end' => 19,
            ],
            [
                'reference' => 'Efeso 2:8-9',
                'verse_text' => 'Sapagkat sa biyaya kayo ay naligtas sa pamamagitan ng pananampalataya; at hindi ito sa inyong sarili: ito ay kaloob ng Dios.',
                'book' => 'Ephesians', 'chapter' => 2, 'verse_start' => 8, 'verse_end' => 9,
            ],
            [
                'reference' => 'Galacia 5:22-23',
                'verse_text' => 'Datapuwa’t ang bunga ng Espiritu ay pag-ibig, kagalakan, kapayapaan, pagpapahinugan, kagandahang-loob, kabutihan, pagtatapat.',
                'book' => 'Galatians', 'chapter' => 5, 'verse_start' => 22, 'verse_end' => 23,
            ],
            [
                'reference' => 'Josue 1:9',
                'verse_text' => 'Hindi ba iniutos ko sa iyo? Magpakalakas ka at magpakatapang; huwag kang matakot, ni manghina ang iyong loob.',
                'book' => 'Joshua', 'chapter' => 1, 'verse_start' => 9, 'verse_end' => 9,
            ],
            [
                'reference' => 'Awit 46:1',
                'verse_text' => 'Ang Dios ay ating kanlungan at kalakasan, isang matibay na tulong sa kabagabagan.',
                'book' => 'Psalms', 'chapter' => 46, 'verse_start' => 1, 'verse_end' => 1,
            ],
            [
                'reference' => 'Kawikaan 18:10',
                'verse_text' => 'Ang pangalan ng Panginoon ay isang matibay na moog; tumatakas sa kaniya ang matuwid at ligtas.',
                'book' => 'Proverbs', 'chapter' => 18, 'verse_start' => 10, 'verse_end' => 10,
            ],
            [
                'reference' => 'Lukas 6:31',
                'verse_text' => 'At gaya ng ibig ninyong gawin ng mga tao sa inyo, gayundin ang gawin ninyo sa kanila.',
                'book' => 'Luke', 'chapter' => 6, 'verse_start' => 31, 'verse_end' => 31,
            ],
            [
                'reference' => 'Marcos 10:27',
                'verse_text' => 'Mga tao, ang mga bagay na ito ay hindi mangyayari; datapuwa’t sa Dios ay mangyayari ang lahat ng mga bagay.',
                'book' => 'Mark', 'chapter' => 10, 'verse_start' => 27, 'verse_end' => 27,
            ],
            [
                'reference' => 'Gawa 16:31',
                'verse_text' => 'At kanilang sinabi, Sumampalataya ka sa Panginoong Jesus, at maliligtas ka, ikaw at ang iyong sangbahayan.',
                'book' => 'Acts', 'chapter' => 16, 'verse_start' => 31, 'verse_end' => 31,
            ],
            [
                'reference' => 'Pahayag 21:4',
                'verse_text' => 'At papahirin niya ang bawat luha sa kanilang mga mata; at hindi na magkakaroon ng kamatayan, ni kalungkutan, ni pagdaing, ni hirap pa man.',
                'book' => 'Revelation', 'chapter' => 21, 'verse_start' => 4, 'verse_end' => 4,
            ],
            [
                'reference' => 'Mateo 6:33',
                'verse_text' => 'Datapuwa’t hanapin muna ninyo ang kaniyang kaharian, at ang kaniyang katuwiran; at ang lahat ng mga bagay na ito ay idaragdag sa inyo.',
                'book' => 'Matthew', 'chapter' => 6, 'verse_start' => 33, 'verse_end' => 33,
            ],
            [
                'reference' => 'Roma 12:2',
                'verse_text' => 'At huwag kayong magsihabi sa anyo ng sanlibutang ito: kundi kayo’y magbagong-anyo sa pamamagitan ng pagbabago ng inyong pagiisip.',
                'book' => 'Romans', 'chapter' => 12, 'verse_start' => 2, 'verse_end' => 2,
            ],
            [
                'reference' => 'Awit 119:105',
                'verse_text' => 'Ang iyong salita ay ilaw sa aking mga paa, at liwanag sa aking landas.',
                'book' => 'Psalms', 'chapter' => 119, 'verse_start' => 105, 'verse_end' => 105,
            ],
            [
                'reference' => 'Isaias 40:31',
                'verse_text' => 'Datapuwa’t ang nagsisiasa sa Panginoon ay makakakuha ng bagong lakas; sila’y lilipad na parang mga agila.',
                'book' => 'Isaiah', 'chapter' => 40, 'verse_start' => 31, 'verse_end' => 31,
            ],
            [
                'reference' => 'Filipos 4:6-7',
                'verse_text' => 'Huwag kayong mangabalisa sa anumang bagay; kundi sa lahat ng mga bagay ay ipagbigay-alam ninyo ang inyong mga kahilingan sa Dios sa pamamagitan ng panalangin.',
                'book' => 'Philippians', 'chapter' => 4, 'verse_start' => 6, 'verse_end' => 7,
            ],
            [
                'reference' => '1 Tesalonica 5:16-18',
                'verse_text' => 'Magsigalak kayong lagi; magsipanalangin kayong walang patid; sa lahat ng mga bagay ay magpasalamat kayo.',
                'book' => '1 Thessalonians', 'chapter' => 5, 'verse_start' => 16, 'verse_end' => 18,
            ],
            [
                'reference' => 'Hebreo 11:1',
                'verse_text' => 'Ngayon, ang pananampalataya ay siyang kapanatagan sa mga bagay na hinihintay, ang katunayan ng mga bagay na hindi nakikita.',
                'book' => 'Hebrews', 'chapter' => 11, 'verse_start' => 1, 'verse_end' => 1,
            ],
            [
                'reference' => 'Colosas 3:23',
                'verse_text' => 'At anumang inyong gawin, gawin ninyo ng buong puso, na gaya ng sa Panginoon, at hindi sa mga tao.',
                'book' => 'Colossians', 'chapter' => 3, 'verse_start' => 23, 'verse_end' => 23,
            ],
            [
                'reference' => 'Mikias 6:8',
                'verse_text' => 'Ipinakita niya sa iyo, Oh tao, kung ano ang mabuti; at ano ang itinakda ng Panginoon sa iyo, kundi ang gumawa ng katarungan, at umibig sa kaawaan, at lumakad na may kapakumbabaan kasama ng iyong Dios?',
                'book' => 'Micah', 'chapter' => 6, 'verse_start' => 8, 'verse_end' => 8,
            ],
        ];

        foreach ($verses as $index => $verse) {
            DailyVerse::query()->updateOrCreate(
                ['day_of_year' => $index + 1],
                [
                    'reference'   => $verse['reference'],
                    'verse_text'  => $verse['verse_text'],
                    'translation' => 'Tagalog',
                    'book'        => $verse['book'],
                    'chapter'     => $verse['chapter'],
                    'verse_start' => $verse['verse_start'],
                    'verse_end'   => $verse['verse_end'],
                ],
            );
        }
    }
}
