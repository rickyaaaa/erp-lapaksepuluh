<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Masuk L10</title></head>
<body><main><h1>Masuk ERP Lapak Sepuluh</h1><form method="post" action="{{ route('login.store') }}">@csrf
<label>Email <input name="email" type="email" required autocomplete="username"></label>
<label>Password <input name="password" type="password" required autocomplete="current-password"></label>
<button type="submit">Masuk</button></form></main></body></html>
