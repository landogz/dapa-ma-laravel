<?php

namespace App\Support;

final class PrivacyNoticeContent
{
    /**
     * @return array{
     *     slug: string,
     *     title_en: string,
     *     title_tl: string,
     *     subtitle_en: string,
     *     subtitle_tl: string,
     *     intro_en: string,
     *     intro_tl: string,
     *     body_en: string,
     *     body_tl: string,
     *     is_active: bool
     * }
     */
    public static function page(): array
    {
        return [
            'slug' => 'privacy_notice',
            'title_en' => 'Privacy Notice',
            'title_tl' => 'Abiso sa Privacy',
            'subtitle_en' => 'Agreement for access to classified and sensitive information',
            'subtitle_tl' => 'Kasunduan para sa access sa classified at sensitibong impormasyon',
            'intro_en' => 'Please read the herein stated Agreement carefully and evidence your acceptance of its terms by clicking on the Agree button below:',
            'intro_tl' => 'Pakibasa nang mabuti ang Kasunduang nakasaad dito at patunayan ang iyong pagtanggap sa mga tuntunin nito sa pamamagitan ng pag-click sa pindutang Agree sa ibaba:',
            'body_en' => self::bodyEn(),
            'body_tl' => self::bodyTl(),
            'is_active' => true,
        ];
    }

    private static function bodyEn(): string
    {
        return <<<'TXT'
I hereby accept the obligations contained in this Agreement in consideration of my being granted access to classified and/or sensitive information under the Drug Abuse Prevention and Education Mobile Application (DAPE-MA). As used in this Agreement, classified information is referred to confidential information, including oral communications.

I understand and accept that by being granted access to classified information to the herein referred online system, the confidence and trust are placed in me by the Dangerous Drugs Board.

I understand that the DDB is the responsible agency in collecting all information submitted to the DAPE-MA by end-users.

I am fully aware that the data collection is for analysis and monitoring of reports and that the information gathered shall be used as basis for the development and improvement of policies and drug abuse prevention programs.

I acknowledge that the information generated from the DAPE-MA may be shared to other authorized organizations, partner agencies, local government units and other stakeholders for research, policy formulation and program development.

I agree that DDB shall immediately be notified if there are errors or discrepancies in the data being reported.

When notifying the DDB for possible security breaches, I shall include the following:
• Name and User ID
• Name of Agency/Organization/Facility
• Contact details
• Date and time of unauthorized access
• Description of security breach or fraud
• Description of error that has occurred.

I have been informed that unauthorized disclosure, unauthorized retention, or negligent handling of classified information by me could cause damage or irreparable injury to the Board and/or Government of the Philippines or could be used to advantage by other nation or people.

I hereby agree that I shall not divulge classified information to anyone unless:

• Officially verified that the recipient has been properly authorized by the Dangerous Drugs Board or by the government of the Philippines to receive it;
• I have been given prior written notice of authorization from the Dangerous Drugs Board responsible for the classified information, or granting me clearance that such disclosure is permitted;
• I have confirmed from an authorized official that the information is unclassified before I may disclose it.

I am obligated to comply with laws and regulations that prohibit the unauthorized disclosure of classified information.

By my (i) use and/or continued use of this Website, or (ii) access and use of the service provided through this Website, I signify my agreement to indemnify and to keep the DDB, its directors, employees fully and effectively indemnified against all actions, liabilities, costs, claims, losses, damages, proceedings and/or expenses (including all legal costs on an indemnity basis) without prejudice to the filing of any criminal case for violation of any statute or law.

Violation of any of the provisions of this agreement shall constitute breach and shall terminate this agreement immediately without need of notice, without prejudice to criminal prosecution for violation of RA 10173 otherwise known as the “Data Privacy Act of 2012,” as well as other pertinent laws.

Unless and until I am released in writing that all conditions and obligations imposed upon me by this Agreement applies during the time I am granted access to classified information and at all times thereafter.

Please accept to continue
TXT;
    }

