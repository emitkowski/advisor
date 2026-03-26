<?php

namespace Tests\Unit\Models;

use App\Models\Signal;
use PHPUnit\Framework\TestCase;

class SignalTest extends TestCase
{
    public function test_detects_rating_as_fraction(): void
    {
        $this->assertSame(7.0, Signal::detectExplicitRating('7/10 good advice'));
        $this->assertSame(10.0, Signal::detectExplicitRating('10/10'));
        $this->assertSame(1.0, Signal::detectExplicitRating('1/10'));
    }

    public function test_detects_rating_out_of_ten(): void
    {
        $this->assertSame(7.0, Signal::detectExplicitRating('7 out of 10'));
        $this->assertSame(8.0, Signal::detectExplicitRating('8 out of 10 response'));
    }

    public function test_detects_rating_with_label(): void
    {
        $this->assertSame(8.0, Signal::detectExplicitRating('rating: 8'));
        $this->assertSame(6.0, Signal::detectExplicitRating('score: 6'));
        $this->assertSame(9.0, Signal::detectExplicitRating('Rating 9'));
    }

    public function test_detects_rating_with_dash_prefix(): void
    {
        $this->assertSame(3.0, Signal::detectExplicitRating('3 - that was wrong'));
        $this->assertSame(5.0, Signal::detectExplicitRating('5 — actually helpful'));
    }

    public function test_returns_null_when_no_rating_present(): void
    {
        $this->assertNull(Signal::detectExplicitRating('That was a good point'));
        $this->assertNull(Signal::detectExplicitRating('I disagree with your assessment'));
    }

    public function test_returns_null_for_out_of_range_values(): void
    {
        $this->assertNull(Signal::detectExplicitRating('11/10'));
        $this->assertNull(Signal::detectExplicitRating('0/10'));
    }

    public function test_sentiment_to_rating_converts_correctly(): void
    {
        $this->assertSame(1.0, Signal::sentimentToRating(0.0));
        $this->assertSame(10.0, Signal::sentimentToRating(1.0));
        $this->assertSame(5.5, Signal::sentimentToRating(0.5));
    }
}
