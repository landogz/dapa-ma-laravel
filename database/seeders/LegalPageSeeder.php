<?php

namespace Database\Seeders;

use App\Models\LegalPage;
use Illuminate\Database\Seeder;

class LegalPageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'privacy_policy',
                'title_en' => 'Privacy Policy',
                'title_tl' => 'Patakaran sa Privacy',
                'subtitle_en' => 'Learn how we protect your data',
                'subtitle_tl' => 'Alamin kung paano namin pinoprotektahan ang data',
                'intro_en' => 'Your privacy matters to us. Read our Privacy Policy to learn how we protect your information.',
                'intro_tl' => 'Mahalaga sa amin ang iyong privacy. Basahin ang aming Patakaran sa Privacy.',
                'body_en' => "DAPE-MA may collect account details, learning activity, bookmarks, device information, and feedback you submit so we can deliver content, improve services, and support your recovery journey.\n\nWe use this information to personalize your experience, send relevant updates, and keep the platform secure. We do not sell your personal data.\n\nYou may request access, correction, or deletion of your information, and you may withdraw consent where applicable. Educational content is intended for guidance and should be used with appropriate professional or parental support when needed.\n\nFor privacy concerns, contact info@ddb.gov.ph.",
                'body_tl' => "Maaaring mangolekta ang DAPE-MA ng detalye ng account, aktibidad sa pag-aaral, bookmark, impormasyon ng device, at feedback upang makapaghatid ng nilalaman at mapabuti ang serbisyo.\n\nGinagamit namin ang impormasyong ito para sa mas angkop na karanasan, mga update, at seguridad. Hindi namin ibinebenta ang iyong personal na data.\n\nMaaari kang humiling ng access, pagwawasto, o pagbura ng iyong impormasyon. Para sa mga concern sa privacy, makipag-ugnayan sa info@ddb.gov.ph.",
                'is_active' => true,
            ],
            [
                'slug' => 'terms_of_use',
                'title_en' => 'Terms of Use',
                'title_tl' => 'Mga Tuntunin ng Paggamit',
                'subtitle_en' => 'Read the terms and conditions',
                'subtitle_tl' => 'Basahin ang mga tuntunin at kundisyon',
                'intro_en' => "Be informed.\nRead the terms and conditions for using the DAPE-MA application.",
                'intro_tl' => "Maging may kaalaman.\nBasahin ang mga tuntunin at kundisyon sa paggamit ng DAPE-MA.",
                'body_en' => "By using DAPE-MA, you agree to use the app responsibly and lawfully. Do not misuse content, attempt unauthorized access, copy or modify the application, or submit harmful or false information.\n\nContent is provided for information and education. It does not replace professional medical, legal, or counseling advice.\n\nWe may update these Terms of Use from time to time. Continued use of the app after updates means you accept the revised terms.\n\nFor questions or concerns regarding these Terms of Use, please contact us at info@ddb.gov.ph.",
                'body_tl' => "Sa paggamit ng DAPE-MA, sumasang-ayon kang gamitin ang app nang responsable at ayon sa batas. Huwag abusuhin ang nilalaman o subukang mag-access nang walang pahintulot.\n\nAng nilalaman ay para sa impormasyon at edukasyon. Hindi ito kapalit ng propesyonal na payo.\n\nMaaaring i-update ang mga Tuntunin. Ang patuloy na paggamit ay nangangahulugang tinatanggap mo ang mga pagbabago.\n\nPara sa katanungan, makipag-ugnayan sa info@ddb.gov.ph.",
                'is_active' => true,
            ],
        ];

        foreach ($pages as $page) {
            LegalPage::query()->updateOrCreate(
                ['slug' => $page['slug']],
                $page,
            );
        }
    }
}