    private static function bodyTl(): string
    {
        return <<<'TXT'
Tinatanggap ko ang mga obligasyong nakapaloob sa Kasunduang ito bilang kapalit ng pagkakaloob sa akin ng access sa classified at/o sensitibong impormasyon sa ilalim ng Drug Abuse Prevention and Education Mobile Application (DAPE-MA). Sa Kasunduang ito, ang classified information ay tumutukoy sa kumpidensyal na impormasyon, kabilang ang mga oral na komunikasyon.

Nauunawaan at tinatanggap ko na sa pagkakaloob sa akin ng access sa classified information sa nabanggit na online system, inilalagak sa akin ng Dangerous Drugs Board ang kanilang tiwala.

Nauunawaan ko na ang DDB ang ahensyang responsable sa pagkolekta ng lahat ng impormasyong isinusumite sa DAPE-MA ng mga end-user.

Batid ko na ang pagkolekta ng datos ay para sa pagsusuri at pagsubaybay ng mga ulat, at gagamitin ang nakalap na impormasyon bilang batayan sa pagpapaunlad at pagpapabuti ng mga polisiya at programa sa pag-iwas sa pag-abuso sa droga.

Kinikilala ko na ang impormasyong nalilikha mula sa DAPE-MA ay maaaring ibahagi sa iba pang awtorisadong organisasyon, kasosyong ahensya, local government units, at iba pang stakeholder para sa pananaliksik, pagbuo ng polisiya, at pagpapaunlad ng programa.

Sumasang-ayon ako na agad na aabisuhan ang DDB kung may mga mali o hindi pagkakatugma sa datos na nire-report.

Kapag aabisuhan ang DDB ukol sa posibleng security breach, isasama ko ang mga sumusunod:
• Pangalan at User ID
• Pangalan ng Ahensya/Organisasyon/Pasilidad
• Detalye ng pakikipag-ugnayan
• Petsa at oras ng hindi awtorisadong pag-access
• Paglalarawan ng security breach o panloloko
• Paglalarawan ng naganap na error.

Naipaalam sa akin na ang hindi awtorisadong pagbunyag, hindi awtorisadong pagtatago, o pabayaang paghawak ko sa classified information ay maaaring magdulot ng pinsala o hindi na mababawi na injury sa Board at/o sa Pamahalaan ng Pilipinas, o magamit nang pabor ng ibang bansa o tao.

Sumasang-ayon ako na hindi ko ibubunyag ang classified information kaninuman maliban kung:

• Opisyal na napatunayan na ang tatanggap ay wastong pinahintulutan ng Dangerous Drugs Board o ng pamahalaan ng Pilipinas na tumanggap nito;
• Binigyan ako ng paunang nakasulat na paunawa ng awtorisasyon mula sa Dangerous Drugs Board na responsable sa classified information, o ng clearance na pinahihintulutan ang naturang pagbunyag;
• Nakumpirma ko mula sa awtorisadong opisyal na unclassified ang impormasyon bago ko ito maibunyag.

Obligado akong sumunod sa mga batas at regulasyong nagbabawal sa hindi awtorisadong pagbunyag ng classified information.

Sa aking (i) paggamit at/o patuloy na paggamit ng Website na ito, o (ii) access at paggamit ng serbisyong ibinibigay sa pamamagitan ng Website na ito, ipinapahayag ko ang aking pagsang-ayon na indemnify at panatilihing fully at effectively indemnified ang DDB, ang mga direktor, at empleyado nito laban sa lahat ng aksyon, pananagutan, gastos, claim, pagkawala, pinsala, proceedings, at/o gastusin (kabilang ang lahat ng legal na gastos on an indemnity basis), nang hindi isinasakripisyo ang paghahain ng anumang criminal case para sa paglabag sa anumang batas.

Ang paglabag sa alinmang probisyon ng kasunduang ito ay ituturing na breach at agad na magwawakas ang kasunduang ito nang hindi na kailangan ng paunawa, nang hindi isinasakripisyo ang criminal prosecution para sa paglabag sa RA 10173 na kilala rin bilang “Data Privacy Act of 2012,” at iba pang naaangkop na batas.

Hangga’t hindi ako pinalalaya sa pamamagitan ng sulat, ang lahat ng kundisyon at obligasyong ipinapataw sa akin ng Kasunduang ito ay nalalapat habang ako ay pinagkalooban ng access sa classified information at sa lahat ng panahon pagkatapos nito.

Pakitanggap upang magpatuloy
TXT;
    }
}
