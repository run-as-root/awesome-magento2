<?php declare(strict_types=1);
namespace AwesomeList\Rendering;

final class BadgeRenderer
{
    public function render(?array $signals): string
    {
        if (!$signals) {
            return '';
        }
        $badges = [];
        if (!empty($signals['vitality_hot'])) {
            $badges[] = '🔥';
        }
        if (!empty($signals['actively_maintained'])) {
            $badges[] = '🫡';
        }
        return $badges === [] ? '' : ' ' . implode(' ', $badges);
    }
}
