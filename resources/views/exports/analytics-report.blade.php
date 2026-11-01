<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px 40px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #0f172a;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.4;
        }
        .masthead {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 18px;
        }
        .masthead td { vertical-align: middle; }
        .brand-bar {
            height: 6px;
            background: linear-gradient(90deg, #055498 0%, #123a60 55%, #FBD116 100%);
            margin: -4px 0 16px;
        }
        .seal {
            width: 54px;
            height: 54px;
        }
        .title {
            margin: 0;
            color: #123a60;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .subtitle {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 10px;
        }
        .meta {
            text-align: right;
            color: #475569;
            font-size: 9px;
        }
        .meta strong {
            display: block;
            color: #055498;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .section {
            margin: 0 0 18px;
            page-break-inside: avoid;
        }
        .section-head {
            background: #123a60;
            color: #fff;
            padding: 8px 12px;
            border-radius: 8px 8px 0 0;
            font-size: 12px;
            font-weight: 700;
        }
        .section-accent {
            height: 3px;
            background: #FBD116;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.data th {
            background: #055498;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 8px 8px;
            border-bottom: 2px solid #FBD116;
        }
        table.data td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            font-size: 10px;
            color: #1e293b;
        }
        table.data tr:nth-child(even) td {
            background: #f8fafc;
        }
        table.data tr:last-child td {
            border-bottom: 0;
        }
        .empty {
            padding: 14px 12px;
            color: #94a3b8;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-top: 0;
            border-radius: 0 0 8px 8px;
        }
        .charts-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 12px;
            margin: 0 0 8px;
        }
        .chart-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px 8px;
            background: #fff;
        }
        .chart-title {
            margin: 0 0 10px;
            color: #123a60;
            font-size: 12px;
            font-weight: 700;
            word-spacing: 0.18em;
        }
        .chart-row {
            border-collapse: collapse;
            width: 100%;
            margin: 0 0 6px;
        }
        .chart-row td {
            padding: 0;
            vertical-align: middle;
            font-size: 9px;
            color: #475569;
        }
        .chart-label {
            width: 110px;
            padding-right: 8px !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chart-bar-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            height: 10px;
        }
        .chart-bar-table td {
            height: 10px;
            padding: 0;
            border: 0;
        }
        .chart-fill-cell {
            background: #055498;
        }
        .chart-track-cell {
            background: #eef2f6;
        }
        .chart-value {
            width: 52px;
            text-align: right;
            padding-left: 8px !important;
            font-weight: 700;
            color: #123a60;
        }
        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -18px;
            color: #94a3b8;
            font-size: 8px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 999px;
            background: rgba(5, 84, 152, 0.1);
            color: #055498;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="brand-bar"></div>
    <table class="masthead">
        <tr>
            <td style="width: 66px;">
                @if (! empty($sealSrc))
                    <img class="seal" src="{{ $sealSrc }}" alt="" width="54" height="54">
                @endif
            </td>
            <td>
                <p class="badge">Dangerous Drugs Board</p>
                <h1 class="title">{{ $title }}</h1>
                <p class="subtitle">{{ $subtitle }}</p>
            </td>
            <td class="meta" style="width: 150px;">
                <strong>DAPE-MA</strong>
                Generated {{ $generatedAt }}
            </td>
        </tr>
    </table>

    @if (! empty($charts))
        <section class="section">
            <div class="section-head">Charts</div>
            <div class="section-accent"></div>
            <div style="border: 1px solid #e2e8f0; border-top: 0; border-radius: 0 0 8px 8px; padding: 12px;">
                @foreach ($charts as $chart)
                    @php
                        $items = collect($chart['items'] ?? [])->filter(fn ($item) => (float) ($item['value'] ?? 0) > 0)->values();
                        $max = max(1, (float) $items->max(fn ($item) => (float) ($item['value'] ?? 0)));
                    @endphp
                    @if ($items->isNotEmpty())
                        <div class="chart-card" style="margin-bottom: 12px;">
                            <p class="chart-title">{{ str_replace(' ', html_entity_decode('&nbsp;'), (string) ($chart['title'] ?? 'Chart')) }}</p>
                            @foreach ($items->take(16) as $item)
                                @php
                                    $value = (float) ($item['value'] ?? 0);
                                    $width = (int) max(2, min(100, round(($value / $max) * 100)));
                                    $rest = 100 - $width;
                                    $rawLabel = (string) ($item['label'] ?? '');
                                    $label = \Illuminate\Support\Str::limit($rawLabel, 22, '…');
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
            </div>
        </section>
    @endif

    @foreach ($sheets as $sheet)
        <section class="section">
            <div class="section-head">{{ $sheet['name'] }}</div>
            <div class="section-accent"></div>
            @if (empty($sheet['rows']))
                <div class="empty">No rows for this section in the selected range.</div>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            @foreach ($sheet['headers'] as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($sheet['rows'], 0, 400) as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (count($sheet['rows']) > 400)
                    <div class="empty">Showing first 400 of {{ number_format(count($sheet['rows'])) }} rows.</div>
                @endif
            @endif
        </section>
    @endforeach

    <div class="footer">
        Confidential — for authorized DAPE-MA administrators · Dangerous Drugs Board · {{ $generatedAt }}
    </div>
</body>
</html>
