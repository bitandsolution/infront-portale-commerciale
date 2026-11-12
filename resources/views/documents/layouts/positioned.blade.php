{{--
    A layout page made of positioned boxes, logos and texts (cover, network and club recap;
    layout-dinamici §6.1). Everything comes already measured from the classes of
    App\Domain\Documents\Layouts: this view only draws it. Every datum is escaped by {{ }} (D-176).
--}}
@foreach ($boxes as $box)
<div style="position: absolute; left: {{ $box['left'] }}px; top: {{ $box['top'] }}px; width: {{ $box['width'] }}px; height: {{ $box['height'] }}px; border: 1px solid {{ $box['border'] }};{{ $box['background'] !== null ? ' background: '.$box['background'].';' : '' }}"></div>
@endforeach
@foreach ($logos as $logo)
<img src="{{ $logo['src'] }}" alt="" style="position: absolute; left: {{ $logo['left'] }}px; top: {{ $logo['top'] }}px; width: {{ $logo['width'] ?? $logo['size'] }}px; height: {{ $logo['height'] ?? $logo['size'] }}px; object-fit: contain;">
@endforeach
@foreach ($texts as $text)
<div class="box {{ $text['align'] }}" style="left: {{ $text['left'] }}px; top: {{ $text['top'] }}px; width: {{ $text['width'] }}px; height: {{ $text['height'] }}px; color: {{ $text['color'] }}; font-weight: {{ $text['weight'] }}; font-size: {{ $text['size'] }}px;{{ $text['strike'] ? ' text-decoration: line-through;' : '' }}">{{ $text['text'] }}</div>
@endforeach
