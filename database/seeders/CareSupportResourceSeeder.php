<?php

namespace Database\Seeders;

use App\Models\CareSupportResource;
use Illuminate\Database\Seeder;

class CareSupportResourceSeeder extends Seeder
{
    public function run(): void
    {
        $metaEn = '24/7 | Free | Confidential';
        $metaTl = '24/7 | Libre | Kumpidensyal';

        $rows = [
            // Hotlines
            [
                'category' => 'hotline', 'sort_order' => 1, 'logo_initial' => 'N',
                'title_en' => 'National Center for Mental Health', 'title_tl' => 'National Center for Mental Health',
                'role_en' => 'Counselors', 'role_tl' => 'Mga Counselor',
                'meta_en' => $metaEn, 'meta_tl' => $metaTl,
                'phone' => '1553', 'web_url' => 'https://ncmh.gov.ph',
            ],
            [
                'category' => 'hotline', 'sort_order' => 2, 'logo_initial' => 'H',
                'title_en' => 'Hopeline Philippines', 'title_tl' => 'Hopeline Philippines',
                'role_en' => 'Counselors', 'role_tl' => 'Mga Counselor',
                'meta_en' => $metaEn, 'meta_tl' => $metaTl,
                'phone' => '0917 558 4673',
            ],
            [
                'category' => 'hotline', 'sort_order' => 3, 'logo_initial' => 'I',
                'title_en' => 'In Touch: Crisis Line', 'title_tl' => 'In Touch: Crisis Line',
                'role_en' => 'Volunteers', 'role_tl' => 'Mga Boluntaryo',
                'meta_en' => $metaEn, 'meta_tl' => $metaTl,
                'phone' => '+63 2 8893 7603', 'web_url' => 'https://in-touch.org',
            ],
            [
                'category' => 'hotline', 'sort_order' => 4, 'logo_initial' => 'D',
                'title_en' => 'Learners Telesafe Helpline', 'title_tl' => 'Learners Telesafe Helpline',
                'role_en' => 'Counselors', 'role_tl' => 'Mga Counselor',
                'meta_en' => $metaEn, 'meta_tl' => $metaTl,
                'phone' => '09451759777 | #33733',
            ],
            [
                'category' => 'hotline', 'sort_order' => 5, 'logo_initial' => 'B',
                'title_en' => 'BIDA Youth Hopeline', 'title_tl' => 'BIDA Youth Hopeline',
                'role_en' => 'Volunteers', 'role_tl' => 'Mga Boluntaryo',
                'meta_en' => $metaEn, 'meta_tl' => $metaTl,
                'phone' => '1553',
            ],

            // Counseling
            [
                'category' => 'counseling', 'sort_order' => 1, 'logo_initial' => 'N',
                'title_en' => 'National Center for Mental Health', 'title_tl' => 'National Center for Mental Health',
                'description_en' => "NCMH 'Usap Tayo' program is a free telemental health and crisis support service",
                'description_tl' => "Ang NCMH 'Usap Tayo' ay libreng telemental health at crisis support",
                'web_url' => 'https://ncmh.gov.ph', 'phone' => '1553',
            ],
            [
                'category' => 'counseling', 'sort_order' => 2, 'logo_initial' => 'P',
                'title_en' => 'Philippine General Hospital', 'title_tl' => 'Philippine General Hospital',
                'description_en' => 'Free outpatient psychiatric consultations, accessible via the PGH Online Consultation Request and Appointment system',
                'description_tl' => 'Libreng outpatient psychiatric consultations sa pamamagitan ng PGH Online Consultation system',
                'web_url' => 'https://www.pgh.gov.ph',
            ],
            [
                'category' => 'counseling', 'sort_order' => 3, 'logo_initial' => 'S',
                'title_en' => 'Sikhay ISIP', 'title_tl' => 'Sikhay ISIP',
                'description_en' => 'Free counseling and psychotherapy services provided by volunteer helping professionals (limited slots)',
                'description_tl' => 'Libreng counseling at psychotherapy mula sa volunteer professionals (limitado ang slots)',
            ],
            [
                'category' => 'counseling', 'sort_order' => 4, 'logo_initial' => 'L',
                'title_en' => 'Lusog MH', 'title_tl' => 'Lusog MH',
                'description_en' => 'Free counseling and psychotherapy services provided by volunteer helping professionals (limited slots)',
                'description_tl' => 'Libreng counseling at psychotherapy mula sa volunteer professionals (limitado ang slots)',
            ],
            [
                'category' => 'counseling', 'sort_order' => 5, 'logo_initial' => 'H',
                'title_en' => 'Hiraya MH, Inc.', 'title_tl' => 'Hiraya MH, Inc.',
                'description_en' => 'Free counseling and psychotherapy services provided by volunteer helping professionals (limited slots)',
                'description_tl' => 'Libreng counseling at psychotherapy mula sa volunteer professionals (limitado ang slots)',
            ],

            // Crisis emergency banner
            [
                'category' => 'crisis_emergency', 'sort_order' => 1, 'logo_initial' => '!',
                'title_en' => 'If you are in immediate danger, call 911 now.',
                'title_tl' => 'Kung nasa agarang panganib ka, tumawag sa 911 ngayon.',
                'description_en' => 'Immediate life-threatening emergencies',
                'description_tl' => 'Agarang emergency na may panganib sa buhay',
                'phone' => '911', 'is_emergency' => true,
            ],

            // Crisis resources (modal content)
            [
                'category' => 'crisis_resource', 'sort_order' => 1, 'icon_key' => 'info', 'logo_initial' => 'i',
                'title_en' => 'What is a crisis?', 'title_tl' => 'Ano ang crisis?',
                'description_en' => 'Learn the signals.', 'description_tl' => 'Alamin ang mga senyales.',
                'body_en' => [
                    'A crisis is when problems or emotions feel too heavy to manage alone.',
                    'It may involve danger to yourself, others, or your well-being.',
                    "A crisis means it's time to reach out for immediate help and guidance.",
                ],
                'body_tl' => [
                    'Ang crisis ay kapag masyadong mabigat ang problema o damdamin para harapin mag-isa.',
                    'Maaaring may panganib sa sarili, sa iba, o sa iyong kapakanan.',
                    'Ang crisis ay senyales na oras na humingi ng agarang tulong at gabay.',
                ],
            ],
            [
                'category' => 'crisis_resource', 'sort_order' => 2, 'icon_key' => 'plan', 'logo_initial' => 'P',
                'title_en' => 'Crisis Plan', 'title_tl' => 'Crisis Plan',
                'description_en' => 'Plan to keep yourself safe.', 'description_tl' => 'Plan para manatiling ligtas.',
                'body_en' => [
                    'Write down warning signs that a crisis may be starting.',
                    'List people and hotlines you can contact right away.',
                    'Include places that feel safe and activities that help you calm down.',
                ],
                'body_tl' => [
                    'Isulat ang mga senyales na maaaring magsimula ang crisis.',
                    'Ilista ang mga tao at hotline na matatawagan agad.',
                    'Isama ang mga ligtas na lugar at gawaing nakakapagpakalma.',
                ],
            ],
            [
                'category' => 'crisis_resource', 'sort_order' => 3, 'icon_key' => 'tips', 'logo_initial' => 'T',
                'title_en' => 'Safety Tips', 'title_tl' => 'Safety Tips',
                'description_en' => 'Steps to take during a crisis', 'description_tl' => 'Mga hakbang sa panahon ng crisis',
                'body_en' => [
                    'Move to a safer space if you can.',
                    'Call a trusted person or a 24/7 hotline.',
                    'Remove or distance yourself from things that could cause harm.',
                ],
                'body_tl' => [
                    'Lumipat sa mas ligtas na lugar kung kaya.',
                    'Tumawag sa pinagkakatiwalaang tao o 24/7 hotline.',
                    'Layuan ang mga bagay na maaaring makasakit.',
                ],
            ],
            [
                'category' => 'crisis_resource', 'sort_order' => 4, 'icon_key' => 'friend', 'logo_initial' => 'F',
                'title_en' => 'Supporting a Friend', 'title_tl' => 'Pagsuporta sa Kaibigan',
                'description_en' => 'How to help a friend in crisis', 'description_tl' => 'Paano tumulong sa kaibigang nasa crisis',
                'body_en' => [
                    'Listen without judgment and take their feelings seriously.',
                    'Ask directly if they are thinking of harming themselves.',
                    'Help them connect to hotlines, counseling, or emergency services.',
                ],
                'body_tl' => [
                    'Makinig nang walang hatol at seryosohin ang kanilang damdamin.',
                    'Direktang itanong kung iniisip nilang saktan ang sarili.',
                    'Tulungan silang makakonekta sa hotline, counseling, o emergency services.',
                ],
            ],
        ];

        foreach ($rows as $row) {
            CareSupportResource::query()->updateOrCreate(
                [
                    'category'  => $row['category'],
                    'title_en'  => $row['title_en'],
                    'sort_order'=> $row['sort_order'],
                ],
                array_merge([
                    'role_en'        => null,
                    'role_tl'        => null,
                    'description_en' => null,
                    'description_tl' => null,
                    'meta_en'        => null,
                    'meta_tl'        => null,
                    'phone'          => null,
                    'web_url'        => null,
                    'logo_url'       => null,
                    'icon_key'       => null,
                    'body_en'        => null,
                    'body_tl'        => null,
                    'is_emergency'   => false,
                    'is_active'      => true,
                ], $row),
            );
        }
    }
}
