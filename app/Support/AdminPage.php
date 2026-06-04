<?php

namespace App\Support;

use Illuminate\Contracts\View\View;

final class AdminPage
{
    public static function render(string $view, string $activePage, string $headerTitle): View
    {
        return view($view, [
            'pageTitle'   => "DAPE-MA Admin | {$headerTitle}",
            'bodyPage'    => 'admin-' . $activePage,
            'activePage'  => $activePage,
            'headerTitle' => $headerTitle,
        ]);
    }
}
