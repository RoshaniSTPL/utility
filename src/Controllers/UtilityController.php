<?php

namespace RoshaniSTPL\utility\Controllers;

use RoshaniSTPL\utility\utility;

class UtilityController
{
    public function __invoke(utility $utility) {
        $quote = $utility->test();
        return view('utility::index', compact('quote'));
    }
}