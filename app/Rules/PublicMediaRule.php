<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicMediaRule implements ValidationRule
{
    protected string $mediaType;

    public function __construct(string $mediaType)
    {
        $this->mediaType = $mediaType;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $hasPublicMedia = false;

        // Check if at least one media item is public
        foreach ($value as $media) {
            if (isset($media['privacy']) && $media['privacy'] === 'public') {
                $hasPublicMedia = true;
                break;
            }
        }

        if (! $hasPublicMedia) {
            $fail("At least one $this->mediaType must be public.");
        }
    }
}
