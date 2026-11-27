<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Dashboard L10</title></head>
<body><main><h1>Dashboard ERP Lapak Sepuluh</h1><p>Masuk sebagai {{ auth()->user()->name }} ({{ auth()->user()->role }}).</p>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Keluar</button></form></main></body></html>
