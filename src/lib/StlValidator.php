<?php

final class StlValidator
{
    /** Basic sanity check: is this plausibly a valid ASCII or binary STL file? */
    public static function isValid(string $path): bool
    {
        $size = filesize($path);
        if ($size === false || $size < 84) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 512) ?: '';
        fclose($handle);

        $trimmed = ltrim($header);
        if (str_starts_with($trimmed, 'solid') && str_contains($header, 'facet')) {
            return true;
        }

        // Binary STL: 80-byte header + uint32 triangle count + 50 bytes/triangle.
        $countBytes = substr($header, 80, 4);
        if (strlen($countBytes) < 4) {
            return false;
        }
        $triangleCount = unpack('V', $countBytes)[1];
        $expectedSize = 84 + ($triangleCount * 50);

        return $expectedSize === $size;
    }
}
