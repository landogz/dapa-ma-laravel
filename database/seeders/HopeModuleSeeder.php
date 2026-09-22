<?php

namespace Database\Seeders;

use App\Models\HopeDirectoryOrganization;
use App\Models\HopeEvent;
use Illuminate\Database\Seeder;

class HopeModuleSeeder extends Seeder
{
    public function run(): void
    {
        $orgs = [
            [
                'name'        => 'Dangerous Drugs Board',
                'description' => 'policy-making body for preventive drug education',
                'category'    => 'government',
                'address'     => 'UP Film Center',
                'phone'       => '+632 8929-45-44',
                'email'       => 'info@ddb.gov.ph',
                'sort_order'  => 1,
            ],
            [
                'name'        => 'Department of Education',
                'description' => 'Barkada Kontra Droga (BKD) program partner in schools',
                'category'    => 'government',
                'address'     => 'NCR - Main Office',
                'phone'       => '8522-9412',
                'email'       => 'ncr@deped.gov.ph',
                'sort_order'  => 2,
            ],
            [
                'name'        => 'Department of Health',
                'description' => 'supports preventive drug and health campaigns',
                'category'    => 'government',
                'address'     => 'Sta. Cruz, Manila',
                'phone'       => '8651-7800',
                'email'       => 'callcenter@doh.gov.ph',
                'sort_order'  => 3,
            ],
            [
                'name'        => 'Philippine Council of NGOs Against Drug & Substance Abuse',
                'description' => 'non-profit organization',
                'category'    => 'ngo',
                'address'     => 'San Antonio, Manila',
                'phone'       => '0932 842 8304',
                'email'       => 'philcadsap@yahoo.com',
                'sort_order'  => 4,
            ],
        ];

        foreach ($orgs as $org) {
            HopeDirectoryOrganization::query()->updateOrCreate(
                ['name' => $org['name']],
                [...$org, 'is_active' => true],
            );
        }

        $events = [
            [
                'title'      => 'National Barkada Kontra Droga Youth Convention',
                'audience'   => 'youth',
                'status'     => 'upcoming',
                'start_date' => '2026-06-15',
                'end_date'   => '2026-06-17',
                'venue'      => 'UP Diliman, Quezon City',
                'is_online'  => false,
                'slots'      => 80,
                'sort_order' => 1,
                'about_text' => 'A three-day national forum that brings together youth leaders, student councils, and community advocates to strengthen Barkada Kontra Droga partnerships with NYC, DDB, and LGUs.',
                'for_you_items' => [
                    'Become a better leader',
                    'Promote awareness among peers',
                    'Build networks with fellow advocates',
                    'Contribute solutions through teamwork',
                ],
                'who_can_join' => 'Youth leaders, student councils, campus organizations, and community advocates aged 15–30.',
                'highlights' => [
                    ['label' => 'Leadership workshops on advocacy'],
                    ['label' => 'Peer education clinics'],
                    ['label' => 'Regional networking sessions'],
                ],
                'details_text' => 'Sessions run daily from 8:00 AM to 5:00 PM. Meals and certificates are provided for confirmed participants.',
                'speakers' => [
                    ['name' => 'NYC Representative', 'role' => 'Keynote'],
                    ['name' => 'DDB Preventive Education Lead', 'role' => 'Resource speaker'],
                ],
                'faqs' => [
                    ['question' => 'Is registration free?', 'answer' => 'Yes, selected participants attend free of charge.'],
                    ['question' => 'Do I need a school endorsement?', 'answer' => 'Yes, a school or LGU endorsement is required.'],
                ],
                'registration_url' => 'https://ddb.gov.ph',
            ],
            [
                'title'      => 'Kids Against Drugs Seminar',
                'audience'   => 'youth',
                'status'     => 'upcoming',
                'start_date' => '2026-06-19',
                'end_date'   => '2026-06-19',
                'venue'      => 'Marikina Convention Center',
                'is_online'  => false,
                'slots'      => 30,
                'sort_order' => 2,
                'about_text' => 'An interactive seminar that helps younger learners understand prevention, peer pressure, and healthy choices.',
                'for_you_items' => ['Learn refusal skills', 'Meet youth mentors'],
                'who_can_join' => 'Elementary and junior high student leaders with a teacher chaperone.',
                'highlights' => [['label' => 'Interactive learning stations']],
                'details_text' => 'Half-day seminar with activity kits.',
                'speakers' => [],
                'faqs' => [],
                'registration_url' => 'https://ddb.gov.ph',
            ],
            [
                'title'        => 'Parents-Youth Resource Against Drug Abuse',
                'audience'     => 'parents',
                'status'       => 'upcoming',
                'start_date'   => '2026-07-01',
                'end_date'     => '2026-07-01',
                'venue'        => null,
                'is_online'    => true,
                'online_label' => 'Zoom / Facebook LIVE',
                'slots'        => 40,
                'sort_order'   => 3,
                'about_text'   => 'A joint parents-and-youth online briefing on communication, early warning signs, and community support.',
                'for_you_items' => ['Strengthen family dialogue', 'Access referral pathways'],
                'who_can_join' => 'Parents, guardians, and youth advocates.',
                'highlights' => [['label' => 'Live Q&A with counselors']],
                'details_text' => 'Online session; link sent after registration.',
                'speakers' => [],
                'faqs' => [],
                'registration_url' => 'https://ddb.gov.ph',
            ],
            [
                'title'      => 'Drug-Abuse Prevention Program: Senior Citizens',
                'audience'   => 'community',
                'status'     => 'upcoming',
                'start_date' => '2026-07-23',
                'end_date'   => '2026-07-23',
                'venue'      => 'QC City Hall AVR',
                'is_online'  => false,
                'slots'      => 20,
                'sort_order' => 4,
                'about_text' => 'Community briefing for senior citizen groups supporting neighborhood prevention efforts.',
                'for_you_items' => ['Share community resources', 'Support barangay campaigns'],
                'who_can_join' => 'Senior citizen organizations and community volunteers.',
                'highlights' => [['label' => 'Barangay partnership clinic']],
                'details_text' => 'On-site registration opens at 7:30 AM.',
                'speakers' => [],
                'faqs' => [],
                'registration_url' => 'https://ddb.gov.ph',
            ],
        ];

        foreach ($events as $event) {
            HopeEvent::query()->updateOrCreate(
                ['title' => $event['title']],
                [...$event, 'is_active' => true],
            );
        }
    }
}
