<?php

namespace App\Support;

final class NavigatieItem
{
    public function __construct(
        public readonly string $label,
        public readonly string $route,
        public readonly string $blokCode,
        public readonly string $icon,
        /**
         * Het niveau dat dit item zichtbaar maakt. Meestal 'lezen'; blok 6 zet
         * het op 'uitvoeren' omdat de Medewerker daar alleen eigen bewijs mag
         * uploaden en het scherm daarop is gescoped.
         */
        public readonly string $niveau = 'lezen',
    ) {}
}
