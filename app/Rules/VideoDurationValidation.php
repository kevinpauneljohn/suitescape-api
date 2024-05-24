<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class VideoDurationValidation implements ValidationRule
{
    protected int $minDuration;

    protected int $maxDuration;

    public function __construct($minDuration = 0, $maxDuration = null)
    {
        $this->minDuration = $minDuration;
        $this->maxDuration = $maxDuration;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $video = FFMpeg::open($value);

        if (! $video->isVideo()) {
            $fail("The {$attribute} must be a video file.");

            return;
        }

        // Get the duration of the video in seconds
        $duration = $video->getDurationInSeconds();

        if ($duration < $this->minDuration) {
            $fail("The {$attribute} must be at least {$this->minDuration} seconds.");
        }

        if ($this->maxDuration && $duration > $this->maxDuration) {
            $fail("The {$attribute} may not be greater than {$this->maxDuration} seconds.");
        }
    }
}
