<?php

namespace App\Helpers;

use InvalidArgumentException;

class GeoJsonHelper
{
    public static function normalizeToMultiPolygonGeoJson(?string $rawCoordinates): ?string
    {
        if (empty($rawCoordinates)) {
            return null;
        }

        $coords = json_decode($rawCoordinates, true);

        if (!is_array($coords)) {
            return null;
        }

        $flipCoords = function ($item) use (&$flipCoords) {
            if (!is_array($item)) {
                return $item;
            }

            if (count($item) === 2 && is_numeric($item[0]) && is_numeric($item[1])) {
                return [(float) $item[1], (float) $item[0]];
            }

            return array_map($flipCoords, $item);
        };

        $flipped = $flipCoords($coords);

        if (isset($flipped[0][0][0]) && is_numeric($flipped[0][0][0])) {
            $flipped = [$flipped];
        }

        return json_encode([
            'type'        => 'MultiPolygon',
            'coordinates' => $flipped,
        ]);
    }
}