<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px 48px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #111; }
        header { border-bottom: 3px solid #FFD400; padding-bottom: 8px; margin-bottom: 14px; }
        .brand { display: inline-block; background: #0b0b0c; color: #FFD400; font-weight: bold; padding: 4px 10px; font-size: 13px; letter-spacing: 2px; }
        h1 { font-size: 16px; margin: 8px 0 2px; }
        .meta { color: #555; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #0b0b0c; color: #FFD400; text-align: left; padding: 6px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .5px; }
        tbody td { padding: 5px 6px; border-bottom: 1px solid #e6e6e6; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #faf8ef; }
        footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8px; color: #777; text-align: right; }
        .pagenum:before { content: counter(page); }
        .empty { text-align: center; color: #777; padding: 18px; }
    </style>
</head>
<body>
<header>
    <span class="brand">TAP</span>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Generado el {{ $generatedAt }} · {{ count($rows) }} registro(s)
        @if ($subtitle) · {{ $subtitle }} @endif
    </div>
</header>

<table>
    <thead>
    <tr>
        @foreach ($columns as $label)
            <th>{{ $label }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @forelse ($rows as $row)
        <tr>
            @foreach (array_keys($columns) as $key)
                <td>{{ $row[$key] ?? '' }}</td>
            @endforeach
        </tr>
    @empty
        <tr><td class="empty" colspan="{{ count($columns) }}">Sin registros</td></tr>
    @endforelse
    </tbody>
</table>

<footer>Sistema de Gestión TAP · Página <span class="pagenum"></span></footer>
</body>
</html>
