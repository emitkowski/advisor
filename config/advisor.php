<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anthropic API Configuration
    |--------------------------------------------------------------------------
    */
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    | claude-sonnet-4-20250514  — best balance of speed and quality (recommended)
    | claude-opus-4-6           — highest quality, slower and more expensive
    | claude-haiku-4-5-20251001 — fastest and cheapest, less nuanced
    */
    'model'      => env('ADVISOR_MODEL', 'claude-sonnet-4-20250514'),
    'max_tokens' => env('ADVISOR_MAX_TOKENS', 2048),

    /*
    |--------------------------------------------------------------------------
    | Learning Job Configuration
    |--------------------------------------------------------------------------
    */
    'learning_queue'           => env('ADVISOR_LEARNING_QUEUE', 'learning'),
    'min_messages_for_learning' => env('ADVISOR_MIN_MESSAGES', 4),

    /*
    |--------------------------------------------------------------------------
    | Memory Configuration
    |--------------------------------------------------------------------------
    */
    'min_confidence_for_context' => 0.5,   // minimum confidence to include in system prompt
    'max_learnings_in_context'   => 20,    // cap to avoid context bloat
    'rating_window_days'         => 30,    // days to consider for rolling average

    /*
    |--------------------------------------------------------------------------
    | Model Pricing (USD per million tokens)
    |--------------------------------------------------------------------------
    */
    'pricing' => [
        'claude-sonnet-4-20250514'   => ['input_per_million' => 3.00,  'output_per_million' => 15.00],
        'claude-opus-4-6'            => ['input_per_million' => 15.00, 'output_per_million' => 75.00],
        'claude-haiku-4-5-20251001'  => ['input_per_million' => 0.80,  'output_per_million' => 4.00],
    ],

];
