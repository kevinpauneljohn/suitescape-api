<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Owenoj\LaravelGetId3\GetId3;

class VideoDurationValidation implements ValidationRule
{
    protected int $minDuration;

    protected ?int $maxDuration;

    public function __construct(int $minDuration = 0, ?int $maxDuration = null)
    {
        $this->minDuration = $minDuration;
        $this->maxDuration = $maxDuration;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     *
     * @throws \getid3_exception
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        //        $video = FFMpeg::open($value);
        //
        //        if (! $video->isVideo()) {
        //            $fail("The {$attribute} must be a video file.");
        //
        //            return;
        //        }

        // Get the duration of the video in seconds
        //        $duration = $video->getDurationInSeconds();
        $duration = GetId3::fromUploadedFile($value)->getPlaytimeSeconds();

        if ($duration < $this->minDuration) {
            $fail("The $attribute must be at least $this->minDuration seconds.");
        }

        if ($this->maxDuration && $duration > $this->maxDuration) {
            $fail("The $attribute may not be greater than $this->maxDuration seconds.");
        }
    }
}
