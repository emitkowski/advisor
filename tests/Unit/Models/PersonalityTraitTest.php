<?php

namespace Tests\Unit\Models;

use App\Models\PersonalityTrait;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalityTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_defaults_creates_thirteen_traits(): void
    {
        $user = User::factory()->create();

        PersonalityTrait::seedDefaults($user->id);

        $this->assertSame(13, PersonalityTrait::where('user_id', $user->id)->count());
    }

    public function test_seed_defaults_is_idempotent(): void
    {
        $user = User::factory()->create();

        PersonalityTrait::seedDefaults($user->id);
        PersonalityTrait::seedDefaults($user->id);

        $this->assertSame(13, PersonalityTrait::where('user_id', $user->id)->count());
    }

    public function test_seed_defaults_marks_traits_as_system(): void
    {
        $user = User::factory()->create();

        PersonalityTrait::seedDefaults($user->id);

        $this->assertSame(
            13,
            PersonalityTrait::where('user_id', $user->id)->where('is_system', true)->count()
        );
    }

    public function test_seed_defaults_includes_expected_traits(): void
    {
        $user = User::factory()->create();

        PersonalityTrait::seedDefaults($user->id);

        $traits = PersonalityTrait::where('user_id', $user->id)->pluck('trait');

        $this->assertContains('directness', $traits);
        $this->assertContains('skepticism', $traits);
        $this->assertContains('validation_resistance', $traits);
    }

    public function test_build_personality_block_returns_empty_string_when_no_traits(): void
    {
        $user = User::factory()->create();

        $this->assertSame('', PersonalityTrait::buildPersonalityBlock($user->id));
    }

    public function test_build_personality_block_includes_all_traits(): void
    {
        $user = User::factory()->create();
        PersonalityTrait::seedDefaults($user->id);

        $block = PersonalityTrait::buildPersonalityBlock($user->id);

        $this->assertStringContainsString('directness', $block);
        $this->assertStringContainsString('validation_resistance', $block);
        $this->assertStringContainsString('/100', $block);
    }
}
