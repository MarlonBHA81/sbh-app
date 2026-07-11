<?php

function blogDoc(array $content): array
{
    return ['type' => 'doc', 'content' => $content];
}

test('a blog post accepts a valid tiptap document', function () {
    $user = userWithProfile();

    $doc = blogDoc([
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [
            ['type' => 'text', 'text' => 'Title'],
        ]],
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Hello ', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => 'world', 'marks' => [
                ['type' => 'link', 'attrs' => ['href' => 'https://example.com']],
            ]],
        ]],
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'item']]],
            ]],
        ]],
        ['type' => 'image', 'attrs' => ['src' => '/storage/media/x.webp']],
    ]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'blog',
        'payload' => ['title' => 'My Post', 'doc' => $doc, 'excerpt' => 'a short excerpt'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'blog')
        ->assertJsonPath('data.payload.title', 'My Post');
});

test('a blog post rejects an unknown node type', function () {
    $user = userWithProfile();

    $doc = blogDoc([
        ['type' => 'iframe', 'attrs' => ['src' => 'https://evil.example']],
    ]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'blog',
        'payload' => ['title' => 'X', 'doc' => $doc],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.doc');
});

test('a blog post rejects a script node', function () {
    $user = userWithProfile();

    $doc = blogDoc([
        ['type' => 'paragraph', 'content' => [
            ['type' => 'script', 'text' => 'alert(1)'],
        ]],
    ]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'blog',
        'payload' => ['title' => 'X', 'doc' => $doc],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.doc');
});

test('a blog post rejects a link with a disallowed scheme', function () {
    $user = userWithProfile();

    $doc = blogDoc([
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'click', 'marks' => [
                ['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']],
            ]],
        ]],
    ]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'blog',
        'payload' => ['title' => 'X', 'doc' => $doc],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.doc');
});

test('a blog post rejects an unknown mark type', function () {
    $user = userWithProfile();

    $doc = blogDoc([
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'blink']]],
        ]],
    ]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'blog',
        'payload' => ['title' => 'X', 'doc' => $doc],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.doc');
});

test('a blog post rejects an out-of-range heading level', function () {
    $user = userWithProfile();

    $doc = blogDoc([
        ['type' => 'heading', 'attrs' => ['level' => 5], 'content' => [
            ['type' => 'text', 'text' => 'too deep'],
        ]],
    ]);

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'blog',
        'payload' => ['title' => 'X', 'doc' => $doc],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.doc');
});

test('a blog post requires a title', function () {
    $user = userWithProfile();

    $this->actingAs($user)->postJson('/api/v1/posts', [
        'type' => 'blog',
        'payload' => ['doc' => blogDoc([['type' => 'paragraph']])],
    ])->assertUnprocessable()->assertJsonValidationErrors('payload.title');
});
