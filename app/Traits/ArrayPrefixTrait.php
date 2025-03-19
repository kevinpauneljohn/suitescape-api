<?php

namespace App\Traits;

trait ArrayPrefixTrait
{
    /**
     * Add prefix to top-level array keys
     *
     * @param array $array The input array
     * @param string $prefix The prefix to add
     * @param string $delimiter The delimiter between prefix and key (default: '.')
     * @return array
     */
    protected function addPrefixToKeys(array $array, string $prefix, string $delimiter = '.'): array
    {
        $prefixedArray = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix . $delimiter . $key;
            $prefixedArray[$newKey] = $value;
        }

        return $prefixedArray;
    }

    /**
     * Remove prefix from array keys
     *
     * @param array $array The input array
     * @param string $prefix The prefix to remove
     * @param string $delimiter The delimiter between prefix and key (default: '.')
     * @return array
     */
    protected function removePrefixFromKeys(array $array, string $prefix, string $delimiter = '.'): array
    {
        $unprefixedArray = [];
        $prefixLength = strlen($prefix . $delimiter);

        foreach ($array as $key => $value) {
            // Check if the key starts with the prefix
            if (str_starts_with($key, $prefix . $delimiter)) {
                $newKey = substr($key, $prefixLength);
                $unprefixedArray[$newKey] = $value;
            } else {
                // Keep the key unchanged if it doesn't have the prefix
                $unprefixedArray[$key] = $value;
            }
        }

        return $unprefixedArray;
    }
}
