{{--
    A slide composed with blocks (D-399, D-428; layout-dinamici §4.11): lines of text, bullets, table
    cells and images already placed by App\Domain\Documents\Layouts\CustomBlocksLayout. Every datum is
    escaped by {{ }} (D-176). The runs of a line are written without spaces between the tags because
    the line keeps its spaces (white-space: pre).
--}}
@foreach ($elements as $element)
@if ($element['kind'] === 'rect')
<div style="position: absolute; left: {{ $element['left'] }}px; top: {{ $element['top'] }}px; width: {{ $element['width'] }}px; height: {{ $element['height'] }}px; border: 1px solid {{ $element['border'] }};{{ $element['background'] !== null ? ' background: '.$element['background'].';' : '' }}"></div>
@elseif ($element['kind'] === 'image')
<img src="{{ $element['src'] }}" alt="" style="position: absolute; left: {{ $element['left'] }}px; top: {{ $element['top'] }}px; width: {{ $element['width'] }}px; height: {{ $element['height'] }}px; object-fit: contain;">
@elseif ($element['kind'] === 'cell')
<div class="box left" style="left: {{ $element['left'] }}px; top: {{ $element['top'] }}px; width: {{ $element['width'] }}px; height: {{ $element['height'] }}px; color: {{ $element['color'] }}; font-weight: {{ $element['weight'] }}; font-size: {{ $element['size'] }}px;">{{ $element['text'] }}</div>
@else
<div style="position: absolute; left: {{ $element['left'] }}px; top: {{ $element['top'] }}px; width: {{ $element['width'] }}px; height: {{ $element['height'] }}px; line-height: {{ $element['height'] }}px; font-size: {{ $element['size'] }}px; color: {{ $element['color'] }}; white-space: pre; overflow: hidden;">@foreach ($element['runs'] as $run)<span style="font-weight: {{ $run['weight'] }};">{{ $run['text'] }}</span>@endforeach</div>
@endif
@endforeach
