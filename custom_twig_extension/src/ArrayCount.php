<?php

namespace Drupal\custom_twig_extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ArrayCount extends AbstractExtension {

    public function getFilters() {
        return [
          new TwigFilter('array_count', [$this, 'arraycountvaules']),
        ];
    }

    public function arraycountvaules($value) {
        
        return array_count_values($value);;
    }

}
