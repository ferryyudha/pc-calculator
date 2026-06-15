@props(['category', 'size' => 80])

<img src="{{ asset('images/components/' . $category . '.svg') }}"
     alt="{{ $category }}"
     width="{{ $size }}"
     height="{{ $size * 0.5 }}"
     class="object-contain">
