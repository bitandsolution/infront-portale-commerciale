{{--
    Simulation of the logo on the LED (D-398, D-424; DM-06): the photo of the field scaled to cover
    the page and, on every area of the LED, a strip in the colour of the background of the logo with
    the logo repeated; the strip is brought onto the four points of the area by a matrix3d computed by
    App\Domain\Documents\Layouts\LedMockupLayout. Everything comes already measured: this view only
    draws it, every datum escaped by {{ }} (D-176).
--}}
<div style="position: absolute; left: {{ $frame['left'] }}px; top: {{ $frame['top'] }}px; width: {{ $frame['width'] }}px; height: {{ $frame['height'] }}px; transform: scale({{ $frame['scale'] }}); transform-origin: 0 0;">
    <img src="{{ $photo }}" alt="" style="position: absolute; left: 0; top: 0; width: {{ $frame['width'] }}px; height: {{ $frame['height'] }}px;">
@foreach ($strips as $strip)
    <div style="position: absolute; left: 0; top: 0; width: {{ $strip['width'] }}px; height: {{ $strip['height'] }}px; overflow: hidden; background: {{ $strip['color'] }}; transform-origin: 0 0; transform: {{ $strip['transform'] }};">
@foreach ($strip['logos'] as $logo)
        <img src="{{ $strip['logo'] }}" alt="" style="position: absolute; left: {{ $logo['left'] }}px; top: {{ $logo['top'] }}px; width: {{ $logo['width'] }}px; height: {{ $logo['height'] }}px;">
@endforeach
@if ($strip['line'] !== null)
        <div style="position: absolute; left: 0; top: {{ $strip['line']['top'] }}px; width: {{ $strip['width'] }}px; height: {{ $strip['line']['height'] }}px; background: {{ $strip['line']['color'] }};"></div>
@endif
    </div>
@endforeach
</div>
