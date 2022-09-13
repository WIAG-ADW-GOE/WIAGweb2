<?php
// src/Twig/AppExtension.php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // example
            new TwigFilter('price', [$this, 'formatPrice']),
            new TwigFilter('protectOrdinal', [$this, 'protectOrdinal']),
        ];
    }

    // example
    public function formatPrice(float $number, int $decimals = 0, string $decPoint = '.', string $thousandsSep = ','): string
    {
        $price = number_format($number, $decimals, $decPoint, $thousandsSep);
        $price = '$'.$price;

        return $price;
    }

    public function protectOrdinal(string $info): string {
        $protectOrdinal = preg_replace('/( [IVX]+\.)/', '&nbsp;${1}', $info);
        return $protectOrdinal;
    }
}
