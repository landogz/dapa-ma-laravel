<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 24px 28px 36px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #0f172a;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 10px;
            line-height: 1.35;
        }
        .top-rule {
            height: 5px;
            background: #055498;
            margin: 0 0 2px;
        }
        .top-gold {
            height: 3px;
            background: #FBD116;
            margin: 0 0 14px;
        }
        .masthead {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .masthead td { vertical-align: middle; padding: 0; }
        .seal { width: 48px; height: 48px; }
        .kicker {
            margin: 0 0 3px;
            color: #055498;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .title {
            margin: 0;
            color: #123a60;
            font-size: {{ ! empty($wide) ? '16px' : '18px' }};
            font-weight: 700;
            line-height: 1.25;
        }
        .subtitle {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 9px;
        }
        .meta {
            text-align: right;
            color: #475569;
            font-size: 8px;
            white-space: nowrap;
            width: 170px;
        }
        .meta strong {
            display: block;
            color: #123a60;
            font-size: 9px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .confidential {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 7px;
            border: 1px solid #FBD116;
            color: #123a60;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .section {
            margin: 0 0 16px;
            border: 1px solid #e2e8f0;
            page-break-inside: auto;
        }
        .section-head {
            background: #123a60;
            color: #fff;
            padding: 7px 12px;
            font-size: 11px;
            font-weight: 700;
        }
        .section-accent { height: 3px; background: #FBD116; }
        .section-note {
            padding: 8px 12px;
            color: #64748b;
            background: #f8fafc;
            font-size: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }
        table.data th {
            background: #055498;
            color: #fff;
            font-size: 8px;
            font-weight: 700;
            text-align: left;
            padding: 7px 8px;
            border-right: 1px solid rgba(255,255,255,0.12);
            white-space: nowrap;
        }
        table.data th:last-child { border-right: 0; }
        table.data td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 9px;
            color: #1e293b;
            word-break: normal;
            overflow-wrap: break-word;
        }
        table.data td:last-child { border-right: 0; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        table.data tr:last-child td { border-bottom: 0; }
        td.is-num, th.is-num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        td.is-date, th.is-date { white-space: nowrap; }
        td.is-rank {
            text-align: center;
            font-weight: 700;
            color: #055498;
            white-space: nowrap;
            width: 42px;
        }
        td.is-text { min-width: 90px; }
        .empty {
            padding: 14px 12px;
            color: #94a3b8;
            background: #fff;
            font-size: 9px;
        }
        .chart-card {
            margin: 10px 12px;
            border: 1px solid #e2e8f0;
            padding: 10px 12px 8px;
            background: #fff;
            page-break-inside: avoid;
        }
        .chart-title {
            margin: 0 0 8px;
            color: #123a60;
            font-size: 11px;
            font-weight: 700;
        }
        .chart-row {
            border-collapse: collapse;
            width: 100%;
            margin: 0 0 5px;
        }
        .chart-row td {
            padding: 0;
            vertical-align: middle;
            font-size: 8px;
            color: #475569;
        }
        .chart-label {
            width: 130px;
            padding-right: 8px !important;
            white-space: nowrap;
        }
        .chart-bar-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            height: 8px;
        }
        .chart-bar-table td { height: 8px; padding: 0; border: 0; }
        .chart-fill-cell { background: #055498; }
        .chart-track-cell { background: #eef2f6; }
        .chart-value {
            width: 48px;
            text-align: right;
            padding-left: 8px !important;
            font-weight: 700;
            color: #123a60;
            white-space: nowrap;
        }
        .footer {
            position: fixed;
            left: 0;
            right: 120px;
            bottom: -14px;
            color: #94a3b8;
            font-size: 7px;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
    </style>
</head>
<body>
    @php
        $titleParts = explode(' — ', (string) $title, 2);
        $heading = $titleParts[1] ?? $titleParts[0];
        $kicker = $titleParts[1] ?? null ? 'Dangerous Drugs Board · DAPE-MA' : 'Dangerous Drugs Board';
    @endphp
    <div class="top-rule"></div>
    <div class="top-gold"></div>
    <table class="masthead">
        <tr>
            <td style="width: 58px;">
                @if (! empty($sealSrc))
                    <img class="seal" src="{{ $sealSrc }}" alt="" width="48" height="48">
                @endif
            </td>
            <td>
                <p class="kicker">{{ $kicker }}</p>
                <h1 class="title">{{ $heading }}</h1>
                <p class="subtitle">{{ $subtitle }}</p>
            </td>
            <td class="meta">
                <strong>DAPE-MA</strong>
                Generated {{ $generatedAt }}
                <div class="confidential">Confidential</div>
            </td>
        </tr>
    </table>

    @if (! empty($charts))
        <section class="section">
            <div class="section-head">Charts</div>
            <div class="section-accent"></div>
            @foreach ($charts as $chart)
                @php
                    $items = collect($chart['items'] ?? [])->filter(fn ($item) => (float) ($item['value'] ?? 0) > 0)->values();
                    $max = max(1, (float) $items->max(fn ($item) => (float) ($item['value'] ?? 0)));
                @endphp
                @if ($items->isNotEmpty())
                    <div class="chart-card">
                        <p class="chart-title">{{ $chart['title'] ?? 'Chart' }}</p>
                        @foreach ($items->take(12) as $item)
                            @php
                                $value = (float) ($item['value'] ?? 0);
                                $width = (int) max(2, min(100, round(($value / $max) * 100)));
                                $rest = 100 - $width;
                                $label = \Illuminate\Support\Str::limit((string) ($item['label'] ?? ''), 28, '…');
                            @endphp
                            <table class="chart-row">
                                <tr>
                                    <td class="chart-label">{{ $label }}</td>
                                    <td>
                                        <table class="chart-bar-table">
                                            <tr>
                                                <td class="chart-fill-cell" width="{{ $width }}%"></td>
                                                <td class="chart-track-cell" width="{{ $rest }}%"></td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td class="chart-value">{{ number_format($value) }}</td>
                                </tr>
                            </table>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </section>
    @endif

    @foreach ($sheets as $sheet)
        @php
            $headers = $sheet['headers'] ?? [];
            $rows = array_slice($sheet['rows'] ?? [], 0, 400);
        @endphp
        <section class="section">
            <div class="section-head">{{ $sheet['name'] }}</div>
            <div class="section-accent"></div>
            @if (! empty($sheet['note']))
                <div class="section-note">{{ $sheet['note'] }}</div>
            @endif
            @if (empty($rows))
                <div class="empty">No rows for this section in the selected range.</div>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            @foreach ($headers as $index => $header)
                                @php $headerClass = $index === 0 && strcasecmp((string) $header, 'Rank') === 0 ? 'is-rank' : ''; @endphp
                                <th class="{{ $headerClass }}">{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($row as $index => $cell)
                                    @php
                                        $header = (string) ($headers[$index] ?? '');
                                        $class = 'is-text';
                                        if ($index === 0 && strcasecmp($header, 'Rank') === 0) {
                                            $class = 'is-rank';
                                        } elseif (is_int($cell) || is_float($cell) || (is_string($cell) && is_numeric($cell))) {
                                            $class = 'is-num';
                                        } elseif (is_string($cell) && preg_match('/^[A-Z][a-z]{2} \d{1,2}, \d{4}/', $cell)) {
                                            $class = 'is-date';
                                        }
                                        $display = is_int($cell) || (is_string($cell) && preg_match('/^-?\d+$/', $cell))
                                            ? number_format((int) $cell)
                                            : $cell;
                                    @endphp
                                    <td class="{{ $class }}">{{ $display }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (count($sheet['rows'] ?? []) > 400)
                    <div class="section-note">Showing first 400 of {{ number_format(count($sheet['rows'])) }} rows.</div>
                @endif
            @endif
        </section>
    @endforeach

    <div class="footer">
        Confidential — for authorized DAPE-MA administrators · Dangerous Drugs Board · {{ $generatedAt }}
    </div>
</body>
</html>
