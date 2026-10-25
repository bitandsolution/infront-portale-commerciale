{{--
    Document pages (progettazione-tecnica §5.5.2 step 4, D-116): one 1920×1080 page per slide.
    Static slide = image on the whole page; layout page = background image + the layout's view.
    No external resource, no JavaScript (D-312): images are files next to this page, fonts are
    file:// sources declared by DocumentFonts. Every datum is escaped by {{ }} (D-176); the only raw
    output is the @font-face CSS built by the portal from the configured font paths.
--}}
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
{!! $fontsCss !!}
@page { size: 1920px 1080px; margin: 0; }
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #ffffff; }
body { font-family: '{{ $fontFamily }}'; font-synthesis: none; -webkit-font-smoothing: antialiased; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
.page { position: relative; width: 1920px; height: 1080px; overflow: hidden; break-after: page; }
.page:last-child { break-after: auto; }
.page > .background { position: absolute; left: 0; top: 0; width: 1920px; height: 1080px; display: block; }
.box { position: absolute; display: flex; align-items: center; white-space: nowrap; overflow: hidden; line-height: 1; }
.box.left { justify-content: flex-start; }
.box.center { justify-content: center; }
.box.right { justify-content: flex-end; }
</style>
</head>
<body>
@foreach ($pages as $page)
<section class="page">
    <img class="background" src="{{ $page['image'] }}" alt="">
@if ($page['view'] !== null)
    @include($page['view'], $page['data'])
@endif
</section>
@endforeach
</body>
</html>
