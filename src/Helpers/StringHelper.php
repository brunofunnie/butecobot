<?php

namespace Chorume\Helpers;

if (!function_exists('format_money')) {
    function format_money($amount) {
        if ($amount >= 1000000) {
            $formatted_amount = number_format($amount / 1000000, 2) . "M";
        } elseif ($amount >= 1000) {
            $formatted_amount = number_format($amount / 1000, 2) . "K";
        } else {
            $formatted_amount = number_format($amount, 2);
        }

        return $formatted_amount;
    }
}