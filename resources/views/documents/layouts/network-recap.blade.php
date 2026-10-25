{{--
    Network economic recap (layout-dinamici §4.8). Coordinates, sizes and texts come already measured
    from App\Domain\Documents\Layouts\NetworkRecapLayout::viewData(): this view only places them.
--}}
<div class="box" style="left: {{ $header['left'] }}px; top: {{ $header['top'] }}px; width: {{ $header['width'] }}px; height: {{ $header['height'] }}px; background: {{ $header['background'] }};"></div>
@foreach ($header['cells'] as $cell)
<div class="box {{ $cell['align'] }}" style="left: {{ $cell['left'] }}px; top: {{ $header['top'] }}px; width: {{ $cell['width'] }}px; height: {{ $header['height'] }}px; color: {{ $header['color'] }}; font-weight: {{ $cell['weight'] }}; font-size: {{ $cell['text']->size }}px;">{{ $cell['text']->text }}</div>
@endforeach
@foreach ($rows as $row)
<div class="box" style="left: {{ $header['left'] }}px; top: {{ $row['top'] + $row['height'] - 1 }}px; width: {{ $header['width'] }}px; height: 1px; background: #d9d9d9;"></div>
@if ($row['logo'] !== null)
<img src="{{ $row['logo']['src'] }}" alt="" style="position: absolute; left: {{ $row['logo']['left'] }}px; top: {{ $row['logo']['top'] }}px; width: {{ $row['logo']['size'] }}px; height: {{ $row['logo']['size'] }}px; object-fit: contain;">
@endif
@foreach ($row['cells'] as $cell)
<div class="box {{ $cell['align'] }}" style="left: {{ $cell['left'] }}px; top: {{ $row['top'] }}px; width: {{ $cell['width'] }}px; height: {{ $row['height'] }}px; color: {{ $textColor }}; font-weight: {{ $cell['weight'] }}; font-size: {{ $cell['text']->size }}px;">{{ $cell['text']->text }}</div>
@endforeach
@endforeach
@if ($totals !== null)
<div class="box" style="left: {{ $totals['left'] }}px; top: {{ $totals['top'] }}px; width: {{ $totals['width'] }}px; height: 2px; background: {{ $textColor }};"></div>
<div class="box right" style="right: {{ $totals['label']['right'] }}px; top: {{ $totals['top'] }}px; height: {{ $totals['height'] }}px; color: {{ $textColor }}; font-weight: 700; font-size: {{ $totals['label']['size'] }}px;">{{ $totals['label']['text'] }}</div>
@foreach ($totals['cells'] as $cell)
<div class="box {{ $cell['align'] }}" style="left: {{ $cell['left'] }}px; top: {{ $totals['top'] }}px; width: {{ $cell['width'] }}px; height: {{ $totals['height'] }}px; color: {{ $textColor }}; font-weight: {{ $cell['weight'] }}; font-size: {{ $cell['text']->size }}px;">{{ $cell['text']->text }}</div>
@endforeach
<div class="box center" style="left: {{ $totals['price']['left'] }}px; top: {{ $totals['top'] }}px; width: {{ $totals['price']['width'] }}px; height: {{ $totals['height'] }}px; flex-direction: column; justify-content: center; gap: 4px; color: {{ $textColor }};">
@foreach ($totals['price']['lines'] as $line)
    <span style="display: block; font-weight: {{ $line['weight'] }}; font-size: {{ $line['size'] }}px;{{ $line['strike'] ? ' text-decoration: line-through;' : '' }}">{{ $line['text'] }}</span>
@endforeach
</div>
@endif
@if ($vatNote !== null)
<div class="box left" style="left: {{ $vatNote['left'] }}px; top: {{ $vatNote['top'] }}px; height: {{ $vatNote['size'] + 4 }}px; color: {{ $textColor }}; font-weight: 400; font-size: {{ $vatNote['size'] }}px;">{{ $vatNote['text'] }}</div>
@endif
@if ($relegationNote !== null)
<div class="box left" style="left: {{ $relegationNote['left'] }}px; top: {{ $relegationNote['top'] }}px; width: {{ $relegationNote['width'] }}px; height: {{ $relegationNote['height'] }}px; color: {{ $textColor }}; font-weight: 700; font-size: {{ $relegationNote['text']->size }}px;">{{ $relegationNote['text']->text }}</div>
@endif
